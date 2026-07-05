import 'package:share_plus/share_plus.dart';

import '../models/models.dart';
import 'app_links.dart';

String buildVerseShareText({
  required String bookNameEs,
  required int chapter,
  required int verseStart,
  required int verseEnd,
  required List<BibleVerseItem> verses,
  String? translationCode,
}) {
  final range = verses.where((v) => v.verse >= verseStart && v.verse <= verseEnd).toList()
    ..sort((a, b) => a.verse.compareTo(b.verse));
  final body = range.map((v) => '${v.verse} ${v.text}').join(' ');
  final ref = verseStart == verseEnd
      ? '$bookNameEs $chapter:$verseStart'
      : '$bookNameEs $chapter:$verseStart-$verseEnd';
  final translationSuffix = translationCode != null ? ' ($translationCode)' : '';

  final buffer = StringBuffer()
    ..writeln('$ref$translationSuffix')
    ..writeln()
    ..writeln('"$body"')
    ..writeln()
    ..write('— Compartido desde Bible Journey');

  if (appShareLink.isNotEmpty) {
    buffer
      ..writeln()
      ..write(appShareLink);
  }

  return buffer.toString();
}

Future<void> shareVerseRange({
  required String bookNameEs,
  required int chapter,
  required int verseStart,
  required int verseEnd,
  required List<BibleVerseItem> verses,
  String? translationCode,
}) {
  final text = buildVerseShareText(
    bookNameEs: bookNameEs,
    chapter: chapter,
    verseStart: verseStart,
    verseEnd: verseEnd,
    verses: verses,
    translationCode: translationCode,
  );
  return SharePlus.instance.share(ShareParams(text: text));
}
