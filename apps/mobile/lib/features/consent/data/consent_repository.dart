import '../../../core/network/api_client.dart';
import '../domain/consent.dart';

/// A guardian's consent to their children's accounts: the same as "Children's
/// privacy" in /my and the portal. The API checks each one again (only the
/// child's own guardians).
class ConsentRepository {
  ConsentRepository(this._api);

  final ApiClient _api;

  Future<Consents> load() async => Consents.fromJson(await _api.get('family/consents'));

  /// "I agree", to the wording ([version]) the guardian read.
  Future<void> grant(String studentId, int version) => _api.post('family/consents/$studentId', body: {'agree': true, 'version': version});

  /// Takes it back: the child is signed out everywhere and waits again.
  Future<void> withdraw(String studentId) => _api.post('family/consents/$studentId/withdraw');
}
