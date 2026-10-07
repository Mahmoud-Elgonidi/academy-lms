import '../../../core/network/api_client.dart';
import '../domain/journey.dart';

/// Who the family follows, and a student's own journey.
class FamilyRepository {
  FamilyRepository(this._api);

  final ApiClient _api;

  Future<List<Learner>> learners() async => [
    for (final l in (await _api.get('family/learners'))['data'] as List<dynamic>) Learner.fromJson(l as Map<String, dynamic>),
  ];

  Future<Journey> journey({String? learnerId}) async =>
      Journey.fromJson((await _api.get('learning/journey', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);
}
