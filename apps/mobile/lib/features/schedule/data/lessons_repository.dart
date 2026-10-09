import '../../../core/network/api_client.dart';
import '../domain/lesson.dart';

/// A learner's lessons and what the family may do with one. Cancelling,
/// turning down a substitute and disputing carry an `Idempotency-Key`, so a
/// retry on a bad network never does it twice.
class LessonsRepository {
  LessonsRepository(this._api);

  final ApiClient _api;

  Future<LessonList> family({String? learnerId, bool past = false}) async =>
      LessonList.fromJson(await _api.get('family/lessons', query: {'learner_id': ?learnerId, if (past) 'when': 'past'}));

  Future<CancelPreview> cancellation(String lessonId) async => CancelPreview.fromJson(await _api.get('lessons/$lessonId/cancellation'));

  Future<Lesson> cancel(String lessonId, {required String reason, String? note}) async => Lesson.fromJson(
    (await _api.post(
          'lessons/$lessonId/cancel',
          idempotent: true,
          body: {'reason': reason, if (note != null && note.trim().isNotEmpty) 'note': note.trim()},
        ))['data']
        as Map<String, dynamic>,
  );

  /// Returns the days the makeup credit stays valid.
  Future<int> refuseSubstitute(String lessonId) async {
    final json = await _api.post('lessons/$lessonId/refuse-substitute', idempotent: true);
    return (json['makeup_days'] as num?)?.toInt() ?? 0;
  }

  /// Disputes what the lesson was recorded as; the lesson comes back under review, with the dispute.
  Future<Lesson> dispute(String lessonId, {required String reason, required String note}) async => Lesson.fromJson(
    (await _api.post('lessons/$lessonId/dispute', idempotent: true, body: {'reason': reason, 'note': note.trim()}))['data']
        as Map<String, dynamic>,
  );

  Future<Rating> rating(String lessonId) async => Rating.fromJson(await _api.get('lessons/$lessonId/rating'));

  Future<Rating> rate(String lessonId, {required int stars, List<String> tags = const [], String? comment}) async => Rating.fromJson(
    await _api.put(
      'lessons/$lessonId/rating',
      body: {'stars': stars, 'tags': tags, if (comment != null && comment.trim().isNotEmpty) 'comment': comment.trim()},
    ),
  );
}
