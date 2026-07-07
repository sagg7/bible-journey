import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api.dart';
import '../core/auth.dart';
import '../core/local_progress.dart';
import '../core/strings.dart';
import '../core/theme.dart';
import '../models/models.dart';
import '../widgets/bookmark_button.dart';
import '../widgets/highlight_selection_bar.dart';
import '../widgets/narrative_flow_sheet.dart';
import '../widgets/pinch_zoom_listener.dart';
import '../widgets/study_mode_sheet.dart';
import '../widgets/text_zoom_sheet.dart';

/// The block to render as the main scripture text. Prefers the
/// `narrative_anchor` block; if a CRS has no anchor (it's a standalone
/// literary/genealogical/poetic window — ~25% of CRS, e.g. all Psalms and
/// most of 1 Chronicles), falls back to the first block so the reader still
/// shows its text instead of an empty placeholder.
ReadingBlockV2? primaryBlock(CrsNodeDetail node) {
  if (node.blocks.isEmpty) return null;
  return node.blocks.where((b) => b.role == 'narrative_anchor').firstOrNull ??
      node.blocks.first;
}

class CrsReaderScreen extends ConsumerStatefulWidget {
  final int planId;
  final int nodeId;

  const CrsReaderScreen({super.key, required this.planId, required this.nodeId});

  @override
  ConsumerState<CrsReaderScreen> createState() => _CrsReaderScreenState();
}

class _CrsReaderScreenState extends ConsumerState<CrsReaderScreen> {
  final ScrollController _scroll = ScrollController();
  final HighlightSelection _selection = HighlightSelection();
  bool _showDeepening = false;
  bool _focusMode = false;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    _selection.addListener(_onSelectionChanged);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(localProgressProvider.notifier).setLastNode(widget.planId, widget.nodeId);
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    _selection.removeListener(_onSelectionChanged);
    super.dispose();
  }

  void _onScroll() {
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 120) {
      if (!_showDeepening) setState(() => _showDeepening = true);
    }
  }

  void _onSelectionChanged() => setState(() {});

  void _toggleFocus() => setState(() => _focusMode = !_focusMode);

  void _swipeTo(BuildContext context, int nodeId, {CrsNodeDetail? completingNode}) {
    _selection.clear();
    // Swiping forward is how most users actually move to the next chapter —
    // not everyone taps the "Continuar" button — so it must mark the chapter
    // just swiped away from as read too, same as the button does.
    if (completingNode != null) _markCompleted(completingNode);
    context.replace('/crs/${widget.planId}/$nodeId');
  }

  Future<void> _markCompleted(CrsNodeDetail node) async {
    final anchor = primaryBlock(node);
    if (anchor == null) return;

    await ref.read(localProgressProvider.notifier).markBlockCompleted(anchor.id);

    final token = ref.read(authProvider);
    if (token != null) {
      try {
        await ref.read(apiProvider).markBlockProgress(anchor.id, widget.planId, 'completed');
        ref.invalidate(progressSummaryProvider);
      } catch (_) {}
    }
  }

  Future<void> _handleContinue(
      BuildContext context, CrsNodeDetail node, AppStrings s) async {
    final anchor = primaryBlock(node);
    if (anchor == null) return;

    await _markCompleted(node);

    if (!context.mounted) return;

    final hasRelated = node.blocks.any((b) => b.id != anchor.id);
    if (hasRelated) {
      final freshNode = await ref.read(apiProvider).crsNode(widget.planId, node.nodeId);
      if (!context.mounted) return;
      await showNarrativeFlowSheet(context, node: freshNode, planId: widget.planId);
      if (ref.read(authProvider) != null && context.mounted) {
        ref.invalidate(progressSummaryProvider);
      }
    } else {
      final neighbors = ref.read(neighborNodesProvider((widget.planId, widget.nodeId)));
      if (neighbors.nextId != null && context.mounted) {
        context.replace('/crs/${widget.planId}/${neighbors.nextId}');
      } else if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(s.t('completado')),
            duration: const Duration(seconds: 2),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final nodeAsync = ref.watch(crsNodeProvider((widget.planId, widget.nodeId)));
    final s = AppStrings(ref.watch(localeProvider));

    return nodeAsync.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: Text(s.t('tabLeer'))),
        body: Center(child: Text(e.toString())),
      ),
      data: (node) => node.locked
          ? _buildLocked(context, node)
          : _buildReader(context, node, s),
    );
  }

  Widget _buildLocked(BuildContext context, CrsNodeDetail node) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios),
          onPressed: () => context.pop(),
        ),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.lock_outline, size: 48, color: BjColors.accentBronze),
              const SizedBox(height: 16),
              Text(
                node.crs.titleEs,
                textAlign: TextAlign.center,
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Text(
                'Este evento es parte del plan cronológico completo. Suscríbete para seguir la historia.',
                textAlign: TextAlign.center,
                style: TextStyle(color: cs.onSurfaceVariant, height: 1.5),
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: () => context.push('/suscripcion'),
                child: const Text('Ver planes de suscripción'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildReader(BuildContext context, CrsNodeDetail node, AppStrings s) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final bg = isDark ? BjColors.surfacePrimary : BjColors.surfaceReaderLight;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    final anchor = primaryBlock(node);
    final related = node.blocks.where((b) => b.id != anchor?.id).toList();

    final neighbors = ref.watch(neighborNodesProvider((widget.planId, widget.nodeId)));
    final blockDetailAsync =
        anchor != null ? ref.watch(readingBlockProvider(anchor.id)) : null;
    final blockDetail = blockDetailAsync?.value;

    return GestureDetector(
      onTap: _focusMode ? _toggleFocus : null,
      onHorizontalDragEnd: (details) {
        final v = details.primaryVelocity ?? 0;
        if (v > 300 && neighbors.prevId != null) {
          _swipeTo(context, neighbors.prevId!);
        } else if (v < -300 && neighbors.nextId != null) {
          _swipeTo(context, neighbors.nextId!, completingNode: node);
        }
      },
      child: Scaffold(
        backgroundColor: bg,
        appBar: _focusMode
            ? null
            : _buildAppBar(context, node, s, isDark, anchor?.displayReference),
        body: Stack(
          children: [
            PinchZoomListener(
              child: CustomScrollView(
              controller: _scroll,
              slivers: [
                // Focus mode minimal header
                if (_focusMode)
                  SliverToBoxAdapter(
                    child: SafeArea(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Nodo ${node.rank}',
                              style: theme.textTheme.labelSmall?.copyWith(
                                  color: textColor.withValues(alpha: 0.5)),
                            ),
                            IconButton(
                              icon: Icon(Icons.center_focus_strong,
                                  color: textColor.withValues(alpha: 0.5)),
                              onPressed: _toggleFocus,
                            ),
                          ],
                        ),
                      ),
                    ),
                  )
                else
                  SliverToBoxAdapter(child: _MetadataStrip(node: node)),

                // Main title
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(24, 24, 24, 8),
                    child: Text(
                      node.crs.titleEs,
                      style: theme.textTheme.headlineSmall?.copyWith(
                        color: textColor,
                        fontWeight: FontWeight.w700,
                        height: 1.3,
                      ),
                    ),
                  ),
                ),

                // Anchor reference
                if (anchor != null)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(24, 0, 24, 20),
                      child: Text(
                        anchor.displayReference,
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: BjColors.accentBronze,
                          letterSpacing: 0.5,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),

                // Scripture text
                SliverToBoxAdapter(
                  child: _ScriptureBody(
                    block: anchor,
                    displayMode: node.displayMode,
                    textColor: textColor,
                    isDark: isDark,
                    s: s,
                    selection: _selection,
                  ),
                ),

                // Related blocks
                if (related.isNotEmpty && !_focusMode)
                  SliverToBoxAdapter(
                    child: _RelatedBlocksList(blocks: related, isDark: isDark),
                  ),

                // Deepening cards + continue button (appears at scroll end)
                if (_showDeepening && !_focusMode)
                  SliverToBoxAdapter(
                    child: _DeepeningSection(
                      node: node,
                      planId: widget.planId,
                      isDark: isDark,
                      s: s,
                      onContinue: () => _handleContinue(context, node, s),
                    ),
                  ),

                const SliverToBoxAdapter(child: SizedBox(height: 80)),
              ],
              ),
            ),

            // Focus toggle button
            if (!_focusMode && !_selection.isActive)
              Positioned(
                right: 20,
                bottom: 30,
                child: _FocusModeButton(onTap: _toggleFocus, isDark: isDark),
              ),

            if (_selection.isActive && blockDetail != null)
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                child: HighlightSelectionBar(
                  bookOsisCode: blockDetail.bookOsisCode ?? '',
                  bookNameEs: blockDetail.bookNameEs ?? '',
                  chapter: blockDetail.primaryChapter ?? 1,
                  verses: blockDetail.verses,
                  translationCode: blockDetail.translationCode,
                  selection: _selection,
                  onSaved: () => setState(_selection.clear),
                  onCancel: () => setState(_selection.clear),
                ),
              ),
          ],
        ),
      ),
    );
  }

  PreferredSizeWidget _buildAppBar(
      BuildContext context, CrsNodeDetail node, AppStrings s, bool isDark,
      [String? anchorReference]) {
    final theme = Theme.of(context);
    return AppBar(
      backgroundColor:
          isDark ? BjColors.surfacePrimary : BjColors.surfaceReaderLight,
      elevation: 0,
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_ios),
        onPressed: () => context.pop(),
      ),
      title: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            node.crs.era,
            style: theme.textTheme.labelSmall?.copyWith(
              color: BjColors.accentBronze,
              fontWeight: FontWeight.w600,
            ),
          ),
          Text(
            node.crs.titleEs,
            style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
      actions: [
        BookmarkButton.crs(
          color: theme.colorScheme.onSurfaceVariant,
          label: node.crs.titleEs,
          planId: widget.planId,
          nodeId: widget.nodeId,
        ),
        TextZoomButton(color: theme.colorScheme.onSurfaceVariant),
        if (node.compareGroup != null)
          IconButton(
            icon: const Icon(Icons.compare_arrows),
            tooltip: s.t('compararRelatos'),
            onPressed: () => context.push('/compare/${node.compareGroup!.id}'),
          ),
        TextButton.icon(
          onPressed: () async {
            final code = await context.push<String>('/traducciones');
            if (code != null) {
              ref.read(translationProvider.notifier).state = code;
              ref.read(localProgressProvider.notifier).setTranslation(code);
            }
          },
          style: TextButton.styleFrom(
            foregroundColor: theme.colorScheme.onSurfaceVariant,
            padding: const EdgeInsets.symmetric(horizontal: 8),
          ),
          icon: Text(
            ref.watch(translationProvider) ?? 'RVA1909',
            style: theme.textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w600),
          ),
          label: const Icon(Icons.expand_more, size: 16),
        ),
        IconButton(
          icon: const Icon(Icons.auto_awesome_outlined),
          onPressed: () =>
              context.push('/ezra/v2?node_id=${widget.nodeId}&plan_id=${widget.planId}'),
        ),
      ],
    );
  }
}

// ─── Metadata strip ──────────────────────────────────────────────────────────

class _MetadataStrip extends StatelessWidget {
  final CrsNodeDetail node;
  const _MetadataStrip({required this.node});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final borderColor = isDark ? BjColors.surfaceBorder : const Color(0xFFE0DDD8);
    final mutedColor = isDark
        ? BjColors.textPrimaryDark.withValues(alpha: 0.6)
        : BjColors.textPrimaryLight.withValues(alpha: 0.6);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: borderColor, width: 0.5)),
      ),
      child: Row(
        children: [
          CertaintyBadge(label: node.crs.placementConfidence, compact: true),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              node.crs.era,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(color: mutedColor),
            ),
          ),
          Text(
            'Nodo ${node.rank}',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: isDark
                  ? BjColors.textPrimaryDark.withValues(alpha: 0.4)
                  : BjColors.textPrimaryLight.withValues(alpha: 0.4),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Scripture body ───────────────────────────────────────────────────────────

class _ScriptureBody extends ConsumerWidget {
  final ReadingBlockV2? block;
  final String displayMode;
  final Color textColor;
  final bool isDark;
  final AppStrings s;
  final HighlightSelection selection;

  const _ScriptureBody({
    required this.block,
    required this.displayMode,
    required this.textColor,
    required this.isDark,
    required this.s,
    required this.selection,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (block == null || displayMode == 'reference_only') {
      return _referenceOnlyBox(context, s.t('referenciaYContextoDisponibles'));
    }

    final blockAsync = ref.watch(readingBlockProvider(block!.id));
    final fontScale = ref.watch(effectiveFontScaleProvider);
    final fontFamily =
        ref.watch(localProgressProvider).value?.fontFamily ?? kDefaultScriptureFont;

    return blockAsync.when(
      loading: () => const Padding(
        padding: EdgeInsets.symmetric(horizontal: 24, vertical: 32),
        child: Center(child: CircularProgressIndicator()),
      ),
      error: (e, _) =>
          _referenceOnlyBox(context, s.t('referenciaYContextoDisponibles')),
      data: (detail) {
        if (!detail.hasText || detail.verses.isEmpty) {
          return _referenceOnlyBox(
              context, s.t('referenciaYContextoDisponibles'));
        }
        final bookOsisCode = detail.bookOsisCode ?? '';
        final chapter = detail.primaryChapter ?? 0;
        final highlights =
            ref.watch(chapterHighlightsProvider((bookOsisCode, chapter))).value ??
                [];
        // Most blocks are a single chapter, but some (e.g. a narrative span
        // covering the end of one chapter and the start of the next) carry
        // verses from more than one. Show a heading whenever the chapter
        // changes so it doesn't read as one continuous chapter.
        final spansMultipleChapters =
            detail.verses.map((v) => v.chapter).toSet().length > 1;
        int? lastChapterShown;

        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (detail.translationName != null)
                Text(
                  detail.translationName!,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: BjColors.accentBronze,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.5,
                  ),
                ),
              const SizedBox(height: 16),
              ...detail.verses.expand((v) {
                final selected = selection.contains(v.verse);
                final savedHex = highlights
                    .where((h) => h.containsVerse(v.verse))
                    .map((h) => h.color.hex)
                    .firstOrNull;

                final showChapterHeading =
                    spansMultipleChapters && v.chapter != lastChapterShown;
                if (showChapterHeading) lastChapterShown = v.chapter;

                final verseTile = GestureDetector(
                  onLongPress: () => selection.beginAt(v.verse),
                  onTap: selection.isActive ? () => selection.extendTo(v.verse) : null,
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    padding: const EdgeInsets.symmetric(vertical: 2, horizontal: 4),
                    decoration: BoxDecoration(
                      color: selected
                          ? BjColors.accentPrimary.withValues(alpha: 0.25)
                          : savedHex != null
                              ? hexToColor(savedHex).withValues(alpha: 0.35)
                              : null,
                      borderRadius: BorderRadius.circular(4),
                    ),
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
                            style: scriptureTextStyle(
                                    fontSize: 17 * fontScale,
                                    height: 1.8,
                                    fontFamily: fontFamily)
                                .copyWith(color: textColor),
                          ),
                        ],
                      ),
                    ),
                  ),
                );

                if (!showChapterHeading) return [verseTile];
                return [
                  Padding(
                    padding: EdgeInsets.only(top: v.chapter == detail.verses.first.chapter ? 0 : 20, bottom: 10),
                    child: Row(
                      children: [
                        Expanded(child: Divider(color: textColor.withValues(alpha: 0.15))),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          child: Text(
                            'Capítulo ${v.chapter}',
                            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                  color: textColor.withValues(alpha: 0.5),
                                  fontWeight: FontWeight.w700,
                                  letterSpacing: 0.6,
                                ),
                          ),
                        ),
                        Expanded(child: Divider(color: textColor.withValues(alpha: 0.15))),
                      ],
                    ),
                  ),
                  verseTile,
                ];
              }),
              const SizedBox(height: 32),
            ],
          ),
        );
      },
    );
  }

  Widget _referenceOnlyBox(BuildContext context, String message) {
    final borderColor = isDark ? BjColors.surfaceBorder : const Color(0xFFE0DDD8);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          border: Border.all(color: borderColor),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          message,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: textColor.withValues(alpha: 0.6),
            fontStyle: FontStyle.italic,
          ),
        ),
      ),
    );
  }
}

// ─── Related blocks list ──────────────────────────────────────────────────────

class _RelatedBlocksList extends StatelessWidget {
  final List<ReadingBlockV2> blocks;
  final bool isDark;

  const _RelatedBlocksList({required this.blocks, required this.isDark});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 8, 24, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Divider(height: 32),
          BjSectionLabel('LECTURAS RELACIONADAS'),
          const SizedBox(height: 12),
          ...blocks.map((b) => _RelatedBlockRow(block: b, isDark: isDark)),
        ],
      ),
    );
  }
}

class _RelatedBlockRow extends StatelessWidget {
  final ReadingBlockV2 block;
  final bool isDark;

  const _RelatedBlockRow({required this.block, required this.isDark});

  String get _roleLabel {
    const map = {
      'parallel_account': 'Relato paralelo',
      'complementary_account': 'Relato complementario',
      'prophetic_context': 'Contexto profético',
      'poetic_literary_mirror': 'Espejo poético',
      'legal_covenant_context': 'Contexto legal',
      'genealogical_context': 'Genealogía',
      'epistolary_context': 'Contexto epistolar',
      'supplementary_reading': 'Lectura suplementaria',
    };
    return map[block.role] ?? block.role;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final cardColor = isDark ? BjColors.surfaceCard : const Color(0xFFEFEDE8);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: cardColor,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    block.displayReference,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: textColor,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    _roleLabel,
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: textColor.withValues(alpha: 0.6),
                    ),
                  ),
                ],
              ),
            ),
            CertaintyBadge(label: block.placementConfidence, compact: true),
          ],
        ),
      ),
    );
  }
}

// ─── Deepening section ────────────────────────────────────────────────────────

class _DeepeningSection extends StatelessWidget {
  final CrsNodeDetail node;
  final int planId;
  final bool isDark;
  final AppStrings s;
  final VoidCallback onContinue;

  const _DeepeningSection({
    required this.node,
    required this.planId,
    required this.isDark,
    required this.s,
    required this.onContinue,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Divider(height: 40),
          BjSectionLabel(s.t('modoDeEstudio')),
          const SizedBox(height: 16),

          // Preview of the study content summary
          if (node.studyContent.summaryEs != null) ...[
            Text(
              node.studyContent.summaryEs!,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall?.copyWith(
                color: textColor.withValues(alpha: 0.75),
                height: 1.55,
              ),
            ),
            const SizedBox(height: 16),
          ],

          // Study Mode button
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => showStudyModeSheet(context, node: node, planId: planId),
              icon: const Icon(Icons.auto_stories_outlined, size: 18),
              label: Text(s.t('modoDeEstudio')),
              style: OutlinedButton.styleFrom(
                foregroundColor: BjColors.accentPrimary,
                side: BorderSide(color: BjColors.accentPrimary.withValues(alpha: 0.5)),
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),
          const SizedBox(height: 10),

          // Quick Ezra button
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () =>
                  context.push('/ezra/v2?node_id=${node.nodeId}&plan_id=$planId'),
              icon: const Icon(Icons.auto_awesome_outlined, size: 18),
              label: const Text('Preguntar a Ezra'),
              style: OutlinedButton.styleFrom(
                foregroundColor: isDark
                    ? BjColors.textPrimaryDark.withValues(alpha: 0.7)
                    : BjColors.textPrimaryLight.withValues(alpha: 0.7),
                side: BorderSide(color: textColor.withValues(alpha: 0.2)),
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),

          if (node.compareGroup != null) ...[
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => context.push('/compare/${node.compareGroup!.id}'),
                icon: const Icon(Icons.compare_arrows, size: 18),
                label: Text(s.t('compararRelatos')),
                style: OutlinedButton.styleFrom(
                  foregroundColor: BjColors.accentBronze,
                  side: BorderSide(color: BjColors.accentBronze.withValues(alpha: 0.4)),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
          ],

          // Continue — marks as read and navigates to next
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: onContinue,
              icon: const Icon(Icons.check, size: 18),
              label: Text(s.t('continuarLaHistoria')),
              style: FilledButton.styleFrom(
                backgroundColor: BjColors.accentPrimary,
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Focus mode button ────────────────────────────────────────────────────────

class _FocusModeButton extends StatelessWidget {
  final VoidCallback onTap;
  final bool isDark;

  const _FocusModeButton({required this.onTap, required this.isDark});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: isDark ? BjColors.surfaceCard : const Color(0xFFE8E6E1),
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.15),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Icon(
          Icons.center_focus_strong,
          size: 20,
          color: isDark
              ? BjColors.textPrimaryDark.withValues(alpha: 0.7)
              : BjColors.textPrimaryLight.withValues(alpha: 0.7),
        ),
      ),
    );
  }
}
