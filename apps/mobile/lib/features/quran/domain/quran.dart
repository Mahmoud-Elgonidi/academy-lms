/// The Quran program as the API writes it (`quran/…`): a course's plan with
/// where the learner is and the next portion (the new part, the near and the
/// far review), a lesson's results part by part, exams and certificates.
/// Passages come by surah and verses with a title in the reader's language,
/// never the Quran's words: the mushaf's pages carry those.
library;

import '../../../core/time/local_time.dart';

List<Map<String, dynamic>> _list(Object? raw) => [
  for (final x in raw is List ? raw : const <Object?>[])
    if (x is Map<String, dynamic>) x,
];

Map<String, dynamic>? _map(Object? raw) => raw is Map<String, dynamic> ? raw : null;

int _int(Object? v) => (v as num?)?.toInt() ?? 0;

String _str(Object? v) => v is String ? v : (v == null ? '' : '$v');

/// The parts of a portion, in the order a lesson takes them.
const portionParts = ['new', 'near', 'far'];

/// The grades from best to worst, as the API names them.
const gradeOrder = ['excellent', 'very_good', 'good', 'acceptable', 'repeat'];

/// A grade as stars, for the kids' look (five for excellent, one for "repeat"); 0 for none.
int gradeStars(String? grade) {
  final i = gradeOrder.indexOf(grade ?? '');
  return i < 0 ? 0 : gradeOrder.length - i;
}

/// A choice the academy set: a kind of mistake (with its weight) or a grade.
class QuranChoice {
  const QuranChoice({required this.key, required this.label, this.weight = 0});

  factory QuranChoice.fromJson(Map<String, dynamic> json) =>
      QuranChoice(key: _str(json['key']), label: _str(json['label']), weight: (json['weight'] as num?)?.toDouble() ?? 0);

  final String key;
  final String label;
  final double weight;

  static List<QuranChoice> listOf(Object? raw) => [for (final x in _list(raw)) QuranChoice.fromJson(x)];
}

/// Verses of one surah: "الملك ١–١٠".
class VerseSpan {
  const VerseSpan({required this.surah, required this.from, required this.to, required this.title});

  factory VerseSpan.fromJson(Map<String, dynamic> json) =>
      VerseSpan(surah: _int(json['surah']), from: _int(json['from']), to: _int(json['to']), title: _str(json['title']));

  final int surah;
  final int from;
  final int to;
  final String title;
}

/// One part of a portion: its name, its passages and the mushaf's pages they are on.
class PortionPart {
  const PortionPart({required this.key, required this.label, required this.ranges, required this.pages});

  factory PortionPart.fromJson(String key, Map<String, dynamic> json) => PortionPart(
    key: key,
    label: _str(json['label']),
    ranges: [for (final r in _list(json['ranges'])) VerseSpan.fromJson(r)],
    pages: [for (final p in json['pages'] is List ? json['pages'] as List : const <Object?>[]) _int(p)],
  );

  /// new, near or far.
  final String key;
  final String label;
  final List<VerseSpan> ranges;
  final List<int> pages;

  String get titles => ranges.map((r) => r.title).join('، ');

  /// "12" or "12–14".
  String get pageSpan {
    if (pages.isEmpty) return '';
    final sorted = [...pages]..sort();
    return sorted.first == sorted.last ? '${sorted.first}' : '${sorted.first}–${sorted.last}';
  }
}

/// A lesson's portion: the parts it has, the Noorani Qaida's lesson, and the exam stops the new part reaches.
class Portion {
  const Portion({required this.parts, this.lessonTitle, this.stops = const [], this.finished = false});

  factory Portion.fromJson(Map<String, dynamic> json) => Portion(
    parts: [
      for (final key in portionParts)
        if (_map(json[key]) case final part?) PortionPart.fromJson(key, part),
    ],
    lessonTitle: _map(json['lesson']) == null ? null : _str(_map(json['lesson'])!['title']),
    stops: [for (final s in _list(json['stops'])) _str(s['label'])],
    finished: json['finished'] == true,
  );

  final List<PortionPart> parts;

  /// On the Noorani Qaida: the lesson's number and title, as the API wrote it.
  final String? lessonTitle;
  final List<String> stops;
  final bool finished;

  PortionPart? part(String key) => parts.where((p) => p.key == key).firstOrNull;
}

/// A course's (or a group's) plan: the program, the learner's pace, where they are, how much is behind them, and
/// the next portion. Frozen while the course is paused or over.
class QuranPlan {
  const QuranPlan({
    required this.id,
    required this.programName,
    required this.trackLabel,
    required this.unitLabel,
    required this.progress,
    required this.finished,
    required this.newAmount,
    required this.nearPortions,
    required this.farPages,
    this.enrollmentId,
    this.groupId,
    this.position,
    this.frozen,
    this.frozenLabel,
    this.portion,
  });

  factory QuranPlan.fromJson(Map<String, dynamic> json) {
    final program = _map(json['program']) ?? const <String, dynamic>{};
    final pace = _map(json['pace']) ?? const <String, dynamic>{};
    final position = _map(json['position']);
    final portion = _map(json['portion']);
    return QuranPlan(
      id: _str(json['id']),
      enrollmentId: json['enrollment_id'] as String?,
      groupId: json['group_id'] as String?,
      programName: _str(program['name']),
      trackLabel: _str(program['track_label']),
      unitLabel: _str(program['unit_label']),
      newAmount: (pace['new_amount'] as num?) ?? 0,
      nearPortions: _int(pace['near_portions']),
      farPages: _int(pace['far_pages']),
      position: position == null ? null : _str(position['title']),
      finished: json['finished'] == true,
      frozen: json['frozen'] as String?,
      frozenLabel: json['frozen_label'] as String?,
      progress: ((json['progress'] as num?)?.toDouble() ?? 0).clamp(0, 1).toDouble(),
      portion: portion == null ? null : Portion.fromJson(portion),
    );
  }

  final String id;
  final String? enrollmentId;
  final String? groupId;
  final String programName;
  final String trackLabel;

  /// The program's unit (verses, lines, a page…), in the reader's language.
  final String unitLabel;
  final num newAmount;
  final int nearPortions;
  final int farPages;

  /// Where the next new portion starts ("الملك ١١"); null once the program is complete.
  final String? position;
  final bool finished;

  /// paused or ended: the plan waits where it is.
  final String? frozen;
  final String? frozenLabel;

  /// 0 to 1.
  final double progress;
  final Portion? portion;

  bool get isGroup => groupId != null;

  /// A whole percent, never 100 before the plan is complete.
  int get percent => finished ? 100 : (progress * 100).floor().clamp(0, 99);
}

/// Mistakes of one kind in a part, as recorded.
class MistakeCount {
  const MistakeCount({required this.kind, required this.label, required this.count});

  factory MistakeCount.fromJson(Map<String, dynamic> json) =>
      MistakeCount(kind: _str(json['kind']), label: _str(json['label']), count: _int(json['count']));

  final String kind;
  final String label;
  final int count;
}

/// How a part of a lesson went: its passages, the grade, the mistakes by kind (none in the kids' look), and for the
/// teacher the grade the mistakes gave and why another was chosen.
class PartResult {
  const PartResult({
    required this.key,
    required this.label,
    required this.titles,
    required this.grade,
    required this.gradeLabel,
    this.mistakes = const [],
    this.computed,
    this.note,
  });

  factory PartResult.fromJson(String key, Map<String, dynamic> json) => PartResult(
    key: key,
    label: _str(json['label']),
    titles: [
      for (final r in _list(json['ranges'])) _str(r['title']),
      if (_map(json['lesson']) case final lesson?) _str(lesson['title']),
    ].join('، '),
    grade: _str(json['grade']),
    gradeLabel: _str(json['grade_label']),
    mistakes: [for (final m in _list(json['mistakes'])) MistakeCount.fromJson(m)],
    computed: json['computed'] as String?,
    note: json['note'] as String?,
  );

  final String key;
  final String label;
  final String titles;
  final String grade;
  final String gradeLabel;
  final List<MistakeCount> mistakes;
  final String? computed;
  final String? note;

  /// "غلط 2 · تردد 1", the kinds that happened.
  String get mistakesLine => [
    for (final m in mistakes)
      if (m.count > 0) '${m.label} ${m.count}',
  ].join(' · ');
}

/// A lesson's result for one student: each part, whether the plan moved past the new part, and when.
class LessonResult {
  const LessonResult({
    required this.id,
    required this.sessionId,
    required this.studentId,
    required this.parts,
    required this.advanced,
    required this.frozen,
    this.recordedAt,
  });

  factory LessonResult.fromJson(Map<String, dynamic> json) {
    final raw = json['parts'];
    final parts = <PartResult>[];
    if (raw is Map<String, dynamic>) {
      for (final key in [...portionParts, ...raw.keys.where((k) => !portionParts.contains(k))]) {
        if (_map(raw[key]) case final part?) parts.add(PartResult.fromJson(key, part));
      }
    }
    return LessonResult(
      id: _str(json['id']),
      sessionId: _str(json['session_id']),
      studentId: _str(json['student_id']),
      parts: parts,
      advanced: json['advanced'] == true,
      frozen: json['frozen'] == true,
      recordedAt: LocalTime.fromJson(json['recorded_at']),
    );
  }

  final String id;
  final String sessionId;
  final String studentId;
  final List<PartResult> parts;
  final bool advanced;
  final bool frozen;
  final LocalTime? recordedAt;
}

/// A student in a lesson.
class LessonStudent {
  const LessonStudent({required this.id, required this.name});

  final String id;
  final String name;
}

/// `GET quran/lessons/{session}/portion`, for the lesson's teacher: the plan with today's portion (null when the
/// course has no Quran plan), the academy's mistake types and grades, and what is already recorded.
class LessonPortion {
  const LessonPortion({
    required this.sessionId,
    required this.group,
    required this.students,
    required this.mistakeTypes,
    required this.grades,
    required this.results,
    this.plan,
  });

  factory LessonPortion.fromJson(Map<String, dynamic> json) => LessonPortion(
    sessionId: _str(json['session_id']),
    group: json['group'] == true,
    students: [for (final s in _list(json['students'])) LessonStudent(id: _str(s['id']), name: _str(s['name']))],
    plan: _map(json['plan']) == null ? null : QuranPlan.fromJson(_map(json['plan'])!),
    mistakeTypes: QuranChoice.listOf(json['mistake_types']),
    grades: QuranChoice.listOf(json['grades']),
    results: [for (final r in _list(json['results'])) LessonResult.fromJson(r)],
  );

  final String sessionId;
  final bool group;
  final List<LessonStudent> students;
  final QuranPlan? plan;
  final List<QuranChoice> mistakeTypes;
  final List<QuranChoice> grades;
  final List<LessonResult> results;

  LessonResult? resultOf(String studentId) => results.where((r) => r.studentId == studentId).firstOrNull;
}

/// What the teacher records for one part: the mistakes by kind when counted here (else the words marked in the
/// mushaf count), and their own grade with a note when it differs from the mistakes'.
class PartScore {
  PartScore({this.recited = true});

  bool recited;
  final Map<String, int> mistakes = {};

  /// A counter was touched: the counts here are the record, zeros included.
  bool counted = false;
  String? grade;
  String note = '';

  void add(String kind, int by) {
    counted = true;
    mistakes[kind] = ((mistakes[kind] ?? 0) + by).clamp(0, 200);
  }

  Map<String, Object?> toJson() => {
    if (counted)
      'mistakes': [
        for (final e in mistakes.entries) {'kind': e.key, 'count': e.value},
      ],
    'grade': ?grade,
    if (grade != null && note.trim().isNotEmpty) 'note': note.trim(),
  };
}

/// What `POST quran/lessons/{session}/result` answers: the results saved and the plan as it stands now.
class LessonRecord {
  const LessonRecord({required this.results, this.plan});

  factory LessonRecord.fromJson(Map<String, dynamic> json) => LessonRecord(
    results: [for (final r in _list(json['results'])) LessonResult.fromJson(r)],
    plan: _map(json['plan']) == null ? null : QuranPlan.fromJson(_map(json['plan'])!),
  );

  final List<LessonResult> results;
  final QuranPlan? plan;

  bool get advanced => results.any((r) => r.advanced);
}

/// `GET quran/students/{enrollment}/plan`, for the course's teacher: the plan (null while it has none), the last
/// lessons' results with their notes, and the academy's grades and mistake types.
class StudentPlan {
  const StudentPlan({required this.enrollmentId, required this.recent, this.plan});

  factory StudentPlan.fromJson(Map<String, dynamic> json) => StudentPlan(
    enrollmentId: _str(json['enrollment_id']),
    plan: _map(json['plan']) == null ? null : QuranPlan.fromJson(_map(json['plan'])!),
    recent: [for (final r in _list(json['recent'])) LessonResult.fromJson(r)],
  );

  final String enrollmentId;
  final QuranPlan? plan;
  final List<LessonResult> recent;
}

/// A Quran course of the learner's, with its plan and the last lessons' grades.
class QuranCourse {
  const QuranCourse({required this.enrollmentId, required this.subject, required this.plan, required this.recent});

  factory QuranCourse.fromJson(Map<String, dynamic> json) => QuranCourse(
    enrollmentId: _str(json['enrollment_id']),
    subject: _str(json['subject']),
    plan: QuranPlan.fromJson(_map(json['plan']) ?? const {}),
    recent: [for (final r in _list(json['recent'])) LessonResult.fromJson(r)],
  );

  final String enrollmentId;
  final String subject;
  final QuranPlan plan;
  final List<LessonResult> recent;
}

/// `GET quran/plan`: each Quran course of the learner that has a plan.
class QuranPlans {
  const QuranPlans({required this.learnerId, required this.courses});

  factory QuranPlans.fromJson(Map<String, dynamic> json) =>
      QuranPlans(learnerId: _str(json['learner_id']), courses: [for (final c in _list(json['courses'])) QuranCourse.fromJson(c)]);

  final String learnerId;
  final List<QuranCourse> courses;
}

/// An exam's result: the grade, whether it passed, the mistakes by kind (none in the kids' look), the examiner's
/// words and the day to try again.
class ExamResult {
  const ExamResult({
    required this.grade,
    required this.gradeLabel,
    required this.passed,
    this.mistakes = const [],
    this.note,
    this.retakeOn,
    this.recordedAt,
  });

  factory ExamResult.fromJson(Map<String, dynamic> json) => ExamResult(
    grade: _str(json['grade']),
    gradeLabel: _str(json['grade_label']),
    passed: json['passed'] == true,
    mistakes: [for (final m in _list(json['mistakes'])) MistakeCount.fromJson(m)],
    note: json['note'] as String?,
    retakeOn: json['retake_on'] as String?,
    recordedAt: LocalTime.fromJson(json['recorded_at']),
  );

  final String grade;
  final String gradeLabel;
  final bool passed;
  final List<MistakeCount> mistakes;
  final String? note;

  /// `2099-10-20`, a day on the academy's calendar.
  final String? retakeOn;
  final LocalTime? recordedAt;
}

/// An exam at a stop (the end of a surah or a part, the program, a khatma): waiting to be booked, booked with its
/// lesson and examiner, passed or failed.
class QuranExam {
  const QuranExam({
    required this.id,
    required this.programName,
    required this.stopLabel,
    required this.status,
    required this.statusLabel,
    this.studentName,
    this.retake = false,
    this.notBefore,
    this.examiner,
    this.sessionId,
    this.startsAt,
    this.result,
  });

  factory QuranExam.fromJson(Map<String, dynamic> json) {
    final lesson = _map(json['lesson']);
    return QuranExam(
      id: _str(json['id']),
      studentName: json['student'] as String?,
      programName: _str(_map(json['program'])?['name']),
      stopLabel: _str(_map(json['stop'])?['label']),
      status: _str(json['status']),
      statusLabel: _str(json['status_label']),
      retake: json['retake'] == true,
      notBefore: json['not_before'] as String?,
      examiner: _map(json['examiner'])?['name'] as String?,
      sessionId: lesson?['session_id'] as String?,
      startsAt: LocalTime.fromJson(lesson?['starts_at']),
      result: _map(json['result']) == null ? null : ExamResult.fromJson(_map(json['result'])!),
    );
  }

  final String id;

  /// For the examiner; families read none.
  final String? studentName;
  final String programName;
  final String stopLabel;

  /// suggested, booked, passed, failed or cancelled.
  final String status;
  final String statusLabel;
  final bool retake;
  final String? notBefore;
  final String? examiner;
  final String? sessionId;
  final LocalTime? startsAt;
  final ExamResult? result;

  bool get passed => status == 'passed';
  bool get failed => status == 'failed';
  bool get booked => status == 'booked';
}

/// `GET quran/examiner/exams`: the exams booked with the examiner (soonest first), the last results they recorded,
/// and the academy's mistake types and grades.
class ExaminerBoard {
  const ExaminerBoard({required this.booked, required this.recent, required this.mistakeTypes, required this.grades});

  factory ExaminerBoard.fromJson(Map<String, dynamic> json) => ExaminerBoard(
    booked: [for (final e in _list(json['booked'])) QuranExam.fromJson(e)],
    recent: [for (final e in _list(json['recent'])) QuranExam.fromJson(e)],
    mistakeTypes: QuranChoice.listOf(json['mistake_types']),
    grades: QuranChoice.listOf(json['grades']),
  );

  final List<QuranExam> booked;
  final List<QuranExam> recent;
  final List<QuranChoice> mistakeTypes;
  final List<QuranChoice> grades;

  bool get isEmpty => booked.isEmpty && recent.isEmpty;
}

/// A certificate: its title, the portion, the grade and the day, and while it stands its page (signed for this
/// reader) and its verification code.
class QuranCertificate {
  const QuranCertificate({
    required this.id,
    required this.number,
    required this.title,
    required this.portion,
    required this.riwaya,
    required this.grade,
    required this.gradeLabel,
    required this.revoked,
    this.examiner,
    this.issuedAt,
    this.url,
    this.code,
  });

  factory QuranCertificate.fromJson(Map<String, dynamic> json) => QuranCertificate(
    id: _str(json['id']),
    number: _str(json['number']),
    title: _str(json['title']),
    portion: _str(_map(json['portion'])?['label']),
    riwaya: _str(json['riwaya']),
    grade: _str(json['grade']),
    gradeLabel: _str(json['grade_label']),
    examiner: json['examiner'] as String?,
    issuedAt: LocalTime.fromJson(json['issued_at']),
    revoked: json['revoked'] == true,
    url: json['url'] as String?,
    code: json['code'] as String?,
  );

  final String id;
  final String number;
  final String title;
  final String portion;
  final String riwaya;
  final String grade;
  final String gradeLabel;
  final String? examiner;
  final LocalTime? issuedAt;
  final bool revoked;
  final String? url;
  final String? code;

  bool get opens => !revoked && (url?.isNotEmpty ?? false);
}

/// `GET quran/certificates`: none while the academy keeps them from families.
class QuranCertificates {
  const QuranCertificates({required this.shown, required this.list});

  factory QuranCertificates.fromJson(Map<String, dynamic> json) {
    final raw = json['certificates'];
    final items = raw is Map<String, dynamic> ? raw.values.toList() : raw;
    return QuranCertificates(shown: json['shown'] == true, list: [for (final c in _list(items)) QuranCertificate.fromJson(c)]);
  }

  final bool shown;
  final List<QuranCertificate> list;
}
