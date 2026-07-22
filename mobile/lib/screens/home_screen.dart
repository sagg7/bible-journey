import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../core/api.dart';
import '../core/auth.dart';
import '../core/local_progress.dart';
import '../core/theme.dart';
import '../models/models.dart';
import '../widgets/study_mode_sheet.dart';
import 'routes_list_screen.dart' show buildEras, slugifyEra;

/// Shared language toggle for the AppBar.
class LanguageToggle extends ConsumerWidget {
  const LanguageToggle({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locale = ref.watch(localeProvider);
    return PopupMenuButton<String>(
      icon: const Icon(Icons.language, size: 20),
      initialValue: locale,
      onSelected: (v) => ref.read(localeProvider.notifier).state = v,
      itemBuilder: (_) => const [
        PopupMenuItem(value: 'es', child: Text('Español')),
        PopupMenuItem(value: 'en', child: Text('English')),
      ],
    );
  }
}

/// Returns the main-stream nodes ordered the same way the "Leer" tab does
/// (by canonical stream rank).
List<CrsNodeItem> _orderedMainNodes(List<CrsNodeItem> nodes) {
  final mainNodes = nodes.where((n) => n.isMainStreamNode).toList()
    ..sort((a, b) {
      return a.rank.compareTo(b.rank);
    });
  return mainNodes;
}

// ─────────────────────────────────────────────
// Home Screen — Modern Deep
// ─────────────────────────────────────────────

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Buenos días';
    if (hour < 19) return 'Buenas tardes';
    return 'Buenas noches';
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final planAsync = ref.watch(streamPlanProvider);
    final progressAsync = ref.watch(localProgressProvider);

    return Scaffold(
      appBar: AppBar(
        toolbarHeight: 64,
        leading: Padding(
          padding: const EdgeInsets.only(left: 16),
          child: Image.asset(
            'assets/icon/icon.png',
            width: 28,
            height: 28,
            errorBuilder: (context, err, stack) => const Icon(
              Icons.auto_stories,
              color: BjColors.accentBronzeLight,
              size: 22,
            ),
          ),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_greeting()),
            Consumer(
              builder: (context, ref, _) {
                final version = ref.watch(appVersionProvider);
                return Text(
                  version.maybeWhen(data: (v) => 'v$v', orElse: () => ''),
                  style: TextStyle(
                    fontSize: 10,
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                );
              },
            ),
          ],
        ),
        actions: [
          const _AccountButton(),
          IconButton(
            icon: const Icon(Icons.menu_book_outlined, size: 20),
            tooltip: 'Traducciones',
            onPressed: () => context.push('/traducciones'),
          ),
          const LanguageToggle(),
        ],
      ),
      body: planAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _ErrorView(
          error: e,
          onRetry: () => ref.invalidate(streamPlanProvider),
        ),
        data: (plan) {
          final mainNodes = _orderedMainNodes(plan.nodes);
          if (mainNodes.isEmpty) {
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
              children: [
                if (progressAsync.valueOrNull?.hasBookmark == true) ...[
                  BjSectionLabel('Tu marcador'),
                  const SizedBox(height: 10),
                  const _BookmarkCard(),
                  const SizedBox(height: 20),
                ],
                BjSectionLabel('Continuar lectura'),
                const SizedBox(height: 10),
                _EmptyReadingCard(),
              ],
            );
          }

          final lastNodeId = progressAsync.valueOrNull?.lastNodeId;
          final currentNode = lastNodeId != null
              ? mainNodes.firstWhere(
                  (n) => n.id == lastNodeId,
                  orElse: () => mainNodes.first,
                )
              : mainNodes.first;

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
            children: [
              if (progressAsync.valueOrNull?.hasBookmark == true) ...[
                BjSectionLabel('Tu marcador'),
                const SizedBox(height: 10),
                const _BookmarkCard(),
                const SizedBox(height: 20),
              ],

              BjSectionLabel('Continuar lectura'),
              const SizedBox(height: 10),
              _ContinueReadingCard(planId: plan.id, node: currentNode),
              const SizedBox(height: 20),

              BjSectionLabel('Progreso de la ruta'),
              const SizedBox(height: 10),
              _RouteProgressCard(
                planTitle: 'Plan cronológico',
                totalMainNodes: mainNodes.length,
              ),
              const SizedBox(height: 20),

              BjSectionLabel('Tu lugar en la historia'),
              const SizedBox(height: 10),
              _HistoricalTimelineCard(planId: plan.id, nodes: plan.nodes),
              const SizedBox(height: 20),

              BjSectionLabel('Sigue explorando'),
              const SizedBox(height: 10),
              _ConnectionCard(planId: plan.id, node: currentNode),
            ],
          );
        },
      ),
    );
  }
}

// ─── Continue Reading card ────────────────────

class _ContinueReadingCard extends ConsumerWidget {
  final int planId;
  final CrsNodeItem node;
  const _ContinueReadingCard({required this.planId, required this.node});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      decoration: BoxDecoration(
        color: cs.surfaceContainer,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cs.outline, width: 0.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Atmospheric header
          Container(
            height: 90,
            decoration: BoxDecoration(
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(16),
              ),
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  const Color(0xFF1A2540),
                  BjColors.accentPrimary.withValues(alpha: 0.25),
                  const Color(0xFF0D1830),
                ],
              ),
            ),
            child: Stack(
              children: [
                Positioned(
                  top: 18,
                  left: 24,
                  child: _AtmosphericDot(size: 2.5, opacity: 0.4),
                ),
                Positioned(
                  top: 30,
                  left: 60,
                  child: _AtmosphericDot(size: 1.5, opacity: 0.25),
                ),
                Positioned(
                  top: 12,
                  left: 110,
                  child: _AtmosphericDot(size: 2.0, opacity: 0.3),
                ),
                Positioned(
                  top: 22,
                  right: 80,
                  child: _AtmosphericDot(size: 1.5, opacity: 0.2),
                ),
                Positioned(
                  top: 12,
                  left: 16,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: BjColors.accentPrimary.withValues(alpha: 0.18),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(
                        color: BjColors.accentPrimary.withValues(alpha: 0.35),
                      ),
                    ),
                    child: Text(
                      node.userFacingEra ?? node.era ?? 'Lectura continua',
                      style: TextStyle(
                        color: BjColors.accentPrimaryMid,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
                Positioned(
                  bottom: 10,
                  right: 14,
                  child: GestureDetector(
                    onTap: () => context.go('/leer'),
                    child: Text(
                      'Viendo: Lectura continua ▾',
                      style: TextStyle(
                        color: cs.onSurfaceVariant,
                        fontSize: 11,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Content
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  node.displayTitle,
                  style: GoogleFonts.inter(
                    color: BjColors.textPrimaryDark,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    height: 1.3,
                  ),
                ),
                if (node.reference != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    node.reference!,
                    style: TextStyle(
                      color: BjColors.accentBronzeLight,
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: () => context.push('/crs/$planId/${node.id}'),
                    child: const Text('Continuar lectura'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AtmosphericDot extends StatelessWidget {
  final double size;
  final double opacity;
  const _AtmosphericDot({required this.size, required this.opacity});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: opacity),
      ),
    );
  }
}

// ─── Bookmark card ────────────────────────────
//
// Shows the user's manually-set reading-spot bookmark (see BookmarkButton),
// separate from "Continuar lectura" which auto-tracks the last chronological
// node visited. This is the only quick-access resume point for canonical
// (book-by-book) reading, which otherwise has no "continue" affordance.
class _BookmarkCard extends ConsumerWidget {
  const _BookmarkCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final progress = ref.watch(localProgressProvider).value;
    if (progress == null || !progress.hasBookmark) {
      return const SizedBox.shrink();
    }

    final isCrs = progress.bookmarkType == 'crs';
    final hasVerse = progress.bookmarkVerse != null;
    final destination = isCrs
        ? '/crs/${progress.bookmarkPlanId}/${progress.bookmarkNodeId}'
              '${hasVerse ? '?chapter=${progress.bookmarkChapter}&verse=${progress.bookmarkVerse}' : ''}'
        : '/canonical/${progress.bookmarkOsisCode}/${progress.bookmarkChapter}'
              '${hasVerse ? '?verse=${progress.bookmarkVerse}' : ''}';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cs.surfaceContainer,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: BjColors.accentBronze.withValues(alpha: 0.35),
          width: 0.5,
        ),
      ),
      child: Row(
        children: [
          Icon(Icons.bookmark, color: BjColors.accentBronzeLight, size: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              progress.bookmarkLabel ?? '',
              style: TextStyle(
                color: cs.onSurface,
                fontWeight: FontWeight.w600,
                fontSize: 14,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          const SizedBox(width: 8),
          FilledButton.tonal(
            onPressed: () => context.push(destination),
            // Override the app-wide FilledButton theme, which defaults to
            // minimumSize: Size(double.infinity, 48) for full-width CTAs —
            // that forces infinite width when placed inside a Row here.
            style: FilledButton.styleFrom(
              minimumSize: const Size(0, 36),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            ),
            child: const Text('Ir'),
          ),
        ],
      ),
    );
  }
}

class _EmptyReadingCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: cs.surfaceContainer,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cs.outline, width: 0.5),
      ),
      child: Column(
        children: [
          Icon(Icons.menu_book_outlined, size: 40, color: cs.onSurfaceVariant),
          const SizedBox(height: 12),
          Text(
            'Aún no hay contenido disponible',
            textAlign: TextAlign.center,
            style: TextStyle(color: cs.onSurfaceVariant),
          ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: () => context.go('/leer'),
            child: const Text('Explorar lectura'),
          ),
        ],
      ),
    );
  }
}

// ─── Route Progress card ─────────────────────

class _RouteProgressCard extends ConsumerWidget {
  final String planTitle;
  final int totalMainNodes;
  const _RouteProgressCard({
    required this.planTitle,
    required this.totalMainNodes,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final auth = ref.watch(authProvider);

    if (auth == null) {
      final localAsync = ref.watch(localProgressProvider);
      final completedCount =
          localAsync.valueOrNull?.completedBlockIds.length ?? 0;
      final pct = totalMainNodes > 0
          ? (completedCount / totalMainNodes).clamp(0.0, 1.0)
          : 0.0;

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: cs.surfaceContainer,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: cs.outline, width: 0.5),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              planTitle,
              style: TextStyle(
                color: cs.onSurface,
                fontWeight: FontWeight.w600,
                fontSize: 15,
              ),
            ),
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: pct,
                minHeight: 4,
                backgroundColor: cs.outline,
                valueColor: AlwaysStoppedAnimation<Color>(
                  BjColors.accentPrimary,
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              '$completedCount de $totalMainNodes lecturas completadas en este dispositivo.',
              style: TextStyle(
                color: cs.onSurfaceVariant,
                fontSize: 12,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 2),
            GestureDetector(
              onTap: () => context.push('/auth'),
              child: Text(
                'Inicia sesión para sincronizarlo en todos tus dispositivos.',
                style: TextStyle(
                  color: BjColors.accentPrimary,
                  fontSize: 12,
                  height: 1.4,
                  decoration: TextDecoration.underline,
                  decorationColor: BjColors.accentPrimary,
                ),
              ),
            ),
          ],
        ),
      );
    }

    final summaryAsync = ref.watch(progressSummaryProvider);
    return summaryAsync.when(
      loading: () =>
          _ProgressSkeleton(routeTitle: planTitle, subtitle: 'Cargando…'),
      error: (err, _) => _ProgressSkeleton(
        routeTitle: planTitle,
        subtitle: 'No se pudo cargar el progreso.',
      ),
      data: (summary) {
        final narrativePct = summary.narrative.percent / 100.0;
        final canonicalPct = summary.canonical.percent / 100.0;

        return Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: cs.surfaceContainer,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: cs.outline, width: 0.5),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                planTitle,
                style: TextStyle(
                  color: cs.onSurface,
                  fontWeight: FontWeight.w600,
                  fontSize: 15,
                ),
              ),
              const SizedBox(height: 12),
              _ProgressRow(
                label: 'Progreso narrativo',
                value: narrativePct,
                color: BjColors.accentPrimary,
                subtitle:
                    '${summary.narrative.primaryComplete + summary.narrative.fullyComplete} de ${summary.narrative.total} acontecimientos',
              ),
              const SizedBox(height: 10),
              _ProgressRow(
                label: 'Cobertura bíblica',
                value: canonicalPct,
                color: BjColors.accentBronze,
                subtitle:
                    '${summary.canonical.completed} de ${summary.canonical.total} pasajes',
              ),
            ],
          ),
        );
      },
    );
  }
}

// ─── Historical timeline card ─────────────────
//
// A horizontal, era-by-era strip of the whole chronological plan. It doesn't
// assert exact years (many biblical dates are genuinely debated — see the
// `placement_confidence` field the backend already tracks per era), but the
// era order itself already *is* the approximate historical placement, so
// showing eras in sequence with fill-in progress gives the user a real sense
// of "where am I in history" without inventing disputed dates.
class _HistoricalTimelineCard extends ConsumerWidget {
  final int planId;
  final List<CrsNodeItem> nodes;
  const _HistoricalTimelineCard({required this.planId, required this.nodes});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final textColor = isDark
        ? BjColors.textPrimaryDark
        : BjColors.textPrimaryLight;
    final eras = buildEras(nodes);
    if (eras.isEmpty) return const SizedBox.shrink();

    final completed =
        ref.watch(localProgressProvider).valueOrNull?.completedBlockIds ??
        const <int>{};
    final lastNodeId = ref.watch(localProgressProvider).valueOrNull?.lastNodeId;

    return SizedBox(
      height: 108,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 4),
        itemCount: eras.length,
        itemBuilder: (context, i) {
          final era = eras[i];
          final total = era.nodes.length;
          final done = era.nodes.where((n) => completed.contains(n.id)).length;
          final fraction = total > 0 ? done / total : 0.0;
          final isCurrent = era.nodes.any((n) => n.id == lastNodeId);
          final isLast = i == eras.length - 1;

          return GestureDetector(
            onTap: () =>
                context.push('/rutas/${slugifyEra(era.title)}', extra: era),
            child: SizedBox(
              width: 96,
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: i == 0
                            ? const SizedBox()
                            : Container(
                                height: 2,
                                color: cs.outline.withValues(alpha: 0.35),
                              ),
                      ),
                      _EraProgressDot(fraction: fraction, isCurrent: isCurrent),
                      Expanded(
                        child: isLast
                            ? const SizedBox()
                            : Container(
                                height: 2,
                                color: cs.outline.withValues(alpha: 0.35),
                              ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    era.title,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: isCurrent
                          ? BjColors.accentPrimary
                          : textColor.withValues(alpha: 0.6),
                      fontWeight: isCurrent ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                  if (fraction > 0) ...[
                    const SizedBox(height: 2),
                    Text(
                      '$done/$total',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: textColor.withValues(alpha: 0.4),
                        fontSize: 10,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _EraProgressDot extends StatelessWidget {
  final double fraction;
  final bool isCurrent;
  const _EraProgressDot({required this.fraction, required this.isCurrent});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final complete = fraction >= 1.0;
    return SizedBox(
      width: 26,
      height: 26,
      child: Stack(
        alignment: Alignment.center,
        children: [
          SizedBox(
            width: 26,
            height: 26,
            child: CircularProgressIndicator(
              value: fraction == 0 ? 1.0 : fraction,
              strokeWidth: 2.5,
              backgroundColor: fraction == 0
                  ? cs.outline.withValues(alpha: 0.3)
                  : null,
              valueColor: AlwaysStoppedAnimation(
                fraction == 0 ? Colors.transparent : BjColors.accentPrimary,
              ),
            ),
          ),
          if (complete)
            Icon(Icons.check, size: 14, color: BjColors.accentPrimary)
          else
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isCurrent ? BjColors.accentPrimary : cs.outline,
              ),
            ),
        ],
      ),
    );
  }
}

class _ProgressRow extends StatelessWidget {
  final String label;
  final double value;
  final Color color;
  final String subtitle;

  const _ProgressRow({
    required this.label,
    required this.value,
    required this.color,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              label,
              style: TextStyle(color: cs.onSurfaceVariant, fontSize: 11),
            ),
            Text(
              '${(value * 100).round()}%',
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: value,
            minHeight: 4,
            backgroundColor: cs.outline,
            valueColor: AlwaysStoppedAnimation<Color>(color),
          ),
        ),
        const SizedBox(height: 3),
        Text(
          subtitle,
          style: TextStyle(color: cs.onSurfaceVariant, fontSize: 10),
        ),
      ],
    );
  }
}

class _ProgressSkeleton extends StatelessWidget {
  final String routeTitle;
  final String subtitle;
  const _ProgressSkeleton({required this.routeTitle, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cs.surfaceContainer,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cs.outline, width: 0.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            routeTitle,
            style: TextStyle(
              color: cs.onSurface,
              fontWeight: FontWeight.w600,
              fontSize: 15,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

// ─── "Keep exploring" card ─────────────────────

class _ConnectionCard extends ConsumerWidget {
  final int planId;
  final CrsNodeItem node;
  const _ConnectionCard({required this.planId, required this.node});

  Future<void> _openStudyMode(BuildContext context, WidgetRef ref) async {
    final freshNode = await ref.read(apiProvider).crsNode(planId, node.id);
    if (!context.mounted) return;
    await showStudyModeSheet(context, node: freshNode, planId: planId);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cs.surfaceContainer,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: BjColors.accentBronze.withValues(alpha: 0.2),
          width: 0.5,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.auto_stories_outlined,
                size: 14,
                color: BjColors.accentBronzeLight,
              ),
              const SizedBox(width: 6),
              Text(
                'Modo de estudio',
                style: TextStyle(
                  color: BjColors.accentBronzeLight,
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Profundiza en "${node.displayTitle}": contexto histórico, personajes, conexiones temáticas y preguntas para Ezra.',
            style: TextStyle(
              color: cs.onSurfaceVariant,
              fontSize: 13,
              height: 1.55,
            ),
          ),
          const SizedBox(height: 12),
          GestureDetector(
            onTap: () => _openStudyMode(context, ref),
            child: Text(
              'Explorar →',
              style: TextStyle(
                color: BjColors.accentPrimary,
                fontWeight: FontWeight.w600,
                fontSize: 13,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Account button ───────────────────────────

class _AccountButton extends ConsumerWidget {
  const _AccountButton();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final token = ref.watch(authProvider);
    if (token != null) {
      return PopupMenuButton<String>(
        icon: const Icon(Icons.account_circle, size: 22),
        onSelected: (v) async {
          if (v == 'logout') {
            await ref.read(apiProvider).logout();
            await ref.read(authProvider.notifier).logout();
          } else if (v == 'delete_account') {
            await _confirmAndDeleteAccount(context, ref);
          }
        },
        itemBuilder: (_) => const [
          PopupMenuItem(value: 'logout', child: Text('Cerrar sesión')),
          PopupMenuItem(
            value: 'delete_account',
            child: Text('Eliminar cuenta', style: TextStyle(color: Colors.red)),
          ),
        ],
      );
    }
    return IconButton(
      icon: const Icon(Icons.account_circle_outlined, size: 22),
      tooltip: 'Cuenta',
      onPressed: () => context.push('/auth'),
    );
  }

  /// Flujo de eliminación de cuenta (requisito de Google Play): confirma con
  /// la contraseña, borra en el servidor (DELETE /api/me — el progreso y los
  /// destacados se eliminan; las preguntas a Ezra se anonimizan) y cierra la
  /// sesión local.
  Future<void> _confirmAndDeleteAccount(
    BuildContext context,
    WidgetRef ref,
  ) async {
    final passwordController = TextEditingController();
    String? errorText;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setState) => AlertDialog(
          title: const Text('Eliminar cuenta'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Esta acción es permanente: se borrarán tu cuenta, tu progreso '
                'de lectura y tus versículos destacados. Las suscripciones se '
                'gestionan por separado en la tienda.\n\n'
                'Escribe tu contraseña para confirmar:',
              ),
              const SizedBox(height: 12),
              TextField(
                controller: passwordController,
                obscureText: true,
                autofocus: true,
                decoration: InputDecoration(
                  labelText: 'Contraseña',
                  errorText: errorText,
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: const Text('Cancelar'),
            ),
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: Colors.red),
              onPressed: () async {
                if (passwordController.text.isEmpty) {
                  setState(() => errorText = 'Escribe tu contraseña');
                  return;
                }
                try {
                  await ref
                      .read(apiProvider)
                      .deleteAccount(passwordController.text);
                  if (dialogContext.mounted) {
                    Navigator.of(dialogContext).pop(true);
                  }
                } on ApiException catch (e) {
                  setState(
                    () => errorText = e.status == 422
                        ? 'Contraseña incorrecta'
                        : 'No se pudo eliminar: ${e.message}',
                  );
                }
              },
              child: const Text('Eliminar definitivamente'),
            ),
          ],
        ),
      ),
    );

    if (confirmed == true) {
      // La cuenta ya no existe en el servidor; limpia la sesión local.
      await ref.read(authProvider.notifier).logout();
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Tu cuenta fue eliminada.')),
        );
      }
    }
  }
}

// ─── Error view ───────────────────────────────

class _ErrorView extends ConsumerWidget {
  final Object? error;
  final VoidCallback onRetry;
  const _ErrorView({this.error, required this.onRetry});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final version = ref.watch(appVersionProvider);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.cloud_off,
              size: 48,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 12),
            const Text(
              'No se pudo cargar el contenido.',
              textAlign: TextAlign.center,
            ),
            if (error != null) ...[
              const SizedBox(height: 8),
              Text(
                error.toString(),
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 12,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ],
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Reintentar')),
            const SizedBox(height: 16),
            Text(
              version.maybeWhen(data: (v) => 'Versión $v', orElse: () => ''),
              style: TextStyle(
                fontSize: 10,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
