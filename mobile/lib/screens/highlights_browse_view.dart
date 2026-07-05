import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api.dart';
import '../core/auth.dart';
import '../core/theme.dart';
import '../models/models.dart';
import '../widgets/highlight_selection_bar.dart' show hexToColor;

/// "Mis subrayados" — third tab under Leer. Lists the user's highlight
/// colors (each with its custom label) and, expanded, every verse range
/// saved under that color.
class HighlightsBrowseView extends ConsumerWidget {
  const HighlightsBrowseView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final cs = Theme.of(context).colorScheme;

    if (ref.watch(authProvider) == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.brush_outlined, size: 40, color: textColor.withValues(alpha: 0.3)),
              const SizedBox(height: 12),
              Text(
                'Inicia sesión para guardar y ver tus subrayados en todos tus dispositivos.',
                textAlign: TextAlign.center,
                style: TextStyle(color: textColor.withValues(alpha: 0.6)),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => context.push('/auth'),
                child: const Text('Iniciar sesión'),
              ),
            ],
          ),
        ),
      );
    }

    final colorsAsync = ref.watch(highlightColorsProvider);

    return colorsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 40),
            const SizedBox(height: 8),
            Text('No se pudo cargar.', style: TextStyle(color: cs.onSurfaceVariant)),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: () => ref.invalidate(highlightColorsProvider),
              child: const Text('Reintentar'),
            ),
          ],
        ),
      ),
      data: (colors) {
        if (colors.isEmpty) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Text(
                'Aún no tienes subrayados. Mantén presionado un versículo en modo Canónica para empezar.',
                textAlign: TextAlign.center,
                style: TextStyle(color: textColor.withValues(alpha: 0.6)),
              ),
            ),
          );
        }
        return ListView.builder(
          padding: const EdgeInsets.symmetric(vertical: 8),
          itemCount: colors.length,
          itemBuilder: (_, i) => _ColorSection(color: colors[i]),
        );
      },
    );
  }
}

class _ColorSection extends ConsumerStatefulWidget {
  final HighlightColorInfo color;
  const _ColorSection({required this.color});

  @override
  ConsumerState<_ColorSection> createState() => _ColorSectionState();
}

class _ColorSectionState extends ConsumerState<_ColorSection> {
  bool _expanded = false;

  Future<void> _rename(BuildContext context) async {
    final controller = TextEditingController(text: widget.color.label ?? '');
    final label = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Nombrar este color'),
        content: TextField(controller: controller, autofocus: true, maxLength: 60),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancelar')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            child: const Text('Guardar'),
          ),
        ],
      ),
    );
    if (label == null || label.isEmpty) return;
    await ref.read(apiProvider).renameHighlightColor(widget.color.id, label);
    ref.invalidate(highlightColorsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        InkWell(
          onTap: () => setState(() => _expanded = !_expanded),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Row(
              children: [
                Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: hexToColor(widget.color.hex),
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    widget.color.label?.isNotEmpty == true ? widget.color.label! : 'Sin nombre',
                    style: TextStyle(color: textColor, fontWeight: FontWeight.w600, fontSize: 15),
                  ),
                ),
                Text('${widget.color.count}',
                    style: TextStyle(color: textColor.withValues(alpha: 0.4), fontSize: 12)),
                IconButton(
                  icon: Icon(Icons.edit_outlined, size: 18, color: textColor.withValues(alpha: 0.5)),
                  onPressed: () => _rename(context),
                ),
                Icon(_expanded ? Icons.expand_less : Icons.expand_more,
                    color: textColor.withValues(alpha: 0.4)),
              ],
            ),
          ),
        ),
        if (_expanded) _VerseList(colorId: widget.color.id),
        Divider(height: 1, color: textColor.withValues(alpha: 0.07)),
      ],
    );
  }
}

class _VerseList extends ConsumerWidget {
  final int colorId;
  const _VerseList({required this.colorId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final versesAsync = ref.watch(highlightsByColorProvider(colorId));
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;

    return versesAsync.when(
      loading: () => const Padding(
        padding: EdgeInsets.all(16),
        child: Center(child: CircularProgressIndicator()),
      ),
      error: (_, _) => const SizedBox.shrink(),
      data: (verses) {
        if (verses.isEmpty) {
          return Padding(
            padding: const EdgeInsets.fromLTRB(52, 0, 16, 12),
            child: Text('Sin versículos.', style: TextStyle(color: textColor.withValues(alpha: 0.4))),
          );
        }
        return Column(
          children: verses
              .map((v) => InkWell(
                    onTap: () => context.push('/canonical/${v.bookOsisCode}/${v.chapter}'),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(52, 8, 16, 8),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${v.bookNameEs} ${v.chapter}:${v.verseStart == v.verseEnd ? v.verseStart : '${v.verseStart}-${v.verseEnd}'}',
                              style: TextStyle(color: textColor.withValues(alpha: 0.8), fontSize: 13),
                            ),
                          ),
                          Icon(Icons.chevron_right, size: 16, color: textColor.withValues(alpha: 0.3)),
                        ],
                      ),
                    ),
                  ))
              .toList(),
        );
      },
    );
  }
}
