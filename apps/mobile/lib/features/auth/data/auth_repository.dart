import '../../../core/auth/account.dart';
import '../../../core/device.dart';
import '../../../core/network/api_client.dart';
import '../../../core/social/social_sign_in.dart';
import '../domain/sign_in_models.dart';

/// Signing in: a code to a phone or email, or the phone's Google or Apple
/// sign-in, that finds the person's accounts in every academy (nothing is
/// said about accounts before that), or a password in one academy.
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

  /// The accounts linked to this Google or Apple account, or carrying the email it verified, in every academy.
  Future<Discovery> discoverWith(SocialCredential credential) async => Discovery.fromJson(
    await _api.post(
      'accounts/social',
      anonymous: true,
      body: {'provider': credential.provider, 'id_token': credential.idToken, 'nonce': ?credential.nonce},
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

  /// The platform's own privacy notice, terms or account-deletion page ([page]: `privacy`, `terms`,
  /// `account-deletion`) in [lang], on the API's host: before signing in no academy is chosen yet (the code finds
  /// accounts in every academy), so these are the platform's; signed in, "My data" links the academy's own.
  Uri legalPage(String page, String lang) {
    final api = Uri.parse(_api.dio.options.baseUrl);
    return Uri(scheme: api.scheme, host: api.host, port: api.hasPort ? api.port : null, path: '/$page', queryParameters: {'lang': lang});
  }

  /// The second step: the code the authenticator app shows now, or a recovery code.
  Future<Account> completeTwoFactor(TwoFactorStep step, String code) async => accountFromToken(
    await _api.post(
      'auth/two-factor',
      anonymous: true,
      academy: step.academySlug,
      body: {'two_factor_token': step.token, 'code': code, 'device_name': deviceName()},
    ),
    found: step.found,
  );
}
