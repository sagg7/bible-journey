import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';
import 'auth.dart';

/// URL base de la API. En el emulador de Android, el host es 10.0.2.2.
/// Sobrescribe con --dart-define=API_BASE_URL=...
const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api',
);

/// Idioma actual de la app (es/en). El interceptor lo envía como X-Locale.
final localeProvider = StateProvider<String>((ref) => 'es');

/// Código de la traducción bíblica seleccionada (null = la del idioma por defecto).
final translationProvider = StateProvider<String?>((ref) => null);

final apiProvider = Provider<ApiClient>((ref) => ApiClient(ref));

class ApiException implements Exception {
  final String message;
  final int? status;
  ApiException(this.message, [this.status]);
  @override
  String toString() => message;
}

class ApiClient {
  final Ref ref;
  late final Dio _dio;

  ApiClient(this.ref) {
    _dio = Dio(BaseOptions(
      baseUrl: apiBaseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 65),
      headers: {'Accept': 'application/json'},
    ));
    _dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      options.headers['X-Locale'] = ref.read(localeProvider);
      final token = ref.read(authProvider);
      if (token != null) options.headers['Authorization'] = 'Bearer $token';
      handler.next(options);
    }));
  }

  Never _fail(Object e) {
    if (e is DioException) {
      final status = e.response?.statusCode;
      final msg = e.response?.data is Map ? (e.response?.data['message'] ?? '') : '';
      throw ApiException(msg.toString().isNotEmpty ? msg.toString() : 'Error de red', status);
    }
    throw ApiException(e.toString());
  }

  Map<String, dynamic> _q() {
    final t = ref.read(translationProvider);
    return {'locale': ref.read(localeProvider), 'translation': ?t};
  }

  Future<List<BibleTranslationOption>> translations() async {
    try {
      final r = await _dio.get('/translations', queryParameters: _q());
      return (r.data['data'] as List).map((e) => BibleTranslationOption.fromJson(e)).toList();
    } catch (e) {
      _fail(e);
    }
  }

  Future<Map<String, dynamic>> register(String name, String email, String password) async {
    try {
      final r = await _dio.post('/register', data: {'name': name, 'email': email, 'password': password});
      return r.data as Map<String, dynamic>;
    } catch (e) {
      _fail(e);
    }
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final r = await _dio.post('/login', data: {'email': email, 'password': password});
      return r.data as Map<String, dynamic>;
    } catch (e) {
      _fail(e);
    }
  }

  Future<void> logout() async {
    try {
      await _dio.post('/logout');
    } catch (_) {} // best-effort: siempre limpiamos el token local
  }

  Future<Map<String, dynamic>> completeEvent(String routeSlug, String eventSlug) async {
    try {
      final r = await _dio.post('/me/progress/complete',
          data: {'route_slug': routeSlug, 'event_slug': eventSlug});
      return r.data['data'] as Map<String, dynamic>;
    } catch (e) {
      _fail(e);
    }
  }

  // ─── API v2 methods ─────────────────────────────────────────────────────

  Future<StreamPlanSummary> streamPlan({String planId = 'active', String profile = 'cautious_default'}) async {
    try {
      final r = await _dio.get('/v2/stream-plans/$planId',
          queryParameters: {'profile': profile, 'locale': ref.read(localeProvider)});
      return StreamPlanSummary.fromJson(r.data as Map<String, dynamic>);
    } catch (e) {
      _fail(e);
    }
  }

  Future<CrsNodeDetail> crsNode(int planId, int nodeId) async {
    try {
      final r = await _dio.get('/v2/stream-plans/$planId/nodes/$nodeId',
          queryParameters: {'locale': ref.read(localeProvider)});
      return CrsNodeDetail.fromJson(r.data as Map<String, dynamic>);
    } catch (e) {
      _fail(e);
    }
  }

  Future<CompareGroupDetail> compareGroup(int groupId) async {
    try {
      final r = await _dio.get('/v2/compare-groups/$groupId');
      return CompareGroupDetail.fromJson(r.data as Map<String, dynamic>);
    } catch (e) {
      _fail(e);
    }
  }

  Future<PassageText> passageText(int blockId, {String translation = 'WEB'}) async {
    try {
      final r = await _dio.get('/v2/passages/block/$blockId',
          queryParameters: {'translation': translation, 'locale': ref.read(localeProvider)});
      return PassageText.fromJson(r.data as Map<String, dynamic>);
    } catch (e) {
      _fail(e);
    }
  }

  Future<bool> markBlockProgress(int blockId, int planId, String status) async {
    try {
      await _dio.post('/v2/progress/blocks/$blockId',
          data: {'status': status, 'plan_id': planId});
      return true;
    } catch (e) {
      _fail(e);
    }
  }

  Future<bool> markNodeState(int nodeId, int planId, String state) async {
    try {
      await _dio.post('/v2/progress/nodes/$nodeId',
          data: {'state': state, 'plan_id': planId});
      return true;
    } catch (e) {
      _fail(e);
    }
  }

  Future<ProgressSummary> progressSummary({int? planId}) async {
    try {
      final r = await _dio.get('/v2/progress/summary',
          queryParameters: planId != null ? {'plan_id': planId} : null);
      return ProgressSummary.fromJson(r.data as Map<String, dynamic>);
    } catch (e) {
      _fail(e);
    }
  }

  Future<EzraStructuredResponse> askEzraV2(String question, {int? nodeId, int? planId}) async {
    try {
      final r = await _dio.post('/v2/ezra/answer', data: {
        'question': question,
        'node_id': ?nodeId,
        'plan_id': ?planId,
      });
      return EzraStructuredResponse.fromJson(r.data['data'] as Map<String, dynamic>);
    } catch (e) {
      _fail(e);
    }
  }

  // ─── Bible text endpoints (/api/readings/*) ──────────────────────────────

  Future<ReadingBlockDetail> readingBlock(int blockId) async {
    try {
      final translation = ref.read(translationProvider) ?? 'RVA1909';
      final r = await _dio.get('/readings/$blockId',
          queryParameters: {'translation': translation});
      return ReadingBlockDetail.fromJson(r.data as Map<String, dynamic>);
    } catch (e) {
      _fail(e);
    }
  }

  Future<CanonicalChapterContent> canonicalChapter(String osisCode, int chapter) async {
    try {
      final translation = ref.read(translationProvider) ?? 'RVA1909';
      final r = await _dio.get('/readings/book/$osisCode/chapter/$chapter',
          queryParameters: {'translation': translation});
      return CanonicalChapterContent.fromJson(r.data as Map<String, dynamic>);
    } catch (e) {
      _fail(e);
    }
  }
}

// --- Providers de datos ---

final translationsListProvider = FutureProvider<List<BibleTranslationOption>>((ref) {
  ref.watch(localeProvider);
  return ref.watch(apiProvider).translations();
});

// ─── V2 providers ────────────────────────────────────────────────────────────

/// Active StreamPlan — full summary including nodes.
final streamPlanProvider = FutureProvider<StreamPlanSummary>((ref) {
  ref.watch(localeProvider);
  return ref.watch(apiProvider).streamPlan();
});

/// Single CRS node detail (for reader screen).
/// Family key: (planId, nodeId)
final crsNodeProvider = FutureProvider.family<CrsNodeDetail, (int, int)>((ref, key) {
  final (planId, nodeId) = key;
  return ref.watch(apiProvider).crsNode(planId, nodeId);
});

/// Compare group detail.
final compareGroupProvider = FutureProvider.family<CompareGroupDetail, int>((ref, groupId) {
  return ref.watch(apiProvider).compareGroup(groupId);
});

/// Passage text for a reading block (legacy v2 endpoint).
/// Family key: blockId
final passageTextProvider = FutureProvider.family<PassageText, int>((ref, blockId) {
  final translation = ref.watch(translationProvider) ?? 'WEB';
  return ref.watch(apiProvider).passageText(blockId, translation: translation);
});

/// Full verse text for a reading block via the new /api/readings/{blockId} endpoint.
final readingBlockProvider = FutureProvider.family<ReadingBlockDetail, int>((ref, blockId) {
  ref.watch(translationProvider);
  return ref.watch(apiProvider).readingBlock(blockId);
});

/// Canonical chapter content via /api/readings/book/{code}/chapter/{num}.
/// Family key: (osisCode, chapterNumber)
final canonicalChapterProvider =
    FutureProvider.family<CanonicalChapterContent, (String, int)>((ref, key) {
  final (osisCode, chapter) = key;
  ref.watch(translationProvider);
  return ref.watch(apiProvider).canonicalChapter(osisCode, chapter);
});

/// Canonical + narrative progress summary.
final progressSummaryProvider = FutureProvider<ProgressSummary>((ref) {
  return ref.watch(apiProvider).progressSummary();
});

/// Prev/next main-stream node IDs adjacent to [nodeId] in the loaded plan.
/// Derived synchronously from [streamPlanProvider] — no extra API call.
/// Family key: (planId, nodeId)
final neighborNodesProvider =
    Provider.family<({int? prevId, int? nextId}), (int, int)>((ref, key) {
  final (_, nodeId) = key;
  final planAsync = ref.watch(streamPlanProvider);
  return planAsync.maybeWhen(
    data: (plan) {
      final mainNodes = plan.nodes.where((n) => n.isMainStreamNode).toList()
        ..sort((a, b) {
          final esA = a.userFacingEraSort ?? 999;
          final esB = b.userFacingEraSort ?? 999;
          if (esA != esB) return esA.compareTo(esB);
          return a.rank.compareTo(b.rank);
        });
      final idx = mainNodes.indexWhere((n) => n.id == nodeId);
      if (idx < 0) return (prevId: null, nextId: null);
      return (
        prevId: idx > 0 ? mainNodes[idx - 1].id : null,
        nextId: idx < mainNodes.length - 1 ? mainNodes[idx + 1].id : null,
      );
    },
    orElse: () => (prevId: null, nextId: null),
  );
});
