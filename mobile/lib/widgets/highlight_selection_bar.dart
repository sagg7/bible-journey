import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api.dart';
import '../core/auth.dart';
import '../core/share_verse.dart';
import '../core/theme.dart';
import '../models/models.dart';

/// Fixed color palette offered when highlighting a verse range. Each entry
/// carries a sensible default Spanish label, used until the user renames it.
const kHighlightPalette = <(String hex, String defaultLabel)>[
  ('#FFF59D', 'Amarillo'),
  ('#A5D6A7', 'Verde'),
  ('#90CAF9', 'Azul'),
  ('#F48FB1', 'Rosa'),
  ('#FFCC80', 'Naranja'),
  ('#CE93D8', 'Morado'),
  ('#EF9A9A', 'Rojo'),
  ('#B0BEC5', 'Gris'),
];

Color hexToColor(String hex) {
  final h = hex.replaceFirst('#', '').padLeft(6, '0');
  return Color(int.parse('FF$h', radix: 16));
}

/// Tracks the verse range currently being selected for highlighting in a
/// reader screen (long-press a verse to start, tap another to extend).
class HighlightSelection extends ChangeNotifier {
  int? _anchor;
  int? _focus;

  bool get isActive => _anchor != null;
  int get rangeStart => _anchor == null
      ? 0
      : (_focus == null
            ? _anchor!
            : (_anchor! <= _focus! ? _anchor! : _focus!));
  int get rangeEnd => _anchor == null
      ? 0
      : (_focus == null
            ? _anchor!
            : (_anchor! >= _focus! ? _anchor! : _focus!));

  bool contains(int verse) =>
      isActive && verse >= rangeStart && verse <= rangeEnd;

  void beginAt(int verse) {
    _anchor = verse;
    _focus = verse;
    notifyListeners();
  }

  void selectRange(int verseStart, int verseEnd) {
    _anchor = verseStart;
    _focus = verseEnd;
    notifyListeners();
  }

  void extendTo(int verse) {
    if (_anchor == null) return;
    _focus = verse;
    notifyListeners();
  }

  void clear() {
    _anchor = null;
    _focus = null;
    notifyListeners();
  }
}

/// Bottom bar shown while a verse range is selected: pick (or create) a
/// highlight color to save the selection, or cancel.
class HighlightSelectionBar extends ConsumerWidget {
  final String bookOsisCode;
  final String bookNameEs;
  final int chapter;
  final List<BibleVerseItem> verses;
  final String? translationCode;
  final HighlightSelection selection;
  final List<VerseHighlight> existingHighlights;
  final VoidCallback onSaved;
  final VoidCallback onCancel;

  const HighlightSelectionBar({
    super.key,
    required this.bookOsisCode,
    required this.bookNameEs,
    required this.chapter,
    required this.verses,
    this.translationCode,
    required this.selection,
    this.existingHighlights = const [],
    required this.onSaved,
    required this.onCancel,
  });

  void _share() {
    shareVerseRange(
      bookNameEs: bookNameEs,
      chapter: chapter,
      verseStart: selection.rangeStart,
      verseEnd: selection.rangeEnd,
      verses: verses,
      translationCode: translationCode,
    );
  }

  Future<void> _apply(
    BuildContext context,
    WidgetRef ref,
    String hex,
    String defaultLabel,
    Map<String, String?> existingLabels,
  ) async {
    String? label = existingLabels[hex.toUpperCase()];
    if (label == null) {
      label = await showDialog<String>(
        context: context,
        builder: (ctx) => _LabelDialog(hex: hex, initial: defaultLabel),
      );
      if (label == null) return; // user cancelled the dialog
    }

    try {
      await ref
          .read(apiProvider)
          .createHighlight(
            book: bookOsisCode,
            chapter: chapter,
            verseStart: selection.rangeStart,
            verseEnd: selection.rangeEnd,
            colorHex: hex,
            label: label.isEmpty ? null : label,
          );
      ref.invalidate(chapterHighlightsProvider((bookOsisCode, chapter)));
      ref.invalidate(highlightColorsProvider);
      ref.invalidate(allHighlightsProvider);
      onSaved();
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('No se pudo guardar: $e')));
      }
    }
  }

  Future<void> _removeHighlights(
    BuildContext context,
    WidgetRef ref,
    List<VerseHighlight> highlights,
  ) async {
    if (highlights.isEmpty) return;

    try {
      final api = ref.read(apiProvider);
      for (final highlight in highlights) {
        await api.deleteHighlight(highlight.id);
      }

      ref.invalidate(chapterHighlightsProvider((bookOsisCode, chapter)));
      ref.invalidate(highlightColorsProvider);
      ref.invalidate(allHighlightsProvider);
      for (final colorId in highlights.map((h) => h.color.id).toSet()) {
        ref.invalidate(highlightsByColorProvider(colorId));
      }

      onSaved();
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              highlights.length == 1
                  ? 'Subrayado quitado.'
                  : 'Subrayados quitados.',
            ),
          ),
        );
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('No se pudo quitar: $e')));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark
        ? BjColors.textPrimaryDark
        : BjColors.textPrimaryLight;
    final isLoggedIn = ref.watch(authProvider) != null;
    final colorsAsync = ref.watch(highlightColorsProvider);
    final existingLabels = <String, String?>{
      for (final c in colorsAsync.value ?? []) c.hex.toUpperCase(): c.label,
    };
    final removableHighlights = existingHighlights
        .where(
          (h) =>
              h.chapter == chapter &&
              h.verseStart <= selection.rangeEnd &&
              h.verseEnd >= selection.rangeStart,
        )
        .toList();

    final range = selection.rangeStart == selection.rangeEnd
        ? 'Versículo ${selection.rangeStart}'
        : 'Versículos ${selection.rangeStart}-${selection.rangeEnd}';

    return SafeArea(
      child: Material(
        color: isDark ? BjColors.surfaceCard : Colors.white,
        elevation: 8,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      range,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: textColor,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  Row(
                    children: [
                      IconButton(
                        icon: Icon(
                          Icons.ios_share,
                          size: 20,
                          color: textColor.withValues(alpha: 0.7),
                        ),
                        tooltip: 'Compartir',
                        onPressed: _share,
                      ),
                      if (isLoggedIn && removableHighlights.isNotEmpty)
                        IconButton(
                          icon: Icon(
                            Icons.delete_outline,
                            size: 20,
                            color: Theme.of(context).colorScheme.error,
                          ),
                          tooltip: 'Quitar subrayado',
                          onPressed: () => _removeHighlights(
                            context,
                            ref,
                            removableHighlights,
                          ),
                        ),
                      TextButton(
                        onPressed: onCancel,
                        child: const Text('Cancelar'),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 10),
              if (!isLoggedIn)
                GestureDetector(
                  onTap: () => context.push('/auth'),
                  child: Text(
                    'Inicia sesión para guardar subrayados.',
                    style: TextStyle(
                      color: BjColors.accentPrimary,
                      fontSize: 12,
                      height: 1.4,
                      decoration: TextDecoration.underline,
                      decorationColor: BjColors.accentPrimary,
                    ),
                  ),
                )
              else
                Row(
                  children: kHighlightPalette
                      .map(
                        (p) => Padding(
                          padding: const EdgeInsets.only(right: 12),
                          child: GestureDetector(
                            onTap: () => _apply(
                              context,
                              ref,
                              p.$1,
                              p.$2,
                              existingLabels,
                            ),
                            child: Container(
                              width: 34,
                              height: 34,
                              decoration: BoxDecoration(
                                color: hexToColor(p.$1),
                                shape: BoxShape.circle,
                                border: Border.all(color: Colors.black12),
                              ),
                            ),
                          ),
                        ),
                      )
                      .toList(),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LabelDialog extends StatefulWidget {
  final String hex;
  final String initial;
  const _LabelDialog({required this.hex, required this.initial});

  @override
  State<_LabelDialog> createState() => _LabelDialogState();
}

class _LabelDialogState extends State<_LabelDialog> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initial,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Row(
        children: [
          Container(
            width: 20,
            height: 20,
            decoration: BoxDecoration(
              color: hexToColor(widget.hex),
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 10),
          const Text('Nombrar este color'),
        ],
      ),
      content: TextField(
        controller: _controller,
        autofocus: true,
        maxLength: 60,
        decoration: const InputDecoration(hintText: 'Ej. Promesas de Dios'),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancelar'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(_controller.text.trim()),
          child: const Text('Guardar'),
        ),
      ],
    );
  }
}
