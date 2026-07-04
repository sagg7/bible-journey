import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../core/api.dart';
import '../core/auth.dart';
import '../core/local_progress.dart';
import '../core/theme.dart';
import '../models/models.dart';

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
/// (by user_facing_era_sort, then rank).
List<CrsNodeItem> _orderedMainNodes(List<CrsNodeItem> nodes) {
  final mainNodes = nodes.where((n) => n.isMainStreamNode).toList()
    ..sort((a, b) {
      final esA = a.userFacingEraSort ?? 999;
      final esB = b.userFacingEraSort ?? 999;
      if (esA != esB) return esA.compareTo(esB);
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
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
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
                Positioned(top: 18, left: 24, child: _AtmosphericDot(size: 2.5, opacity: 0.4)),
                Positioned(top: 30, left: 60, child: _AtmosphericDot(size: 1.5, opacity: 0.25)),
                Positioned(top: 12, left: 110, child: _AtmosphericDot(size: 2.0, opacity: 0.3)),
                Positioned(top: 22, right: 80, child: _AtmosphericDot(size: 1.5, opacity: 0.2)),
                Positioned(
                  top: 12,
                  left: 16,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
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
                      style: TextStyle(color: cs.onSurfaceVariant, fontSize: 11),
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
  const _RouteProgressCard({required this.planTitle, required this.totalMainNodes});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final auth = ref.watch(authProvider);

    if (auth == null) {
      final localAsync = ref.watch(localProgressProvider);
      final completedCount = localAsync.valueOrNull?.completedBlockIds.length ?? 0;
      final pct = totalMainNodes > 0 ? (completedCount / totalMainNodes).clamp(0.0, 1.0) : 0.0;

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
              style: TextStyle(color: cs.onSurface, fontWeight: FontWeight.w600, fontSize: 15),
            ),
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: pct,
                minHeight: 4,
                backgroundColor: cs.outline,
                valueColor: AlwaysStoppedAnimation<Color>(BjColors.accentPrimary),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              '$completedCount de $totalMainNodes lecturas completadas en este dispositivo.',
              style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12, height: 1.4),
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
      loading: () => _ProgressSkeleton(routeTitle: planTitle, subtitle: 'Cargando…'),
      error: (err, _) => _ProgressSkeleton(routeTitle: planTitle, subtitle: 'No se pudo cargar el progreso.'),
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
                style: TextStyle(color: cs.onSurface, fontWeight: FontWeight.w600, fontSize: 15),
              ),
              const SizedBox(height: 12),
              _ProgressRow(
                label: 'Progreso narrativo',
                value: narrativePct,
                color: BjColors.accentPrimary,
                subtitle: '${summary.narrative.primaryComplete + summary.narrative.fullyComplete} de ${summary.narrative.total} acontecimientos',
              ),
              const SizedBox(height: 10),
              _ProgressRow(
                label: 'Cobertura bíblica',
                value: canonicalPct,
                color: BjColors.accentBronze,
                subtitle: '${summary.canonical.completed} de ${summary.canonical.total} pasajes',
              ),
            ],
          ),
        );
      },
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
            Text(label, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 11)),
            Text(
              '${(value * 100).round()}%',
              style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700),
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
        Text(subtitle, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 10)),
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
          Text(routeTitle, style: TextStyle(color: cs.onSurface, fontWeight: FontWeight.w600, fontSize: 15)),
          const SizedBox(height: 8),
          Text(subtitle, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
        ],
      ),
    );
  }
}

// ─── "Keep exploring" card ─────────────────────

class _ConnectionCard extends StatelessWidget {
  final int planId;
  final CrsNodeItem node;
  const _ConnectionCard({required this.planId, required this.node});

  @override
  Widget build(BuildContext context) {
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
              Icon(Icons.auto_stories_outlined, size: 14, color: BjColors.accentBronzeLight),
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
            style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13, height: 1.55),
          ),
          const SizedBox(height: 12),
          GestureDetector(
            onTap: () => context.push('/crs/$planId/${node.id}'),
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
          }
        },
        itemBuilder: (_) => const [
          PopupMenuItem(value: 'logout', child: Text('Cerrar sesión')),
        ],
      );
    }
    return IconButton(
      icon: const Icon(Icons.account_circle_outlined, size: 22),
      tooltip: 'Cuenta',
      onPressed: () => context.push('/auth'),
    );
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
            Icon(Icons.cloud_off, size: 48, color: Theme.of(context).colorScheme.onSurfaceVariant),
            const SizedBox(height: 12),
            const Text('No se pudo cargar el contenido.', textAlign: TextAlign.center),
            if (error != null) ...[
              const SizedBox(height: 8),
              Text(
                error.toString(),
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12, color: Theme.of(context).colorScheme.onSurfaceVariant),
              ),
            ],
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Reintentar')),
            const SizedBox(height: 16),
            Text(
              version.maybeWhen(data: (v) => 'Versión $v', orElse: () => ''),
              style: TextStyle(fontSize: 10, color: Theme.of(context).colorScheme.onSurfaceVariant),
            ),
          ],
        ),
      ),
    );
  }
}
