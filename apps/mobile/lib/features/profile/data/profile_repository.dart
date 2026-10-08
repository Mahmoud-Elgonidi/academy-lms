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

class ProfileRepository {
  ProfileRepository(this._api);

  final ApiClient _api;

  Future<List<DeviceSession>> devices() async => [
    for (final d in (await _api.get('me/sessions'))['data'] as List<dynamic>) DeviceSession.fromJson(d as Map<String, dynamic>),
  ];

  Future<void> signOutDevice(int id) => _api.delete('me/sessions/$id');

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
