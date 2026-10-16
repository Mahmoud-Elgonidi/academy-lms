import '../../../core/network/api_client.dart';
import '../../../core/network/api_problem.dart';
import '../domain/mushaf.dart';

/// A Quran lesson's shared mushaf (its teacher turns the pages and marks
/// words; its student and their guardians follow) and the mistakes
/// notebook: a learner's (the student themself, or a guardian's child by
/// learnerId) and a student's for their teacher.
class MushafRepository {
  MushafRepository(this._api, {DateTime Function()? clock}) : _clock = clock ?? DateTime.now;

  final ApiClient _api;
  final DateTime Function() _clock;

  /// Whether a lesson has a mushaf, as last asked: a lesson card asks once in a while, not each time it is drawn.
  final _known = <String, ({DateTime at, Future<bool> has})>{};

  static const _remember = Duration(minutes: 10);

  /// The page asked for, else the one the teacher has open, else where the lesson starts.
  Future<LessonMushaf> page(String sessionId, {int? page}) async =>
      LessonMushaf.fromJson((await _api.get('learning/lessons/$sessionId/mushaf', query: {'page': ?page}))['data'] as Map<String, dynamic>);

  /// The teacher turns the lesson's mushaf to [page]: the student's screen follows.
  Future<LessonMushaf> turn(String sessionId, int page) async =>
      LessonMushaf.fromJson((await _api.put('learning/lessons/$sessionId/mushaf', body: {'page': page}))['data'] as Map<String, dynamic>);

  /// The teacher marks a word (marking it again changes what it was); the answer is [page], else the word's.
  Future<LessonMushaf> mark(String sessionId, WordAt at, MistakeKind kind, {int? page}) async => LessonMushaf.fromJson(
    (await _api.post(
          'learning/lessons/$sessionId/mistakes',
          body: {'surah': at.surah, 'verse': at.verse, 'word': at.word, 'kind': kind.name, 'page': ?page},
        ))['data']
        as Map<String, dynamic>,
  );

  /// The teacher takes a word's mark off.
  Future<LessonMushaf> unmark(String sessionId, WordAt at, {int? page}) async => LessonMushaf.fromJson(
    (await _api.delete(
          'learning/lessons/$sessionId/mistakes',
          body: {'surah': at.surah, 'verse': at.verse, 'word': at.word, 'page': ?page},
        ))['data']
        as Map<String, dynamic>,
  );

  /// Whether the lesson has a mushaf for me (a Quran lesson of a course the hifz map belongs to, not cancelled).
  /// A network that is down says no for now, and asks again next time.
  Future<bool> has(String sessionId) {
    final now = _clock();
    final known = _known[sessionId];
    if (known != null && now.difference(known.at) < _remember) return known.has;
    late final Future<bool> asked;
    asked = page(sessionId).then<bool>(
      (_) => true,
      onError: (Object e) {
        if (e is ApiProblem && (e.isNotFound || e.status == 403)) return false;
        if (identical(_known[sessionId]?.has, asked)) _known.remove(sessionId);
        return false;
      },
    );
    _known[sessionId] = (at: now, has: asked);
    return asked;
  }

  /// A learner's notebook: the words their teachers marked in the last six months.
  Future<MistakesBook> book({String? learnerId}) async =>
      MistakesBook.fromJson((await _api.get('learning/mistakes', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  /// A student's notebook, for their teacher.
  Future<MistakesBook> student(String enrollmentId) async =>
      MistakesBook.fromJson((await _api.get('teacher/students/$enrollmentId/mistakes'))['data'] as Map<String, dynamic>);
}
