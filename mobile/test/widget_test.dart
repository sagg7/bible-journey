import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:bible_journey/main.dart';

void main() {
  setUpAll(() {
    // Sin red en tests: google_fonts usa el fallback local.
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('La app arranca y muestra el shell de navegación',
      (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: BibleJourneyApp()));
    // Varios frames para providers iniciales; sin pumpAndSettle porque las
    // llamadas de red quedan pendientes (y fallan) en el entorno de test.
    await tester.pump(const Duration(milliseconds: 50));
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.text('Inicio'), findsWidgets);
    expect(find.text('Leer'), findsWidgets);
    expect(find.text('Rutas'), findsWidgets);
    expect(find.text('Ezra'), findsWidgets);
  });
}
