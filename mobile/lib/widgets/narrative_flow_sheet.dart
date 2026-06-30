import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api.dart';
import '../core/strings.dart';
import '../core/theme.dart';
import '../models/models.dart';

/// Bottom sheet shown after completing the narrative anchor block.
/// Presents pending related blocks and two action choices:
///   1. "Continuar la historia" — defer related blocks (narrative_complete state)
///   2. "Leer relatos relacionados ahora" — dismiss sheet, stay on reader
class NarrativeFlowPendingSheet extends ConsumerStatefulWidget {
  final CrsNodeDetail node;
  final int planId;

  const NarrativeFlowPendingSheet({
    super.key,
    required this.node,
    required this.planId,
  });

  @override
  ConsumerState<NarrativeFlowPendingSheet> createState() =>
      _NarrativeFlowPendingSheetState();
}

class _NarrativeFlowPendingSheetState
    extends ConsumerState<NarrativeFlowPendingSheet> {
  bool _loading = false;

  List<ReadingBlockV2> get _relatedBlocks =>
      widget.node.blocks.where((b) => b.role != 'narrative_anchor').toList();

  Future<void> _continueStory() async {
    setState(() => _loading = true);
    try {
      await ref.read(apiProvider).markNodeState(
            widget.node.nodeId,
            widget.planId,
            'narrative_complete',
          );
      ref.invalidate(progressSummaryProvider);
      if (mounted) Navigator.of(context).pop(NarrativeFlowResult.deferred);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _readNow() => Navigator.of(context).pop(NarrativeFlowResult.readNow);

  @override
  Widget build(BuildContext context) {
    final s = AppStrings(ref.watch(localeProvider));
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final bg = isDark ? BjColors.surfaceRaised : Colors.white;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final mutedColor = textColor.withValues(alpha: 0.6);

    return Container(
      decoration: BoxDecoration(
        color: bg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Drag handle
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 20),
                  decoration: BoxDecoration(
                    color: mutedColor.withValues(alpha: 0.3),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),

              // Anchor completion check
              Row(
                children: [
                  Container(
                    width: 28,
                    height: 28,
                    decoration: const BoxDecoration(
                      color: BjColors.certaintyHigh,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.check, size: 16, color: Colors.white),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      s.t('relatoPrincipalCompletado'),
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: BjColors.certaintyHigh,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 16),
              Divider(color: textColor.withValues(alpha: 0.1)),
              const SizedBox(height: 16),

              // Pending related blocks header
              Text(
                s.t('esteAcontecimientoTieneLecturasPendientes'),
                style: theme.textTheme.bodyMedium?.copyWith(color: textColor),
              ),
              const SizedBox(height: 12),

              // Related block list
              ..._relatedBlocks.map(
                (b) => _PendingBlockRow(block: b, isDark: isDark),
              ),

              const SizedBox(height: 20),

              // Progress state description
              _ProgressStateRow(
                pendingCount: _relatedBlocks.length,
                isDark: isDark,
                s: s,
              ),

              const SizedBox(height: 20),

              // Action buttons
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _loading ? null : _continueStory,
                  icon: _loading
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.arrow_forward, size: 18),
                  label: Text(s.t('continuarLaHistoria')),
                  style: FilledButton.styleFrom(
                    backgroundColor: BjColors.accentPrimary,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _loading ? null : _readNow,
                  icon: const Icon(Icons.menu_book_outlined, size: 18),
                  label: Text(s.t('leerRelatosRelacionadosAhora')),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    side: BorderSide(
                      color: isDark
                          ? BjColors.surfaceBorder
                          : const Color(0xFFD4CFC8),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Result enum ─────────────────────────────────────────────────────────────

enum NarrativeFlowResult { deferred, readNow }


// ─── Pending block row ────────────────────────────────────────────────────────

class _PendingBlockRow extends StatelessWidget {
  final ReadingBlockV2 block;
  final bool isDark;

  const _PendingBlockRow({required this.block, required this.isDark});

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
    return block.displayLabelEs ?? map[block.role] ?? block.role;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 6,
            height: 6,
            margin: const EdgeInsets.only(top: 7),
            decoration: BoxDecoration(
              color: BjColors.accentBronze,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  block.displayReference,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: textColor,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  '$_roleLabel — ${_pendingLabel()}',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: textColor.withValues(alpha: 0.6),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _pendingLabel() => 'Pendiente';
}

// ─── Progress state row ───────────────────────────────────────────────────────

class _ProgressStateRow extends StatelessWidget {
  final int pendingCount;
  final bool isDark;
  final AppStrings s;

  const _ProgressStateRow({
    required this.pendingCount,
    required this.isDark,
    required this.s,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final bg = isDark
        ? BjColors.surfaceCard
        : const Color(0xFFF0EDE8);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Expanded(
            child: RichText(
              text: TextSpan(
                children: [
                  TextSpan(
                    text: '${s.t("progresoNarrativo")} ',
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: textColor.withValues(alpha: 0.7),
                    ),
                  ),
                  TextSpan(
                    text: s.t('completado'),
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: BjColors.certaintyHigh,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 12),
          Text(
            '$pendingCount ${s.t("relatosRelacionadosPendientes")}',
            style: theme.textTheme.labelSmall?.copyWith(
              color: textColor.withValues(alpha: 0.6),
            ),
          ),
        ],
      ),
    );
  }
}

/// Helper to show the sheet and return the user's choice.
Future<NarrativeFlowResult?> showNarrativeFlowSheet(
  BuildContext context, {
  required CrsNodeDetail node,
  required int planId,
}) {
  return showModalBottomSheet<NarrativeFlowResult>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => NarrativeFlowPendingSheet(node: node, planId: planId),
  );
}
