import '../../../core/network/api_client.dart';
import '../../../core/network/api_problem.dart';
import '../domain/quran.dart';

/// The Quran program (`quran/…`): a lesson's portion and recording what each
/// student recited, a student's plan and pace for their teacher, the
/// examiner's queue and results, and a learner's plans, exams and
/// certificates (the student themself, or a guardian's child by learnerId).
/// Without the Quran in the academy's plan every call answers 403
/// `feature-not-in-plan`: the ways in ask first ([hasPortion], [follows],
/// [examines]) and simply stay hidden.
class QuranRepository {
  QuranRepository(this._api, {DateTime Function()? clock}) : _clock = clock ?? DateTime.now;

  final ApiClient _api;
  final DateTime Function() _clock;

  /// What a way in asked last, by what it asked about: asked once in a while, not each time it is drawn.
  final _known = <String, ({DateTime at, Future<bool> answer})>{};

  static const _remember = Duration(minutes: 10);

  /// A lesson's plan and today's portion, for its teacher.
  Future<LessonPortion> portion(String sessionId) async =>
      LessonPortion.fromJson((await _api.get('quran/lessons/$sessionId/portion'))['data'] as Map<String, dynamic>);

  /// Records what [parts] (new, near, far) a student recited, with mistakes and grades; once per lesson and student.
  /// A group's lesson names the student. The key is kept across retries.
  Future<LessonRecord> record(String sessionId, {required Map<String, Object?> parts, String? studentId, bool group = false}) async {
    final body = group
        ? {
            'students': [
              {'student_id': studentId, 'parts': parts},
            ],
          }
        : {'student_id': ?studentId, 'parts': parts};
    final json = await _api.post('quran/lessons/$sessionId/result', body: body, idempotent: true);
    _known.remove('lesson:$sessionId');
    return LessonRecord.fromJson(json['data'] as Map<String, dynamic>);
  }

  /// A student's plan, its next portion and the last lessons' results, for the course's teacher.
  Future<StudentPlan> studentPlan(String enrollmentId) async =>
      StudentPlan.fromJson((await _api.get('quran/students/$enrollmentId/plan'))['data'] as Map<String, dynamic>);

  /// The student's own pace (null goes back to the program's); a group's is the office's.
  Future<QuranPlan> setPace(String enrollmentId, {num? newAmount, int? nearPortions, int? farPages}) async {
    final json = await _api.patch(
      'quran/students/$enrollmentId/plan',
      body: {'new_amount': newAmount, 'near_portions': nearPortions, 'far_pages': farPages},
    );
    return QuranPlan.fromJson((json['data'] as Map<String, dynamic>)['plan'] as Map<String, dynamic>);
  }

  /// The exams booked with the person signed in as examiner, and the last results they recorded.
  Future<ExaminerBoard> examiner() async =>
      ExaminerBoard.fromJson((await _api.get('quran/examiner/exams'))['data'] as Map<String, dynamic>);

  /// The examiner's result: mistakes by kind (else the words marked in the lesson's mushaf), their own grade with
  /// a note, and for a fail what to work on and the retake day (`2099-10-20`). The key is kept across retries.
  Future<QuranExam> recordExam(String examId, {Map<String, int>? mistakes, String? grade, String? note, String? retakeOn}) async {
    final json = await _api.post(
      'quran/exams/$examId/result',
      body: {
        if (mistakes != null)
          'mistakes': [
            for (final e in mistakes.entries) {'kind': e.key, 'count': e.value},
          ],
        'grade': ?grade,
        'note': ?note,
        'retake_on': ?retakeOn,
      },
      idempotent: true,
    );
    _known.remove('examiner');
    return QuranExam.fromJson(json['data'] as Map<String, dynamic>);
  }

  /// A learner's Quran courses with a plan.
  Future<QuranPlans> plans({String? learnerId}) async =>
      QuranPlans.fromJson((await _api.get('quran/plan', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  /// A learner's exams, newest first.
  Future<List<QuranExam>> exams({String? learnerId}) async {
    final data = (await _api.get('quran/exams', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>;
    return [
      for (final e in data['exams'] as List<dynamic>? ?? const [])
        if (e is Map<String, dynamic>) QuranExam.fromJson(e),
    ];
  }

  /// A learner's certificates, while the academy shows them to families.
  Future<QuranCertificates> certificates({String? learnerId}) async =>
      QuranCertificates.fromJson((await _api.get('quran/certificates', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  /// Whether the lesson follows a Quran plan (its teacher's way to the portion).
  Future<bool> hasPortion(String sessionId) => _ask('lesson:$sessionId', () async => (await portion(sessionId)).plan != null);

  /// Whether the learner has a Quran course with a plan (the journey's way to the Quran screen).
  Future<bool> follows({String? learnerId}) =>
      _ask('learner:${learnerId ?? 'self'}', () async => (await plans(learnerId: learnerId)).courses.isNotEmpty);

  /// Whether the person signed in examines: anything booked with them or recorded by them.
  Future<bool> examines() => _ask('examiner', () async => !(await examiner()).isEmpty);

  /// Asks once in a while; the Quran left out of the plan (403), or nothing there, says no. A network that is down
  /// says no for now, and asks again next time.
  Future<bool> _ask(String key, Future<bool> Function() ask) {
    final now = _clock();
    final known = _known[key];
    if (known != null && now.difference(known.at) < _remember) return known.answer;
    late final Future<bool> asked;
    asked = ask().then<bool>(
      (yes) => yes,
      onError: (Object e) {
        if (e is ApiProblem && (e.isNotFound || e.status == 403)) return false;
        if (identical(_known[key]?.answer, asked)) _known.remove(key);
        return false;
      },
    );
    _known[key] = (at: now, answer: asked);
    return asked;
  }
}
