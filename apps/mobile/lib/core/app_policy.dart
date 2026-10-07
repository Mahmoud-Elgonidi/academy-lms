/// What the platform asks of the apps (`app` with the academy's look): the
/// oldest version still served, and where to get a newer one.
class AppPolicy {
  const AppPolicy({required this.minVersion, this.androidUrl, this.iosUrl});

  static AppPolicy? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    return AppPolicy(
      minVersion: json['min_version'] as String? ?? '0.0.0',
      androidUrl: json['android_url'] as String?,
      iosUrl: json['ios_url'] as String?,
    );
  }

  final String minVersion;
  final String? androidUrl;
  final String? iosUrl;

  /// [current] (like "1.2.0") is older than the oldest served.
  bool tooOld(String current) => compare(current, minVersion) < 0;

  /// Compares dotted versions number by number ("1.10.0" > "1.9.2"); a build suffix ("+3") is ignored.
  static int compare(String a, String b) {
    List<int> parts(String v) => v.split('+').first.split('.').map((p) => int.tryParse(p) ?? 0).toList();
    final x = parts(a);
    final y = parts(b);
    for (var i = 0; i < 3; i++) {
      final d = (i < x.length ? x[i] : 0) - (i < y.length ? y[i] : 0);
      if (d != 0) return d.sign;
    }
    return 0;
  }
}
