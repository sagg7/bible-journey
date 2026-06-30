import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api.dart';
import '../core/local_progress.dart';
import '../core/theme.dart';
import '../models/models.dart';

class CanonicalChapterScreen extends ConsumerStatefulWidget {
  final String osisCode;
  final int chapter;

  const CanonicalChapterScreen({
    super.key,
    required this.osisCode,
    required this.chapter,
  });

  @override
  ConsumerState<CanonicalChapterScreen> createState() =>
      _CanonicalChapterScreenState();
}

class _CanonicalChapterScreenState
    extends ConsumerState<CanonicalChapterScreen> {
  late int _currentChapter;

  @override
  void initState() {
    super.initState();
    _currentChapter = widget.chapter;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref
          .read(localProgressProvider.notifier)
          .setLastCanonical(widget.osisCode, widget.chapter);
    });
  }

  void _goChapter(int chapter) {
    setState(() => _currentChapter = chapter);
    ref
        .read(localProgressProvider.notifier)
        .setLastCanonical(widget.osisCode, chapter);
  }

  @override
  Widget build(BuildContext context) {
    final chapterAsync =
        ref.watch(canonicalChapterProvider((widget.osisCode, _currentChapter)));

    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? BjColors.surfacePrimary : BjColors.surfaceReaderLight;
    final textColor =
        isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return Scaffold(
      backgroundColor: bg,
      body: chapterAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off, size: 40),
              const SizedBox(height: 8),
              Text(e.toString()),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () => ref.invalidate(
                    canonicalChapterProvider((widget.osisCode, _currentChapter))),
                child: const Text('Reintentar'),
              ),
            ],
          ),
        ),
        data: (content) => _buildContent(context, content, textColor, isDark),
      ),
    );
  }

  Widget _buildContent(BuildContext context, CanonicalChapterContent content,
      Color textColor, bool isDark) {
    final theme = Theme.of(context);
    return CustomScrollView(
      slivers: [
        SliverAppBar(
          backgroundColor:
              isDark ? BjColors.surfacePrimary : BjColors.surfaceReaderLight,
          pinned: true,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios),
            onPressed: () => context.pop(),
          ),
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                content.bookNameEs,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: BjColors.accentBronze,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                'Capítulo ${content.chapter}',
                style: theme.textTheme.bodyMedium
                    ?.copyWith(fontWeight: FontWeight.w600),
              ),
            ],
          ),
          actions: [
            if (content.translationCode != null)
              Padding(
                padding: const EdgeInsets.only(right: 16),
                child: Center(
                  child: Text(
                    content.translationCode!,
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: textColor.withValues(alpha: 0.5),
                    ),
                  ),
                ),
              ),
          ],
        ),

        // Book + chapter heading
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 8),
            child: Text(
              '${content.bookNameEs} ${content.chapter}',
              style: theme.textTheme.headlineSmall?.copyWith(
                color: textColor,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),

        // Verses
        if (!content.hasText || content.verses.isEmpty)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  border: Border.all(
                      color: isDark
                          ? BjColors.surfaceBorder
                          : const Color(0xFFE0DDD8)),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  'Texto no disponible para este capítulo.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: textColor.withValues(alpha: 0.6),
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ),
            ),
          )
        else
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(24, 0, 24, 16),
            sliver: SliverList(
              delegate: SliverChildBuilderDelegate(
                (_, i) {
                  final v = content.verses[i];
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: RichText(
                      text: TextSpan(
                        children: [
                          TextSpan(
                            text: '${v.verse} ',
                            style: TextStyle(
                              color: textColor.withValues(alpha: 0.35),
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          TextSpan(
                            text: v.text,
                            style: scriptureTextStyle(fontSize: 17, height: 1.8)
                                .copyWith(color: textColor),
                          ),
                        ],
                      ),
                    ),
                  );
                },
                childCount: content.verses.length,
              ),
            ),
          ),

        // Prev / next navigation
        SliverToBoxAdapter(
          child: _ChapterNav(
            content: content,
            textColor: textColor,
            isDark: isDark,
            onChapter: _goChapter,
          ),
        ),

        const SliverToBoxAdapter(child: SizedBox(height: 48)),
      ],
    );
  }
}

class _ChapterNav extends StatelessWidget {
  final CanonicalChapterContent content;
  final Color textColor;
  final bool isDark;
  final ValueChanged<int> onChapter;

  const _ChapterNav({
    required this.content,
    required this.textColor,
    required this.isDark,
    required this.onChapter,
  });

  @override
  Widget build(BuildContext context) {
    final cardColor =
        isDark ? BjColors.surfaceCard : const Color(0xFFEFEDE8);

    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 8, 24, 0),
      child: Row(
        children: [
          if (content.prevChapter != null)
            Expanded(
              child: GestureDetector(
                onTap: () => onChapter(content.prevChapter!),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
                  decoration: BoxDecoration(
                    color: cardColor,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.chevron_left,
                          size: 18,
                          color: textColor.withValues(alpha: 0.6)),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          'Capítulo ${content.prevChapter}',
                          style: TextStyle(
                              color: textColor.withValues(alpha: 0.8),
                              fontSize: 13),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            )
          else
            const Expanded(child: SizedBox()),

          const SizedBox(width: 8),

          if (content.nextChapter != null)
            Expanded(
              child: GestureDetector(
                onTap: () => onChapter(content.nextChapter!),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
                  decoration: BoxDecoration(
                    color: cardColor,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Text(
                          'Capítulo ${content.nextChapter}',
                          textAlign: TextAlign.end,
                          style: TextStyle(
                              color: textColor.withValues(alpha: 0.8),
                              fontSize: 13),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 4),
                      Icon(Icons.chevron_right,
                          size: 18,
                          color: textColor.withValues(alpha: 0.6)),
                    ],
                  ),
                ),
              ),
            )
          else
            const Expanded(child: SizedBox()),
        ],
      ),
    );
  }
}
