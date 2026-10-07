import 'package:equatable/equatable.dart';

/// An instant as the API gives it: `{utc, local, timezone, label}`, already
/// in the viewer's zone (the app sends `X-Timezone`). The app never converts
/// zones itself; it shows `label`, or the wall-clock parts of `local`.
class LocalTime extends Equatable {
  const LocalTime({required this.utc, required this.local, required this.timezone, required this.label});

  static LocalTime? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    return LocalTime(
      utc: DateTime.parse(json['utc'] as String),
      local: json['local'] as String,
      timezone: json['timezone'] as String,
      label: json['label'] as String,
    );
  }

  final DateTime utc;

  /// ISO 8601 with the viewer's offset: `2026-10-07T19:00:00+03:00`.
  final String local;
  final String timezone;
  final String label;

  /// `2026-10-07` on the viewer's calendar.
  String get localDate => local.substring(0, 10);

  /// `19:00` on the viewer's clock.
  String get hhmm => local.substring(11, 16);

  int get hour => int.parse(local.substring(11, 13));
  int get minute => int.parse(local.substring(14, 16));

  @override
  List<Object?> get props => [utc, local, timezone];
}
