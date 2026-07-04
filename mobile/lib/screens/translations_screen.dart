import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api.dart';
import '../core/strings.dart';
import '../core/theme.dart';
import '../models/models.dart';

class TranslationsScreen extends ConsumerWidget {
  const TranslationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = AppStrings(ref.watch(localeProvider));
    final translationsAsync = ref.watch(translationsListProvider);

    return Scaffold(
      appBar: AppBar(title: Text(s.t('traducciones'))),
      body: translationsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.cloud_off,
                  size: 44,
                  color: Theme.of(context).colorScheme.onSurfaceVariant),
              const SizedBox(height: 12),
              Text(s.t('errorLoading')),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () => ref.invalidate(translationsListProvider),
                child: Text(s.t('retry')),
              ),
            ],
          ),
        ),
        data: (translations) {
          final selectedCode = ref.watch(translationProvider) ?? 'RVA1909';

          // Group by language (API returns ISO codes like "es"/"en")
          const langLabels = {'es': 'Español', 'en': 'Inglés'};
          final Map<String, List<BibleTranslationOption>> byLang = {};
          for (final t in translations) {
            final label = langLabels[t.language] ?? t.language;
            byLang.putIfAbsent(label, () => []).add(t);
          }

          // Ensure Spanish and English appear first
          final orderedKeys = [
            ...['Español', 'Inglés'].where(byLang.containsKey),
            ...byLang.keys.where((k) => k != 'Español' && k != 'Inglés'),
          ];

          if (translations.isEmpty) {
            return Center(
              child: Text(
                'Sin traducciones disponibles.',
                style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant),
              ),
            );
          }

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
            children: [
              for (final lang in orderedKeys) ...[
                Padding(
                  padding: const EdgeInsets.only(top: 20, bottom: 10),
                  child: BjSectionLabel(lang),
                ),
                ...byLang[lang]!.map((t) => _TranslationTile(
                      translation: t,
                      isSelected: t.code == selectedCode,
                    )),
              ],
            ],
          );
        },
      ),
    );
  }
}

// ─── Translation tile ─────────────────────────

class _TranslationTile extends StatelessWidget {
  final dynamic translation;
  final bool isSelected;
  const _TranslationTile({required this.translation, required this.isSelected});

  _AvailabilityState _state() {
    final hasText = translation.canDisplayFullText == true;
    return hasText ? _AvailabilityState.fullText : _AvailabilityState.referenceOnly;
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final state = _state();

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: isSelected
            ? BjColors.accentPrimary.withValues(alpha: 0.08)
            : cs.surfaceContainer,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isSelected ? BjColors.accentPrimary.withValues(alpha: 0.4) : cs.outline,
          width: isSelected ? 1 : 0.5,
        ),
      ),
      child: ListTile(
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        title: Row(
          children: [
            Expanded(
              child: Text(
                translation.name ?? translation.code ?? '',
                style: TextStyle(
                  color: cs.onSurface,
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                ),
              ),
            ),
            if (isSelected)
              Icon(Icons.check_circle,
                  size: 16, color: BjColors.accentPrimary),
          ],
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: _AvailabilityBadge(state: state),
        ),
        onTap: state == _AvailabilityState.fullText ||
                state == _AvailabilityState.referenceOnly ||
                state == _AvailabilityState.viaProvider
            ? () => Navigator.of(context).pop(translation.code)
            : null,
      ),
    );
  }
}

// ─── Availability badge ───────────────────────

enum _AvailabilityState {
  fullText,
  referenceOnly,
  pendingLicense,
  viaProvider,
}

class _AvailabilityBadge extends StatelessWidget {
  final _AvailabilityState state;
  const _AvailabilityBadge({required this.state});

  @override
  Widget build(BuildContext context) {
    final (label, color, icon) = switch (state) {
      _AvailabilityState.fullText => (
          'Texto completo disponible',
          BjColors.certaintyHigh,
          Icons.check_circle_outline,
        ),
      _AvailabilityState.referenceOnly => (
          'Referencia y contexto disponibles',
          BjColors.certaintyDebated,
          Icons.library_books_outlined,
        ),
      _AvailabilityState.pendingLicense => (
          'Texto completo pendiente de licencia',
          BjColors.certaintyUnresolved,
          Icons.lock_clock_outlined,
        ),
      _AvailabilityState.viaProvider => (
          'Disponible mediante proveedor autorizado',
          BjColors.certaintyProbable,
          Icons.open_in_new,
        ),
    };

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 12, color: color),
        const SizedBox(width: 5),
        Flexible(
          child: Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ],
    );
  }
}
