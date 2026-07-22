import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:bible_journey/core/verse_locator.dart';

void main() {
  // Igual que el lector real: los versículos se construyen eagerly dentro de
  // una Column (no lazy), por eso scrollTo puede resolver cualquier versículo.
  Widget verseList(VerseLocator locator, ScrollController controller) {
    return MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          controller: controller,
          child: Column(
            children: [
              for (var v = 1; v <= 20; v++)
                SizedBox(
                  key: locator.keyFor(1, v),
                  height: 100,
                  child: Text('1:$v'),
                ),
            ],
          ),
        ),
      ),
    );
  }

  testWidgets('firstVisible devuelve el primer versículo bajo el inset',
      (tester) async {
    final locator = VerseLocator();
    final controller = ScrollController();
    addTearDown(controller.dispose);

    await tester.pumpWidget(verseList(locator, controller));

    final initial = locator.firstVisible(topInset: 104);
    expect(initial, isNotNull);
    // Con scroll 0, el versículo que cruza y=104 es 1:2 (ocupa 100..200).
    expect(initial!.chapter, 1);
    expect(initial.verse, 2);

    // Scroll de 500px → cruza el inset el versículo 1:7 (600..700 global).
    controller.jumpTo(500);
    await tester.pump();

    final after = locator.firstVisible(topInset: 104);
    expect(after!.verse, 7);
  });

  testWidgets('scrollTo lleva el versículo pedido a la vista', (tester) async {
    final locator = VerseLocator();
    final controller = ScrollController();
    addTearDown(controller.dispose);

    await tester.pumpWidget(verseList(locator, controller));

    locator.scrollTo(1, 15, duration: const Duration(milliseconds: 1));
    await tester.pumpAndSettle();

    expect(find.text('1:15'), findsOneWidget);
    final box = tester.renderObject<RenderBox>(find.text('1:15'));
    final y = box.localToGlobal(Offset.zero).dy;
    expect(y, greaterThanOrEqualTo(0));
    expect(y, lessThan(600), reason: 'debe quedar dentro del viewport');
  });

  testWidgets('scrollTo con versículo inexistente no truena', (tester) async {
    final locator = VerseLocator();
    final controller = ScrollController();
    addTearDown(controller.dispose);

    await tester.pumpWidget(verseList(locator, controller));

    // No hay capítulo 99 — debe ser un no-op silencioso.
    locator.scrollTo(99, 1);
    await tester.pump();
  });

  test('VerseLocation.storageKey', () {
    const loc = VerseLocation(chapter: 3, verse: 16);
    expect(loc.storageKey, '3:16');
  });
}
