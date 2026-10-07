import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Small choices that are not secrets: the app's language, each academy's
/// last known look (so the app opens in its colours), the clock the person
/// chose to see times on, and whether the app plays sounds.
class AppPrefs {
  AppPrefs(this._prefs);

  static const _localeKey = 'lms.locale';
  static const _brandingKey = 'lms.branding.';
  static const _clockKey = 'lms.clock.';
  static const _soundsKey = 'lms.sounds';

  final SharedPreferencesAsync _prefs;

  Future<String?> locale() => _prefs.getString(_localeKey);

  Future<void> setLocale(String locale) => _prefs.setString(_localeKey, locale);

  /// Little sounds on taps and rewards (on unless turned off).
  Future<bool> sounds() async => await _prefs.getBool(_soundsKey) ?? true;

  Future<void> setSounds(bool on) => _prefs.setBool(_soundsKey, on);

  Future<Map<String, dynamic>?> branding(String academy) async {
    final raw = await _prefs.getString('$_brandingKey$academy');
    if (raw == null) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } on FormatException {
      return null;
    }
  }

  Future<void> setBranding(String academy, Map<String, dynamic> json) => _prefs.setString('$_brandingKey$academy', jsonEncode(json));

  /// The zone an account shows times in when the person chose to keep their
  /// profile's clock over the phone's (null: the phone's clock).
  Future<String?> clock(String accountId) => _prefs.getString('$_clockKey$accountId');

  Future<void> setClock(String accountId, String? timezone) =>
      timezone == null ? _prefs.remove('$_clockKey$accountId') : _prefs.setString('$_clockKey$accountId', timezone);
}
