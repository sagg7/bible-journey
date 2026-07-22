import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api.dart';
import '../core/theme.dart';
import '../models/models.dart';
import 'highlights_browse_view.dart';

// Canonical biblical books in order with chapter counts (Spanish names)
const _canonicalBooks = [
  ('Génesis', 50),
  ('Éxodo', 40),
  ('Levítico', 27),
  ('Números', 36),
  ('Deuteronomio', 34),
  ('Josué', 24),
  ('Jueces', 21),
  ('Rut', 4),
  ('1 Samuel', 31),
  ('2 Samuel', 24),
  ('1 Reyes', 22),
  ('2 Reyes', 25),
  ('1 Crónicas', 29),
  ('2 Crónicas', 36),
  ('Esdras', 10),
  ('Nehemías', 13),
  ('Ester', 10),
  ('Job', 42),
  ('Salmos', 150),
  ('Proverbios', 31),
  ('Eclesiastés', 12),
  ('Cantares', 8),
  ('Isaías', 66),
  ('Jeremías', 52),
  ('Lamentaciones', 5),
  ('Ezequiel', 48),
  ('Daniel', 12),
  ('Oseas', 14),
  ('Joel', 3),
  ('Amós', 9),
  ('Abdías', 1),
  ('Jonás', 4),
  ('Miqueas', 7),
  ('Nahúm', 3),
  ('Habacuc', 3),
  ('Sofonías', 3),
  ('Hageo', 2),
  ('Zacarías', 14),
  ('Malaquías', 4),
  ('Mateo', 28),
  ('Marcos', 16),
  ('Lucas', 24),
  ('Juan', 21),
  ('Hechos', 28),
  ('Romanos', 16),
  ('1 Corintios', 16),
  ('2 Corintios', 13),
  ('Gálatas', 6),
  ('Efesios', 6),
  ('Filipenses', 4),
  ('Colosenses', 4),
  ('1 Tesalonicenses', 5),
  ('2 Tesalonicenses', 3),
  ('1 Timoteo', 6),
  ('2 Timoteo', 4),
  ('Tito', 3),
  ('Filemón', 1),
  ('Hebreos', 13),
  ('Santiago', 5),
  ('1 Pedro', 5),
  ('2 Pedro', 3),
  ('1 Juan', 5),
  ('2 Juan', 1),
  ('3 Juan', 1),
  ('Judas', 1),
  ('Apocalipsis', 22),
];

// Spanish book name → OSIS code
const _bookNameToOsis = {
  'Génesis': 'GEN',
  'Éxodo': 'EXO',
  'Levítico': 'LEV',
  'Números': 'NUM',
  'Deuteronomio': 'DEU',
  'Josué': 'JOS',
  'Jueces': 'JDG',
  'Rut': 'RUT',
  '1 Samuel': '1SA',
  '2 Samuel': '2SA',
  '1 Reyes': '1KI',
  '2 Reyes': '2KI',
  '1 Crónicas': '1CH',
  '2 Crónicas': '2CH',
  'Esdras': 'EZR',
  'Nehemías': 'NEH',
  'Ester': 'EST',
  'Job': 'JOB',
  'Salmos': 'PSA',
  'Proverbios': 'PRO',
  'Eclesiastés': 'ECC',
  'Cantares': 'SNG',
  'Isaías': 'ISA',
  'Jeremías': 'JER',
  'Lamentaciones': 'LAM',
  'Ezequiel': 'EZK',
  'Daniel': 'DAN',
  'Oseas': 'HOS',
  'Joel': 'JOL',
  'Amós': 'AMO',
  'Abdías': 'OBA',
  'Jonás': 'JON',
  'Miqueas': 'MIC',
  'Nahúm': 'NAH',
  'Habacuc': 'HAB',
  'Sofonías': 'ZEP',
  'Hageo': 'HAG',
  'Zacarías': 'ZEC',
  'Malaquías': 'MAL',
  'Mateo': 'MAT',
  'Marcos': 'MRK',
  'Lucas': 'LUK',
  'Juan': 'JHN',
  'Hechos': 'ACT',
  'Romanos': 'ROM',
  '1 Corintios': '1CO',
  '2 Corintios': '2CO',
  'Gálatas': 'GAL',
  'Efesios': 'EPH',
  'Filipenses': 'PHP',
  'Colosenses': 'COL',
  '1 Tesalonicenses': '1TH',
  '2 Tesalonicenses': '2TH',
  '1 Timoteo': '1TI',
  '2 Timoteo': '2TI',
  'Tito': 'TIT',
  'Filemón': 'PHM',
  'Hebreos': 'HEB',
  'Santiago': 'JAS',
  '1 Pedro': '1PE',
  '2 Pedro': '2PE',
  '1 Juan': '1JN',
  '2 Juan': '2JN',
  '3 Juan': '3JN',
  'Judas': 'JUD',
  'Apocalipsis': 'REV',
};

// sourceMap → canonical book name (by CRS code prefix)
const _crsToBook = {
  'GEN': 'Génesis', 'EXO': 'Éxodo', 'LEV': 'Levítico', 'NUM': 'Números',
  'DEU': 'Deuteronomio', 'JOS': 'Josué', 'JDG': 'Jueces', 'RUT': 'Rut',
  '1SA': '1 Samuel', '2SA': '2 Samuel', '1KI': '1 Reyes', '2KI': '2 Reyes',
  '1CH': '1 Crónicas', '2CH': '2 Crónicas',
  'EZR': 'Esdras', 'NEH': 'Nehemías', 'EST': 'Ester',
  'JOB': 'Job', 'PSA': 'Salmos', 'PRO': 'Proverbios',
  'ECC': 'Eclesiastés', 'SOL': 'Cantares', 'SNG': 'Cantares',
  'ISA': 'Isaías', 'JER': 'Jeremías', 'LAM': 'Lamentaciones',
  'EZK': 'Ezequiel', 'EZE': 'Ezequiel', 'DAN': 'Daniel',
  'HOS': 'Oseas', 'JOL': 'Joel', 'AMO': 'Amós', 'OBA': 'Abdías',
  'JON': 'Jonás', 'MIC': 'Miqueas', 'NAH': 'Nahúm', 'HAB': 'Habacuc',
  'ZEP': 'Sofonías', 'HAG': 'Hageo', 'ZEC': 'Zacarías', 'MAL': 'Malaquías',
  'MAT': 'Mateo', 'MRK': 'Marcos', 'LUK': 'Lucas', 'JHN': 'Juan',
  'ACT': 'Hechos', 'ROM': 'Romanos',
  '1CO': '1 Corintios', '2CO': '2 Corintios', 'GAL': 'Gálatas',
  'EPH': 'Efesios', 'PHP': 'Filipenses', 'COL': 'Colosenses',
  '1TH': '1 Tesalonicenses', '2TH': '2 Tesalonicenses',
  '1TI': '1 Timoteo', '2TI': '2 Timoteo', 'TIT': 'Tito',
  'PHM': 'Filemón', 'HEB': 'Hebreos', 'JAS': 'Santiago',
  '1PE': '1 Pedro', '2PE': '2 Pedro',
  '1JN': '1 Juan', '2JN': '2 Juan', '3JN': '3 Juan',
  'JUD': 'Judas', 'REV': 'Apocalipsis',
  // Gospel harmony prefix
  'GOS': 'Los evangelios',
  'BR': 'Crónicas',
};

String? _bookFromSourceMap(String? sourceMap) {
  if (sourceMap == null) return null;
  // Parse CRS code prefix: CRS-1SA-001 → 1SA, CRS-GOS-001 → GOS, CRS-BR-2CH-001 → 2CH
  final parts = sourceMap.split('-');
  if (parts.length >= 3 && parts[0] == 'CRS') {
    // Handle CRS-BR-2CH-001 pattern
    if (parts[1] == 'BR' && parts.length >= 4) {
      return _crsToBook[parts[2]];
    }
    return _crsToBook[parts[1]];
  }
  return null;
}

// ─────────────────────────────────────────────

class ReadScreen extends ConsumerStatefulWidget {
  const ReadScreen({super.key});

  @override
  ConsumerState<ReadScreen> createState() => _ReadScreenState();
}

class _ReadScreenState extends ConsumerState<ReadScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Lectura continua'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: _ModeToggle(controller: _tabController),
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: const [
          _CronologicaView(),
          _CanonicalView(),
          HighlightsBrowseView(),
        ],
      ),
    );
  }
}

// ─── Mode toggle ──────────────────────────────

class _ModeToggle extends StatefulWidget {
  final TabController controller;
  const _ModeToggle({required this.controller});

  @override
  State<_ModeToggle> createState() => _ModeToggleState();
}

class _ModeToggleState extends State<_ModeToggle> {
  @override
  void initState() {
    super.initState();
    widget.controller.addListener(() => setState(() {}));
  }

  @override
  Widget build(BuildContext context) {
    final selected = widget.controller.index;
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
      child: Container(
        height: 36,
        decoration: BoxDecoration(
          color: cs.surfaceContainerHigh,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(
          children: [
            _Option(
              label: 'Cronológica',
              selected: selected == 0,
              onTap: () => widget.controller.animateTo(0),
            ),
            _Option(
              label: 'Canónica',
              selected: selected == 1,
              onTap: () => widget.controller.animateTo(1),
            ),
            _Option(
              label: 'Subrayados',
              selected: selected == 2,
              onTap: () => widget.controller.animateTo(2),
            ),
          ],
        ),
      ),
    );
  }
}

class _Option extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _Option({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          margin: const EdgeInsets.all(3),
          decoration: BoxDecoration(
            color: selected ? cs.primary : Colors.transparent,
            borderRadius: BorderRadius.circular(17),
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              color: selected ? Colors.white : cs.onSurfaceVariant,
              fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
              fontSize: 13,
            ),
          ),
        ),
      ),
    );
  }
}

// ─── Cronológica view ─────────────────────────

class _CronologicaView extends ConsumerWidget {
  const _CronologicaView();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final planAsync = ref.watch(streamPlanProvider);

    return planAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 40),
            const SizedBox(height: 8),
            Text(
              'No se pudo cargar el contenido.',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: () => ref.invalidate(streamPlanProvider),
              child: const Text('Reintentar'),
            ),
          ],
        ),
      ),
      data: (plan) {
        if (plan.nodes.isEmpty) {
          return Center(
            child: Text(
              'Sin contenido aún.',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          );
        }
        final grouped = _groupByEra(plan.nodes);
        return ListView.builder(
          padding: const EdgeInsets.only(bottom: 32),
          itemCount: grouped.length,
          itemBuilder: (_, i) => _EraSection(
            era: grouped[i].$1,
            nodes: grouped[i].$2,
            planId: plan.id,
          ),
        );
      },
    );
  }

  List<(String, List<CrsNodeItem>)> _groupByEra(List<CrsNodeItem> nodes) {
    // Only show main historical-stream nodes; hide editorial/literary/compositional metadata
    final mainNodes = nodes.where((n) => n.isMainStreamNode).toList();

    // Sort by user_facing_era_sort first, then rank
    mainNodes.sort((a, b) {
      final esA = a.userFacingEraSort ?? 999;
      final esB = b.userFacingEraSort ?? 999;
      if (esA != esB) return esA.compareTo(esB);
      return a.rank.compareTo(b.rank);
    });

    final order = <String>[];
    final map = <String, List<CrsNodeItem>>{};
    for (final n in mainNodes) {
      final era = n.userFacingEra ?? n.era ?? n.eraSlug ?? 'General';
      if (!map.containsKey(era)) {
        order.add(era);
        map[era] = [];
      }
      map[era]!.add(n);
    }
    return order.map((e) => (e, map[e]!)).toList();
  }
}

// ─── Era section ──────────────────────────────

class _EraSection extends StatefulWidget {
  final String era;
  final List<CrsNodeItem> nodes;
  final int planId;

  const _EraSection({
    required this.era,
    required this.nodes,
    required this.planId,
  });

  @override
  State<_EraSection> createState() => _EraSectionState();
}

class _EraSectionState extends State<_EraSection> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark
        ? BjColors.textPrimaryDark
        : BjColors.textPrimaryLight;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        InkWell(
          onTap: () => setState(() => _expanded = !_expanded),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 10),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    widget.era.toUpperCase(),
                    style: TextStyle(
                      color: BjColors.accentBronze,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.0,
                    ),
                  ),
                ),
                Text(
                  '${widget.nodes.length}',
                  style: TextStyle(
                    color: textColor.withValues(alpha: 0.35),
                    fontSize: 11,
                  ),
                ),
                const SizedBox(width: 4),
                Icon(
                  _expanded ? Icons.expand_less : Icons.expand_more,
                  size: 16,
                  color: textColor.withValues(alpha: 0.35),
                ),
              ],
            ),
          ),
        ),

        if (_expanded) ...[
          Divider(
            height: 1,
            indent: 16,
            endIndent: 16,
            color: textColor.withValues(alpha: 0.07),
          ),
          ...widget.nodes.map(
            (node) => _NodeTile(node: node, planId: widget.planId),
          ),
        ],

        Divider(height: 1, color: cs.outline.withValues(alpha: 0.2)),
      ],
    );
  }
}

// ─── Node tile ────────────────────────────────

class _NodeTile extends StatelessWidget {
  final CrsNodeItem node;
  final int planId;

  const _NodeTile({required this.node, required this.planId});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final textColor = isDark
        ? BjColors.textPrimaryDark
        : BjColors.textPrimaryLight;

    // Only show certainty when it's notable (not just 'probable')
    final showCertainty =
        node.confidence != null && node.confidence!.toLowerCase() != 'probable';

    return InkWell(
      onTap: () => context.push('/crs/$planId/${node.id}'),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 26,
              height: 26,
              margin: const EdgeInsets.only(top: 1, right: 12),
              decoration: BoxDecoration(
                color: BjColors.accentPrimary.withValues(alpha: 0.08),
                shape: BoxShape.circle,
              ),
              child: Center(
                child: Text(
                  '${node.rank}',
                  style: TextStyle(
                    color: BjColors.accentPrimary,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),

            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    node.displayTitle,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: node.locked
                          ? textColor.withValues(alpha: 0.45)
                          : textColor,
                      fontWeight: FontWeight.w500,
                      height: 1.35,
                    ),
                  ),
                  if (showCertainty) ...[
                    const SizedBox(height: 5),
                    CertaintyBadge(label: node.confidence, compact: true),
                  ],
                ],
              ),
            ),

            node.locked
                ? Icon(
                    Icons.lock_outline,
                    size: 15,
                    color: BjColors.accentBronze.withValues(alpha: 0.7),
                  )
                : Icon(
                    Icons.chevron_right,
                    size: 16,
                    color: textColor.withValues(alpha: 0.25),
                  ),
          ],
        ),
      ),
    );
  }
}

// ─── Canónica view ────────────────────────────

String _normalizeBookQuery(String value) => value
    .toLowerCase()
    .replaceAll('á', 'a')
    .replaceAll('é', 'e')
    .replaceAll('í', 'i')
    .replaceAll('ó', 'o')
    .replaceAll('ú', 'u')
    .replaceAll('ü', 'u')
    .replaceAll('ñ', 'n')
    .trim();

class _CanonicalView extends ConsumerStatefulWidget {
  const _CanonicalView();

  @override
  ConsumerState<_CanonicalView> createState() => _CanonicalViewState();
}

class _CanonicalViewState extends ConsumerState<_CanonicalView> {
  final TextEditingController _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final planAsync = ref.watch(streamPlanProvider);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark
        ? BjColors.textPrimaryDark
        : BjColors.textPrimaryLight;
    final cs = Theme.of(context).colorScheme;

    return planAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (_, _) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 40),
            const SizedBox(height: 8),
            Text(
              'No se pudo cargar.',
              style: TextStyle(color: cs.onSurfaceVariant),
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: () => ref.invalidate(streamPlanProvider),
              child: const Text('Reintentar'),
            ),
          ],
        ),
      ),
      data: (plan) {
        // Build a set of books that have nodes in the stream plan
        final booksInPlan = <String>{};
        for (final node in plan.nodes) {
          final book = _bookFromSourceMap(node.sourceMap);
          if (book != null) booksInPlan.add(book);
        }

        // Count nodes per book for progress display
        final nodeCountPerBook = <String, int>{};
        for (final node in plan.nodes) {
          final book = _bookFromSourceMap(node.sourceMap);
          if (book != null) {
            nodeCountPerBook[book] = (nodeCountPerBook[book] ?? 0) + 1;
          }
        }

        final normalizedQuery = _normalizeBookQuery(_query);
        final filteredBooks = normalizedQuery.isEmpty
            ? _canonicalBooks
            : _canonicalBooks
                  .where(
                    (book) =>
                        _normalizeBookQuery(book.$1).contains(normalizedQuery),
                  )
                  .toList();

        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: TextField(
                controller: _searchController,
                onChanged: (value) => setState(() => _query = value),
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  hintText: 'Buscar libro',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _query.isEmpty
                      ? null
                      : IconButton(
                          tooltip: 'Limpiar búsqueda',
                          onPressed: () {
                            _searchController.clear();
                            setState(() => _query = '');
                          },
                          icon: const Icon(Icons.close),
                        ),
                  isDense: true,
                  filled: true,
                  fillColor: cs.surfaceContainer,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
            ),
            Expanded(
              child: filteredBooks.isEmpty
                  ? Center(
                      child: Text(
                        'No encontramos ese libro.',
                        style: TextStyle(color: cs.onSurfaceVariant),
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(0, 0, 0, 32),
                      itemCount: filteredBooks.length,
                      separatorBuilder: (_, _) => Divider(
                        height: 1,
                        indent: 16,
                        endIndent: 16,
                        color: textColor.withValues(alpha: 0.07),
                      ),
                      itemBuilder: (_, i) {
                        final (bookName, chapters) = filteredBooks[i];
                        return _CanonicalBookTile(
                          bookName: bookName,
                          chapters: chapters,
                          inPlan: booksInPlan.contains(bookName),
                          nodeCount: nodeCountPerBook[bookName] ?? 0,
                          textColor: textColor,
                          cs: cs,
                        );
                      },
                    ),
            ),
          ],
        );
      },
    );
  }
}

void _showChapterPicker(
  BuildContext context,
  String bookName,
  int chapters,
  String osisCode,
) {
  final cs = Theme.of(context).colorScheme;
  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: cs.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(8)),
    ),
    builder: (_) => FractionallySizedBox(
      heightFactor: 0.82,
      child: _CanonicalReferencePicker(
        bookName: bookName,
        chapters: chapters,
        osisCode: osisCode,
      ),
    ),
  );
}

class _CanonicalReferencePicker extends ConsumerStatefulWidget {
  final String bookName;
  final int chapters;
  final String osisCode;

  const _CanonicalReferencePicker({
    required this.bookName,
    required this.chapters,
    required this.osisCode,
  });

  @override
  ConsumerState<_CanonicalReferencePicker> createState() =>
      _CanonicalReferencePickerState();
}

class _CanonicalReferencePickerState
    extends ConsumerState<_CanonicalReferencePicker> {
  int? _selectedChapter;

  void _openReader({required int chapter, int? verse}) {
    final router = GoRouter.of(context);
    Navigator.of(context).pop();
    final verseQuery = verse == null ? '' : '?verse=$verse';
    router.push('/canonical/${widget.osisCode}/$chapter$verseQuery');
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final chapter = _selectedChapter;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (chapter != null)
                IconButton(
                  tooltip: 'Volver a capítulos',
                  onPressed: () => setState(() => _selectedChapter = null),
                  icon: const Icon(Icons.arrow_back),
                ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.bookName,
                      style: TextStyle(
                        color: cs.onSurface,
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      chapter == null
                          ? 'Selecciona un capítulo'
                          : 'Capítulo $chapter · Selecciona un versículo',
                      style: TextStyle(
                        color: cs.onSurfaceVariant,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: 'Cerrar',
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.close),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Expanded(
            child: chapter == null
                ? _NumberGrid(
                    itemCount: widget.chapters,
                    onSelected: (value) =>
                        setState(() => _selectedChapter = value),
                  )
                : _VersePicker(
                    osisCode: widget.osisCode,
                    chapter: chapter,
                    onOpenChapter: () => _openReader(chapter: chapter),
                    onOpenVerse: (verse) =>
                        _openReader(chapter: chapter, verse: verse),
                  ),
          ),
        ],
      ),
    );
  }
}

class _NumberGrid extends StatelessWidget {
  final int itemCount;
  final ValueChanged<int> onSelected;

  const _NumberGrid({required this.itemCount, required this.onSelected});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return GridView.builder(
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 7,
        mainAxisSpacing: 6,
        crossAxisSpacing: 6,
        childAspectRatio: 1,
      ),
      itemCount: itemCount,
      itemBuilder: (_, index) {
        final value = index + 1;
        return InkWell(
          onTap: () => onSelected(value),
          borderRadius: BorderRadius.circular(8),
          child: Container(
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: cs.surfaceContainerHigh,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              '$value',
              style: TextStyle(
                color: cs.onSurface,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        );
      },
    );
  }
}

class _VersePicker extends ConsumerWidget {
  final String osisCode;
  final int chapter;
  final VoidCallback onOpenChapter;
  final ValueChanged<int> onOpenVerse;

  const _VersePicker({
    required this.osisCode,
    required this.chapter,
    required this.onOpenChapter,
    required this.onOpenVerse,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final contentAsync = ref.watch(
      canonicalChapterProvider((osisCode, chapter)),
    );

    return contentAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (_, _) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'No se pudieron cargar los versículos.',
              style: TextStyle(color: cs.onSurfaceVariant),
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: () =>
                  ref.invalidate(canonicalChapterProvider((osisCode, chapter))),
              child: const Text('Reintentar'),
            ),
          ],
        ),
      ),
      data: (content) => Column(
        children: [
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: onOpenChapter,
              icon: const Icon(Icons.menu_book_outlined),
              label: const Text('Leer capítulo completo'),
            ),
          ),
          const SizedBox(height: 10),
          Expanded(
            child: content.verses.isEmpty
                ? Center(
                    child: Text(
                      'No hay versículos disponibles.',
                      style: TextStyle(color: cs.onSurfaceVariant),
                    ),
                  )
                : GridView.builder(
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 7,
                          mainAxisSpacing: 6,
                          crossAxisSpacing: 6,
                          childAspectRatio: 1,
                        ),
                    itemCount: content.verses.length,
                    itemBuilder: (_, index) {
                      final verse = content.verses[index].verse;
                      return InkWell(
                        onTap: () => onOpenVerse(verse),
                        borderRadius: BorderRadius.circular(8),
                        child: Container(
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: cs.surfaceContainerHigh,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            '$verse',
                            style: TextStyle(
                              color: cs.onSurface,
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class _CanonicalBookTile extends StatelessWidget {
  final String bookName;
  final int chapters;
  final bool inPlan;
  final int nodeCount;
  final Color textColor;
  final ColorScheme cs;

  const _CanonicalBookTile({
    required this.bookName,
    required this.chapters,
    required this.inPlan,
    required this.nodeCount,
    required this.textColor,
    required this.cs,
  });

  @override
  Widget build(BuildContext context) {
    final osisCode = _bookNameToOsis[bookName];
    return InkWell(
      onTap: osisCode != null
          ? () => _showChapterPicker(context, bookName, chapters, osisCode)
          : null,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            // Status indicator
            Container(
              width: 8,
              height: 8,
              margin: const EdgeInsets.only(right: 14, top: 2),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: textColor.withValues(alpha: 0.18),
              ),
            ),

            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    bookName,
                    style: TextStyle(
                      color: textColor,
                      fontWeight: FontWeight.w600,
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '$chapters capítulos · Sin iniciar',
                    style: TextStyle(
                      color: textColor.withValues(alpha: 0.45),
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),

            Icon(
              Icons.chevron_right,
              size: 16,
              color: textColor.withValues(alpha: 0.3),
            ),
          ],
        ),
      ), // Padding
    ); // InkWell
  }
}
