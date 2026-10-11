import '../../../core/network/api_client.dart';
import '../domain/challenges.dart';

/// The kids' sticker album, and cheers: a guardian's, and the student seeing them.
class ChallengesRepository {
  ChallengesRepository(this._api);

  final ApiClient _api;

  /// The student's own sticker album ([learnerId] null) or a guardian's child's, from their journey.
  Future<ChallengeBoard> board({String? learnerId}) async =>
      ChallengeBoard.fromJson((await _api.get('learning/journey/map', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  /// A guardian cheers a learner on: clap, star, heart or fire.
  Future<void> cheer(String learnerId, String kind) => _api.post('learning/cheers', body: {'learner_id': learnerId, 'kind': kind});

  /// The student saw their cheers.
  Future<void> seen() => _api.post('learning/cheers/seen');
}
