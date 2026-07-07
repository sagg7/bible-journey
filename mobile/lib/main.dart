import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api.dart';
import 'core/local_progress.dart';
import 'core/router.dart';
import 'core/theme.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await configureRevenueCat();
  runApp(const ProviderScope(child: BibleJourneyApp()));
}

class BibleJourneyApp extends ConsumerWidget {
  const BibleJourneyApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locale = ref.watch(localeProvider);
    final themeMode = ref.watch(themeModeProvider);

    // Al cargar el progreso local guardado, aplica la traducción favorita
    // del usuario (una sola vez, cuando pasa de "cargando" a datos listos).
    ref.listen<AsyncValue<LocalProgress>>(localProgressProvider,
        (previous, next) {
      final saved = next.value?.translationCode;
      if (previous is! AsyncData && saved != null) {
        ref.read(translationProvider.notifier).state = saved;
      }
    });
    return MaterialApp.router(
      title: 'Bible Journey',
      debugShowCheckedModeBanner: false,
      theme: editorialLightTheme,
      darkTheme: modernDeepTheme,
      themeMode: themeMode,
      routerConfig: appRouter,
      locale: Locale(locale),
      supportedLocales: const [Locale('es'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
    );
  }
}
