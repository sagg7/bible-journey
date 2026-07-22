import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:bible_journey/core/local_progress.dart';

Future<LocalProgressNotifier> _notifier(ProviderContainer container) async {
  // Espera a que build() termine antes de mutar.
  await container.read(localProgressProvider.future);
  return container.read(localProgressProvider.notifier);
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  test('estado inicial: sin bloques, RVA1909, fontScale 1.0', () async {
    final container = ProviderContainer();
    addTearDown(container.dispose);

    final progress = await container.read(localProgressProvider.future);

    expect(progress.completedBlockIds, isEmpty);
    expect(progress.translationCode, 'RVA1909');
    expect(progress.fontScale, 1.0);
    expect(progress.hasBookmark, isFalse);
  });

  test('markBlockCompleted persiste y sobrevive un reinicio', () async {
    final container = ProviderContainer();
    final notifier = await _notifier(container);

    await notifier.markBlockCompleted(42);
    await notifier.markBlockCompleted(7);
    container.dispose();

    // "Reinicio": nuevo container leyendo las mismas prefs simuladas.
    final restarted = ProviderContainer();
    addTearDown(restarted.dispose);
    final progress = await restarted.read(localProgressProvider.future);

    expect(progress.isCompleted(42), isTrue);
    expect(progress.isCompleted(7), isTrue);
    expect(progress.isCompleted(99), isFalse);
  });

  test('markBlockCompleted es idempotente (sin duplicados)', () async {
    final container = ProviderContainer();
    addTearDown(container.dispose);
    final notifier = await _notifier(container);

    await notifier.markBlockCompleted(42);
    await notifier.markBlockCompleted(42);

    final progress = container.read(localProgressProvider).value!;
    expect(progress.completedBlockIds, {42});
  });

  test('setFontScale respeta los límites de accesibilidad', () async {
    final container = ProviderContainer();
    addTearDown(container.dispose);
    final notifier = await _notifier(container);

    await notifier.setFontScale(5.0);
    expect(container.read(localProgressProvider).value!.fontScale, kMaxFontScale);

    await notifier.setFontScale(0.1);
    expect(container.read(localProgressProvider).value!.fontScale, kMinFontScale);
  });

  test('bookmark CRS: set, roundtrip y clear', () async {
    final container = ProviderContainer();
    final notifier = await _notifier(container);

    await notifier.setBookmarkCrs(10, 245, 'Éxodo 3', chapter: 3, verse: 7);

    var progress = container.read(localProgressProvider).value!;
    expect(progress.hasBookmark, isTrue);
    expect(progress.bookmarkType, 'crs');
    expect(progress.bookmarkPlanId, 10);
    expect(progress.bookmarkNodeId, 245);
    expect(progress.bookmarkChapter, 3);
    expect(progress.bookmarkVerse, 7);
    container.dispose();

    // Sobrevive reinicio
    final restarted = ProviderContainer();
    progress = await restarted.read(localProgressProvider.future);
    expect(progress.bookmarkLabel, 'Éxodo 3');

    // Clear elimina todo
    final notifier2 = restarted.read(localProgressProvider.notifier);
    await notifier2.clearBookmark();
    expect(restarted.read(localProgressProvider).value!.hasBookmark, isFalse);
    restarted.dispose();
  });

  test('bookmark canónico reemplaza al bookmark CRS por completo', () async {
    final container = ProviderContainer();
    addTearDown(container.dispose);
    final notifier = await _notifier(container);

    await notifier.setBookmarkCrs(10, 245, 'Éxodo 3', chapter: 3, verse: 7);
    await notifier.setBookmarkCanonical('PSA', 23, 'Salmos 23');

    final progress = container.read(localProgressProvider).value!;
    expect(progress.bookmarkType, 'canonical');
    expect(progress.bookmarkOsisCode, 'PSA');
    expect(progress.bookmarkChapter, 23);
    // Los campos del bookmark CRS anterior no deben sobrevivir
    expect(progress.bookmarkPlanId, isNull);
    expect(progress.bookmarkNodeId, isNull);
    expect(progress.bookmarkVerse, isNull);
  });

  test('setTranslation persiste la traducción elegida', () async {
    final container = ProviderContainer();
    final notifier = await _notifier(container);
    await notifier.setTranslation('KJV');
    container.dispose();

    final restarted = ProviderContainer();
    addTearDown(restarted.dispose);
    final progress = await restarted.read(localProgressProvider.future);
    expect(progress.translationCode, 'KJV');
  });
}
