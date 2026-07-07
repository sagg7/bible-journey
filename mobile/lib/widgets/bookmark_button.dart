import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/local_progress.dart';

/// Explicit "mark my reading spot" control — deliberately separate from the
/// automatic lastNode/lastCanonical tracking, which updates on every screen
/// visit even when the user is just browsing. Tapping this is the user
/// saying "this is where I want to come back to."
class BookmarkButton extends ConsumerWidget {
  final Color color;
  final String label;

  // Either crs (planId + nodeId) or canonical (osisCode + chapter).
  final int? planId;
  final int? nodeId;
  final String? osisCode;
  final int? chapter;

  const BookmarkButton.crs({
    super.key,
    required this.color,
    required this.label,
    required int this.planId,
    required int this.nodeId,
  })  : osisCode = null,
        chapter = null;

  const BookmarkButton.canonical({
    super.key,
    required this.color,
    required this.label,
    required String this.osisCode,
    required int this.chapter,
  })  : planId = null,
        nodeId = null;

  bool _matches(LocalProgress p) {
    if (planId != null) {
      return p.bookmarkType == 'crs' &&
          p.bookmarkPlanId == planId &&
          p.bookmarkNodeId == nodeId;
    }
    return p.bookmarkType == 'canonical' &&
        p.bookmarkOsisCode == osisCode &&
        p.bookmarkChapter == chapter;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final progress = ref.watch(localProgressProvider).value;
    final isBookmarked = progress != null && _matches(progress);

    return IconButton(
      icon: Icon(isBookmarked ? Icons.bookmark : Icons.bookmark_border, color: color),
      tooltip: isBookmarked ? 'Quitar marcador' : 'Marcar como mi lugar de lectura',
      onPressed: () async {
        final notifier = ref.read(localProgressProvider.notifier);
        if (isBookmarked) {
          await notifier.clearBookmark();
          if (context.mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Marcador eliminado'), duration: Duration(seconds: 2)),
            );
          }
          return;
        }
        if (planId != null) {
          await notifier.setBookmarkCrs(planId!, nodeId!, label);
        } else {
          await notifier.setBookmarkCanonical(osisCode!, chapter!, label);
        }
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Marcado: $label'), duration: const Duration(seconds: 2)),
          );
        }
      },
    );
  }
}
