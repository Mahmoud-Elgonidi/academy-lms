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
  const ShiftComment({required this.mine, required this.body, this.role = '', this.at});

  factory ShiftComment.fromJson(Map<String, dynamic> json) => ShiftComment(
    mine: json['mine'] == true,
    role: json['author_role'] as String? ?? '',
    body: json['body'] as String? ?? '',
    at: LocalTime.fromJson(json['at']),
  );

  final bool mine;

  /// teacher, admin or system (a step the proposal took, said by the academy's system).
  final String role;
  final String body;
  final LocalTime? at;

  bool get system => role == 'system';
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
    this.submittedAt,
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
    submittedAt: LocalTime.fromJson(json['submitted_at']),
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
  final LocalTime? submittedAt;

  bool get countered => status == 'countered';
  bool get myTurn => waitingOn == 'teacher';

  /// The words said, without the steps the system wrote down.
  int get messageCount => comments.where((c) => !c.system).length;

  ShiftProposal withComments(List<ShiftComment> next) => ShiftProposal(
    id: id,
    status: status,
    statusLabel: statusLabel,
    effectiveFrom: effectiveFrom,
    week: week,
    comments: next,
    waitingOn: waitingOn,
    note: note,
    minWeeklyMinutes: minWeeklyMinutes,
    maxWeeklyMinutes: maxWeeklyMinutes,
    previousWeek: previousWeek,
    submittedAt: submittedAt,
  );
}

/// An approved schedule that starts on a later day; the week in force stays until then.
class UpcomingShifts {
  const UpcomingShifts({required this.startsOn, required this.version, required this.week});

  factory UpcomingShifts.fromJson(Map<String, dynamic> json) => UpcomingShifts(
    startsOn: LocalTime.fromJson(json['starts_on'])!,
    version: (json['version'] as num?)?.toInt() ?? 0,
    week: ShiftWeek.fromJson(json),
  );

  /// The first day it is in force: `label` names it, `localDate` is the date.
  final LocalTime startsOn;
  final int version;
  final ShiftWeek week;
}

/// A proposal the academy decided: approved, rejected, or approved and replaced since.
class ShiftDecision {
  const ShiftDecision({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.effectiveFrom,
    this.decidedAt,
    this.reason,
  });

  factory ShiftDecision.fromJson(Map<String, dynamic> json) => ShiftDecision(
    id: json['id'] as String,
    status: json['status'] as String,
    statusLabel: json['status_label'] as String? ?? '',
    effectiveFrom: json['effective_from'] as String? ?? '',
    decidedAt: LocalTime.fromJson(json['decided_at']),
    reason: json['reason'] as String?,
  );

  final String id;

  /// approved, rejected, superseded.
  final String status;
  final String statusLabel;
  final String effectiveFrom;
  final LocalTime? decidedAt;

  /// The academy's reason, for a rejected proposal.
  final String? reason;

  bool get approved => status == 'approved' || status == 'superseded';
}

/// Where a proposal stands, as the steps the teacher sees: written, sent,
/// reviewed by the academy, and its answer.
enum ShiftStage {
  draft,
  review,
  changesRequested,
  countered,
  approved,
  rejected;

  static ShiftStage of(String status) => switch (status) {
    'draft' => draft,
    'submitted' => review,
    'changes_requested' => changesRequested,
    'countered' => countered,
    'rejected' => rejected,
    _ => approved,
  };

  /// The step it is on (0 draft … 3 the academy's answer).
  int get step => switch (this) {
    draft => 0,
    review => 2,
    _ => 3,
  };
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
    this.upcoming,
    this.history = const [],
  });

  factory MyShifts.fromJson(Map<String, dynamic> json) {
    final earliest = json['earliest_start'] as Map<String, dynamic>? ?? const {};
    return MyShifts(
      timezone: json['timezone'] as String? ?? '',
      current: ShiftWeek.fromJson(json['current'] as Map<String, dynamic>?),
      proposal: json['proposal'] == null ? null : ShiftProposal.fromJson(json['proposal'] as Map<String, dynamic>),
      earliestAdding: earliest['adding_time'] as String? ?? '',
      earliestRemoving: earliest['removing_time'] as String? ?? '',
      upcoming: json['upcoming'] == null ? null : UpcomingShifts.fromJson(json['upcoming'] as Map<String, dynamic>),
      history: [for (final h in json['history'] as List<dynamic>? ?? const []) ShiftDecision.fromJson(h as Map<String, dynamic>)],
    );
  }

  final String timezone;
  final ShiftWeek current;
  final ShiftProposal? proposal;
  final String earliestAdding;
  final String earliestRemoving;
  final UpcomingShifts? upcoming;

  /// The last decided proposals, newest first.
  final List<ShiftDecision> history;

  /// The latest week agreed: the one starting later if there is one, else the week in force.
  ShiftWeek get latest => upcoming?.week ?? current;

  MyShifts withProposal(ShiftProposal? next) => MyShifts(
    timezone: timezone,
    current: current,
    earliestAdding: earliestAdding,
    earliestRemoving: earliestRemoving,
    proposal: next,
    upcoming: upcoming,
    history: history,
  );
}
