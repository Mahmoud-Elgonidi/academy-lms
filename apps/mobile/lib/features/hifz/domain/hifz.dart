/// The hifz map: every page of the mushaf by part, where each stands for a
/// learner (new, learning, or memorized and strong, fading or weak now), the
/// day's review, a page's text, a recited page's check, and the teacher's
/// view of a student's map.
library;

import '../../../core/time/local_time.dart';
import '../../homework/domain/recitation.dart';

List<Map<String, dynamic>> _list(Object? raw) => [for (final x in raw as List<dynamic>? ?? const []) x as Map<String, dynamic>];

Map<String, dynamic>? _map(Object? raw) => raw is Map<String, dynamic> ? raw : null;

int _int(Object? v) => (v as num?)?.toInt() ?? 0;

/// Where a page stands: not on the map yet, being learnt, or memorized and strong, fading or weak now.
enum Strength {
  strong('strong'),
  fading('fading'),
  weak('weak'),
  learning('learning'),
  fresh('new');

  const Strength(this.key);

  /// As the API writes it.
  final String key;

  static Strength of(Object? key) => values.where((s) => s.key == key).firstOrNull ?? fresh;
}

/// How many pages are memorized and learning; of the memorized, how many are strong, fading and weak now; and how
/// many are due by the end of the learner's day.
class HifzCounts {
  const HifzCounts({this.memorized = 0, this.learning = 0, this.strong = 0, this.fading = 0, this.weak = 0, this.due = 0});

  factory HifzCounts.fromJson(Map<String, dynamic> json) => HifzCounts(
    memorized: _int(json['memorized']),
    learning: _int(json['learning']),
    strong: _int(json['strong']),
    fading: _int(json['fading']),
    weak: _int(json['weak']),
    due: _int(json['due']),
  );

  final int memorized;
  final int learning;
  final int strong;
  final int fading;
  final int weak;
  final int due;

  /// The pages of [strength] out of [total]: the ones not on the map are what is left.
  int of(Strength strength, int total) => switch (strength) {
    Strength.strong => strong,
    Strength.fading => fading,
    Strength.weak => weak,
    Strength.learning => learning,
    Strength.fresh => (total - memorized - learning).clamp(0, total),
  };
}

/// A page on the map.
class MapPage {
  const MapPage(this.page, this.strength);

  final int page;
  final Strength strength;
}

/// A part (juz) of the mushaf and its pages, in order.
class HifzJuz {
  const HifzJuz({required this.juz, required this.pages});

  factory HifzJuz.fromJson(Map<String, dynamic> json) =>
      HifzJuz(juz: _int(json['juz']), pages: [for (final p in _list(json['pages'])) MapPage(_int(p['page']), Strength.of(p['strength']))]);

  final int juz;
  final List<MapPage> pages;

  /// How many of its pages stand at each strength.
  Map<Strength, int> get tally {
    final out = <Strength, int>{};
    for (final p in pages) {
      out.update(p.strength, (n) => n + 1, ifAbsent: () => 1);
    }
    return out;
  }
}

/// The map itself: every page by part, the counts, and each strength's name in the reader's language.
class HifzMap {
  const HifzMap({required this.pagesTotal, required this.counts, required this.juzs, required this.strengths});

  factory HifzMap.fromJson(Map<String, dynamic> json) => HifzMap(
    pagesTotal: _int(json['pages_total']),
    counts: HifzCounts.fromJson(_map(json['counts']) ?? const {}),
    juzs: [for (final j in _list(json['juzs'])) HifzJuz.fromJson(j)],
    strengths: {for (final e in (_map(json['strengths']) ?? const <String, dynamic>{}).entries) e.key: '${e.value}'},
  );

  /// 604 once the platform has the Quran's text; 0 until then (nothing to show).
  final int pagesTotal;
  final HifzCounts counts;
  final List<HifzJuz> juzs;
  final Map<String, String> strengths;

  bool get available => pagesTotal > 0;

  String label(Strength s) => strengths[s.key] ?? s.key;

  /// The pages of [juzs], each once, in order.
  Set<int> pagesOf(Iterable<int> juzs) => {
    for (final j in this.juzs)
      if (juzs.contains(j.juz)) ...j.pages.map((p) => p.page),
  };
}

/// A page of today's review: where it is, its surahs, how it stands and how many days late it is.
class WirdPage {
  const WirdPage({
    required this.page,
    required this.juz,
    required this.title,
    required this.surahs,
    required this.strength,
    required this.overdueDays,
  });

  factory WirdPage.fromJson(Map<String, dynamic> json) => WirdPage(
    page: _int(json['page']),
    juz: _int(json['juz']),
    title: json['title'] as String? ?? '',
    surahs: [for (final s in _list(json['surahs'])) Surah.fromJson(s)],
    strength: Strength.of(json['strength']),
    overdueDays: _int(json['overdue_days']),
  );

  final int page;
  final int juz;

  /// "صفحة ١٢ · البقرة", in Arabic.
  final String title;
  final List<Surah> surahs;
  final Strength strength;
  final int overdueDays;
}

/// Today's review on the learner's clock: a day's pages at most, how many are done, and what is left.
class Wird {
  const Wird({required this.daily, required this.doneToday, required this.pages});

  factory Wird.fromJson(Map<String, dynamic> json) => Wird(
    daily: _int(json['daily']),
    doneToday: _int(json['done_today']),
    pages: [for (final p in _list(json['pages'])) WirdPage.fromJson(p)],
  );

  final int daily;
  final int doneToday;

  /// What is left of today's review, most overdue first.
  final List<WirdPage> pages;

  /// Something was reviewed today and nothing is left.
  bool get finished => pages.isEmpty && doneToday > 0;
}

/// A page recited for the review and its check word by word: pending, ready or failed.
class HifzRecitation {
  const HifzRecitation({required this.id, required this.page, required this.check, this.grade, this.gradeLabel, this.recitedAt});

  factory HifzRecitation.fromJson(Map<String, dynamic> json) => HifzRecitation(
    id: json['id'] as String? ?? '',
    page: _int(json['page']),
    grade: (json['grade'] as num?)?.toInt(),
    gradeLabel: json['grade_label'] as String?,
    recitedAt: LocalTime.fromJson(json['recited_at']),
    check: RecitationCheck.fromJson(json)!,
  );

  final String id;
  final int page;

  /// 1 forgot, 2 hard, 3 good, 4 easy: the review it counted as (once heard).
  final int? grade;
  final String? gradeLabel;
  final LocalTime? recitedAt;

  /// The words in order with how each was said, the summary and the note, as the teacher's checks are read.
  final RecitationCheck check;

  bool get pending => !check.ready && !check.failed;
}

/// `GET learning/hifz`: a learner's map, today's review, and the page recited last (within a day).
class HifzBoard {
  const HifzBoard({required this.learnerId, required this.mode, required this.map, required this.wird, this.latest});

  factory HifzBoard.fromJson(Map<String, dynamic> json) => HifzBoard(
    learnerId: json['learner_id'] as String? ?? '',
    mode: json['mode'] as String? ?? 'self',
    map: HifzMap.fromJson(json),
    wird: Wird.fromJson(_map(json['wird']) ?? const {}),
    latest: _map(json['latest_recitation']) == null ? null : HifzRecitation.fromJson(_map(json['latest_recitation'])!),
  );

  final String learnerId;

  /// check: a page is recited and checked word by word; self: the learner says how it went.
  final String mode;
  final HifzMap map;
  final Wird wird;
  final HifzRecitation? latest;

  bool get checks => mode == 'check';
}

/// One verse of a page, as the platform's copy writes it.
class PageVerse {
  const PageVerse({required this.surah, required this.verse, required this.text});

  final int surah;
  final int verse;
  final String text;
}

/// `GET learning/hifz/pages/{page}`: a page's verses (perhaps the end of one surah and the start of the next), with
/// where it stands for the learner and when it is due again.
class QuranPage {
  const QuranPage({
    required this.page,
    required this.juz,
    required this.title,
    required this.surahs,
    required this.verses,
    required this.attribution,
    required this.strength,
    this.dueAt,
  });

  factory QuranPage.fromJson(Map<String, dynamic> json) => QuranPage(
    page: _int(json['page']),
    juz: _int(json['juz']),
    title: json['title'] as String? ?? '',
    surahs: [for (final s in _list(json['surahs'])) Surah.fromJson(s)],
    verses: [
      for (final v in _list(json['verses'])) PageVerse(surah: _int(v['surah']), verse: _int(v['verse']), text: v['text'] as String? ?? ''),
    ],
    attribution: json['attribution'] as String? ?? '',
    strength: Strength.of(json['strength']),
    dueAt: LocalTime.fromJson(json['due_at']),
  );

  final int page;
  final int juz;
  final String title;
  final List<Surah> surahs;
  final List<PageVerse> verses;

  /// What the text is shown with.
  final String attribution;
  final Strength strength;
  final LocalTime? dueAt;

  Surah? surah(int number) => surahs.where((s) => s.number == number).firstOrNull;
}

/// A review of a page in the last two weeks: its grade and where it came from (the teacher, a report, a homework's
/// check, the daily review or the learner's own word), in the reader's words.
class HifzReview {
  const HifzReview({
    required this.page,
    required this.grade,
    required this.gradeLabel,
    required this.source,
    required this.sourceLabel,
    this.at,
  });

  factory HifzReview.fromJson(Map<String, dynamic> json) => HifzReview(
    page: _int(json['page']),
    grade: _int(json['grade']),
    gradeLabel: json['grade_label'] as String? ?? '',
    source: json['source'] as String? ?? '',
    sourceLabel: json['source_label'] as String? ?? '',
    at: LocalTime.fromJson(json['at']),
  );

  final int page;
  final int grade;
  final String gradeLabel;
  final String source;
  final String sourceLabel;
  final LocalTime? at;
}

/// A surah a teacher may mark whole, with the pages it is on.
class SurahPages {
  const SurahPages({required this.surah, required this.pages});

  factory SurahPages.fromJson(Map<String, dynamic> json) =>
      SurahPages(surah: Surah.fromJson(json), pages: [for (final p in json['pages'] as List<dynamic>? ?? const []) _int(p)]);

  final Surah surah;
  final List<int> pages;
}

/// `GET teacher/students/{enrollment}/hifz`: a student's map for their teacher, the last two weeks' reviews, the
/// surahs with their pages, and how well a teacher may say a part is known (strong, good, weak).
class StudentHifz {
  const StudentHifz({
    required this.enrollmentId,
    required this.follows,
    required this.studentName,
    required this.map,
    required this.recent,
    required this.surahs,
    required this.marks,
  });

  factory StudentHifz.fromJson(Map<String, dynamic> json) => StudentHifz(
    enrollmentId: json['enrollment_id'] as String? ?? '',
    follows: json['follows'] as bool? ?? true,
    studentName: _map(json['student'])?['name'] as String? ?? '',
    map: HifzMap.fromJson(json),
    recent: [for (final r in _list(json['recent'])) HifzReview.fromJson(r)],
    surahs: [for (final s in _list(json['surahs'])) SurahPages.fromJson(s)],
    marks: {for (final e in (_map(json['marks']) ?? const <String, dynamic>{}).entries) e.key: '${e.value}'},
  );

  final String enrollmentId;

  /// Whether the map belongs to this course (its curriculum reads the Quran, or the student already has pages).
  final bool follows;
  final String studentName;
  final HifzMap map;
  final List<HifzReview> recent;
  final List<SurahPages> surahs;

  /// strong, good, weak → their names, in this order.
  final Map<String, String> marks;

  /// The pages a choice of parts and surahs covers, each once.
  Set<int> chosen({Set<int> juzs = const {}, Set<int> surahs = const {}}) => {
    ...map.pagesOf(juzs),
    for (final s in this.surahs)
      if (surahs.contains(s.surah.number)) ...s.pages,
  };
}
