import '../../../core/time/local_time.dart';
import '../../schedule/domain/lesson.dart';

List<Map<String, dynamic>> _list(Object? raw) => [for (final x in raw as List<dynamic>? ?? const []) x as Map<String, dynamic>];

/// A teacher's lessons over some days, on their own clock.
class TeacherLessons {
  const TeacherLessons({required this.from, required this.to, required this.count, required this.minutes, required this.lessons});

  factory TeacherLessons.fromJson(Map<String, dynamic> json) => TeacherLessons(
    from: DateTime.parse(json['from'] as String),
    to: DateTime.parse(json['to'] as String),
    count: (json['count'] as num?)?.toInt() ?? 0,
    minutes: (json['minutes'] as num?)?.toInt() ?? 0,
    lessons: _list(json['data']).map(Lesson.fromJson).toList(),
  );

  /// First and last day shown (dates on the teacher's calendar).
  final DateTime from;
  final DateTime to;
  final int count;
  final int minutes;
  final List<Lesson> lessons;
}

/// A student a teacher teaches, and where they are.
class TeacherStudent {
  const TeacherStudent({
    required this.enrollmentId,
    required this.student,
    required this.subject,
    required this.paused,
    required this.toMark,
    this.percent,
    this.here,
    this.lastReportAt,
  });

  factory TeacherStudent.fromJson(Map<String, dynamic> json) => TeacherStudent(
    enrollmentId: json['enrollment_id'] as String,
    student: json['student'] as String? ?? '',
    subject: json['subject'] as String? ?? '',
    paused: json['paused'] == true,
    percent: (json['percent'] as num?)?.toInt(),
    here: json['here'] as String?,
    toMark: (json['to_mark'] as num?)?.toInt() ?? 0,
    lastReportAt: LocalTime.fromJson(json['last_report_at']),
  );

  final String enrollmentId;
  final String student;
  final String subject;
  final bool paused;
  final int? percent;
  final String? here;
  final int toMark;
  final LocalTime? lastReportAt;
}

/// A month of a teacher's pay (`GET teacher/earnings`), already formatted.
class Earnings {
  const Earnings({
    required this.month,
    required this.label,
    required this.closed,
    required this.unpriced,
    required this.totals,
    required this.lines,
    required this.adjustments,
  });

  factory Earnings.fromJson(Map<String, dynamic> json) {
    String money(Object? m) => (m as Map<String, dynamic>?)?['formatted'] as String? ?? '';
    return Earnings(
      month: json['month'] as String,
      label: json['label'] as String? ?? '',
      closed: json['closed'] == true,
      unpriced: (json['unpriced'] as num?)?.toInt() ?? 0,
      totals: [
        for (final t in _list(json['totals']))
          (
            lessons: (t['lessons'] as num?)?.toInt() ?? 0,
            hours: '${t['hours'] ?? ''}',
            lessonPay: money(t['lesson_pay']),
            bonuses: money(t['bonuses']),
            deductions: money(t['deductions']),
            total: money(t['total']),
          ),
      ],
      lines: [
        for (final l in _list(json['lessons']))
          (
            when: l['when'] as String? ?? '',
            student: l['student'] as String? ?? '',
            subject: l['subject'] as String? ?? '',
            outcome: l['outcome'] as String? ?? '',
            share: l['share'] as String? ?? '',
            amount: l['amount'] as String? ?? '',
            negative: l['negative'] == true,
            corrected: l['corrected'] == true,
            notes: [for (final n in l['notes'] as List<dynamic>? ?? const []) '$n'],
          ),
      ],
      adjustments: [
        for (final a in _list(json['adjustments']))
          if (a['reversed'] != true)
            (
              kind: a['kind'] as String? ?? '',
              note: a['note'] as String? ?? '',
              amount: a['amount'] as String? ?? '',
              negative: a['negative'] == true,
              at: a['at'] as String? ?? '',
            ),
      ],
    );
  }

  /// YYYY-MM.
  final String month;
  final String label;
  final bool closed;
  final int unpriced;
  final List<({int lessons, String hours, String lessonPay, String bonuses, String deductions, String total})> totals;
  final List<
    ({
      String when,
      String student,
      String subject,
      String outcome,
      String share,
      String amount,
      bool negative,
      bool corrected,
      List<String> notes,
    })
  >
  lines;
  final List<({String kind, String note, String amount, bool negative, String at})> adjustments;
}

class TimeOff {
  const TimeOff({
    required this.id,
    required this.period,
    required this.reasonLabel,
    required this.status,
    required this.statusLabel,
    required this.canWithdraw,
    this.note,
    this.decisionNote,
    this.cover,
  });

  factory TimeOff.fromJson(Map<String, dynamic> json) {
    final cover = json['cover'] as Map<String, dynamic>?;
    return TimeOff(
      id: json['id'] as String,
      period: json['period'] as String? ?? '',
      reasonLabel: json['reason_label'] as String? ?? '',
      note: json['note'] as String?,
      status: json['status'] as String? ?? '',
      statusLabel: json['status_label'] as String? ?? '',
      decisionNote: json['decision_note'] as String?,
      canWithdraw: json['can_withdraw'] == true,
      cover: cover == null ? null : (total: (cover['total'] as num?)?.toInt() ?? 0, covered: (cover['covered'] as num?)?.toInt() ?? 0),
    );
  }

  final String id;
  final String period;
  final String reasonLabel;
  final String? note;

  /// pending, approved, rejected, withdrawn.
  final String status;
  final String statusLabel;
  final String? decisionNote;
  final bool canWithdraw;
  final ({int total, int covered})? cover;
}

class TimeOffList {
  const TimeOffList({required this.requests, required this.holidays});

  factory TimeOffList.fromJson(Map<String, dynamic> json) => TimeOffList(
    requests: _list(json['data']).map(TimeOff.fromJson).toList(),
    holidays: [for (final h in _list(json['holidays'])) (name: h['name'] as String? ?? '', period: h['period'] as String? ?? '')],
  );

  final List<TimeOff> requests;
  final List<({String name, String period})> holidays;
}

/// What waits on the teacher besides lessons: reports to write, homework to mark.
class TeacherTodo {
  const TeacherTodo({required this.reports, required this.homework, this.firstReport});

  final int reports;
  final int homework;

  /// The report due first: whose, and by when.
  final ({String student, LocalTime? dueAt})? firstReport;
}
