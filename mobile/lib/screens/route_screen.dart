import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import '../core/api.dart';
import '../core/strings.dart';
import '../core/theme.dart';
import '../models/models.dart';
import 'home_screen.dart';
import 'routes_list_screen.dart';

/// Era detail screen — shows the main-stream CRS nodes for one era of the
/// active Stream Plan, grouped into the same 4 tabs as the original Journey
/// design (Historia / Línea de tiempo / Personas / Mapa), now backed by the
/// real chronological engine instead of the old single-route pilot data.
class RouteScreen extends ConsumerStatefulWidget {
  final String slug;
  final Era? era;
  const RouteScreen({super.key, required this.slug, this.era});

  @override
  ConsumerState<RouteScreen> createState() => _RouteScreenState();
}

class _RouteScreenState extends ConsumerState<RouteScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings(ref.watch(localeProvider));
    final cs = Theme.of(context).colorScheme;

    // Prefer the era passed via `extra` (no extra API call). Fall back to
    // re-deriving it from the already-loaded plan, matching by slug — covers
    // the case where this screen is opened without the `extra` payload.
    if (widget.era != null) {
      return _buildBody(context, s, cs, widget.era!);
    }

    final planAsync = ref.watch(streamPlanProvider);
    return Scaffold(
      body: planAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Text('${s.t('errorLoading')}\n$e',
              textAlign: TextAlign.center, style: TextStyle(color: cs.error)),
        ),
        data: (plan) {
          final eras = buildEras(plan.nodes);
          final era = eras.firstWhere(
            (e) => slugifyEra(e.title) == widget.slug,
            orElse: () => Era(title: '', sort: 0, nodes: const []),
          );
          return _buildBody(context, s, cs, era, planId: plan.id);
        },
      ),
    );
  }

  Widget _buildBody(BuildContext context, AppStrings s, ColorScheme cs, Era era, {int? planId}) {
    final nodes = era.nodes;
    final resolvedPlanId = planId ?? (nodes.isNotEmpty ? null : null);

    return Scaffold(
      body: NestedScrollView(
        headerSliverBuilder: (context, innerBoxIsScrolled) => [
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            actions: const [LanguageToggle()],
            flexibleSpace: FlexibleSpaceBar(
              titlePadding: const EdgeInsets.fromLTRB(16, 0, 16, 56),
              title: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    era.title,
                    style: GoogleFonts.inter(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
                ],
              ),
              background: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      BjColors.accentPrimary.withValues(alpha: 0.6),
                      BjColors.surfacePrimary,
                    ],
                  ),
                ),
                child: Align(
                  alignment: Alignment.bottomLeft,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 56),
                    child: Text(
                      '${nodes.length} ${nodes.length == 1 ? 'acontecimiento' : 'acontecimientos'}',
                      style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12),
                    ),
                  ),
                ),
              ),
            ),
            bottom: TabBar(
              controller: _tabController,
              isScrollable: false,
              indicatorColor: BjColors.accentPrimary,
              indicatorWeight: 2,
              tabs: [
                Tab(text: s.t('historia')),
                Tab(text: s.t('lineaDeTiempo')),
                Tab(text: s.t('personas')),
                Tab(text: s.t('mapa')),
              ],
            ),
          ),
        ],
        body: TabBarView(
          controller: _tabController,
          children: [
            _HistoriaTab(nodes: nodes, planId: resolvedPlanId),
            _LineaDeTiempoTab(nodes: nodes, planId: resolvedPlanId),
            _PersonasTab(nodes: nodes, planId: resolvedPlanId),
            _MapaTab(nodes: nodes),
          ],
        ),
      ),
    );
  }
}

/// Resolves the plan id for navigation when it wasn't passed explicitly —
/// reads the currently-cached streamPlanProvider value (already loaded by
/// the time this screen is reachable).
class _PlanIdResolver extends ConsumerWidget {
  final int? planId;
  final Widget Function(BuildContext, int) builder;
  const _PlanIdResolver({required this.planId, required this.builder});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (planId != null) return builder(context, planId!);
    final plan = ref.watch(streamPlanProvider).valueOrNull;
    if (plan == null) return const SizedBox.shrink();
    return builder(context, plan.id);
  }
}

// ─── Historia tab ─────────────────────────────

class _HistoriaTab extends StatelessWidget {
  final List<CrsNodeItem> nodes;
  final int? planId;
  const _HistoriaTab({required this.nodes, required this.planId});

  @override
  Widget build(BuildContext context) {
    if (nodes.isEmpty) {
      return Center(
        child: Text('Sin acontecimientos.',
            style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
      );
    }
    return _PlanIdResolver(
      planId: planId,
      builder: (context, planId) => ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
        itemCount: nodes.length,
        itemBuilder: (context, index) {
          final node = nodes[index];
          return _NodeTimelineTile(
            node: node,
            planId: planId,
            isLast: index == nodes.length - 1,
          );
        },
      ),
    );
  }
}

class _NodeTimelineTile extends StatelessWidget {
  final CrsNodeItem node;
  final int planId;
  final bool isLast;
  const _NodeTimelineTile({required this.node, required this.planId, required this.isLast});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 28,
            child: Column(
              children: [
                Container(
                  width: 14,
                  height: 14,
                  margin: const EdgeInsets.only(top: 14, left: 7),
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: BjColors.accentPrimary.withValues(alpha: 0.2),
                    border: Border.all(color: BjColors.accentPrimary, width: 1.5),
                  ),
                ),
                if (!isLast)
                  Expanded(
                    child: Container(width: 1.5, color: cs.outline.withValues(alpha: 0.5)),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: GestureDetector(
              onTap: () => context.push('/crs/$planId/${node.id}'),
              child: Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: cs.surfaceContainer,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: cs.outline, width: 0.5),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      node.displayTitle,
                      style: TextStyle(color: cs.onSurface, fontWeight: FontWeight.w600, fontSize: 14),
                    ),
                    if (node.reference != null) ...[
                      const SizedBox(height: 2),
                      Text(node.reference!, style: TextStyle(color: cs.onSurfaceVariant, fontSize: 11)),
                    ],
                    if (node.confidence != null) ...[
                      const SizedBox(height: 8),
                      CertaintyBadge(label: node.confidence, compact: true),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Línea de tiempo tab ──────────────────────

class _LineaDeTiempoTab extends StatelessWidget {
  final List<CrsNodeItem> nodes;
  final int? planId;
  const _LineaDeTiempoTab({required this.nodes, required this.planId});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    if (nodes.isEmpty) {
      return Center(
        child: Text('Sin acontecimientos.', style: TextStyle(color: theme.colorScheme.onSurfaceVariant)),
      );
    }

    return _PlanIdResolver(
      planId: planId,
      builder: (context, planId) => ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 100),
        itemCount: nodes.length,
        itemBuilder: (context, i) {
          final n = nodes[i];
          final isLast = i == nodes.length - 1;
          return IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 40,
                  child: Padding(
                    padding: const EdgeInsets.only(top: 2, right: 8),
                    child: Text(
                      '${n.rank}',
                      textAlign: TextAlign.right,
                      style: TextStyle(
                        color: BjColors.accentBronze,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
                Column(
                  children: [
                    Container(
                      width: 10,
                      height: 10,
                      margin: const EdgeInsets.only(top: 4),
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        color: BjColors.accentPrimary,
                      ),
                    ),
                    if (!isLast)
                      Expanded(
                        child: Container(width: 1.5, color: theme.colorScheme.outline.withValues(alpha: 0.35)),
                      ),
                  ],
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: GestureDetector(
                    onTap: () => context.push('/crs/$planId/${n.id}'),
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            n.displayTitle,
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: textColor,
                              fontWeight: FontWeight.w600,
                              height: 1.3,
                            ),
                          ),
                          if (n.reference != null) ...[
                            const SizedBox(height: 3),
                            Text(
                              n.reference!,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: textColor.withValues(alpha: 0.55),
                                height: 1.4,
                              ),
                            ),
                          ],
                          if (n.confidence != null) ...[
                            const SizedBox(height: 6),
                            CertaintyBadge(label: n.confidence!, compact: true),
                          ],
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

// ─── Personas tab ─────────────────────────────

class _PersonasTab extends StatelessWidget {
  final List<CrsNodeItem> nodes;
  final int? planId;
  const _PersonasTab({required this.nodes, required this.planId});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return _PlanIdResolver(
      planId: planId,
      builder: (context, planId) => ListView(
        padding: const EdgeInsets.fromLTRB(16, 24, 16, 100),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: BjColors.accentPrimary.withValues(alpha: 0.06),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: BjColors.accentPrimary.withValues(alpha: 0.15)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.person_search_outlined, color: BjColors.accentPrimary, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Los personajes de cada acontecimiento están disponibles en el Modo de Estudio de la lectura. Toca uno para explorarlos.',
                    style: theme.textTheme.bodySmall?.copyWith(color: textColor.withValues(alpha: 0.8), height: 1.5),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          ...nodes.map((n) => InkWell(
                onTap: () => context.push('/crs/$planId/${n.id}'),
                borderRadius: BorderRadius.circular(10),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
                  child: Row(
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          color: BjColors.accentPrimary.withValues(alpha: 0.1),
                          shape: BoxShape.circle,
                        ),
                        child: Center(
                          child: Icon(Icons.people_outline, size: 18, color: BjColors.accentPrimary),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(n.displayTitle,
                                style: theme.textTheme.bodyMedium?.copyWith(color: textColor, fontWeight: FontWeight.w600)),
                            if (n.reference != null)
                              Text(n.reference!,
                                  style: theme.textTheme.labelSmall?.copyWith(color: textColor.withValues(alpha: 0.5))),
                          ],
                        ),
                      ),
                      Icon(Icons.chevron_right, size: 16, color: textColor.withValues(alpha: 0.3)),
                    ],
                  ),
                ),
              )),
        ],
      ),
    );
  }
}

// ─── Mapa tab ─────────────────────────────────

class _MapaTab extends StatelessWidget {
  final List<CrsNodeItem> nodes;
  const _MapaTab({required this.nodes});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 100),
      children: [
        Container(
          height: 160,
          decoration: BoxDecoration(
            color: isDark ? BjColors.surfaceRaised : const Color(0xFFEEEBE4),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: isDark ? BjColors.surfaceBorder : const Color(0xFFD9D5CE)),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.map_outlined, size: 40, color: textColor.withValues(alpha: 0.25)),
              const SizedBox(height: 10),
              Text(
                'Vista de mapa',
                style: theme.textTheme.titleSmall?.copyWith(color: textColor.withValues(alpha: 0.4), fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 4),
              Text(
                'Disponible cuando se añadan coordenadas a las secuencias',
                style: theme.textTheme.labelSmall?.copyWith(color: textColor.withValues(alpha: 0.35)),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        ...nodes.map((n) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.location_on_outlined, size: 16, color: BjColors.accentBronze),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(n.displayTitle, style: theme.textTheme.bodySmall?.copyWith(color: textColor, fontWeight: FontWeight.w600)),
                        if (n.reference != null)
                          Text(n.reference!, style: theme.textTheme.labelSmall?.copyWith(color: textColor.withValues(alpha: 0.45))),
                      ],
                    ),
                  ),
                ],
              ),
            )),
      ],
    );
  }
}
