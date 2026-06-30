import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../models/models.dart';

String slugifyEra(String s) => s
    .toLowerCase()
    .replaceAll(RegExp(r'[áàä]'), 'a')
    .replaceAll(RegExp(r'[éèë]'), 'e')
    .replaceAll(RegExp(r'[íìï]'), 'i')
    .replaceAll(RegExp(r'[óòö]'), 'o')
    .replaceAll(RegExp(r'[úùü]'), 'u')
    .replaceAll(RegExp(r'[^a-z0-9]+'), '-')
    .replaceAll(RegExp(r'^-+|-+$'), '');

class Era {
  final String title;
  final int sort;
  final List<CrsNodeItem> nodes;
  Era({required this.title, required this.sort, required this.nodes});
}

List<Era> buildEras(List<CrsNodeItem> nodes) {
  final mainNodes = nodes.where((n) => n.isMainStreamNode).toList()
    ..sort((a, b) {
      final esA = a.userFacingEraSort ?? 999;
      final esB = b.userFacingEraSort ?? 999;
      if (esA != esB) return esA.compareTo(esB);
      return a.rank.compareTo(b.rank);
    });

  final order = <String>[];
  final map = <String, List<CrsNodeItem>>{};
  final sorts = <String, int>{};
  for (final n in mainNodes) {
    final era = n.userFacingEra ?? n.era ?? n.eraSlug ?? 'General';
    if (!map.containsKey(era)) {
      order.add(era);
      map[era] = [];
      sorts[era] = n.userFacingEraSort ?? 999;
    }
    map[era]!.add(n);
  }
  return order.map((e) => Era(title: e, sort: sorts[e]!, nodes: map[e]!)).toList();
}

class RoutesListScreen extends ConsumerWidget {
  const RoutesListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final planAsync = ref.watch(streamPlanProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final cs = theme.colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Rutas'),
        elevation: 0,
      ),
      body: planAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.cloud_off, size: 40, color: cs.onSurfaceVariant),
              const SizedBox(height: 8),
              Text('No se pudo cargar las rutas.', style: TextStyle(color: cs.onSurfaceVariant)),
              const SizedBox(height: 12),
              FilledButton(onPressed: () => ref.invalidate(streamPlanProvider), child: const Text('Reintentar')),
            ],
          ),
        ),
        data: (plan) {
          final eras = buildEras(plan.nodes);
          if (eras.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.route_outlined, size: 48, color: cs.onSurfaceVariant),
                    const SizedBox(height: 16),
                    Text(
                      'Aún no hay rutas disponibles.',
                      style: TextStyle(color: cs.onSurfaceVariant, fontSize: 15),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
            itemCount: eras.length,
            itemBuilder: (_, i) => Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: _EraCard(
                planId: plan.id,
                era: eras[i],
                isDark: isDark,
                textColor: textColor,
              ),
            ),
          );
        },
      ),
    );
  }
}

class _EraCard extends StatelessWidget {
  final int planId;
  final Era era;
  final bool isDark;
  final Color textColor;

  const _EraCard({required this.planId, required this.era, required this.isDark, required this.textColor});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return GestureDetector(
      onTap: () => context.push('/rutas/${slugifyEra(era.title)}', extra: era),
      child: Container(
        decoration: BoxDecoration(
          color: cs.surfaceContainer,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: cs.outline, width: 0.5),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 110,
              decoration: BoxDecoration(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: isDark
                      ? [
                          const Color(0xFF1A2A4A),
                          BjColors.accentPrimary.withValues(alpha: 0.3),
                          const Color(0xFF0D1830),
                        ]
                      : [
                          const Color(0xFFD4C8A8),
                          const Color(0xFFE8DFC4),
                          const Color(0xFFF0E8D0),
                        ],
                ),
              ),
              child: Stack(
                children: [
                  Positioned(
                    bottom: 0, left: 0, right: 0,
                    child: CustomPaint(
                      size: const Size(double.infinity, 40),
                      painter: _HillsPainter(isDark: isDark),
                    ),
                  ),
                  Positioned(
                    top: 12, left: 14,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: (isDark ? Colors.black : Colors.white).withValues(alpha: 0.25),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(
                          color: (isDark ? Colors.white : Colors.black).withValues(alpha: 0.12),
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.route_outlined,
                            size: 11,
                            color: isDark ? BjColors.accentPrimaryMid : BjColors.accentPrimary,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            'Era',
                            style: TextStyle(
                              color: isDark ? BjColors.accentPrimaryMid : BjColors.accentPrimary,
                              fontSize: 10,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          era.title,
                          style: TextStyle(
                            color: textColor,
                            fontWeight: FontWeight.w700,
                            fontSize: 17,
                            height: 1.3,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Icon(Icons.arrow_forward, size: 18, color: textColor.withValues(alpha: 0.35)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: BjColors.accentBronze.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(color: BjColors.accentBronze.withValues(alpha: 0.2)),
                        ),
                        child: Text(
                          '${era.nodes.length} ${era.nodes.length == 1 ? 'acontecimiento' : 'acontecimientos'}',
                          style: TextStyle(
                            color: BjColors.accentBronze,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HillsPainter extends CustomPainter {
  final bool isDark;
  const _HillsPainter({required this.isDark});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = (isDark ? Colors.black : Colors.white).withValues(alpha: isDark ? 0.15 : 0.20)
      ..style = PaintingStyle.fill;

    final path = Path();
    path.moveTo(0, size.height);
    path.quadraticBezierTo(size.width * 0.25, size.height * 0.3, size.width * 0.5, size.height * 0.55);
    path.quadraticBezierTo(size.width * 0.75, size.height * 0.8, size.width, size.height * 0.4);
    path.lineTo(size.width, size.height);
    path.close();
    canvas.drawPath(path, paint);

    final paint2 = Paint()
      ..color = (isDark ? Colors.black : Colors.white).withValues(alpha: isDark ? 0.08 : 0.12)
      ..style = PaintingStyle.fill;

    final path2 = Path();
    path2.moveTo(0, size.height);
    path2.quadraticBezierTo(size.width * 0.35, size.height * 0.5, size.width * 0.6, size.height * 0.7);
    path2.quadraticBezierTo(size.width * 0.8, size.height * 0.85, size.width, size.height * 0.6);
    path2.lineTo(size.width, size.height);
    path2.close();
    canvas.drawPath(path2, paint2);
  }

  @override
  bool shouldRepaint(_) => false;
}
