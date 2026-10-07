import '../network/api_client.dart';

/// The person behind the open account, as `GET /me` gives it: the clock of
/// their profile and whether the phone's differs.
class Me {
  const Me({required this.name, required this.timezone, required this.displayTimezone, required this.mismatch});

  factory Me.fromJson(Map<String, dynamic> json) => Me(
    name: json['name'] as String? ?? '',
    timezone: json['timezone'] as String?,
    displayTimezone: json['display_timezone'] as String? ?? 'UTC',
    mismatch: json['timezone_mismatch'] == true,
  );

  final String name;
  final String? timezone;
  final String displayTimezone;
  final bool mismatch;
}

/// What the session needs from the API besides signing in: the academy's
/// look, the person's profile and, for a family account, the look of their
/// own area.
class SessionRepository {
  SessionRepository(this._api);

  final ApiClient _api;

  /// Public: works before and after sign-in.
  Future<Map<String, dynamic>> branding(String academy) async =>
      (await _api.get('branding', academy: academy, anonymous: true))['data'] as Map<String, dynamic>;

  Future<Me> me() async => Me.fromJson((await _api.get('me'))['data'] as Map<String, dynamic>);

  Future<void> updateMe({String? locale, String? timezone}) => _api.patch('me', body: {'locale': ?locale, 'timezone': ?timezone});

  /// kids, middle, teen or classic.
  Future<String> viewerStage() async => (await _api.get('family/learners'))['viewer_stage'] as String? ?? 'classic';

  /// Best effort: the account leaves this device even when the call fails.
  Future<void> signOut({required String academy, required String token}) async {
    try {
      await _api.post('auth/logout', academy: academy, token: token);
    } on Exception {
      // Offline or already ended: the token is dropped here either way.
    }
  }
}
