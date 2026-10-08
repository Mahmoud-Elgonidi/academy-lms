import '../../../core/network/api_client.dart';
import '../domain/recordings.dart';

/// Lesson recordings: consent (for oneself or one's child), the recordings to
/// watch, and starting and stopping one in a lesson. The API checks each one
/// again (who may agree for whom, whose recording it is, who runs the lesson).
class RecordingsRepository {
  RecordingsRepository(this._api);

  final ApiClient _api;

  Future<RecordingConsents> consents() async => RecordingConsents.fromJson(await _api.get('recordings/consents'));

  /// "I agree", to the wording ([version]) read.
  Future<void> agree(String personId, int version) => _api.post('recordings/consents/$personId', body: {'agree': true, 'version': version});

  /// Takes it back: a recording running stops, and nothing more is recorded.
  Future<void> withdraw(String personId) => _api.post('recordings/consents/$personId/withdraw');

  Future<RecordingShelf> family(String? learnerId) async =>
      RecordingShelf.fromJson(await _api.get('family/recordings', query: {'learner_id': ?learnerId}));

  Future<RecordingShelf> teacher() async => RecordingShelf.fromJson(await _api.get('teacher/recordings'));

  /// An address that plays the recording (signed for a while); the opening is logged.
  Future<String> watch(String recordingId) async => (await _api.post('recordings/$recordingId/watch'))['url'] as String;

  /// Starts recording a lesson; whether it runs now.
  Future<bool> start(String lessonId) async =>
      ((await _api.post('lessons/$lessonId/recording'))['data'] as Map<String, dynamic>)['running'] == true;

  Future<void> stop(String lessonId) => _api.post('lessons/$lessonId/recording/stop');

  /// The consents, and the learner's recordings (a family) or the teacher's own.
  Future<RecordingsScreen> screen({required bool teacher, String? learnerId}) async {
    final results = await Future.wait<Object>([consents(), if (teacher) this.teacher() else family(learnerId)]);
    return RecordingsScreen(consents: results[0] as RecordingConsents, shelf: results[1] as RecordingShelf);
  }
}
