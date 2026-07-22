import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:bible_journey/core/offline_cache.dart';

/// Handler mínimo para capturar lo que hace el interceptor.
class _CapturingResponseHandler extends ResponseInterceptorHandler {}

class _CapturingErrorHandler extends ErrorInterceptorHandler {
  Response? resolved;
  DioException? passed;

  @override
  void resolve(Response response, [bool _ = true]) {
    resolved = response;
  }

  @override
  void next(DioException err) {
    passed = err;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  RequestOptions readerRequest() => RequestOptions(
        path: '/readings/book/GEN/chapter/1',
        method: 'GET',
        baseUrl: 'https://example.test/api',
        queryParameters: {'translation': 'RVA1909'},
      );

  Future<void> primeCache(RequestOptions req, Map<String, dynamic> body) async {
    final interceptor = OfflineCacheInterceptor();
    final response = Response(
      requestOptions: req,
      statusCode: 200,
      data: body,
    );
    await interceptor.onResponse(response, _CapturingResponseHandler());
  }

  test('fallo de red devuelve la copia cacheada', () async {
    final req = readerRequest();
    await primeCache(req, {'verses': ['En el principio…']});

    final interceptor = OfflineCacheInterceptor();
    final handler = _CapturingErrorHandler();
    await interceptor.onError(
      DioException(
        requestOptions: req,
        type: DioExceptionType.connectionError,
      ),
      handler,
    );

    expect(handler.resolved, isNotNull, reason: 'debe resolver desde cache');
    expect(handler.resolved!.statusCode, 200);
    expect(handler.resolved!.data['verses'], isNotEmpty);
    expect(handler.resolved!.headers.value('x-from-cache'), '1');
    expect(handler.passed, isNull);
  });

  test('fallo de red sin cache propaga el error', () async {
    final interceptor = OfflineCacheInterceptor();
    final handler = _CapturingErrorHandler();
    await interceptor.onError(
      DioException(
        requestOptions: readerRequest(),
        type: DioExceptionType.connectionError,
      ),
      handler,
    );

    expect(handler.resolved, isNull);
    expect(handler.passed, isNotNull);
  });

  test('un 404 real no se enmascara con cache', () async {
    final req = readerRequest();
    await primeCache(req, {'verses': ['x']});

    final interceptor = OfflineCacheInterceptor();
    final handler = _CapturingErrorHandler();
    await interceptor.onError(
      DioException(
        requestOptions: req,
        type: DioExceptionType.badResponse,
        response: Response(requestOptions: req, statusCode: 404),
      ),
      handler,
    );

    expect(handler.resolved, isNull, reason: 'un error del servidor no es un fallo de red');
    expect(handler.passed, isNotNull);
  });

  test('endpoints no-lectura (POST /login) no se cachean', () async {
    final req = RequestOptions(path: '/login', method: 'POST');
    await primeCache(req, {'token': 'secreto'});

    final prefs = await SharedPreferences.getInstance();
    final keys = prefs.getKeys().where((k) => k.startsWith('bj_cache:'));
    expect(keys, isEmpty, reason: 'nunca cachear credenciales/respuestas de auth');
  });

  test('el índice LRU poda las entradas más viejas', () async {
    final interceptor = OfflineCacheInterceptor();

    for (var i = 0; i < OfflineCacheInterceptor.maxEntries + 10; i++) {
      final req = RequestOptions(path: '/readings/$i', method: 'GET');
      await interceptor.onResponse(
        Response(requestOptions: req, statusCode: 200, data: {'i': i}),
        _CapturingResponseHandler(),
      );
    }

    final prefs = await SharedPreferences.getInstance();
    final cacheKeys = prefs.getKeys().where((k) => k.startsWith('bj_cache:'));
    expect(cacheKeys.length, OfflineCacheInterceptor.maxEntries);
    // La entrada 0 (más vieja) fue podada; la última sigue viva.
    expect(prefs.getString('bj_cache:/readings/0?'), isNull);
    expect(
      prefs.getString(
          'bj_cache:/readings/${OfflineCacheInterceptor.maxEntries + 9}?'),
      isNotNull,
    );
  });
}
