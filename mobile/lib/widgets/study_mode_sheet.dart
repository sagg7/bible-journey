import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/api.dart';
import '../core/strings.dart';
import '../core/theme.dart';
import '../models/models.dart';

/// Study Mode bottom sheet — 5 tabs: Resumen · Contexto · Personas · Conexiones · Notas
class StudyModeSheet extends ConsumerStatefulWidget {
  final CrsNodeDetail node;
  final int planId;

  const StudyModeSheet({super.key, required this.node, required this.planId});

  @override
  ConsumerState<StudyModeSheet> createState() => _StudyModeSheetState();
}

class _StudyModeSheetState extends ConsumerState<StudyModeSheet>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  static const _labels = ['Resumen', 'Contexto', 'Personas', 'Conexiones', 'Notas'];

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: _labels.length, vsync: this);
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final bg = isDark ? BjColors.surfaceRaised : Colors.white;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final s = AppStrings(ref.watch(localeProvider));
    final screenH = MediaQuery.of(context).size.height;

    return Container(
      height: screenH * 0.88,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        children: [
          // Drag handle
          Padding(
            padding: const EdgeInsets.only(top: 12, bottom: 4),
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: textColor.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),

          // Header row
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 6, 8, 2),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        s.t('modoDeEstudio'),
                        style: theme.textTheme.titleMedium?.copyWith(
                          color: textColor,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        widget.node.crs.titleEs,
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: textColor.withValues(alpha: 0.55),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: Icon(Icons.close, color: textColor.withValues(alpha: 0.5)),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
          ),

          // Tab bar
          TabBar(
            controller: _tabs,
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            labelColor: BjColors.accentPrimary,
            unselectedLabelColor: textColor.withValues(alpha: 0.5),
            indicatorColor: BjColors.accentPrimary,
            dividerColor: textColor.withValues(alpha: 0.1),
            labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
            tabs: _labels.map((l) => Tab(text: l)).toList(),
          ),

          // Content
          Expanded(
            child: TabBarView(
              controller: _tabs,
              children: [
                _ResumenTab(
                  node: widget.node,
                  onTabSwitch: _tabs.animateTo,
                  s: s,
                  isDark: isDark,
                ),
                _ContextoTab(
                  node: widget.node,
                  planId: widget.planId,
                  s: s,
                  isDark: isDark,
                ),
                _PersonasTab(s: s, isDark: isDark),
                _ConexionesTab(
                  node: widget.node,
                  planId: widget.planId,
                  s: s,
                  isDark: isDark,
                ),
                _NotasTab(
                  nodeId: widget.node.nodeId,
                  s: s,
                  isDark: isDark,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Open StudyModeSheet as a modal bottom sheet.
Future<void> showStudyModeSheet(
  BuildContext context, {
  required CrsNodeDetail node,
  required int planId,
}) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => StudyModeSheet(node: node, planId: planId),
  );
}

// ─── Tab 1: Resumen ───────────────────────────────────────────────────────────

class _ResumenTab extends StatelessWidget {
  final CrsNodeDetail node;
  final void Function(int) onTabSwitch;
  final AppStrings s;
  final bool isDark;

  const _ResumenTab({
    required this.node,
    required this.onTabSwitch,
    required this.s,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // "Qué sucedió" section
          BjSectionLabel(s.t('queSucedio')),
          const SizedBox(height: 10),
          if (node.crs.editorialNote != null)
            Text(
              node.crs.editorialNote!,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: textColor,
                height: 1.6,
              ),
            )
          else
            _PlaceholderText(
              '${node.crs.titleEs} — ${node.crs.era}',
              isDark: isDark,
              theme: theme,
            ),

          const SizedBox(height: 24),

          // "Por qué importa en la historia" section
          BjSectionLabel(s.t('porQueImportaEnLaHistoria')),
          const SizedBox(height: 10),
          if (node.crs.narrativeFlowMessage != null)
            Text(
              node.crs.narrativeFlowMessage!,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: textColor,
                height: 1.6,
              ),
            )
          else
            _PlaceholderText(
              'Información del flujo narrativo disponible próximamente.',
              isDark: isDark,
              theme: theme,
            ),

          const SizedBox(height: 28),

          // "Explora más" chips row
          BjSectionLabel(s.t('explorarMas')),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _ExploreChip(
                icon: Icons.people_outline,
                label: s.t('personas'),
                color: BjColors.accentPrimary,
                onTap: () => onTabSwitch(2),
              ),
              _ExploreChip(
                icon: Icons.place_outlined,
                label: s.t('lugar'),
                color: BjColors.accentBronze,
                onTap: () => onTabSwitch(1),
              ),
              _ExploreChip(
                icon: Icons.link,
                label: s.t('conexiones'),
                color: BjColors.certaintyHigh,
                onTap: () => onTabSwitch(3),
              ),
              _ExploreChip(
                icon: Icons.history_edu_outlined,
                label: s.t('contexto'),
                color: BjColors.certaintyProbable,
                onTap: () => onTabSwitch(1),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ─── Tab 2: Contexto ──────────────────────────────────────────────────────────

class _ContextoTab extends ConsumerWidget {
  final CrsNodeDetail node;
  final int planId;
  final AppStrings s;
  final bool isDark;

  const _ContextoTab({
    required this.node,
    required this.planId,
    required this.s,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Era + placement row
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      s.t('lineaDeTiempo'),
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: textColor.withValues(alpha: 0.5),
                        letterSpacing: 0.8,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      node.crs.era,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: textColor,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              CertaintyBadge(label: node.crs.placementConfidence),
            ],
          ),

          const SizedBox(height: 20),
          Divider(color: textColor.withValues(alpha: 0.08)),
          const SizedBox(height: 16),

          // Source map
          BjSectionLabel('FUENTE BÍBLICA'),
          const SizedBox(height: 8),
          Text(
            node.crs.sourceMap,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: textColor,
              height: 1.5,
            ),
          ),

          const SizedBox(height: 20),

          // Event confidence
          BjSectionLabel('CERTEZA DEL EVENTO'),
          const SizedBox(height: 8),
          Row(
            children: [
              CertaintyBadge(label: node.crs.eventConfidence),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  _confidenceLabel(node.crs.eventConfidence),
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: textColor.withValues(alpha: 0.7),
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ),

          if (node.explanationEs != null) ...[
            const SizedBox(height: 20),
            Divider(color: textColor.withValues(alpha: 0.08)),
            const SizedBox(height: 16),
            BjSectionLabel('¿POR QUÉ ESTÁ AQUÍ?'),
            const SizedBox(height: 8),
            Text(
              node.explanationEs!,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: textColor,
                height: 1.6,
              ),
            ),
          ],

          const SizedBox(height: 20),
          Divider(color: textColor.withValues(alpha: 0.08)),
          const SizedBox(height: 16),

          // Nodo info
          _InfoRow(
            label: 'Posición en la secuencia',
            value: 'Nodo ${node.rank}',
            textColor: textColor,
            theme: theme,
          ),
          _InfoRow(
            label: 'Modo de visualización',
            value: _displayModeLabel(node.displayMode),
            textColor: textColor,
            theme: theme,
          ),
        ],
      ),
    );
  }

  String _confidenceLabel(String confidence) {
    return switch (confidence) {
      'alta' => 'El evento está bien documentado y ampliamente aceptado.',
      'probable' => 'La evidencia apunta fuertemente a que sucedió.',
      'debatida' => 'Existen interpretaciones distintas entre estudiosos.',
      'tradicion_popular' => 'Basado en tradición, no en fuentes directas.',
      'especulativa' => 'Inferencia editorial sin base documental sólida.',
      _ => confidence,
    };
  }

  String _displayModeLabel(String mode) {
    return switch (mode) {
      'full' => 'Texto completo',
      'reference_only' => 'Solo referencia',
      'narrative_flow' => 'Flujo narrativo',
      _ => mode,
    };
  }
}

// ─── Tab 3: Personas ──────────────────────────────────────────────────────────

class _PersonasTab extends StatelessWidget {
  final AppStrings s;
  final bool isDark;

  const _PersonasTab({required this.s, required this.isDark});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: BjColors.accentPrimary.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.people_outline,
                  size: 32, color: BjColors.accentPrimary),
            ),
            const SizedBox(height: 16),
            Text(
              s.t('personas'),
              style: theme.textTheme.titleMedium?.copyWith(
                color: textColor,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Los personajes de este pasaje estarán disponibles en la próxima actualización.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: textColor.withValues(alpha: 0.6),
                height: 1.5,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Tab 4: Conexiones ────────────────────────────────────────────────────────

class _ConexionesTab extends StatelessWidget {
  final CrsNodeDetail node;
  final int planId;
  final AppStrings s;
  final bool isDark;

  const _ConexionesTab({
    required this.node,
    required this.planId,
    required this.s,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final related = node.blocks.where((b) => b.role != 'narrative_anchor').toList();

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Compare group card
          if (node.compareGroup != null) ...[
            BjSectionLabel(s.t('compararRelatos')),
            const SizedBox(height: 10),
            _ConnectionCard(
              icon: Icons.compare_arrows,
              title: node.compareGroup!.titleEs,
              subtitle: node.compareGroup!.relationLevel != null
                  ? 'Nivel de relación: ${node.compareGroup!.relationLevel}'
                  : null,
              color: BjColors.accentBronze,
              onTap: () => context.push('/compare/${node.compareGroup!.id}'),
              isDark: isDark,
              theme: theme,
            ),
            const SizedBox(height: 20),
          ],

          // Related blocks
          if (related.isNotEmpty) ...[
            BjSectionLabel('LECTURAS RELACIONADAS'),
            const SizedBox(height: 10),
            ...related.map((b) => _RelatedConnectionRow(
                  block: b,
                  textColor: textColor,
                  isDark: isDark,
                  theme: theme,
                )),
            const SizedBox(height: 20),
          ],

          // Placeholder for future graph connections
          BjSectionLabel('CONEXIONES EN EL PLAN'),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: isDark ? BjColors.surfaceCard : const Color(0xFFF0EDE8),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              'El grafo de conexiones entre eventos del plan estará disponible próximamente.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: textColor.withValues(alpha: 0.6),
                height: 1.5,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Tab 5: Notas ─────────────────────────────────────────────────────────────

class _NotasTab extends ConsumerStatefulWidget {
  final int nodeId;
  final AppStrings s;
  final bool isDark;

  const _NotasTab({required this.nodeId, required this.s, required this.isDark});

  @override
  ConsumerState<_NotasTab> createState() => _NotasTabState();
}

class _NotasTabState extends ConsumerState<_NotasTab> {
  final _controller = TextEditingController();
  bool _loading = true;
  bool _saving = false;

  String get _prefKey => 'note_node_${widget.nodeId}';

  @override
  void initState() {
    super.initState();
    _loadNote();
  }

  Future<void> _loadNote() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_prefKey) ?? '';
    if (mounted) {
      setState(() {
        _controller.text = saved;
        _loading = false;
      });
    }
  }

  Future<void> _saveNote() async {
    setState(() => _saving = true);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefKey, _controller.text.trim());
    if (mounted) {
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Nota guardada'), duration: Duration(seconds: 2)),
      );
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final textColor = widget.isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          BjSectionLabel(widget.s.t('notas')),
          const SizedBox(height: 12),
          Expanded(
            child: TextField(
              controller: _controller,
              maxLines: null,
              expands: true,
              textAlignVertical: TextAlignVertical.top,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: textColor,
                height: 1.6,
              ),
              decoration: InputDecoration(
                hintText:
                    'Escribe tus reflexiones, preguntas o conexiones personales sobre este pasaje…',
                hintStyle: TextStyle(
                  color: textColor.withValues(alpha: 0.35),
                  fontSize: 14,
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: textColor.withValues(alpha: 0.15)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: textColor.withValues(alpha: 0.15)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: BjColors.accentPrimary, width: 1.5),
                ),
                contentPadding: const EdgeInsets.all(14),
                filled: true,
                fillColor: widget.isDark
                    ? BjColors.surfaceCard
                    : const Color(0xFFF8F6F0),
              ),
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: _saving ? null : _saveNote,
              icon: _saving
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.save_outlined, size: 18),
              label: const Text('Guardar nota'),
              style: FilledButton.styleFrom(
                backgroundColor: BjColors.accentPrimary,
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Reusable sub-widgets ─────────────────────────────────────────────────────

class _ExploreChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _ExploreChip({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: color.withValues(alpha: 0.3)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PlaceholderText extends StatelessWidget {
  final String text;
  final bool isDark;
  final ThemeData theme;

  const _PlaceholderText(this.text, {required this.isDark, required this.theme});

  @override
  Widget build(BuildContext context) {
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    return Text(
      text,
      style: theme.textTheme.bodyMedium?.copyWith(
        color: textColor.withValues(alpha: 0.5),
        fontStyle: FontStyle.italic,
        height: 1.5,
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final Color textColor;
  final ThemeData theme;

  const _InfoRow({
    required this.label,
    required this.value,
    required this.textColor,
    required this.theme,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 170,
            child: Text(
              label,
              style: theme.textTheme.labelSmall?.copyWith(
                color: textColor.withValues(alpha: 0.5),
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: theme.textTheme.bodySmall?.copyWith(color: textColor),
            ),
          ),
        ],
      ),
    );
  }
}

class _ConnectionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? subtitle;
  final Color color;
  final VoidCallback onTap;
  final bool isDark;
  final ThemeData theme;

  const _ConnectionCard({
    required this.icon,
    required this.title,
    this.subtitle,
    required this.color,
    required this.onTap,
    required this.isDark,
    required this.theme,
  });

  @override
  Widget build(BuildContext context) {
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final cardColor = isDark ? BjColors.surfaceCard : const Color(0xFFF0EDE8);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: cardColor,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 18, color: color),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: textColor,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (subtitle != null)
                    Text(
                      subtitle!,
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: textColor.withValues(alpha: 0.6),
                      ),
                    ),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: textColor.withValues(alpha: 0.3)),
          ],
        ),
      ),
    );
  }
}

class _RelatedConnectionRow extends StatelessWidget {
  final ReadingBlockV2 block;
  final Color textColor;
  final bool isDark;
  final ThemeData theme;

  const _RelatedConnectionRow({
    required this.block,
    required this.textColor,
    required this.isDark,
    required this.theme,
  });

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
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Container(
            width: 6,
            height: 6,
            margin: const EdgeInsets.only(top: 2, right: 10),
            decoration: BoxDecoration(
              color: BjColors.accentBronze,
              shape: BoxShape.circle,
            ),
          ),
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
                  _roleLabel,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: textColor.withValues(alpha: 0.55),
                  ),
                ),
              ],
            ),
          ),
          CertaintyBadge(label: block.placementConfidence, compact: true),
        ],
      ),
    );
  }
}
