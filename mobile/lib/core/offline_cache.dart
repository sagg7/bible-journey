import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Cache read-through para lectura offline.
///
/// Guarda las respuestas GET de los endpoints de lectura y, cuando una
/// petición falla por red (sin conexión, timeout), responde con la última
/// copia guardada en vez de propagar el error. Así, todo capítulo o nodo ya
/// visitado sigue siendo legible sin conexión.
///
/// El progreso local ya vive en SharedPreferences (local_progress.dart), de
/// modo que leer + marcar progreso funciona offline; la sincronización remota
/// se reintenta cuando vuelve la red (best-effort en los providers).
class OfflineCacheInterceptor extends Interceptor {
  static const _indexKey = 'bj_offline_cache_index';
  static const _prefix = 'bj_cache:';
  static const maxEntries = 200;

  /// Prefijos de path cacheables (solo lectura pública).
  static const _cacheablePrefixes = [
    '/readings',
    '/v2/stream-plans',
    '/v2/passages',
    '/v2/compare-groups',
    '/v2/explanations',
    '/translations',
    '/routes',
  ];

  bool _isCacheable(RequestOptions options) {
    if (options.method.toUpperCase() != 'GET') return false;
    return _cacheablePrefixes.any((p) => options.path.startsWith(p));
  }

  String _keyFor(RequestOptions options) =>
      '$_prefix${options.uri.path}?${options.uri.query}';

  @override
  Future<void> onResponse(
    Response response,
    ResponseInterceptorHandler handler,
  ) async {
    if (_isCacheable(response.requestOptions) &&
        response.statusCode == 200 &&
        response.data != null) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final key = _keyFor(response.requestOptions);
        await prefs.setString(key, jsonEncode(response.data));
        await _touchIndex(prefs, key);
      } catch (_) {
        // El cache nunca debe romper una respuesta buena.
      }
    }
    handler.next(response);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final isNetworkFailure = err.response == null &&
        (err.type == DioExceptionType.connectionError ||
            err.type == DioExceptionType.connectionTimeout ||
            err.type == DioExceptionType.receiveTimeout ||
            err.type == DioExceptionType.sendTimeout ||
            err.type == DioExceptionType.unknown);

    if (isNetworkFailure && _isCacheable(err.requestOptions)) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final cached = prefs.getString(_keyFor(err.requestOptions));
        if (cached != null) {
          handler.resolve(
            Response(
              requestOptions: err.requestOptions,
              statusCode: 200,
              data: jsonDecode(cached),
              headers: Headers.fromMap({
                'x-from-cache': ['1'],
              }),
            ),
          );
          return;
        }
      } catch (_) {
        // Cae al error original.
      }
    }
    handler.next(err);
  }

  /// Mantiene un índice LRU y poda las entradas más viejas.
  Future<void> _touchIndex(SharedPreferences prefs, String key) async {
    final index = prefs.getStringList(_indexKey) ?? <String>[];
    index.remove(key);
    index.add(key);
    while (index.length > maxEntries) {
      final evicted = index.removeAt(0);
      await prefs.remove(evicted);
    }
    await prefs.setStringList(_indexKey, index);
  }
}
