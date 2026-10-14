import 'local_time.dart';

/// How a course stands: simply on, paused, a pause ahead, ending, or ended.
enum CourseStand { on, paused, pauseAhead, ending, ended }

/// A course's status and days as the API gives them on the family's course
/// endpoints (the balance, the journey): paused until a day, a pause ahead,
/// ending from a day, or ended (docs/architecture/02, section 3, "إيقاف
/// الكورس وإنهاؤه"). The days are the academy's calendar days, each the
/// midnight it starts there on the academy's clock, shown as written and
/// never converted on the phone.
class CourseDays {
  const CourseDays({this.status = 'active', this.pausedFrom, this.pausedUntil, this.endsOn});

  factory CourseDays.fromJson(Map<String, dynamic> json) => CourseDays(
    status: json['status'] as String? ?? 'active',
    pausedFrom: LocalTime.fromJson(json['paused_from']),
    pausedUntil: LocalTime.fromJson(json['paused_until']),
    endsOn: LocalTime.fromJson(json['ends_on']),
  );

  /// pending_payment, active, paused or ended.
  final String status;
  final LocalTime? pausedFrom;

  /// The pause's last day (included): lessons start again the day after.
  final LocalTime? pausedUntil;

  /// The first day without lessons of a course ending (or ended).
  final LocalTime? endsOn;

  /// Lessons can be bought or booked on it now: only while it is simply on.
  bool get isOn => status == 'active';

  CourseStand get stand {
    if (status == 'ended') return CourseStand.ended;
    if (endsOn != null) return CourseStand.ending;
    if (status == 'paused' && pausedUntil != null) return CourseStand.paused;
    if (pausedFrom != null && pausedUntil != null) return CourseStand.pauseAhead;
    return CourseStand.on;
  }
}
