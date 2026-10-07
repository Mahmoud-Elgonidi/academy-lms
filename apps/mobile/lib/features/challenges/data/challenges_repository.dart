import '../../../core/network/api_client.dart';
import '../domain/challenges.dart';

/// The game layer: a learner's challenges, collecting a reward, and cheers.
class ChallengesRepository {
  ChallengesRepository(this._api);

  final ApiClient _api;

  /// The student's own board ([learnerId] null) or a guardian's child's.
  Future<ChallengeBoard> board({String? learnerId}) async =>
      ChallengeBoard.fromJson((await _api.get('learning/challenges', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  /// Collects a finished challenge, once (the key is kept across retries).
  Future<ClaimResult> claim(Challenge challenge) async => ClaimResult.fromJson(
    (await _api.post('learning/challenges/claim', body: {'challenge': challenge.key, 'period': challenge.period}, idempotent: true))['data']
        as Map<String, dynamic>,
  );

  /// A guardian cheers a learner on: clap, star, heart or fire.
  Future<void> cheer(String learnerId, String kind) => _api.post('learning/cheers', body: {'learner_id': learnerId, 'kind': kind});

  /// The student saw their cheers.
  Future<void> seen() => _api.post('learning/cheers/seen');
}
