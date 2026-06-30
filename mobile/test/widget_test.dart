import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:bible_journey/main.dart';

void main() {
  testWidgets('App arranca y muestra el título', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: BibleJourneyApp()));
    await tester.pump();

    expect(find.text('Bible Journey'), findsWidgets);
  });
}
