import '../../../core/auth/account.dart';
import '../../../core/device.dart';
import '../../../core/network/api_client.dart';
import '../domain/sign_in_models.dart';

/// Signing in: a code to a phone or email that finds the person's accounts
/// in every academy (nothing is said about accounts before the code), or a
/// password in one academy.
class AuthRepository {
  AuthRepository(this._api);

  final ApiClient _api;

  Future<SentCode> sendCode(String contact, {String? channel}) async {
    final json = await _api.post('accounts/code', anonymous: true, body: {'contact': contact, 'channel': ?channel});
    return SentCode.fromJson(json, DateTime.now());
  }

  Future<Discovery> verify(String challengeId, String code) async => Discovery.fromJson(
    await _api.post(
      'accounts/code/verify',
      anonymous: true,
      body: {'challenge_id': challengeId, 'code': code, 'device_name': deviceName()},
    ),
  );

  Future<Account> signInFound(Discovery discovery, FoundAccount account) async => accountFromToken(
    await _api.post(
      'accounts/sign-in',
      anonymous: true,
      academy: account.academySlug,
      body: {'discovery_token': discovery.token, 'user_id': account.userId, 'device_name': deviceName()},
    ),
    found: account,
  );

  Future<Account> signInWithPassword({required String academy, required String login, required String password}) async => accountFromToken(
    await _api.post(
      'auth/login',
      anonymous: true,
      academy: academy,
      body: {'login': login, 'password': password, 'device_name': deviceName()},
    ),
  );
}
