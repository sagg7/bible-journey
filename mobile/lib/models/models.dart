// Modelos de datos de Bible Journey. Parsean el JSON de la API Laravel.

// ─── V2 models ──────────────────────────────────────────────────────────────

class StreamPlanSummary {
  final int id;
  final String profileId;
  final String locale;
  final String publicationStatus;
  final int nodeCount;
  final int edgeCount;
  final String? publishedAt;
  final List<CrsNodeItem> nodes;

  StreamPlanSummary({
    required this.id,
    required this.profileId,
    required this.locale,
    required this.publicationStatus,
    required this.nodeCount,
    required this.edgeCount,
    this.publishedAt,
    this.nodes = const [],
  });

  factory StreamPlanSummary.fromJson(Map<String, dynamic> j) =>
      StreamPlanSummary(
        id: j['id'] ?? 0,
        profileId: j['profile_id'] ?? '',
        locale: j['locale'] ?? 'es',
        publicationStatus: j['publication_status'] ?? '',
        nodeCount: j['node_count'] ?? 0,
        edgeCount: j['edge_count'] ?? 0,
        publishedAt: j['published_at'],
        nodes: ((j['nodes'] as List?) ?? [])
            .map((n) => CrsNodeItem.fromJson(n))
            .toList(),
      );
}

class CrsNodeItem {
  final int id;
  final int rank;
  final String displayMode;
  final int crsId;
  final String? sourceMap;
  final String? titleEs;
  final String? reference;
  final String? era;
  final String? eraSlug;
  final int? sortKey;
  final String? confidence;
  final String? streamRole;
  final String? userFacingEra;
  final int? userFacingEraSort;
  final bool isMainStreamNode;
  final bool locked;

  CrsNodeItem({
    required this.id,
    required this.rank,
    required this.displayMode,
    required this.crsId,
    this.sourceMap,
    this.titleEs,
    this.reference,
    this.era,
    this.eraSlug,
    this.sortKey,
    this.confidence,
    this.streamRole,
    this.userFacingEra,
    this.userFacingEraSort,
    this.isMainStreamNode = true,
    this.locked = false,
  });

  String get displayTitle => titleEs ?? reference ?? sourceMap ?? '';

  factory CrsNodeItem.fromJson(Map<String, dynamic> j) => CrsNodeItem(
    id: j['id'] ?? 0,
    rank: j['rank'] ?? 0,
    displayMode: j['display_mode'] ?? 'full',
    crsId: j['crs_id'] ?? 0,
    sourceMap: j['source_map'],
    titleEs: j['title_es'],
    reference: j['reference'],
    era: j['era'],
    eraSlug: j['era_slug'],
    sortKey: j['sort_key'],
    confidence: j['confidence'],
    streamRole: j['stream_role'],
    userFacingEra: j['user_facing_era'],
    userFacingEraSort: j['user_facing_era_sort'],
    isMainStreamNode: j['is_main_stream_node'] ?? true,
    locked: j['locked'] ?? false,
  );
}

class PassageText {
  final bool hasText;
  final String reference;
  final String? book;
  final String? translation;
  final String? translationName;
  final List<Map<String, dynamic>> verses;

  PassageText({
    required this.hasText,
    required this.reference,
    this.book,
    this.translation,
    this.translationName,
    required this.verses,
  });

  factory PassageText.fromJson(Map<String, dynamic> j) => PassageText(
    hasText: j['has_text'] == true,
    reference: j['reference'] ?? '',
    book: j['book'],
    translation: j['translation'],
    translationName: j['translation_name'],
    verses: ((j['verses'] as List?) ?? [])
        .map((v) => Map<String, dynamic>.from(v as Map))
        .toList(),
  );
}

class CompareGroupDetail {
  final int id;
  final String titleEs;
  final String? editorialSummaryEs;
  final String? disclaimerEs;
  final String? relationLevel;
  final List<String> keyDifferencesEs;
  final List<CompareAccount> accounts;

  CompareGroupDetail({
    required this.id,
    required this.titleEs,
    this.editorialSummaryEs,
    this.disclaimerEs,
    this.relationLevel,
    required this.keyDifferencesEs,
    required this.accounts,
  });

  factory CompareGroupDetail.fromJson(Map<String, dynamic> j) =>
      CompareGroupDetail(
        id: j['id'] ?? 0,
        titleEs: j['title_es'] ?? '',
        editorialSummaryEs: j['editorial_summary_es'],
        disclaimerEs: j['disclaimer_es'],
        relationLevel: j['relation_level'],
        keyDifferencesEs: ((j['key_differences_es'] as List?) ?? [])
            .cast<String>(),
        accounts: ((j['accounts'] as List?) ?? [])
            .map((a) => CompareAccount.fromJson(a))
            .toList(),
      );
}

class CompareAccount {
  final int id;
  final String sourceMap;
  final String role;
  final String displayReference;
  final String? displayLabelEs;
  final String confidence;
  final bool hasText;

  CompareAccount({
    required this.id,
    required this.sourceMap,
    required this.role,
    required this.displayReference,
    this.displayLabelEs,
    required this.confidence,
    required this.hasText,
  });

  factory CompareAccount.fromJson(Map<String, dynamic> j) => CompareAccount(
    id: j['id'] ?? 0,
    sourceMap: j['source_map'] ?? '',
    role: j['role'] ?? 'narrative_anchor',
    displayReference: j['display_reference'] ?? '',
    displayLabelEs: j['display_label_es'],
    confidence: j['confidence'] ?? 'probable',
    hasText: j['has_text'] == true,
  );
}

class ReadingBlockV2 {
  final int id;
  final String sourceMap;
  final String role;
  final int displayOrder;
  final String displayReference;
  final String? displayLabelEs;
  final String placementConfidence;
  final bool requiredInCompleteMode;
  final bool shownInNarrativeFlow;
  final bool hasText;

  ReadingBlockV2({
    required this.id,
    required this.sourceMap,
    required this.role,
    required this.displayOrder,
    required this.displayReference,
    this.displayLabelEs,
    required this.placementConfidence,
    required this.requiredInCompleteMode,
    required this.shownInNarrativeFlow,
    required this.hasText,
  });

  factory ReadingBlockV2.fromJson(Map<String, dynamic> j) => ReadingBlockV2(
    id: j['id'] ?? 0,
    sourceMap: j['source_map'] ?? '',
    role: j['role'] ?? 'narrative_anchor',
    displayOrder: j['display_order'] ?? 0,
    displayReference: j['display_reference'] ?? '',
    displayLabelEs: j['display_label_es'],
    placementConfidence: j['placement_confidence'] ?? 'probable',
    requiredInCompleteMode: j['required_in_complete_mode'] ?? true,
    shownInNarrativeFlow: j['shown_in_narrative_flow'] ?? true,
    hasText: j['has_text'] ?? false,
  );
}

class CrsNodeDetail {
  final int nodeId;
  final int rank;
  final String displayMode;
  final String? requiredState;
  final String? explanationEs;
  final CrsMeta crs;
  final List<ReadingBlockV2> blocks;
  final CompareGroupRef? compareGroup;
  final StudyContent studyContent;
  final SpiritOfProphecyContent spiritOfProphecy;
  final bool locked;

  CrsNodeDetail({
    required this.nodeId,
    required this.rank,
    required this.displayMode,
    this.requiredState,
    this.explanationEs,
    required this.crs,
    required this.blocks,
    this.compareGroup,
    required this.studyContent,
    required this.spiritOfProphecy,
    this.locked = false,
  });

  factory CrsNodeDetail.fromJson(Map<String, dynamic> j) => CrsNodeDetail(
    nodeId: j['node_id'] ?? 0,
    rank: j['rank'] ?? 0,
    displayMode: j['display_mode'] ?? 'full',
    requiredState: j['required_state'],
    explanationEs: j['explanation_es'],
    crs: CrsMeta.fromJson(j['crs'] ?? {}),
    blocks: ((j['blocks'] as List?) ?? [])
        .map((b) => ReadingBlockV2.fromJson(b))
        .toList(),
    compareGroup: j['compare_group'] != null
        ? CompareGroupRef.fromJson(j['compare_group'])
        : null,
    studyContent: StudyContent.fromJson(j['study_content'] ?? {}),
    locked: j['locked'] ?? false,
    spiritOfProphecy: SpiritOfProphecyContent.fromJson(
      j['spirit_of_prophecy'] ?? {},
    ),
  );
}

class SpiritOfProphecyContent {
  final String? locale;
  final String? sourceBookCode;
  final String? sourceBookTitle;
  final List<SpiritOfProphecyExcerpt> excerpts;
  final String? copyright;
  final String? version;

  SpiritOfProphecyContent({
    this.locale,
    this.sourceBookCode,
    this.sourceBookTitle,
    this.excerpts = const [],
    this.copyright,
    this.version,
  });

  factory SpiritOfProphecyContent.fromJson(Map<String, dynamic> j) =>
      SpiritOfProphecyContent(
        locale: j['locale'],
        sourceBookCode: j['source_book_code'],
        sourceBookTitle: j['source_book_title'],
        excerpts: ((j['excerpts'] as List?) ?? [])
            .map(
              (e) => SpiritOfProphecyExcerpt.fromJson(
                Map<String, dynamic>.from(e as Map),
              ),
            )
            .toList(),
        copyright: j['copyright'],
        version: j['version'],
      );
}

class SpiritOfProphecyExcerpt {
  final String snippet;
  final String refcodeShort;
  final String refcodeLong;

  SpiritOfProphecyExcerpt({
    required this.snippet,
    required this.refcodeShort,
    required this.refcodeLong,
  });

  factory SpiritOfProphecyExcerpt.fromJson(Map<String, dynamic> j) =>
      SpiritOfProphecyExcerpt(
        snippet: j['snippet'] ?? '',
        refcodeShort: j['refcode_short'] ?? '',
        refcodeLong: j['refcode_long'] ?? '',
      );
}

class StudyContent {
  final String? summaryEs;
  final String? contextEs;
  final List<StudyPerson> people;
  final List<StudyPlace> places;
  final List<StudyConnection> connections;
  final List<StudySource> sources;
  final String? version;

  StudyContent({
    this.summaryEs,
    this.contextEs,
    this.people = const [],
    this.places = const [],
    this.connections = const [],
    this.sources = const [],
    this.version,
  });

  factory StudyContent.fromJson(Map<String, dynamic> j) => StudyContent(
    summaryEs: j['summary_es'],
    contextEs: j['context_es'],
    people: ((j['people'] as List?) ?? [])
        .map((p) => StudyPerson.fromJson(Map<String, dynamic>.from(p as Map)))
        .toList(),
    places: ((j['places'] as List?) ?? [])
        .map((p) => StudyPlace.fromJson(Map<String, dynamic>.from(p as Map)))
        .toList(),
    connections: ((j['connections'] as List?) ?? [])
        .map(
          (c) => StudyConnection.fromJson(Map<String, dynamic>.from(c as Map)),
        )
        .toList(),
    sources: ((j['sources'] as List?) ?? [])
        .map((s) => StudySource.fromJson(Map<String, dynamic>.from(s as Map)))
        .toList(),
    version: j['version'],
  );
}

class StudyPerson {
  final String name;
  final String? role;

  StudyPerson({required this.name, this.role});

  factory StudyPerson.fromJson(Map<String, dynamic> j) =>
      StudyPerson(name: j['name'] ?? '', role: j['role']);
}

class StudyPlace {
  final String name;
  final String? certaintyLevel;
  final String? note;

  StudyPlace({required this.name, this.certaintyLevel, this.note});

  factory StudyPlace.fromJson(Map<String, dynamic> j) => StudyPlace(
    name: j['name'] ?? '',
    certaintyLevel: j['certainty_level'],
    note: j['note'],
  );
}

class StudyConnection {
  final String type;
  final String title;
  final String? subtitle;
  final String? sourceMap;
  final String? confidence;
  final int? compareGroupId;

  StudyConnection({
    required this.type,
    required this.title,
    this.subtitle,
    this.sourceMap,
    this.confidence,
    this.compareGroupId,
  });

  factory StudyConnection.fromJson(Map<String, dynamic> j) => StudyConnection(
    type: j['type'] ?? '',
    title: j['title'] ?? '',
    subtitle: j['subtitle'],
    sourceMap: j['source_map'],
    confidence: j['confidence'],
    compareGroupId: j['compare_group_id'],
  );
}

class StudySource {
  final String label;

  StudySource({required this.label});

  factory StudySource.fromJson(Map<String, dynamic> j) =>
      StudySource(label: j['label'] ?? '');
}

class CrsMeta {
  final int id;
  final String sourceMap;
  final String era;
  final String eraSlug;
  final String titleEs;
  final String? titleEn;
  final String placementConfidence;
  final String eventConfidence;
  final String? narrativeFlowMessage;
  final String? editorialNote;

  CrsMeta({
    required this.id,
    required this.sourceMap,
    required this.era,
    required this.eraSlug,
    required this.titleEs,
    this.titleEn,
    required this.placementConfidence,
    required this.eventConfidence,
    this.narrativeFlowMessage,
    this.editorialNote,
  });

  factory CrsMeta.fromJson(Map<String, dynamic> j) => CrsMeta(
    id: j['id'] ?? 0,
    sourceMap: j['source_map'] ?? '',
    era: j['era'] ?? '',
    eraSlug: j['era_slug'] ?? '',
    titleEs: j['title_es'] ?? '',
    titleEn: j['title_en'],
    placementConfidence: j['placement_confidence'] ?? 'probable',
    eventConfidence: j['event_confidence'] ?? 'probable',
    narrativeFlowMessage: j['narrative_flow_message'],
    editorialNote: j['editorial_note'],
  );
}

class CompareGroupRef {
  final int id;
  final String titleEs;
  final String? relationLevel;

  CompareGroupRef({
    required this.id,
    required this.titleEs,
    this.relationLevel,
  });

  factory CompareGroupRef.fromJson(Map<String, dynamic> j) => CompareGroupRef(
    id: j['id'] ?? 0,
    titleEs: j['title_es'] ?? '',
    relationLevel: j['relation_level'],
  );
}

class ProgressSummary {
  final int planId;
  final CanonicalProgress canonical;
  final NarrativeProgress narrative;

  ProgressSummary({
    required this.planId,
    required this.canonical,
    required this.narrative,
  });

  factory ProgressSummary.fromJson(Map<String, dynamic> j) => ProgressSummary(
    planId: j['plan_id'] ?? 0,
    canonical: CanonicalProgress.fromJson(j['canonical'] ?? {}),
    narrative: NarrativeProgress.fromJson(j['narrative'] ?? {}),
  );
}

class CanonicalProgress {
  final int total;
  final int completed;
  final int inProgress;
  final int deferred;
  final double percent;

  CanonicalProgress({
    required this.total,
    required this.completed,
    required this.inProgress,
    required this.deferred,
    required this.percent,
  });

  factory CanonicalProgress.fromJson(Map<String, dynamic> j) =>
      CanonicalProgress(
        total: j['total'] ?? 0,
        completed: j['completed'] ?? 0,
        inProgress: j['in_progress'] ?? 0,
        deferred: j['deferred'] ?? 0,
        percent: (j['percent'] as num?)?.toDouble() ?? 0.0,
      );
}

class NarrativeProgress {
  final int total;
  final int notStarted;
  final int inProgress;
  final int primaryComplete;
  final int fullyComplete;
  final double percent;

  NarrativeProgress({
    required this.total,
    required this.notStarted,
    required this.inProgress,
    required this.primaryComplete,
    required this.fullyComplete,
    required this.percent,
  });

  factory NarrativeProgress.fromJson(Map<String, dynamic> j) =>
      NarrativeProgress(
        total: j['total'] ?? 0,
        notStarted: j['not_started'] ?? 0,
        inProgress: j['in_progress'] ?? 0,
        primaryComplete: j['primary_complete'] ?? 0,
        fullyComplete: j['fully_complete'] ?? 0,
        percent: (j['percent'] as num?)?.toDouble() ?? 0.0,
      );
}

class EzraStructuredResponse {
  final String directAnswer;
  final Map<String, dynamic>? biblicalBasis;
  final String? historicalContext;
  final String? editorialNote;
  final String certaintyLevel;
  final String? certaintyExplanation;
  final List<String> sources;
  final String? reflectionQuestion;

  EzraStructuredResponse({
    required this.directAnswer,
    this.biblicalBasis,
    this.historicalContext,
    this.editorialNote,
    required this.certaintyLevel,
    this.certaintyExplanation,
    required this.sources,
    this.reflectionQuestion,
  });

  factory EzraStructuredResponse.fromJson(Map<String, dynamic> j) =>
      EzraStructuredResponse(
        directAnswer: j['direct_answer'] ?? j['answer'] ?? '',
        biblicalBasis: j['biblical_basis'],
        historicalContext: j['historical_context'],
        editorialNote: j['editorial_note'],
        certaintyLevel: j['certainty_level'] ?? 'probable',
        certaintyExplanation: j['certainty_explanation'],
        sources: ((j['sources'] as List?) ?? []).cast<String>(),
        reflectionQuestion: j['reflection_question'],
      );
}

class MeProfile {
  final int id;
  final String name;
  final String email;
  final String subscriptionStatus;
  final bool isPremium;
  final int? institutionId;

  MeProfile({
    required this.id,
    required this.name,
    required this.email,
    required this.subscriptionStatus,
    required this.isPremium,
    this.institutionId,
  });

  factory MeProfile.fromJson(Map<String, dynamic> j) => MeProfile(
        id: j['id'] ?? 0,
        name: j['name'] ?? '',
        email: j['email'] ?? '',
        subscriptionStatus: j['subscription_status'] ?? 'free',
        isPremium: j['is_premium'] ?? false,
        institutionId: j['institution_id'],
      );
}

class BibleTranslationOption {
  final String code;
  final String name;
  final String language;
  final bool canDisplayFullText;

  BibleTranslationOption({
    required this.code,
    required this.name,
    required this.language,
    required this.canDisplayFullText,
  });

  factory BibleTranslationOption.fromJson(Map<String, dynamic> j) =>
      BibleTranslationOption(
        code: j['code'] ?? '',
        name: j['name'] ?? '',
        language: j['language'] ?? '',
        canDisplayFullText: j['can_display_full_text'] ?? false,
      );
}

// ─── Bible text models (new /api/readings/* endpoints) ─────────────────────

class BibleVerseItem {
  final int chapter;
  final int verse;
  final String text;
  final String ref;

  BibleVerseItem({
    required this.chapter,
    required this.verse,
    required this.text,
    required this.ref,
  });

  factory BibleVerseItem.fromJson(Map<String, dynamic> j) => BibleVerseItem(
    chapter: j['chapter'] ?? 0,
    verse: j['verse'] ?? j['number'] ?? 0,
    text: j['text'] ?? '',
    ref: j['ref'] ?? '',
  );
}

class ReadingBlockDetail {
  final bool hasText;
  final int verseCount;
  final List<BibleVerseItem> verses;
  final String? translationCode;
  final String? translationName;

  ReadingBlockDetail({
    required this.hasText,
    required this.verseCount,
    required this.verses,
    this.translationCode,
    this.translationName,
  });

  factory ReadingBlockDetail.fromJson(Map<String, dynamic> j) =>
      ReadingBlockDetail(
        hasText: j['has_text'] == true,
        verseCount: j['verse_count'] ?? 0,
        verses: ((j['verses'] as List?) ?? [])
            .map((v) => BibleVerseItem.fromJson(v as Map<String, dynamic>))
            .toList(),
        translationCode: (j['translation'] as Map<String, dynamic>?)?['code'],
        translationName: (j['translation'] as Map<String, dynamic>?)?['name'],
      );
}

class CanonicalChapterContent {
  final String bookOsisCode;
  final String bookNameEs;
  final int chapterCount;
  final int chapter;
  final int verseCount;
  final bool hasText;
  final List<BibleVerseItem> verses;
  final String? translationCode;
  final String? translationName;
  final int? prevChapter;
  final int? nextChapter;

  CanonicalChapterContent({
    required this.bookOsisCode,
    required this.bookNameEs,
    required this.chapterCount,
    required this.chapter,
    required this.verseCount,
    required this.hasText,
    required this.verses,
    this.translationCode,
    this.translationName,
    this.prevChapter,
    this.nextChapter,
  });

  factory CanonicalChapterContent.fromJson(Map<String, dynamic> j) {
    final book = j['book'] as Map<String, dynamic>? ?? {};
    final nav = j['navigation'] as Map<String, dynamic>? ?? {};
    final chNum = j['chapter'] as int? ?? 0;
    return CanonicalChapterContent(
      bookOsisCode: book['osis_code'] ?? '',
      bookNameEs: book['name_es'] ?? '',
      chapterCount: book['chapter_count'] ?? 0,
      chapter: chNum,
      verseCount: j['verse_count'] ?? 0,
      hasText: j['has_text'] == true,
      verses: ((j['verses'] as List?) ?? []).map((v) {
        final m = v as Map<String, dynamic>;
        final num = m['number'] as int? ?? 0;
        return BibleVerseItem(
          chapter: chNum,
          verse: num,
          text: m['text'] ?? '',
          ref: '$chNum:$num',
        );
      }).toList(),
      translationCode: (j['translation'] as Map<String, dynamic>?)?['code'],
      translationName: (j['translation'] as Map<String, dynamic>?)?['name'],
      prevChapter: nav['prev_chapter'] as int?,
      nextChapter: nav['next_chapter'] as int?,
    );
  }
}
