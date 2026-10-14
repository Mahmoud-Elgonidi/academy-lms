import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// A report begun on this phone and kept for later: the form as it stood,
/// and when.
class ReportDraft {
  const ReportDraft({required this.savedAt, required this.form});

  final DateTime savedAt;
  final Map<String, dynamic> form;
}

/// Reports a teacher began and kept as drafts, by report, for each account
/// on this phone. They never leave the phone, and go once the report is sent.
class ReportDrafts {
  ReportDrafts([SharedPreferencesAsync? prefs]) : _prefs = prefs ?? SharedPreferencesAsync();

  final SharedPreferencesAsync _prefs;

  static String _key(String account) => 'lms.report-drafts.$account';

  Future<Map<String, ReportDraft>> all(String account) async {
    final raw = await _prefs.getString(_key(account));
    if (raw == null) return {};
    try {
      final map = jsonDecode(raw) as Map<String, dynamic>;
      return {
        for (final MapEntry(:key, :value) in map.entries)
          if (value case {'saved_at': final String at, 'form': final Map<String, dynamic> form})
            key: ReportDraft(savedAt: DateTime.parse(at), form: form),
      };
    } on FormatException {
      return {};
    }
  }

  Future<ReportDraft?> of(String account, String report) async => (await all(account))[report];

  Future<void> save(String account, String report, Map<String, Object?> form) async {
    final drafts = await all(account);
    drafts[report] = ReportDraft(savedAt: DateTime.now().toUtc(), form: form.cast<String, dynamic>());
    await _write(account, drafts);
  }

  Future<void> remove(String account, String report) async {
    final drafts = await all(account);
    if (drafts.remove(report) != null) await _write(account, drafts);
  }

  Future<void> _write(String account, Map<String, ReportDraft> drafts) => drafts.isEmpty
      ? _prefs.remove(_key(account))
      : _prefs.setString(
          _key(account),
          jsonEncode({
            for (final MapEntry(:key, :value) in drafts.entries) key: {'saved_at': value.savedAt.toIso8601String(), 'form': value.form},
          }),
        );
}
