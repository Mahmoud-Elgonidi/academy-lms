/// A Quran lesson's shared mushaf (the page open, its words as the
/// platform's copy writes them, the words its teacher marked) and a
/// learner's mistakes notebook (the words marked in their lessons, by surah
/// and verse).
library;

import 'dart:convert';

import '../../../core/time/local_time.dart';
import '../../homework/domain/recitation.dart' show Surah;

List<Map<String, dynamic>> _list(Object? raw) => [for (final x in raw as List<dynamic>? ?? const []) x as Map<String, dynamic>];

Map<String, String> _labels(Object? raw) => {
  for (final e in (raw is Map<String, dynamic> ? raw : const <String, dynamic>{}).entries) e.key: '${e.value}',
};

int _int(Object? v) => (v as num?)?.toInt() ?? 0;

List<String> _words(Object? raw) => [for (final w in raw as List<dynamic>? ?? const []) '$w'];

/// What a teacher marks a word as: said wrong, a hesitation, or a slip of tajweed.
enum MistakeKind {
  wrong,
  hesitation,
  tajweed;

  /// As the API writes it; null for a kind this app does not know.
  static MistakeKind? of(Object? key) => values.where((k) => k.name == key).firstOrNull;
}

/// A word of the mushaf: its surah, its verse and its place in the verse from 1.
typedef WordAt = ({int surah, int verse, int word});

/// One verse of the page, word by word as the platform's copy writes it.
class MushafVerse {
  const MushafVerse({required this.surah, required this.verse, required this.words});

  factory MushafVerse.fromJson(Map<String, dynamic> json) =>
      MushafVerse(surah: _int(json['surah']), verse: _int(json['verse']), words: _words(json['words']));

  final int surah;
  final int verse;
  final List<String> words;

  /// The word at [place] (from 1).
  WordAt at(int place) => (surah: surah, verse: verse, word: place);
}

/// A word marked in the student's earlier lessons: the latest kind and how many times.
typedef EarlierMark = ({MistakeKind kind, int times});

/// `GET learning/lessons/{session}/mushaf`: the page shown (the one asked
/// for, else the teacher's, else where the lesson starts), its verses word
/// by word, the words marked in this lesson and the ones marked on it
/// before, and who is looking (the teacher turns and marks; the student and
/// their guardians follow and read).
class LessonMushaf {
  const LessonMushaf({
    required this.sessionId,
    required this.role,
    required this.canMark,
    required this.page,
    required this.startPage,
    required this.pagesTotal,
    required this.juz,
    required this.title,
    required this.surahs,
    required this.verses,
    required this.attribution,
    this.teacherPage,
    this.basmala,
    this.marks = const {},
    this.earlier = const {},
    this.kinds = const {},
  });

  factory LessonMushaf.fromJson(Map<String, dynamic> json) {
    WordAt at(Map<String, dynamic> m) => (surah: _int(m['surah']), verse: _int(m['verse']), word: _int(m['word']));
    final basmala = json['basmala'];
    return LessonMushaf(
      sessionId: json['session_id'] as String? ?? '',
      role: json['role'] as String? ?? 'student',
      canMark: json['can_mark'] == true,
      page: _int(json['page']),
      teacherPage: (json['teacher_page'] as num?)?.toInt(),
      startPage: _int(json['start_page']),
      pagesTotal: _int(json['pages_total']),
      juz: _int(json['juz']),
      title: json['title'] as String? ?? '',
      surahs: [for (final s in _list(json['surahs'])) Surah.fromJson(s)],
      basmala: basmala is List ? _words(basmala) : null,
      verses: [for (final v in _list(json['verses'])) MushafVerse.fromJson(v)],
      marks: {for (final m in _list(json['marks'])) at(m): ?MistakeKind.of(m['kind'])},
      earlier: {
        for (final m in _list(json['earlier']))
          if (MistakeKind.of(m['kind']) case final kind?) at(m): (kind: kind, times: _int(m['times'])),
      },
      kinds: _labels(json['kinds']),
      attribution: json['attribution'] as String? ?? '',
    );
  }

  final String sessionId;

  /// teacher, student or guardian.
  final String role;

  /// The teacher may mark words now (from the room's opening until the report is handed in).
  final bool canMark;

  /// The page shown (1 to [pagesTotal]).
  final int page;

  /// The page the teacher has open; null until they turn one.
  final int? teacherPage;

  /// Where the lesson starts: what the teacher's screen shows until they turn a page.
  final int startPage;
  final int pagesTotal;
  final int juz;

  /// "صفحة ١٢ · البقرة", in Arabic.
  final String title;
  final List<Surah> surahs;

  /// The basmala's words from the platform's copy, when a surah other than al-Fatiha and at-Tawba begins here.
  final List<String>? basmala;
  final List<MushafVerse> verses;

  /// The words marked in this lesson.
  final Map<WordAt, MistakeKind> marks;

  /// The words on this page marked in the student's earlier lessons (shown faintly).
  final Map<WordAt, EarlierMark> earlier;

  /// Each kind's name in the reader's language.
  final Map<String, String> kinds;

  /// What the text is shown with.
  final String attribution;

  bool get teaches => role == 'teacher';

  bool get hasPrevious => page > 1;
  bool get hasNext => page < pagesTotal;

  /// The page the teacher's screen shows: the one they turned to, else where the lesson starts.
  int get teachersView => teacherPage ?? startPage;

  /// Whether this is the page the teacher's screen shows (the student is following them).
  bool get onTeachersPage => page == teachersView;

  String label(MistakeKind kind) => kinds[kind.name] ?? kind.name;

  Surah? surah(int number) => surahs.where((s) => s.number == number).firstOrNull;

  /// The basmala goes at the head of this verse: the first of a surah other than al-Fatiha and at-Tawba.
  bool basmalaBefore(MushafVerse v) => basmala != null && v.verse == 1 && v.surah != 1 && v.surah != 9;

  /// The verses grouped by surah, in order (a page may end one surah and begin the next).
  List<List<MushafVerse>> get bySurah {
    final groups = <List<MushafVerse>>[];
    for (final v in verses) {
      if (groups.isEmpty || groups.last.last.surah != v.surah) {
        groups.add([v]);
      } else {
        groups.last.add(v);
      }
    }
    return groups;
  }
}

/// A marked word in the notebook: its place in the verse, how many times, what it was each time, and when last.
class BookMark {
  const BookMark({required this.word, required this.times, required this.kinds, this.lastAt});

  factory BookMark.fromJson(Map<String, dynamic> json) => BookMark(
    word: _int(json['word']),
    times: _int(json['times']),
    kinds: [for (final k in json['kinds'] as List<dynamic>? ?? const []) ?MistakeKind.of(k)],
    lastAt: LocalTime.fromJson(json['last_at']),
  );

  final int word;
  final int times;
  final List<MistakeKind> kinds;
  final LocalTime? lastAt;

  /// The kind it is drawn in: wrong first, then a hesitation, then tajweed.
  MistakeKind get worst => kinds.isEmpty ? MistakeKind.wrong : (kinds.toList()..sort((a, b) => a.index - b.index)).first;
}

/// A verse of the notebook: its words as written, and its marked words by place.
class BookVerse {
  const BookVerse({required this.verse, required this.words, required this.marks});

  factory BookVerse.fromJson(Map<String, dynamic> json) => BookVerse(
    verse: _int(json['verse']),
    words: _words(json['words']),
    marks: {for (final m in _list(json['marks'])) _int(m['word']): BookMark.fromJson(m)},
  );

  final int verse;
  final List<String> words;
  final Map<int, BookMark> marks;
}

/// A surah of the notebook and how many of its words are marked.
class BookSurah {
  const BookSurah({required this.surah, required this.nameAr, required this.nameEn, required this.words, required this.verses});

  factory BookSurah.fromJson(Map<String, dynamic> json) => BookSurah(
    surah: _int(json['surah']),
    nameAr: json['name_ar'] as String? ?? '',
    nameEn: json['name_en'] as String? ?? '',
    words: _int(json['words']),
    verses: [for (final v in _list(json['verses'])) BookVerse.fromJson(v)],
  );

  final int surah;
  final String nameAr;
  final String nameEn;
  final int words;
  final List<BookVerse> verses;

  String name(String locale) => locale == 'ar' || nameEn.isEmpty ? nameAr : nameEn;
}

/// `GET learning/mistakes` (a learner's) and `GET teacher/students/{enrollment}/mistakes` (a student's for their
/// teacher): the words marked in the last six months, by surah and verse.
class MistakesBook {
  const MistakesBook({required this.words, required this.surahs, this.kinds = const {}});

  factory MistakesBook.fromJson(Map<String, dynamic> json) => MistakesBook(
    words: _int(json['words']),
    surahs: [for (final s in _list(json['surahs'])) BookSurah.fromJson(s)],
    kinds: _labels(json['kinds']),
  );

  final int words;
  final List<BookSurah> surahs;
  final Map<String, String> kinds;

  bool get isEmpty => surahs.isEmpty;

  String label(MistakeKind kind) => kinds[kind.name] ?? kind.name;
}

/// The topic of the room's data that says "the mushaf changed, read it again".
const mushafTopic = 'mushaf';

/// What the teacher sends on [mushafTopic]: nothing but the shape's version.
List<int> mushafSignal() => utf8.encode(jsonEncode({'v': 1}));

/// Whether data heard in a lesson's room says the mushaf changed: on [mushafTopic], from the lesson's teacher (the
/// server signs each seat's metadata, `{role, user_id}`). Anything else, from anyone else, is let be.
bool isMushafSignal({required String? topic, required String? metadata}) {
  if (topic != mushafTopic || metadata == null || metadata.isEmpty) return false;
  try {
    final json = jsonDecode(metadata);
    return json is Map<String, dynamic> && json['role'] == 'teacher';
  } on FormatException {
    return false;
  }
}

/// Arabic-Indic digits, as the mushaf numbers its verses.
String arabicDigits(int n) => '$n'.split('').map((d) => '٠١٢٣٤٥٦٧٨٩'[int.parse(d)]).join();
