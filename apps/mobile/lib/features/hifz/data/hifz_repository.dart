import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../homework/data/homework_repository.dart';
import '../domain/hifz.dart';

/// The hifz map: a learner's (the student themself, or a guardian's child by
/// [learnerId]) with today's review, a page's text, reciting a page to be
/// checked or saying how it went; and a teacher's view of a student's map,
/// saying what they already know or taking pages off.
class HifzRepository {
  HifzRepository(this._api);

  final ApiClient _api;

  Future<HifzBoard> board({String? learnerId}) async =>
      HifzBoard.fromJson((await _api.get('learning/hifz', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  Future<QuranPage> page(int page, {String? learnerId}) async =>
      QuranPage.fromJson((await _api.get('learning/hifz/pages/$page', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  /// How the page went (1 forgot, 2 hard, 3 good, 4 easy): today's review of it. The key is kept across retries.
  Future<QuranPage> grade(int page, int grade, {String? learnerId}) async => QuranPage.fromJson(
    (await _api.post('learning/hifz/pages/$page/grade', body: {'grade': grade, 'learner_id': ?learnerId}, idempotent: true))['data']
        as Map<String, dynamic>,
  );

  /// Sends a page recited from memory; it is heard in the background (pending until the hifz signal says it is done).
  /// A long recording takes a while to go up, so it waits up to a minute.
  Future<HifzRecitation> recite(int page, HandInFile audio, {String? learnerId}) async => HifzRecitation.fromJson(
    (await _api.post(
          'learning/hifz/pages/$page/recitations',
          body: FormData.fromMap({
            'audio': MultipartFile.fromBytes(audio.bytes, filename: audio.name, contentType: DioMediaType.parse(audio.mime)),
            'learner_id': ?learnerId,
          }),
          timeout: const Duration(seconds: 60),
        ))['data']
        as Map<String, dynamic>,
  );

  Future<HifzRecitation> recitation(String id, {String? learnerId}) async => HifzRecitation.fromJson(
    (await _api.get('learning/hifz/recitations/$id', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>,
  );

  /// A student's map for their teacher.
  Future<StudentHifz> student(String enrollmentId) async =>
      StudentHifz.fromJson((await _api.get('teacher/students/$enrollmentId/hifz'))['data'] as Map<String, dynamic>);

  /// Says the student already knows these parts, surahs or pages by heart ([strength]: strong, good or weak); how many
  /// pages it marked.
  Future<int> markMemorized(
    String enrollmentId, {
    Set<int> juzs = const {},
    Set<int> surahs = const {},
    Set<int> pages = const {},
    required String strength,
  }) async {
    final json = await _api.post(
      'teacher/students/$enrollmentId/hifz/memorized',
      body: {..._choice(juzs, surahs, pages), 'strength': strength},
    );
    return ((json['data'] as Map<String, dynamic>?)?['marked'] as num?)?.toInt() ?? 0;
  }

  /// Takes pages off the student's map (marked by mistake): they are new again. How many it took off.
  Future<int> clear(String enrollmentId, {Set<int> juzs = const {}, Set<int> surahs = const {}, Set<int> pages = const {}}) async {
    final json = await _api.post('teacher/students/$enrollmentId/hifz/clear', body: _choice(juzs, surahs, pages));
    return ((json['data'] as Map<String, dynamic>?)?['cleared'] as num?)?.toInt() ?? 0;
  }

  static Map<String, Object> _choice(Set<int> juzs, Set<int> surahs, Set<int> pages) => {
    if (juzs.isNotEmpty) 'juz': (juzs.toList()..sort()),
    if (surahs.isNotEmpty) 'surahs': (surahs.toList()..sort()),
    if (pages.isNotEmpty) 'pages': (pages.toList()..sort()),
  };
}
