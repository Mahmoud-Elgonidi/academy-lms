import 'package:equatable/equatable.dart';

import '../../../core/time/course_days.dart';
import '../../../core/time/local_time.dart';

/// One course's balance as the person signed in may read it
/// (`GET family/courses/{enrollment}/balance`, docs/architecture/02, section
/// 27, J5a). What a look does not show comes as null: the kids' look reads
/// the lessons left only; middle school and teens the plan, the expiry and
/// the course's days, no money; the classic look (a guardian, an adult) also
/// the invoice waiting and the next one to come.
class CourseBalance extends Equatable {
  const CourseBalance({
    required this.enrollmentId,
    required this.stage,
    this.days = const CourseDays(),
    this.lessonsLeft,
    this.lessonsText,
    this.makeupLessons,
    this.plan,
    this.expiry,
    this.isPayer,
    this.renews,
    this.waiting,
    this.next,
    this.renewalNote,
  });

  factory CourseBalance.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic> ? json['data'] as Map<String, dynamic> : json;
    final plan = data['plan'];
    final expiry = data['expiry'];
    final waiting = data['waiting_invoice'];
    final next = data['next_invoice'];
    return CourseBalance(
      enrollmentId: data['enrollment_id'] as String? ?? '',
      stage: data['viewer_stage'] as String? ?? 'classic',
      days: CourseDays.fromJson(data),
      lessonsLeft: (data['lessons_left'] as num?)?.toInt(),
      lessonsText: _text(data['lessons_text']),
      makeupLessons: (data['makeup_lessons'] as num?)?.toInt(),
      plan: plan is Map<String, dynamic> ? BalancePlan.fromJson(plan) : null,
      expiry: expiry is Map<String, dynamic> ? BalanceExpiry.fromJson(expiry) : null,
      isPayer: data['is_payer'] as bool?,
      renews: data['renews'] as bool?,
      waiting: waiting is Map<String, dynamic> ? WaitingInvoice.fromJson(waiting) : null,
      next: next is Map<String, dynamic> ? NextInvoice.fromJson(next) : null,
      renewalNote: _text(data['renewal_note']),
    );
  }

  final String enrollmentId;

  /// The look these fields follow: kids, middle, teen or classic.
  final String stage;

  /// Running, paused (until a day), ending (from a day) or ended.
  final CourseDays days;

  /// Paid-for lessons left (makeup lessons aside); null when the plan has no lesson length.
  final int? lessonsLeft;

  /// The same in the API's words ("بقيت 5 حصص").
  final String? lessonsText;

  /// Lessons given back besides (not in the kids' look).
  final int? makeupLessons;
  final BalancePlan? plan;
  final BalanceExpiry? expiry;

  /// The person signed in pays for this course (the classic look).
  final bool? isPayer;

  /// The course renews: on, and not ending (the classic look).
  final bool? renews;

  /// The next package's invoice, waiting to be paid (the classic look).
  final WaitingInvoice? waiting;

  /// The next invoice still to come, an estimate (the classic look).
  final NextInvoice? next;

  /// Why no invoice comes now: paused, or ending (the classic look).
  final String? renewalNote;

  bool get kids => stage == 'kids';
  bool get classic => stage == 'classic';

  /// How full the package still is, 0..1 (null without a plan's block to measure against).
  double? get share {
    final left = lessonsLeft;
    final block = plan?.perBlock ?? 0;
    if (left == null || block <= 0) return null;
    return (left / block).clamp(0, 1).toDouble();
  }

  /// Where on the bar the next invoice goes out, 0..1 (null when it cannot be placed).
  double? get mark {
    final at = plan?.renewsAt ?? 0;
    final block = plan?.perBlock ?? 0;
    if (block <= 0 || at <= 0 || at >= block) return null;
    return at / block;
  }

  /// Few lessons left: the number takes the attention colour.
  bool get low => (lessonsLeft ?? 99) <= 1 && days.isOn;

  @override
  List<Object?> get props => [
    enrollmentId,
    stage,
    days.status,
    lessonsLeft,
    makeupLessons,
    plan,
    expiry,
    isPayer,
    renews,
    waiting,
    next,
    renewalNote,
  ];
}

/// The plan and its shape, in numbers the card puts in words.
class BalancePlan extends Equatable {
  const BalancePlan({
    required this.name,
    this.group = false,
    this.shared = false,
    this.minutes = 0,
    this.perWeek = 0,
    this.perBlock = 0,
    this.renewsAt = 0,
  });

  factory BalancePlan.fromJson(Map<String, dynamic> json) => BalancePlan(
    name: json['name'] as String? ?? '',
    group: json['format'] == 'group',
    shared: json['shared'] == true,
    minutes: (json['session_minutes'] as num?)?.toInt() ?? 0,
    perWeek: (json['sessions_per_week'] as num?)?.toInt() ?? 0,
    perBlock: (json['sessions_per_block'] as num?)?.toInt() ?? 0,
    renewsAt: (json['renews_at_lessons_left'] as num?)?.toInt() ?? 0,
  );

  final String name;
  final bool group;

  /// A shared package: one balance for several subjects.
  final bool shared;
  final int minutes;
  final int perWeek;
  final int perBlock;

  /// The next invoice goes out when this many lessons are left.
  final int renewsAt;

  @override
  List<Object?> get props => [name, group, shared, minutes, perWeek, perBlock, renewsAt];
}

/// When the soonest lessons not booked yet expire; [soon] within a week (a warning).
class BalanceExpiry extends Equatable {
  const BalanceExpiry({required this.text, this.at, this.lessons = 0, this.soon = false});

  factory BalanceExpiry.fromJson(Map<String, dynamic> json) => BalanceExpiry(
    at: LocalTime.fromJson(json['at']),
    lessons: (json['lessons'] as num?)?.toInt() ?? 0,
    soon: json['soon'] == true,
    text: _text(json['text']) ?? '',
  );

  final LocalTime? at;
  final int lessons;
  final bool soon;

  /// The API's sentence (what expires, and when).
  final String text;

  @override
  List<Object?> get props => [at, lessons, soon, text];
}

/// The next package's invoice waiting to be paid; its [id] only for the payer (who may open and pay it).
class WaitingInvoice extends Equatable {
  const WaitingInvoice({
    required this.number,
    required this.outstanding,
    required this.text,
    this.id,
    this.outstandingMinor = 0,
    this.dueAt,
  });

  factory WaitingInvoice.fromJson(Map<String, dynamic> json) => WaitingInvoice(
    id: _text(json['id']),
    number: json['number'] as String? ?? '',
    outstanding: json['outstanding'] as String? ?? '',
    outstandingMinor: (json['outstanding_minor'] as num?)?.toInt() ?? 0,
    dueAt: LocalTime.fromJson(json['due_at']),
    text: _text(json['text']) ?? '',
  );

  final String? id;
  final String number;

  /// What is left to pay, written ("400 ر.س.").
  final String outstanding;
  final int outstandingMinor;
  final LocalTime? dueAt;
  final String text;

  @override
  List<Object?> get props => [id, number, outstanding, outstandingMinor, dueAt];
}

/// The next invoice still to come: when [atLessonsLeft] lessons are left (after [lessonsUntil] more), about [amount], around [on].
class NextInvoice extends Equatable {
  const NextInvoice({required this.amount, required this.text, this.atLessonsLeft = 0, this.lessonsUntil = 0, this.on});

  factory NextInvoice.fromJson(Map<String, dynamic> json) => NextInvoice(
    atLessonsLeft: (json['at_lessons_left'] as num?)?.toInt() ?? 0,
    lessonsUntil: (json['lessons_until'] as num?)?.toInt() ?? 0,
    amount: json['amount'] as String? ?? '',
    on: LocalTime.fromJson(json['on']),
    text: _text(json['text']) ?? '',
  );

  final int atLessonsLeft;
  final int lessonsUntil;
  final String amount;

  /// The day it should go out, on the academy's calendar.
  final LocalTime? on;
  final String text;

  /// It goes out now (the balance reached the mark).
  bool get now => lessonsUntil <= 0;

  @override
  List<Object?> get props => [atLessonsLeft, lessonsUntil, amount, on];
}

String? _text(Object? value) {
  final text = value is String ? value.trim() : '';
  return text.isEmpty ? null : text;
}
