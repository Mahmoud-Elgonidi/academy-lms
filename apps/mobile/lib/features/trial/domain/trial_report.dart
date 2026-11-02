import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';

/// How a free trial went, as the family reads it (`GET family/trials`).
enum TrialStatus {
  booked,
  attended,
  missed,
  cancelled;

  static TrialStatus of(Object? v) => values.where((s) => s.name == v).firstOrNull ?? booked;
}

/// The academy's offer after the trial: open (with its end), ended (its time
/// passed), used, started (a course began) or none.
enum OfferState {
  open,
  ended,
  used,
  started,
  none;

  static OfferState of(Object? v) => values.where((s) => s.name == v).firstOrNull ?? none;
}

/// An amount as the API wrote it for the reader ("270.00 ر.س"), with its minor units and currency.
class Price extends Equatable {
  const Price({required this.text, required this.minor, required this.currency});

  static Price? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    return Price(
      text: json['price'] as String? ?? '',
      minor: (json['amount_minor'] as num?)?.toInt() ?? 0,
      currency: json['currency'] as String? ?? '',
    );
  }

  final String text;
  final int minor;
  final String currency;

  @override
  List<Object?> get props => [text, minor, currency];
}

/// One more free trial a trial allows (classic look only): the second with
/// another teacher after a report, or a new time after one that did not take
/// place; [whyNot] is the API's own sentence when it cannot be booked now.
class NextTrial extends Equatable {
  const NextTrial({required this.kind, required this.available, this.whyNot, this.until});

  static NextTrial? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final why = json['why_not'] as String?;
    return NextTrial(
      kind: json['kind'] as String? ?? 'second',
      available: json['available'] == true,
      whyNot: why == null || why.isEmpty ? null : why,
      until: LocalTime.fromJson(json['until']),
    );
  }

  /// `second` (another teacher) or `rebook` (after one that did not take place).
  final String kind;
  final bool available;
  final String? whyNot;

  /// The last moment to book the second trial (the report says it).
  final LocalTime? until;

  bool get second => kind == 'second';

  @override
  List<Object?> get props => [kind, available, whyNot, until];
}

/// A learner's free trial in the family's list.
class FamilyTrial extends Equatable {
  const FamilyTrial({
    required this.id,
    required this.subject,
    required this.status,
    this.startsAt,
    this.teacher = '',
    this.kind = 'first',
    this.outcome,
    this.reportReady = false,
    this.next,
  });

  factory FamilyTrial.fromJson(Map<String, dynamic> json) {
    final subject = json['subject'];
    return FamilyTrial(
      id: json['id'] as String,
      subject: subject is Map<String, dynamic> ? subject['name'] as String? ?? '' : '',
      startsAt: LocalTime.fromJson(json['starts_at']),
      teacher: json['teacher'] as String? ?? '',
      kind: json['kind'] as String? ?? 'first',
      status: TrialStatus.of(json['status']),
      outcome: json['outcome'] as String?,
      reportReady: json['report_ready'] == true,
      next: NextTrial.fromJson(json['next']),
    );
  }

  /// The trial lesson's id: its report is `family/trials/{id}`.
  final String id;
  final String subject;
  final LocalTime? startsAt;

  /// The teacher's first name with a title ("أ. منى").
  final String teacher;

  /// first, rebook or second.
  final String kind;
  final TrialStatus status;

  /// Why it did not take place: student_absent, teacher_absent, family, academy.
  final String? outcome;
  final bool reportReady;
  final NextTrial? next;

  /// Taken place, the teacher still writing the report.
  bool get reportComing => status == TrialStatus.attended && !reportReady;

  /// The academy's side, never the family's (a teacher away, a cancellation): it never counts.
  bool get notTheirs => outcome == 'teacher_absent' || outcome == 'academy';

  @override
  List<Object?> get props => [id, subject, startsAt, teacher, kind, status, outcome, reportReady, next];
}

/// The learner's trials, latest first, and the look of the one reading.
class FamilyTrials extends Equatable {
  const FamilyTrials({required this.learnerName, required this.stage, required this.trials});

  factory FamilyTrials.fromJson(Map<String, dynamic> json) {
    final learner = json['learner'];
    return FamilyTrials(
      learnerName: learner is Map<String, dynamic> ? learner['name'] as String? ?? '' : '',
      stage: json['viewer_stage'] as String? ?? 'classic',
      trials: [for (final t in json['data'] as List<dynamic>? ?? const []) FamilyTrial.fromJson(t as Map<String, dynamic>)],
    );
  }

  final String learnerName;

  /// kids, middle, teen or classic (prices, the offer and booking only in classic).
  final String stage;
  final List<FamilyTrial> trials;

  bool get classic => stage == 'classic';

  /// How long a trial stays on the home: as long as its report's link lasts.
  static const homeFor = Duration(days: 30);

  /// The one a home card speaks of: a recent trial with a report to read, or one more trial to book; null when none.
  FamilyTrial? headline(DateTime now) => trials
      .where((t) => t.reportReady || (t.next?.available ?? false))
      .where((t) => t.startsAt == null || now.toUtc().difference(t.startsAt!.utc) < homeFor)
      .firstOrNull;

  @override
  List<Object?> get props => [learnerName, stage, trials];
}

/// The plan the teacher recommends, as its shape reads.
class RecommendedPlan extends Equatable {
  const RecommendedPlan({
    required this.id,
    required this.name,
    this.group = false,
    this.sessionMinutes = 0,
    this.sessionsPerWeek = 0,
    this.sessionsPerBlock = 0,
    this.validityDays = 0,
  });

  static RecommendedPlan? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    int n(String k) => (json[k] as num?)?.toInt() ?? 0;
    return RecommendedPlan(
      id: json['id'] as String? ?? '',
      name: json['name'] as String? ?? '',
      group: json['format'] == 'group',
      sessionMinutes: n('session_minutes'),
      sessionsPerWeek: n('sessions_per_week'),
      sessionsPerBlock: n('sessions_per_block'),
      validityDays: n('validity_days'),
    );
  }

  final String id;
  final String name;
  final bool group;
  final int sessionMinutes;
  final int sessionsPerWeek;
  final int sessionsPerBlock;
  final int validityDays;

  @override
  List<Object?> get props => [id, name, group, sessionMinutes, sessionsPerWeek, sessionsPerBlock, validityDays];
}

/// What the teacher recommends, with today's price in the family's region (classic look only).
class Recommendation extends Equatable {
  const Recommendation({this.plan, this.withdrawn = false, this.perWeek, this.price, this.region});

  factory Recommendation.fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return const Recommendation();
    final region = json['region'];
    return Recommendation(
      plan: RecommendedPlan.fromJson(json['plan']),
      withdrawn: json['plan_withdrawn'] == true,
      perWeek: (json['per_week'] as num?)?.toInt(),
      price: Price.fromJson(json['price']),
      region: region is Map<String, dynamic> ? region['name'] as String? : null,
    );
  }

  final RecommendedPlan? plan;

  /// The academy no longer offers the plan the teacher chose.
  final bool withdrawn;

  /// Lessons a week the teacher recommends (else the plan's own).
  final int? perWeek;
  final Price? price;
  final String? region;

  bool get any => plan != null || withdrawn || perWeek != null;

  @override
  List<Object?> get props => [plan, withdrawn, perWeek, price, region];
}

/// The open offer: what it takes off, until when, the price after it and the saving.
class TrialOffer extends Equatable {
  const TrialOffer({required this.percent, this.endsAt, this.price, this.saving, this.familyDiscountBigger = false});

  static TrialOffer? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    return TrialOffer(
      percent: json['percent'] as num? ?? 0,
      endsAt: LocalTime.fromJson(json['ends_at']),
      price: Price.fromJson(json['price']),
      saving: Price.fromJson(json['saving']),
      familyDiscountBigger: json['family_discount_bigger'] == true,
    );
  }

  final num percent;
  final LocalTime? endsAt;
  final Price? price;
  final Price? saving;

  /// The family discount is bigger, so it applies instead (they never add up).
  final bool familyDiscountBigger;

  /// "10" or "12.5".
  String get percentText => percent == percent.roundToDouble() ? '${percent.round()}' : '$percent';

  @override
  List<Object?> get props => [percent, endsAt, price, saving, familyDiscountBigger];
}

/// «ابدأ بهذه الباقة» (J3, classic look only): whether the family may start
/// the course on its own now and what the start flow opens with, else why
/// not in the API's words with its reason (a course in the subject already,
/// the academy enrolling itself).
class ReportStart extends Equatable {
  const ReportStart({
    required this.available,
    this.whyNot,
    this.reason,
    this.learnerId,
    this.subjectId,
    this.planId,
    this.teacherId,
    this.enrollmentId,
  });

  static ReportStart? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final why = json['why_not'] as String?;
    return ReportStart(
      available: json['available'] == true,
      whyNot: why == null || why.trim().isEmpty ? null : why.trim(),
      reason: json['reason'] as String?,
      learnerId: json['learner_id'] as String?,
      subjectId: json['subject_id'] as String?,
      planId: json['plan_id'] as String?,
      teacherId: json['teacher_id'] as String?,
      enrollmentId: json['enrollment_id'] as String?,
    );
  }

  final bool available;
  final String? whyNot;

  /// self_serve_off, has_course, course_pending_own, course_pending, course_paused, course_ending.
  final String? reason;
  final String? learnerId;
  final String? subjectId;

  /// The recommended plan while the academy offers it.
  final String? planId;

  /// The trial's teacher while they still take the learner.
  final String? teacherId;

  /// The learner's course in the subject, when that is why not.
  final String? enrollmentId;

  /// A course the family started is waiting for its first payment: it may be paid, or called off.
  bool get ownPending => reason == 'course_pending_own' && enrollmentId != null;

  @override
  List<Object?> get props => [available, whyNot, reason, learnerId, subjectId, planId, teacherId, enrollmentId];
}

/// The teacher's card on the academy's page, when it shows one.
class TeacherCard extends Equatable {
  const TeacherCard({required this.name, this.bio, this.photoUrl});

  static TeacherCard? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    String? text(String k) => switch (json[k]) {
      final String s when s.trim().isNotEmpty => s,
      _ => null,
    };
    return TeacherCard(name: json['name'] as String? ?? '', bio: text('bio'), photoUrl: text('photo_url'));
  }

  final String name;
  final String? bio;
  final String? photoUrl;

  @override
  List<Object?> get props => [name, bio, photoUrl];
}

/// A free trial's report for the family (`GET family/trials/{session}`):
/// what the teacher found and recommends, today's price, the offer while it
/// is open, a course already begun, and the second trial with another teacher.
class TrialReport extends Equatable {
  const TrialReport({
    required this.id,
    required this.learner,
    required this.subject,
    required this.teacher,
    required this.level,
    required this.stage,
    this.heldAt,
    this.second = false,
    this.card,
    this.strengths,
    this.toWorkOn,
    this.note,
    this.recommendation = const Recommendation(),
    this.courseStatus,
    this.offerState = OfferState.none,
    this.offer,
    this.next,
    this.start,
  });

  factory TrialReport.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? json;
    final subject = data['subject'];
    final teacher = data['teacher'];
    final course = data['course'];
    String? text(String k) => switch (data[k]) {
      final String s when s.trim().isNotEmpty => s.trim(),
      _ => null,
    };
    return TrialReport(
      id: data['id'] as String,
      learner: data['learner'] as String? ?? '',
      subject: subject is Map<String, dynamic> ? subject['name'] as String? ?? '' : '',
      heldAt: LocalTime.fromJson(data['held_at']),
      second: data['second'] == true,
      teacher: teacher is Map<String, dynamic> ? teacher['name'] as String? ?? '' : '',
      card: teacher is Map<String, dynamic> ? TeacherCard.fromJson(teacher['card']) : null,
      level: data['level'] as String? ?? '',
      strengths: text('strengths'),
      toWorkOn: text('to_work_on'),
      note: text('note'),
      recommendation: Recommendation.fromJson(data['recommendation']),
      courseStatus: course is Map<String, dynamic> ? course['status'] as String? : null,
      offerState: OfferState.of(data['offer_state']),
      offer: TrialOffer.fromJson(data['offer']),
      next: NextTrial.fromJson(data['next_trial']),
      start: ReportStart.fromJson(data['start']),
      stage: json['viewer_stage'] as String? ?? data['viewer_stage'] as String? ?? 'classic',
    );
  }

  final String id;

  /// The learner's first name.
  final String learner;
  final String subject;
  final LocalTime? heldAt;

  /// This was the free second trial, with another teacher.
  final bool second;
  final String teacher;
  final TeacherCard? card;
  final String level;
  final String? strengths;
  final String? toWorkOn;
  final String? note;
  final Recommendation recommendation;

  /// pending_payment, active or paused: the learner has begun a course in the subject.
  final String? courseStatus;
  final OfferState offerState;
  final TrialOffer? offer;
  final NextTrial? next;

  /// Starting the course from the report (classic look only; null from an earlier server).
  final ReportStart? start;

  /// kids, middle, teen or classic.
  final String stage;

  /// Money, the offer and booking are for the classic look alone.
  bool get classic => stage == 'classic';
  bool get kids => stage == 'kids';
  bool get started => courseStatus != null || offerState == OfferState.started;

  /// The offer while it is open and its end has not come by the phone's clock either.
  bool openAt(DateTime now) =>
      classic && offerState == OfferState.open && offer != null && (offer!.endsAt == null || offer!.endsAt!.utc.isAfter(now.toUtc()));

  /// What the clock alone changes on the report: the offer's end.
  Iterable<DateTime> get clockMoments => [?offer?.endsAt?.utc];

  @override
  List<Object?> get props => [
    id,
    learner,
    subject,
    heldAt,
    second,
    teacher,
    card,
    level,
    strengths,
    toWorkOn,
    note,
    recommendation,
    courseStatus,
    offerState,
    offer,
    next,
    start,
    stage,
  ];
}

/// The new trial booked from a report (`POST family/trials/{session}/book`).
class NextTrialBooked extends Equatable {
  const NextTrialBooked({required this.id, this.startsAt, this.subject = '', this.teacher, this.minutes});

  factory NextTrialBooked.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? const {};
    final teacher = data['teacher'] as String?;
    final minutes = (data['minutes'] as num?)?.toInt();
    return NextTrialBooked(
      id: data['id'] as String? ?? '',
      startsAt: LocalTime.fromJson(data['starts_at']),
      subject: data['subject'] as String? ?? '',
      teacher: teacher == null || teacher.isEmpty ? null : teacher,
      minutes: minutes == null || minutes <= 0 ? null : minutes,
    );
  }

  final String id;
  final LocalTime? startsAt;
  final String subject;
  final String? teacher;
  final int? minutes;

  @override
  List<Object?> get props => [id, startsAt, subject, teacher, minutes];
}
