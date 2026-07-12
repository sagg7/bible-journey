import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'theme.dart' show kDefaultScriptureFont;

class LocalProgress {
  final Set<int> completedBlockIds;
  final int? lastPlanId;
  final int? lastNodeId;
  final String? lastCanonicalOsisCode;
  final int? lastCanonicalChapter;
  final String translationCode;
  final double fontScale;
  final String fontFamily;
  final String? readerBackground;

  // Manual "reading spot" bookmark — distinct from lastPlanId/lastNodeId and
  // lastCanonicalOsisCode/lastCanonicalChapter above, which update silently
  // on every screen visit (including just browsing around). This is only
  // set when the user explicitly taps the bookmark button, so it reliably
  // means "this is where I meant to stop."
  final String? bookmarkType; // 'crs' | 'canonical'
  final int? bookmarkPlanId;
  final int? bookmarkNodeId;
  final String? bookmarkOsisCode;
  final int? bookmarkChapter;
  final String? bookmarkLabel;

  const LocalProgress({
    this.completedBlockIds = const {},
    this.lastPlanId,
    this.lastNodeId,
    this.lastCanonicalOsisCode,
    this.lastCanonicalChapter,
    this.translationCode = 'RVA1909',
    this.fontScale = 1.0,
    this.fontFamily = kDefaultScriptureFont,
    this.readerBackground,
    this.bookmarkType,
    this.bookmarkPlanId,
    this.bookmarkNodeId,
    this.bookmarkOsisCode,
    this.bookmarkChapter,
    this.bookmarkLabel,
  });

  bool isCompleted(int blockId) => completedBlockIds.contains(blockId);

  bool get hasBookmark => bookmarkType != null;

  /// Replaces the bookmark fields wholesale (unlike [copyWith], which can't
  /// null out a field once set) — pass nothing to clear the bookmark.
  LocalProgress withBookmark({
    String? type,
    int? planId,
    int? nodeId,
    String? osisCode,
    int? chapter,
    String? label,
  }) => LocalProgress(
    completedBlockIds: completedBlockIds,
    lastPlanId: lastPlanId,
    lastNodeId: lastNodeId,
    lastCanonicalOsisCode: lastCanonicalOsisCode,
    lastCanonicalChapter: lastCanonicalChapter,
    translationCode: translationCode,
    fontScale: fontScale,
    fontFamily: fontFamily,
    readerBackground: readerBackground,
    bookmarkType: type,
    bookmarkPlanId: planId,
    bookmarkNodeId: nodeId,
    bookmarkOsisCode: osisCode,
    bookmarkChapter: chapter,
    bookmarkLabel: label,
  );

  LocalProgress copyWith({
    Set<int>? completedBlockIds,
    int? lastPlanId,
    int? lastNodeId,
    String? lastCanonicalOsisCode,
    int? lastCanonicalChapter,
    String? translationCode,
    double? fontScale,
    String? fontFamily,
    String? readerBackground,
    String? bookmarkType,
    int? bookmarkPlanId,
    int? bookmarkNodeId,
    String? bookmarkOsisCode,
    int? bookmarkChapter,
    String? bookmarkLabel,
  }) => LocalProgress(
    completedBlockIds: completedBlockIds ?? this.completedBlockIds,
    lastPlanId: lastPlanId ?? this.lastPlanId,
    lastNodeId: lastNodeId ?? this.lastNodeId,
    lastCanonicalOsisCode: lastCanonicalOsisCode ?? this.lastCanonicalOsisCode,
    lastCanonicalChapter: lastCanonicalChapter ?? this.lastCanonicalChapter,
    translationCode: translationCode ?? this.translationCode,
    fontScale: fontScale ?? this.fontScale,
    fontFamily: fontFamily ?? this.fontFamily,
    readerBackground: readerBackground ?? this.readerBackground,
    bookmarkType: bookmarkType ?? this.bookmarkType,
    bookmarkPlanId: bookmarkPlanId ?? this.bookmarkPlanId,
    bookmarkNodeId: bookmarkNodeId ?? this.bookmarkNodeId,
    bookmarkOsisCode: bookmarkOsisCode ?? this.bookmarkOsisCode,
    bookmarkChapter: bookmarkChapter ?? this.bookmarkChapter,
    bookmarkLabel: bookmarkLabel ?? this.bookmarkLabel,
  );
}

const kMinFontScale = 0.85;
const kMaxFontScale = 1.7;

class LocalProgressNotifier extends AsyncNotifier<LocalProgress> {
  static const _kCompleted = 'bj_completed_blocks';
  static const _kLastPlan = 'bj_last_plan_id';
  static const _kLastNode = 'bj_last_node_id';
  static const _kLastOsis = 'bj_last_osis';
  static const _kLastChapter = 'bj_last_chapter';
  static const _kTranslation = 'bj_translation';
  static const _kFontScale = 'bj_font_scale';
  static const _kFontFamily = 'bj_font_family';
  static const _kReaderBackground = 'bj_reader_background';
  static const _kBookmarkType = 'bj_bookmark_type';
  static const _kBookmarkPlanId = 'bj_bookmark_plan_id';
  static const _kBookmarkNodeId = 'bj_bookmark_node_id';
  static const _kBookmarkOsis = 'bj_bookmark_osis';
  static const _kBookmarkChapter = 'bj_bookmark_chapter';
  static const _kBookmarkLabel = 'bj_bookmark_label';

  @override
  Future<LocalProgress> build() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getStringList(_kCompleted) ?? [];
    return LocalProgress(
      completedBlockIds: raw.map(int.parse).toSet(),
      lastPlanId: prefs.getInt(_kLastPlan),
      lastNodeId: prefs.getInt(_kLastNode),
      lastCanonicalOsisCode: prefs.getString(_kLastOsis),
      lastCanonicalChapter: prefs.getInt(_kLastChapter),
      translationCode: prefs.getString(_kTranslation) ?? 'RVA1909',
      fontScale: prefs.getDouble(_kFontScale) ?? 1.0,
      fontFamily: prefs.getString(_kFontFamily) ?? kDefaultScriptureFont,
      readerBackground: prefs.getString(_kReaderBackground),
      bookmarkType: prefs.getString(_kBookmarkType),
      bookmarkPlanId: prefs.getInt(_kBookmarkPlanId),
      bookmarkNodeId: prefs.getInt(_kBookmarkNodeId),
      bookmarkOsisCode: prefs.getString(_kBookmarkOsis),
      bookmarkChapter: prefs.getInt(_kBookmarkChapter),
      bookmarkLabel: prefs.getString(_kBookmarkLabel),
    );
  }

  Future<void> markBlockCompleted(int blockId) async {
    final current = await future;
    final updated = {...current.completedBlockIds, blockId};
    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList(_kCompleted, updated.map((e) => '$e').toList());
    state = AsyncData(current.copyWith(completedBlockIds: updated));
  }

  Future<void> setLastNode(int planId, int nodeId) async {
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_kLastPlan, planId);
    await prefs.setInt(_kLastNode, nodeId);
    state = AsyncData(current.copyWith(lastPlanId: planId, lastNodeId: nodeId));
  }

  Future<void> setLastCanonical(String osisCode, int chapter) async {
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kLastOsis, osisCode);
    await prefs.setInt(_kLastChapter, chapter);
    state = AsyncData(
      current.copyWith(
        lastCanonicalOsisCode: osisCode,
        lastCanonicalChapter: chapter,
      ),
    );
  }

  Future<void> setTranslation(String code) async {
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kTranslation, code);
    state = AsyncData(current.copyWith(translationCode: code));
  }

  Future<void> setFontScale(double scale) async {
    final clamped = scale.clamp(kMinFontScale, kMaxFontScale);
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setDouble(_kFontScale, clamped);
    state = AsyncData(current.copyWith(fontScale: clamped));
  }

  Future<void> setFontFamily(String family) async {
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kFontFamily, family);
    state = AsyncData(current.copyWith(fontFamily: family));
  }

  Future<void> setReaderBackground(String background) async {
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kReaderBackground, background);
    state = AsyncData(current.copyWith(readerBackground: background));
  }

  Future<void> setBookmarkCrs(int planId, int nodeId, String label) async {
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kBookmarkType, 'crs');
    await prefs.setInt(_kBookmarkPlanId, planId);
    await prefs.setInt(_kBookmarkNodeId, nodeId);
    await prefs.remove(_kBookmarkOsis);
    await prefs.remove(_kBookmarkChapter);
    await prefs.setString(_kBookmarkLabel, label);
    state = AsyncData(
      current.withBookmark(
        type: 'crs',
        planId: planId,
        nodeId: nodeId,
        label: label,
      ),
    );
  }

  Future<void> setBookmarkCanonical(
    String osisCode,
    int chapter,
    String label,
  ) async {
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kBookmarkType, 'canonical');
    await prefs.setString(_kBookmarkOsis, osisCode);
    await prefs.setInt(_kBookmarkChapter, chapter);
    await prefs.remove(_kBookmarkPlanId);
    await prefs.remove(_kBookmarkNodeId);
    await prefs.setString(_kBookmarkLabel, label);
    state = AsyncData(
      current.withBookmark(
        type: 'canonical',
        osisCode: osisCode,
        chapter: chapter,
        label: label,
      ),
    );
  }

  Future<void> clearBookmark() async {
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kBookmarkType);
    await prefs.remove(_kBookmarkPlanId);
    await prefs.remove(_kBookmarkNodeId);
    await prefs.remove(_kBookmarkOsis);
    await prefs.remove(_kBookmarkChapter);
    await prefs.remove(_kBookmarkLabel);
    state = AsyncData(current.withBookmark());
  }
}

final localProgressProvider =
    AsyncNotifierProvider<LocalProgressNotifier, LocalProgress>(
      LocalProgressNotifier.new,
    );

/// Valor temporal de fontScale mientras el usuario hace el gesto de
/// pellizco; null cuando no hay gesto de pellizco activo.
final pinchFontScaleProvider = StateProvider<double?>((ref) => null);

/// fontScale a usar para renderizar el texto de lectura: el valor en vivo
/// del pellizco si hay uno activo, si no el valor persistido.
final effectiveFontScaleProvider = Provider<double>((ref) {
  final live = ref.watch(pinchFontScaleProvider);
  if (live != null) return live;
  return ref.watch(localProgressProvider).value?.fontScale ?? 1.0;
});
