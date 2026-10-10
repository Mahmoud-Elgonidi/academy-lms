import '../../../core/network/api_client.dart';
import '../../../core/social/social_sign_in.dart';
import '../../../core/time/local_time.dart';

/// A device signed in to this account (`GET me/sessions`).
class DeviceSession {
  const DeviceSession({required this.id, required this.device, required this.current, this.lastUsedAt});

  factory DeviceSession.fromJson(Map<String, dynamic> json) => DeviceSession(
    id: (json['id'] as num).toInt(),
    device: json['device'] as String? ?? '',
    current: json['current'] == true,
    lastUsedAt: LocalTime.fromJson(json['last_used_at']),
  );

  final int id;
  final String device;
  final bool current;
  final LocalTime? lastUsedAt;
}

/// Google or Apple on this account (`GET me/social`): linked as which email, and when it last signed in.
class SignInMethod {
  const SignInMethod({required this.provider, required this.label, required this.linked, this.email, this.lastUsedAt});

  factory SignInMethod.fromJson(Map<String, dynamic> json) => SignInMethod(
    provider: json['provider'] as String,
    label: json['label'] as String? ?? json['provider'] as String,
    linked: json['linked'] == true,
    email: json['email'] as String?,
    lastUsedAt: LocalTime.fromJson(json['last_used_at']),
  );

  final String provider;
  final String label;
  final bool linked;
  final String? email;
  final LocalTime? lastUsedAt;
}

/// The account's email and mobile number (`GET me`), and whether the person may change them themselves.
class ContactDetails {
  const ContactDetails({required this.canChange, this.email, this.phone});

  factory ContactDetails.fromJson(Map<String, dynamic> json) =>
      ContactDetails(email: json['email'] as String?, phone: json['phone'] as String?, canChange: json['can_change_contact'] == true);

  final String? email;
  final String? phone;

  /// Not a child the academy runs under a guardian, and not the platform team signed in as someone.
  final bool canChange;
}

/// A code sent to a new address: the challenge to confirm, where it went (masked), and until when it works.
class ContactCode {
  const ContactCode({required this.challengeId, required this.sentTo, this.expiresAt});

  factory ContactCode.fromJson(Map<String, dynamic> json) => ContactCode(
    challengeId: json['challenge_id'] as String,
    sentTo: json['sent_to'] as String? ?? '',
    expiresAt: LocalTime.fromJson(json['expires_at']),
  );

  final String challengeId;
  final String sentTo;
  final LocalTime? expiresAt;
}

class ProfileRepository {
  ProfileRepository(this._api);

  final ApiClient _api;

  Future<List<DeviceSession>> devices() async => [
    for (final d in (await _api.get('me/sessions'))['data'] as List<dynamic>) DeviceSession.fromJson(d as Map<String, dynamic>),
  ];

  Future<void> signOutDevice(int id) => _api.delete('me/sessions/$id');

  Future<ContactDetails> contact() async => ContactDetails.fromJson((await _api.get('me'))['data'] as Map<String, dynamic>);

  /// Sends a code to a new email or mobile number ([kind] email or phone); nothing changes until it comes back.
  Future<ContactCode> sendContactCode(String kind, String value) async =>
      ContactCode.fromJson(await _api.post('me/contact', body: {'kind': kind, 'value': value.trim()}));

  /// Confirms the new address with its code: it becomes the account's way in and where messages go.
  Future<ContactDetails> confirmContact(String challengeId, String code) async => ContactDetails.fromJson(
    (await _api.post('me/contact/confirm', body: {'challenge_id': challengeId, 'code': code.trim()}))['data'] as Map<String, dynamic>,
  );

  Future<List<SignInMethod>> signInMethods() async => _methods(await _api.get('me/social'));

  /// Links the phone's Google or Apple account to this one.
  Future<List<SignInMethod>> link(SocialCredential credential) async => _methods(
    await _api.post('me/social', body: {'provider': credential.provider, 'id_token': credential.idToken, 'nonce': ?credential.nonce}),
  );

  /// Takes it off (the API refuses when it is the only way in).
  Future<List<SignInMethod>> unlink(String provider) async => _methods(await _api.delete('me/social/$provider'));

  static List<SignInMethod> _methods(dynamic json) => [
    for (final m in (json as Map<String, dynamic>)['data'] as List<dynamic>) SignInMethod.fromJson(m as Map<String, dynamic>),
  ];
}
