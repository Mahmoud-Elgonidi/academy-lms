import '../../../core/time/local_time.dart';

/// One working span on a weekday (ISO: 1 Monday … 7 Sunday), on the
/// teacher's own clock: "17:00"–"22:00" (the end may be "24:00").
class ShiftBlock {
  const ShiftBlock({required this.weekday, required this.start, required this.end});

  factory ShiftBlock.fromJson(Map<String, dynamic> json) =>
      ShiftBlock(weekday: (json['weekday'] as num).toInt(), start: json['start'] as String, end: json['end'] as String);

  final int weekday;
  final String start;
  final String end;

  static int minutes(String hhmm) => int.parse(hhmm.substring(0, 2)) * 60 + int.parse(hhmm.substring(3, 5));

  int get durationMin => minutes(end) - minutes(start);

  Map<String, dynamic> toJson() => {'weekday': weekday, 'start': start, 'end': end};

  @override
  bool operator ==(Object other) => other is ShiftBlock && other.weekday == weekday && other.start == start && other.end == end;

  @override
  int get hashCode => Object.hash(weekday, start, end);
}

class ShiftWeek {
  const ShiftWeek({required this.timezone, required this.blocks});

  factory ShiftWeek.fromJson(Map<String, dynamic>? json) => ShiftWeek(
    timezone: json?['timezone'] as String? ?? '',
    blocks: [for (final b in json?['blocks'] as List<dynamic>? ?? const []) ShiftBlock.fromJson(b as Map<String, dynamic>)],
  );

  final String timezone;
  final List<ShiftBlock> blocks;

  int get weeklyMinutes => blocks.fold(0, (n, b) => n + b.durationMin);

  List<ShiftBlock> on(int weekday) => blocks.where((b) => b.weekday == weekday).toList()..sort((a, b) => a.start.compareTo(b.start));
}

class ShiftComment {
  const ShiftComment({required this.mine, required this.body, this.at});

  factory ShiftComment.fromJson(Map<String, dynamic> json) =>
      ShiftComment(mine: json['mine'] == true, body: json['body'] as String? ?? '', at: LocalTime.fromJson(json['at']));

  final bool mine;
  final String body;
  final LocalTime? at;
}

/// The teacher's open (or draft) proposal and where it stands.
class ShiftProposal {
  const ShiftProposal({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.effectiveFrom,
    required this.week,
    required this.comments,
    this.waitingOn,
    this.note,
    this.minWeeklyMinutes,
    this.maxWeeklyMinutes,
    this.previousWeek,
  });

  factory ShiftProposal.fromJson(Map<String, dynamic> json) => ShiftProposal(
    id: json['id'] as String,
    status: json['status'] as String,
    statusLabel: json['status_label'] as String? ?? '',
    waitingOn: json['waiting_on'] as String?,
    effectiveFrom: json['effective_from'] as String,
    note: json['note'] as String?,
    minWeeklyMinutes: (json['min_weekly_minutes'] as num?)?.toInt(),
    maxWeeklyMinutes: (json['max_weekly_minutes'] as num?)?.toInt(),
    week: ShiftWeek.fromJson(json['week'] as Map<String, dynamic>?),
    previousWeek: json['previous_week'] == null ? null : ShiftWeek.fromJson(json['previous_week'] as Map<String, dynamic>),
    comments: [for (final c in json['comments'] as List<dynamic>? ?? const []) ShiftComment.fromJson(c as Map<String, dynamic>)],
  );

  final String id;

  /// draft, submitted, changes_requested, countered (and, once closed, approved, rejected, withdrawn).
  final String status;
  final String statusLabel;

  /// admin or teacher: whose turn it is.
  final String? waitingOn;

  /// `YYYY-MM-DD` on the teacher's calendar.
  final String effectiveFrom;
  final String? note;
  final int? minWeeklyMinutes;
  final int? maxWeeklyMinutes;
  final ShiftWeek week;

  /// The version before the academy's counter-offer, to show what changed.
  final ShiftWeek? previousWeek;
  final List<ShiftComment> comments;

  bool get countered => status == 'countered';
  bool get myTurn => waitingOn == 'teacher';
}

/// `GET teacher/shifts`: the week in force, the open proposal, and the
/// earliest start dates (adding time: tomorrow; cutting time: after the notice).
class MyShifts {
  const MyShifts({
    required this.timezone,
    required this.current,
    required this.earliestAdding,
    required this.earliestRemoving,
    this.proposal,
  });

  factory MyShifts.fromJson(Map<String, dynamic> json) {
    final earliest = json['earliest_start'] as Map<String, dynamic>? ?? const {};
    return MyShifts(
      timezone: json['timezone'] as String? ?? '',
      current: ShiftWeek.fromJson(json['current'] as Map<String, dynamic>?),
      proposal: json['proposal'] == null ? null : ShiftProposal.fromJson(json['proposal'] as Map<String, dynamic>),
      earliestAdding: earliest['adding_time'] as String? ?? '',
      earliestRemoving: earliest['removing_time'] as String? ?? '',
    );
  }

  final String timezone;
  final ShiftWeek current;
  final ShiftProposal? proposal;
  final String earliestAdding;
  final String earliestRemoving;
}
