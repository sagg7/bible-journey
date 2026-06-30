import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LocalProgress {
  final Set<int> completedBlockIds;
  final int? lastPlanId;
  final int? lastNodeId;
  final String? lastCanonicalOsisCode;
  final int? lastCanonicalChapter;
  final String translationCode;

  const LocalProgress({
    this.completedBlockIds = const {},
    this.lastPlanId,
    this.lastNodeId,
    this.lastCanonicalOsisCode,
    this.lastCanonicalChapter,
    this.translationCode = 'RVA1909',
  });

  bool isCompleted(int blockId) => completedBlockIds.contains(blockId);

  LocalProgress copyWith({
    Set<int>? completedBlockIds,
    int? lastPlanId,
    int? lastNodeId,
    String? lastCanonicalOsisCode,
    int? lastCanonicalChapter,
    String? translationCode,
  }) =>
      LocalProgress(
        completedBlockIds: completedBlockIds ?? this.completedBlockIds,
        lastPlanId: lastPlanId ?? this.lastPlanId,
        lastNodeId: lastNodeId ?? this.lastNodeId,
        lastCanonicalOsisCode: lastCanonicalOsisCode ?? this.lastCanonicalOsisCode,
        lastCanonicalChapter: lastCanonicalChapter ?? this.lastCanonicalChapter,
        translationCode: translationCode ?? this.translationCode,
      );
}

class LocalProgressNotifier extends AsyncNotifier<LocalProgress> {
  static const _kCompleted = 'bj_completed_blocks';
  static const _kLastPlan = 'bj_last_plan_id';
  static const _kLastNode = 'bj_last_node_id';
  static const _kLastOsis = 'bj_last_osis';
  static const _kLastChapter = 'bj_last_chapter';
  static const _kTranslation = 'bj_translation';

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
    state = AsyncData(current.copyWith(
      lastCanonicalOsisCode: osisCode,
      lastCanonicalChapter: chapter,
    ));
  }

  Future<void> setTranslation(String code) async {
    final current = await future;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kTranslation, code);
    state = AsyncData(current.copyWith(translationCode: code));
  }
}

final localProgressProvider =
    AsyncNotifierProvider<LocalProgressNotifier, LocalProgress>(
  LocalProgressNotifier.new,
);
