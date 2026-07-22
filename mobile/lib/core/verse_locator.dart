import 'package:flutter/material.dart';

class VerseLocation {
  final int chapter;
  final int verse;

  const VerseLocation({required this.chapter, required this.verse});

  String get storageKey => '$chapter:$verse';
}

class VerseLocator {
  final Map<String, GlobalKey> _keys = {};

  GlobalKey keyFor(int chapter, int verse) {
    final key = _key(chapter, verse);
    return _keys.putIfAbsent(key, () => GlobalKey(debugLabel: 'verse-$key'));
  }

  VerseLocation? firstVisible({double topInset = 104}) {
    VerseLocation? bestCrossing;
    double bestCrossingTop = double.negativeInfinity;
    VerseLocation? bestBelow;
    double bestBelowTop = double.infinity;

    for (final entry in _keys.entries) {
      final context = entry.value.currentContext;
      if (context == null) continue;

      final box = context.findRenderObject() as RenderBox?;
      if (box == null || !box.attached || !box.hasSize) continue;

      final top = box.localToGlobal(Offset.zero).dy;
      final bottom = top + box.size.height;
      if (bottom < topInset) continue;

      final location = _parseKey(entry.key);
      if (location == null) continue;

      if (top <= topInset && top > bestCrossingTop) {
        bestCrossingTop = top;
        bestCrossing = location;
      } else if (top > topInset && top < bestBelowTop) {
        bestBelowTop = top;
        bestBelow = location;
      }
    }

    return bestCrossing ?? bestBelow;
  }

  void scrollTo(
    int chapter,
    int verse, {
    Duration duration = const Duration(milliseconds: 450),
    double alignment = 0.14,
  }) {
    final context = _keys[_key(chapter, verse)]?.currentContext;
    if (context == null) return;

    Scrollable.ensureVisible(
      context,
      duration: duration,
      curve: Curves.easeOutCubic,
      alignment: alignment,
    );
  }

  String _key(int chapter, int verse) => '$chapter:$verse';

  VerseLocation? _parseKey(String value) {
    final parts = value.split(':');
    if (parts.length != 2) return null;

    final chapter = int.tryParse(parts[0]);
    final verse = int.tryParse(parts[1]);
    if (chapter == null || verse == null) return null;

    return VerseLocation(chapter: chapter, verse: verse);
  }
}
