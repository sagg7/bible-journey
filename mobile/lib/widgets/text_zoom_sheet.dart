import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/local_progress.dart';
import '../core/theme.dart';

/// Accessibility control for scripture text size. Persists via
/// [localProgressProvider] so the chosen size is remembered across readers.
class TextZoomButton extends StatelessWidget {
  final Color color;
  const TextZoomButton({super.key, required this.color});

  @override
  Widget build(BuildContext context) {
    return IconButton(
      icon: Icon(Icons.format_size, color: color),
      tooltip: 'Tamaño de texto',
      onPressed: () => showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => const _TextZoomSheet(),
      ),
    );
  }
}

class _TextZoomSheet extends ConsumerWidget {
  const _TextZoomSheet();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? BjColors.surfacePrimary : Colors.white;
    final textColor = isDark ? BjColors.textPrimaryDark : BjColors.textPrimaryLight;
    final progress = ref.watch(localProgressProvider).value;
    final scale = progress?.fontScale ?? 1.0;
    final family = progress?.fontFamily ?? kDefaultScriptureFont;

    return SafeArea(
      child: Container(
        decoration: BoxDecoration(
          color: bg,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.fromLTRB(24, 20, 24, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Texto de lectura',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(color: textColor, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
              decoration: BoxDecoration(
                color: isDark ? BjColors.surfaceCard : const Color(0xFFF3F1EC),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                'En el principio creó Dios los cielos y la tierra.',
                style: scriptureTextStyle(
                        fontSize: 17 * scale, height: 1.6, fontFamily: family)
                    .copyWith(color: textColor),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Tamaño',
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: textColor.withValues(alpha: 0.7), fontWeight: FontWeight.w600),
            ),
            Row(
              children: [
                IconButton(
                  icon: Icon(Icons.remove_circle_outline, color: textColor.withValues(alpha: 0.7)),
                  onPressed: scale > kMinFontScale
                      ? () => ref
                          .read(localProgressProvider.notifier)
                          .setFontScale(scale - 0.1)
                      : null,
                ),
                Expanded(
                  child: Slider(
                    value: scale,
                    min: kMinFontScale,
                    max: kMaxFontScale,
                    divisions: 17,
                    label: '${(scale * 100).round()}%',
                    activeColor: BjColors.accentPrimary,
                    onChanged: (v) =>
                        ref.read(localProgressProvider.notifier).setFontScale(v),
                  ),
                ),
                IconButton(
                  icon: Icon(Icons.add_circle_outline, color: textColor.withValues(alpha: 0.7)),
                  onPressed: scale < kMaxFontScale
                      ? () => ref
                          .read(localProgressProvider.notifier)
                          .setFontScale(scale + 0.1)
                      : null,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Tipo de letra',
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: textColor.withValues(alpha: 0.7), fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: kScriptureFonts.entries.map((entry) {
                final selected = entry.key == family;
                return ChoiceChip(
                  label: Text(
                    entry.value,
                    style: scriptureTextStyle(fontSize: 13, fontFamily: entry.key).copyWith(
                      color: selected ? Colors.white : textColor,
                    ),
                  ),
                  selected: selected,
                  selectedColor: BjColors.accentPrimary,
                  backgroundColor: isDark ? BjColors.surfaceCard : const Color(0xFFF3F1EC),
                  side: BorderSide(
                    color: selected
                        ? BjColors.accentPrimary
                        : textColor.withValues(alpha: 0.15),
                  ),
                  onSelected: (_) =>
                      ref.read(localProgressProvider.notifier).setFontFamily(entry.key),
                );
              }).toList(),
            ),
          ],
        ),
      ),
    );
  }
}
