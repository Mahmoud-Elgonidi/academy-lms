import '../../../core/network/api_client.dart';
import '../domain/learner.dart';

/// Who the family follows: the person themself, or a guardian's children;
/// and a guardian adding a child of their own.
class FamilyRepository {
  FamilyRepository(this._api);

  final ApiClient _api;

  Future<List<Learner>> learners() async => [
    for (final l in (await _api.get('family/learners'))['data'] as List<dynamic>) Learner.fromJson(l as Map<String, dynamic>),
  ];

  /// «إضافة طفل»: a child of the guardian's own, with the consent wording's [version] agreed to at once. Sent once,
  /// whatever the network does.
  Future<AddedChild> addChild({required String name, required int version, String? birthDate, int? birthYear, String? gender}) async {
    final json = await _api.post(
      'family/children',
      idempotent: true,
      body: {'name': name, 'birth_date': ?birthDate, 'birth_year': ?birthYear, 'gender': ?gender, 'agree': true, 'version': version},
    );
    return AddedChild.fromJson(json);
  }
}
