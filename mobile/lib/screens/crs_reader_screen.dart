import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api.dart';
import '../core/auth.dart';
import '../core/local_progress.dart';
import '../core/strings.dart';
import '../core/theme.dart';
import '../models/models.dart';
import '../widgets/narrative_flow_sheet.dart';
import '../widgets/study_mode_sheet.dart';

class CrsReaderScreen extends ConsumerStatefulWidget {
  final int planId;
  final int nodeId;

  const CrsReaderScreen({super.key, required this.planId, required this.nodeId});

  @override
  ConsumerState<CrsReaderScreen> createState() => _CrsReaderScreenState();
}

class _CrsReaderScreenState extends ConsumerState<CrsReaderScreen> {
  final ScrollController _scroll = ScrollController();
  bool _showDeepening = false;
  bool _focusMode = false;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(localProgressProvider.notifier).setLastNode(widget.planId, widget.nodeId);
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 120) {
      if (!_showDeepening) setState(() => _showDeepening = true);
    }
  }

  void _toggleFocus() => setState(() => _focusMode = !_focusMode);

  void _swipeTo(BuildContext context, int nodeId) {
    context.replace('/crs/${widget.planId}/$nodeId');
  }

  Future<void> _handleContinue(
      BuildContext context, CrsNodeDetail node, AppStrings s) async {
    final anchor = node.blocks.where((b) => b.role == 'narrative_anchor').firstOrNull;
    if (anchor == null) return;

    await ref.read(localProgressProvider.notifier).markBlockCompleted(anchor.id);

    final token = ref.read(authProvider);
    if (token != null) {
      try {
        await ref.read(apiProvider).markBlockProgress(anchor.id, widget.planId, 'completed');
        ref.invalidate(progressSummaryProvider);
      } catch (_) {}
    }

    if (!context.mounted) return;

    final hasRelated = node.blocks.any((b) => b.role != 'narrative_anchor');
    if (hasRelated) {
      final freshNode = await ref.read(apiProvider).crsNode(widget.planId, node.nodeId);
      if (!context.mounted) return;
      await showNarrativeFlowSheet(context, node: freshNode, planId: widget.planId);
      if (token != null && context.mounted) ref.invalidate(progressSummaryProvider);
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
      data: (node) => _buildReader(context, node, s),
    );
  }

  Widget _buildReader(BuildContext context, CrsNodeDetail node, AppStrings s) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final bg = isDark ? BjColors.surfacePrimary : BjColors.surfaceReaderLight;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    final anchor = node.blocks.where((b) => b.role == 'narrative_anchor').firstOrNull;
    final related = node.blocks.where((b) => b.role != 'narrative_anchor').toList();

    final neighbors = ref.watch(neighborNodesProvider((widget.planId, widget.nodeId)));

    return GestureDetector(
      onTap: _focusMode ? _toggleFocus : null,
      onHorizontalDragEnd: (details) {
        final v = details.primaryVelocity ?? 0;
        if (v > 300 && neighbors.prevId != null) {
          _swipeTo(context, neighbors.prevId!);
        } else if (v < -300 && neighbors.nextId != null) {
          _swipeTo(context, neighbors.nextId!);
        }
      },
      child: Scaffold(
        backgroundColor: bg,
        appBar: _focusMode
            ? null
            : _buildAppBar(context, node, s, isDark, anchor?.displayReference),
        body: Stack(
          children: [
            CustomScrollView(
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
                              icon: Icon(Icons.text_fields,
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

            // Focus toggle button
            if (!_focusMode)
              Positioned(
                right: 20,
                bottom: 30,
                child: _FocusModeButton(onTap: _toggleFocus, isDark: isDark),
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
        if (node.compareGroup != null)
          IconButton(
            icon: const Icon(Icons.compare_arrows),
            tooltip: s.t('compararRelatos'),
            onPressed: () => context.push('/compare/${node.compareGroup!.id}'),
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

  const _ScriptureBody({
    required this.block,
    required this.displayMode,
    required this.textColor,
    required this.isDark,
    required this.s,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (block == null || displayMode == 'reference_only') {
      return _referenceOnlyBox(context, s.t('referenciaYContextoDisponibles'));
    }

    final blockAsync = ref.watch(readingBlockProvider(block!.id));

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
              ...detail.verses.map((v) => Padding(
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
                  )),
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

          // Preview of editorial note
          if (node.crs.editorialNote != null) ...[
            Text(
              node.crs.editorialNote!,
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
          Icons.text_fields,
          size: 20,
          color: isDark
              ? BjColors.textPrimaryDark.withValues(alpha: 0.7)
              : BjColors.textPrimaryLight.withValues(alpha: 0.7),
        ),
      ),
    );
  }
}
