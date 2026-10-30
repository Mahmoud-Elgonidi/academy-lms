import '../network/api_client.dart';
import '../time/clock_style.dart';

/// The person behind the open account, as `GET /me` gives it: the clock of
/// their profile and whether the phone's differs, and how they read a clock.
class Me {
  const Me({
    required this.name,
    required this.timezone,
    required this.displayTimezone,
    required this.mismatch,
    this.timezoneLabel,
    this.academyTimezone,
    this.academyTimezoneLabel,
    this.timeFormat = TimeFormat.h12,
    this.timeFormatChoice,
    this.academyTimeFormat = TimeFormat.h12,
    this.zoneChip = true,
  });

  factory Me.fromJson(Map<String, dynamic> json) {
    final inForce = TimeFormat.parse(json['time_format']) ?? TimeFormat.h12;
    final choice = TimeFormat.parse(json['time_format_choice']);
    final academy = json['academy'];
    final school = academy is Map<String, dynamic> ? academy : const <String, dynamic>{};
    return Me(
      name: json['name'] as String? ?? '',
      timezone: json['timezone'] as String?,
      displayTimezone: json['display_timezone'] as String? ?? 'UTC',
      mismatch: json['timezone_mismatch'] == true || json['timezone_mismatch'] == 'true',
      timezoneLabel: json['timezone_label'] as String?,
      academyTimezone: school['default_timezone'] as String?,
      academyTimezoneLabel: school['timezone_label'] as String?,
      timeFormat: inForce,
      timeFormatChoice: choice,
      academyTimeFormat: TimeFormat.parse(school['time_format']) ?? (choice == null ? inForce : TimeFormat.h12),
      zoneChip: json['zone_chip'] != false,
    );
  }

  final String name;
  final String? timezone;
  final String displayTimezone;
  final bool mismatch;

  /// [displayTimezone] as people read it, in their language: "القاهرة (UTC+2)".
  final String? timezoneLabel;

  /// The academy's own zone (for accounts with none, and its holidays).
  final String? academyTimezone;

  /// [academyTimezone] as people read it: "الرياض (UTC+3)".
  final String? academyTimezoneLabel;

  /// How they read a clock: their own choice, else the academy's.
  final TimeFormat timeFormat;

  /// Their own choice (null: the academy's).
  final TimeFormat? timeFormatChoice;

  /// The academy's way, for everyone who has not chosen their own.
  final TimeFormat academyTimeFormat;

  /// Whether they show the zone chip in the headers (hidden from its card,
  /// shown again from the account's settings; the same on the web).
  final bool zoneChip;

  /// The same person having chosen [choice] (null: the academy's way).
  Me choosing(TimeFormat? choice) => _with(timeFormat: choice ?? academyTimeFormat, timeFormatChoice: () => choice);

  /// The same person showing the zone chip or not.
  Me showingZoneChip(bool shown) => _with(zoneChip: shown);

  Me _with({TimeFormat? timeFormat, TimeFormat? Function()? timeFormatChoice, bool? zoneChip}) => Me(
    name: name,
    timezone: timezone,
    displayTimezone: displayTimezone,
    mismatch: mismatch,
    timezoneLabel: timezoneLabel,
    academyTimezone: academyTimezone,
    academyTimezoneLabel: academyTimezoneLabel,
    timeFormat: timeFormat ?? this.timeFormat,
    timeFormatChoice: timeFormatChoice == null ? this.timeFormatChoice : timeFormatChoice(),
    academyTimeFormat: academyTimeFormat,
    zoneChip: zoneChip ?? this.zoneChip,
  );
}

/// What the session needs from the API besides signing in: the academy's
/// look, the person's profile and, for a family account, the look of their
/// own area.
class SessionRepository {
  SessionRepository(this._api);

  final ApiClient _api;

  /// Public: works before and after sign-in. The app policy (the oldest
  /// version still served) rides along under `app`, so it is cached with the look.
  Future<Map<String, dynamic>> branding(String academy) async {
    final json = await _api.get('branding', academy: academy, anonymous: true);
    return {...json['data'] as Map<String, dynamic>, 'app': json['app']};
  }

  /// This phone receives the open account's push notifications.
  Future<void> registerDevice({required String platform, required String token, String? appVersion}) =>
      _api.post('devices', body: {'platform': platform, 'token': token, 'app_version': ?appVersion});

  /// The account (signing out of this phone) stops sending pushes here. Best effort.
  Future<void> forgetDevice({required String academy, required String token, required String pushToken}) async {
    try {
      await _api.delete('devices', body: {'token': pushToken}, academy: academy, token: token);
    } on Exception {
      // The API drops dead tokens by itself on the next push.
    }
  }

  Future<Me> me() async => Me.fromJson((await _api.get('me'))['data'] as Map<String, dynamic>);

  /// Changes what is given of the profile. [timeFormat], when given, sets how
  /// they read a clock (its value null: the academy's way); [zoneChip] shows
  /// or hides the zone chip. Answers the profile as it now stands (null when
  /// the API said nothing of it).
  Future<Me?> updateMe({String? locale, String? timezone, TimeFormat? Function()? timeFormat, bool? zoneChip}) async {
    final json = await _api.patch(
      'me',
      body: {'locale': ?locale, 'timezone': ?timezone, if (timeFormat != null) 'time_format': timeFormat()?.name, 'zone_chip': ?zoneChip},
    );
    final data = json['data'];
    return data is Map<String, dynamic> ? Me.fromJson(data) : null;
  }

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
