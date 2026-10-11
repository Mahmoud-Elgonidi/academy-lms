import '../../../core/network/api_client.dart';
import '../domain/learner.dart';

/// Who the family follows: the person themself, or a guardian's children.
class FamilyRepository {
  FamilyRepository(this._api);

  final ApiClient _api;

  Future<List<Learner>> learners() async => [
    for (final l in (await _api.get('family/learners'))['data'] as List<dynamic>) Learner.fromJson(l as Map<String, dynamic>),
  ];
}
