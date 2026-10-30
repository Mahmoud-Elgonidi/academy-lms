import '../network/api_client.dart';

/// A time zone to choose from, as `GET timezones` names it in the reader's
/// language: "Africa/Cairo", "القاهرة", "UTC+2", "القاهرة (UTC+2)", "أفريقيا".
class Zone {
  const Zone({required this.id, required this.city, required this.offset, required this.label, required this.region});

  factory Zone.fromJson(Map<String, dynamic> json) => Zone(
    id: json['id'] as String,
    city: json['city'] as String? ?? json['id'] as String,
    offset: json['offset'] as String? ?? '',
    label: json['label'] as String? ?? json['id'] as String,
    region: json['region'] as String? ?? '',
  );

  final String id;
  final String city;
  final String offset;
  final String label;
  final String region;

  /// Typed [query] is in its city, its IANA name, its region or its offset.
  bool matches(String query) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return true;
    return [city, id.replaceAll('_', ' '), region, offset].any((s) => s.toLowerCase().contains(q));
  }
}

/// The zones a person may choose for their account, in the API's order
/// (by region, then by offset, then by city), kept for the app's life per
/// language.
class ZoneRepository {
  ZoneRepository(this._api);

  final ApiClient _api;
  final _kept = <String, List<Zone>>{};

  Future<List<Zone>> all(String locale) async {
    final kept = _kept[locale];
    if (kept != null) return kept;
    final json = await _api.get('timezones');
    final zones = [for (final z in json['data'] as List<dynamic>) Zone.fromJson(z as Map<String, dynamic>)];
    return _kept[locale] = zones;
  }
}

/// "UTC+2", "UTC+5:30", "UTC−3" (the API writes a true minus), "UTC" → how
/// far from UTC; null when it says no such thing.
Duration? utcOffsetOf(String? text) {
  if (text == null) return null;
  final m = RegExp(r'UTC(?:\s*([+\-−])\s*(\d{1,2})(?::(\d{2}))?)?').firstMatch(text);
  if (m == null) return null;
  if (m.group(1) == null) return Duration.zero;
  final d = Duration(hours: int.parse(m.group(2)!), minutes: int.parse(m.group(3) ?? '0'));
  return m.group(1) == '+' ? d : -d;
}

/// "القاهرة (UTC+2)" → "القاهرة": the city of a label the API wrote.
String cityOf(String label) {
  final i = label.lastIndexOf(' (');
  return i > 0 ? label.substring(0, i) : label;
}
