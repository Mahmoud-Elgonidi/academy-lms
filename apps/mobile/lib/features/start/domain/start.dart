import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';

/// An amount as the API wrote it for the reader ("300.00 ر.س"), with its minor units.
class Amount extends Equatable {
  const Amount({required this.text, required this.minor, this.currency = ''});

  static Amount? fromJson(Object? json, {String currency = ''}) {
    if (json is! Map<String, dynamic>) return null;
    return Amount(
      text: json['amount'] as String? ?? '',
      minor: (json['amount_minor'] as num?)?.toInt() ?? 0,
      currency: json['currency'] as String? ?? currency,
    );
  }

  final String text;
  final int minor;
  final String currency;

  @override
  List<Object?> get props => [text, minor, currency];
}

String? _text(Object? v) => v is String && v.trim().isNotEmpty ? v.trim() : null;
int _int(Object? v) => (v as num?)?.toInt() ?? 0;
List<Map<String, dynamic>> _list(Object? v) => [for (final x in v as List<dynamic>? ?? const []) x as Map<String, dynamic>];

/// Someone the family may start a course for: a child, or the adult themself.
class StartLearner extends Equatable {
  const StartLearner({required this.id, required this.name, this.isSelf = false, this.consentWaiting = false});

  factory StartLearner.fromJson(Map<String, dynamic> j) => StartLearner(
    id: j['id'] as String,
    name: j['name'] as String? ?? '',
    isSelf: j['is_self'] == true,
    consentWaiting: j['consent_waiting'] == true,
  );

  final String id;
  final String name;
  final bool isSelf;

  /// A child whose account waits for a guardian's consent: asked before the course starts.
  final bool consentWaiting;

  @override
  List<Object?> get props => [id, name, isSelf, consentWaiting];
}

/// A subject the academy teaches, the learner's course in it, and whether a new one may start.
class StartSubject extends Equatable {
  const StartSubject({required this.id, required this.name, this.canStart = true, this.whyNot, this.enrollmentId, this.courseStatus});

  factory StartSubject.fromJson(Map<String, dynamic> j) {
    final course = j['course'];
    return StartSubject(
      id: j['id'] as String,
      name: j['name'] as String? ?? '',
      canStart: j['can_start'] != false,
      whyNot: _text(j['why_not']),
      enrollmentId: course is Map<String, dynamic> ? course['enrollment_id'] as String? : null,
      courseStatus: course is Map<String, dynamic> ? course['status'] as String? : null,
    );
  }

  final String id;
  final String name;
  final bool canStart;
  final String? whyNot;
  final String? enrollmentId;

  /// pending_payment, active or paused.
  final String? courseStatus;

  bool get waitingPayment => courseStatus == 'pending_payment';

  @override
  List<Object?> get props => [id, name, canStart, whyNot, enrollmentId, courseStatus];
}

/// A discount on the first invoice (the sibling discount or the trial's offer): a negative amount.
class Discount extends Equatable {
  const Discount({required this.label, required this.amount});

  factory Discount.fromJson(Map<String, dynamic> j) => Discount(
    label: j['label'] as String? ?? '',
    amount: Amount.fromJson(j) ?? const Amount(text: '', minor: 0),
  );

  final String label;
  final Amount amount;

  @override
  List<Object?> get props => [label, amount];
}

/// A plan as the family chooses it, priced in the payer's region.
class StartPlan extends Equatable {
  const StartPlan({
    required this.id,
    required this.name,
    required this.price,
    this.group = false,
    this.shared = false,
    this.sessionMinutes = 0,
    this.perWeek = 0,
    this.perBlock = 0,
    this.validityDays = 0,
    this.renewsAt = 0,
    this.perLesson,
    this.discounts = const [],
    this.firstTotal,
    this.offerApplied = false,
  });

  factory StartPlan.fromJson(Map<String, dynamic> j) {
    final first = j['first_invoice'] as Map<String, dynamic>? ?? const {};
    return StartPlan(
      id: j['id'] as String,
      name: j['name'] as String? ?? '',
      group: j['format'] == 'group',
      shared: j['shared'] == true,
      sessionMinutes: _int(j['session_minutes']),
      perWeek: _int(j['sessions_per_week']),
      perBlock: _int(j['sessions_per_block']),
      validityDays: _int(j['validity_days']),
      renewsAt: _int(j['renews_at_lessons_left']),
      price: Amount.fromJson(j['price']) ?? const Amount(text: '', minor: 0),
      perLesson: Amount.fromJson(j['per_lesson']),
      discounts: [for (final d in _list(first['discounts'])) Discount.fromJson(d)],
      firstTotal: Amount.fromJson(first['total']),
      offerApplied: first['offer_applied'] == true,
    );
  }

  final String id;
  final String name;

  /// A seat in a group (else one student a lesson).
  final bool group;

  /// A shared package: one balance for the learner's courses on it.
  final bool shared;
  final int sessionMinutes;
  final int perWeek;
  final int perBlock;
  final int validityDays;

  /// The next block's invoice comes when this many lessons are left.
  final int renewsAt;

  /// The block's price: sent back as it was seen.
  final Amount price;
  final Amount? perLesson;

  /// What comes off the first invoice for this learner.
  final List<Discount> discounts;

  /// The first invoice before VAT, after its discounts.
  final Amount? firstTotal;

  /// The trial's offer is the discount here.
  final bool offerApplied;

  /// The first invoice comes to less than the plan's price.
  bool get discounted => firstTotal != null && firstTotal!.minor < price.minor;

  StartPlan withPrice(Amount next) => StartPlan(
    id: id,
    name: name,
    price: next,
    group: group,
    shared: shared,
    sessionMinutes: sessionMinutes,
    perWeek: perWeek,
    perBlock: perBlock,
    validityDays: validityDays,
    renewsAt: renewsAt,
    perLesson: perLesson,
  );

  @override
  List<Object?> get props => [
    id,
    name,
    price,
    group,
    shared,
    sessionMinutes,
    perWeek,
    perBlock,
    validityDays,
    renewsAt,
    discounts,
    firstTotal,
  ];
}

/// A teacher who takes the learner in the subject.
class StartTeacher extends Equatable {
  const StartTeacher({
    required this.id,
    required this.name,
    this.bio,
    this.photoUrl,
    this.trialTeacher = false,
    this.private = true,
    this.groups = 0,
  });

  factory StartTeacher.fromJson(Map<String, dynamic> j) => StartTeacher(
    id: j['id'] as String,
    name: j['name'] as String? ?? '',
    bio: _text(j['bio']),
    photoUrl: _text(j['photo_url']),
    trialTeacher: j['trial_teacher'] == true,
    private: j['private'] != false,
    groups: _int(j['groups']),
  );

  final String id;
  final String name;
  final String? bio;
  final String? photoUrl;

  /// The teacher of the learner's free trial in this subject.
  final bool trialTeacher;

  /// Takes new students for one-to-one lessons.
  final bool private;
  final int groups;

  @override
  List<Object?> get props => [id, name, bio, photoUrl, trialTeacher, private, groups];
}

/// What may be started for a learner (`GET family/start/options`).
class StartOptions extends Equatable {
  const StartOptions({
    required this.selfServe,
    this.whyOff,
    this.learnerId,
    this.learners = const [],
    this.subjects = const [],
    this.region,
    this.plans = const [],
    this.teachers = const [],
    this.offerOpen = false,
    this.offerPercent,
    this.offerEndsAt,
    this.trialTeacherAvailable,
    this.unpaidCancelHours = 0,
  });

  factory StartOptions.fromJson(Map<String, dynamic> json) {
    final d = json['data'] as Map<String, dynamic>? ?? json;
    final offer = d['offer'] as Map<String, dynamic>?;
    final trial = d['trial'] as Map<String, dynamic>?;
    final region = d['region'] as Map<String, dynamic>?;
    final payment = d['payment'] as Map<String, dynamic>?;
    return StartOptions(
      selfServe: d['self_serve'] != false,
      whyOff: _text(d['why_off']),
      learnerId: (d['learner'] as Map<String, dynamic>?)?['id'] as String?,
      learners: [for (final l in _list(d['learners'])) StartLearner.fromJson(l)],
      subjects: [for (final s in _list(d['subjects'])) StartSubject.fromJson(s)],
      region: region?['name'] as String?,
      plans: [for (final p in _list(d['plans'])) StartPlan.fromJson(p)],
      teachers: [for (final t in _list(d['teachers'])) StartTeacher.fromJson(t)],
      offerOpen: offer?['state'] == 'open',
      offerPercent: offer?['percent'] as num?,
      offerEndsAt: LocalTime.fromJson(offer?['ends_at']),
      trialTeacherAvailable: trial == null ? null : trial['teacher_available'] == true,
      unpaidCancelHours: _int(payment?['unpaid_cancel_hours']),
    );
  }

  final bool selfServe;

  /// Why the academy enrols itself, and how to reach it.
  final String? whyOff;
  final String? learnerId;
  final List<StartLearner> learners;
  final List<StartSubject> subjects;
  final String? region;
  final List<StartPlan> plans;
  final List<StartTeacher> teachers;
  final bool offerOpen;
  final num? offerPercent;
  final LocalTime? offerEndsAt;

  /// Whether the free trial's teacher still takes the learner (null: no trial).
  final bool? trialTeacherAvailable;

  /// A lesson still unpaid this many hours before it starts is cancelled (0: never).
  final int unpaidCancelHours;

  String get offerPercentText {
    final p = offerPercent ?? 0;
    return p == p.roundToDouble() ? '${p.round()}' : '$p';
  }

  StartPlan? plan(String? id) => plans.where((p) => p.id == id).firstOrNull;
  StartSubject? subject(String? id) => subjects.where((s) => s.id == id).firstOrNull;
  StartTeacher? teacher(String? id) => teachers.where((t) => t.id == id).firstOrNull;
  StartLearner? learner(String? id) => learners.where((l) => l.id == id).firstOrNull;

  /// The teachers who take the learner one to one, the trial's first.
  List<StartTeacher> get privateTeachers => teachers.where((t) => t.private).toList();

  @override
  List<Object?> get props => [
    selfServe,
    whyOff,
    learnerId,
    learners,
    subjects,
    region,
    plans,
    teachers,
    offerOpen,
    offerPercent,
    offerEndsAt,
    trialTeacherAvailable,
    unpaidCancelHours,
  ];
}

/// A weekly time free in all of the first four weeks, on the family's clock.
class WeeklyTime extends Equatable {
  const WeeklyTime({required this.key, required this.weekday, required this.start, this.end = '', this.label = '', this.first});

  factory WeeklyTime.fromJson(Map<String, dynamic> j, {int weekday = 0}) => WeeklyTime(
    key: j['key'] as String? ?? '',
    weekday: (j['weekday'] as num?)?.toInt() ?? weekday,
    start: j['start'] as String? ?? '',
    end: j['end'] as String? ?? '',
    label: j['label'] as String? ?? '',
    first: LocalTime.fromJson(j['first']),
  );

  /// Sent back in times[] to start.
  final String key;

  /// ISO weekday (1 Monday … 7 Sunday).
  final int weekday;

  /// "17:00" on the family's clock.
  final String start;
  final String end;

  /// "الثلاثاء 6:00 م", as the API wrote it.
  final String label;

  /// This weekly time's first lesson.
  final LocalTime? first;

  @override
  List<Object?> get props => [key, weekday, start, end, label, first];
}

class SlotDay extends Equatable {
  const SlotDay({required this.weekday, required this.name, required this.times});

  factory SlotDay.fromJson(Map<String, dynamic> j) {
    final weekday = _int(j['weekday']);
    return SlotDay(
      weekday: weekday,
      name: j['name'] as String? ?? '',
      times: [for (final t in _list(j['times'])) WeeklyTime.fromJson(t, weekday: weekday)],
    );
  }

  final int weekday;
  final String name;
  final List<WeeklyTime> times;

  @override
  List<Object?> get props => [weekday, name, times];
}

/// One of an open group's weekly times.
class GroupTime extends Equatable {
  const GroupTime({required this.weekday, required this.start, required this.label});

  factory GroupTime.fromJson(Map<String, dynamic> j) =>
      GroupTime(weekday: _int(j['weekday']), start: j['start'] as String? ?? '', label: j['label'] as String? ?? '');

  final int weekday;
  final String start;
  final String label;

  @override
  List<Object?> get props => [weekday, start, label];
}

/// An open group of the plan's shape with a seat for the learner.
class OpenGroup extends Equatable {
  const OpenGroup({
    required this.id,
    required this.name,
    required this.teacher,
    required this.forming,
    required this.capacity,
    required this.taken,
    required this.free,
    this.minToStart,
    this.times = const [],
    this.next,
    this.held = 0,
  });

  factory OpenGroup.fromJson(Map<String, dynamic> j) {
    final seats = j['seats'] as Map<String, dynamic>? ?? const {};
    final teacher = j['teacher'] as Map<String, dynamic>? ?? const {};
    return OpenGroup(
      id: j['id'] as String,
      name: j['name'] as String? ?? '',
      teacher: teacher['name'] as String? ?? '',
      forming: j['status'] == 'forming',
      capacity: _int(seats['capacity']),
      taken: _int(seats['taken']),
      free: _int(seats['free']),
      minToStart: (j['min_to_start'] as num?)?.toInt(),
      times: [for (final t in _list(j['times'])) GroupTime.fromJson(t)],
      next: LocalTime.fromJson(j['next']),
      held: _int(j['held']),
    );
  }

  final String id;
  final String name;
  final String teacher;

  /// Starts once it has [minToStart] students.
  final bool forming;
  final int capacity;
  final int taken;
  final int free;
  final int? minToStart;
  final List<GroupTime> times;

  /// Its next lesson, when it is running.
  final LocalTime? next;

  /// Lessons it held so far.
  final int held;

  /// Students still needed before it starts (with the learner counted).
  int get toStart => forming && minToStart != null ? (minToStart! - taken - 1).clamp(0, 99) : 0;

  @override
  List<Object?> get props => [id, name, teacher, forming, capacity, taken, free, minToStart, times, next, held];
}

/// A teacher's weekly times, or the open groups, for a plan (`GET family/start/slots`).
class StartSlots extends Equatable {
  const StartSlots({
    this.group = false,
    this.perWeek = 1,
    this.days = const [],
    this.suggested = const [],
    this.firstDay,
    this.groups = const [],
  });

  factory StartSlots.fromJson(Map<String, dynamic> json) {
    final d = json['data'] as Map<String, dynamic>? ?? json;
    return StartSlots(
      group: d['format'] == 'group',
      perWeek: _int(d['per_week']).clamp(1, 7),
      days: [for (final x in _list(d['days'])) SlotDay.fromJson(x)],
      suggested: [for (final k in d['suggested'] as List<dynamic>? ?? const []) '$k'],
      firstDay: LocalTime.fromJson(d['first_day']),
      groups: [for (final g in _list(d['groups'])) OpenGroup.fromJson(g)],
    );
  }

  final bool group;
  final int perWeek;
  final List<SlotDay> days;

  /// Keys of a pattern worth suggesting (empty when the teacher has too few days free).
  final List<String> suggested;
  final LocalTime? firstDay;
  final List<OpenGroup> groups;

  WeeklyTime? time(String key) {
    for (final d in days) {
      for (final t in d.times) {
        if (t.key == key) return t;
      }
    }
    return null;
  }

  @override
  List<Object?> get props => [group, perWeek, days, suggested, firstDay, groups];
}

/// A line of the payer's invoice; a discount is negative.
class PayLine extends Equatable {
  const PayLine({required this.kind, required this.description, required this.amount, this.quantity = 1});

  factory PayLine.fromJson(Map<String, dynamic> j) => PayLine(
    kind: j['kind'] as String? ?? '',
    description: j['description'] as String? ?? '',
    quantity: _int(j['quantity']),
    amount: Amount.fromJson(j) ?? const Amount(text: '', minor: 0),
  );

  /// block, extra or discount.
  final String kind;
  final String description;
  final int quantity;
  final Amount amount;

  bool get isDiscount => kind == 'discount';

  @override
  List<Object?> get props => [kind, description, quantity, amount];
}

/// An invoice as its payer reads it before paying (`GET family/invoices/{id}`, and the start's answer).
class PayerInvoice extends Equatable {
  const PayerInvoice({
    required this.id,
    required this.number,
    required this.status,
    required this.lines,
    required this.subtotal,
    required this.total,
    required this.outstanding,
    this.vatRate = 0,
    this.vatIncluded = false,
    this.vat,
    this.paid,
    this.dueAt,
    this.couponCode,
    this.couponPersonal = false,
    this.couponOpen = false,
    this.couponWhyNot,
    this.payUrl,
    this.documentUrl,
  });

  factory PayerInvoice.fromJson(Map<String, dynamic> j) {
    final vat = j['vat'] as Map<String, dynamic>? ?? const {};
    final coupon = j['coupon'] as Map<String, dynamic>?;
    final entry = j['coupon_entry'] as Map<String, dynamic>? ?? const {};
    final currency = j['currency'] as String? ?? '';
    return PayerInvoice(
      id: j['id'] as String,
      number: j['number'] as String? ?? '',
      status: j['status'] as String? ?? 'issued',
      lines: [for (final l in _list(j['lines'])) PayLine.fromJson(l)],
      subtotal: Amount.fromJson(j['subtotal'], currency: currency) ?? const Amount(text: '', minor: 0),
      vatRate: vat['rate'] as num? ?? 0,
      vatIncluded: vat['included'] == true,
      vat: Amount.fromJson(vat, currency: currency),
      total: Amount.fromJson(j['total'], currency: currency) ?? const Amount(text: '', minor: 0),
      paid: Amount.fromJson(j['paid'], currency: currency),
      outstanding: Amount.fromJson(j['outstanding'], currency: currency) ?? const Amount(text: '', minor: 0),
      dueAt: LocalTime.fromJson(j['due_at']),
      couponCode: coupon?['code'] as String?,
      couponPersonal: coupon?['personal'] == true,
      couponOpen: entry['open'] == true,
      couponWhyNot: _text(entry['why_not']),
      payUrl: _text(j['pay_url']),
      documentUrl: _text(j['document_url']),
    );
  }

  final String id;
  final String number;

  /// issued, partly_paid, overdue, paid or void.
  final String status;
  final List<PayLine> lines;
  final Amount subtotal;
  final num vatRate;
  final bool vatIncluded;
  final Amount? vat;
  final Amount total;
  final Amount? paid;
  final Amount outstanding;
  final LocalTime? dueAt;
  final String? couponCode;

  /// The trial's offer: it stays on.
  final bool couponPersonal;
  final bool couponOpen;
  final String? couponWhyNot;
  final String? payUrl;
  final String? documentUrl;

  bool get isPaid => status == 'paid' || (outstanding.minor <= 0 && status != 'void');
  bool get isVoid => status == 'void';
  bool get hasVat => vatRate > 0 && (vat?.minor ?? 0) > 0;

  String get vatRateText => vatRate == vatRate.roundToDouble() ? '${vatRate.round()}' : '$vatRate';

  @override
  List<Object?> get props => [id, number, status, lines, subtotal, vatRate, vat, total, outstanding, couponCode, couponOpen, couponWhyNot];
}

/// The latest online payment the payer began on an invoice.
enum CheckoutStatus {
  paid,
  open,
  failed,
  expired,
  refundDue;

  static CheckoutStatus? of(Object? v) => switch (v) {
    'paid' => paid,
    'open' => open,
    'failed' => failed,
    'expired' => expired,
    'refund_due' => refundDue,
    _ => null,
  };
}

class Checkout extends Equatable {
  const Checkout({required this.id, required this.status, this.text, this.hint});

  static Checkout? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final status = CheckoutStatus.of(json['status']);
    if (status == null) return null;
    return Checkout(id: json['id'] as String? ?? '', status: status, text: _text(json['text']), hint: _text(json['hint']));
  }

  final String id;
  final CheckoutStatus status;
  final String? text;
  final String? hint;

  @override
  List<Object?> get props => [id, status, text, hint];
}

/// The invoice and the latest online payment on it.
class InvoiceView extends Equatable {
  const InvoiceView({required this.invoice, this.checkout});

  factory InvoiceView.fromJson(Map<String, dynamic> json) =>
      InvoiceView(invoice: PayerInvoice.fromJson(json['data'] as Map<String, dynamic>), checkout: Checkout.fromJson(json['checkout']));

  final PayerInvoice invoice;
  final Checkout? checkout;

  @override
  List<Object?> get props => [invoice, checkout];
}

/// A course the family just started (`POST family/courses`).
class StartedCourse extends Equatable {
  const StartedCourse({
    required this.enrollmentId,
    required this.status,
    this.learner = '',
    this.subject = '',
    this.teacher = '',
    this.planName = '',
    this.group = false,
    this.weeklyTimes = const [],
    this.firstLesson,
    this.joinedPackage = false,
    this.groupForming = false,
    this.groupStarted = false,
    this.invoice,
    this.payUrl,
    this.offerState = 'none',
    this.offerMessage,
    this.payBy,
    this.payText,
    this.renews,
    this.canCancel = false,
  });

  factory StartedCourse.fromJson(Map<String, dynamic> json) {
    final d = json['data'] as Map<String, dynamic>? ?? json;
    String name(String k) => (d[k] as Map<String, dynamic>?)?['name'] as String? ?? '';
    final plan = d['plan'] as Map<String, dynamic>?;
    final group = d['group'] as Map<String, dynamic>?;
    final offer = d['offer'] as Map<String, dynamic>? ?? const {};
    final payment = d['payment'] as Map<String, dynamic>? ?? const {};
    final invoice = d['invoice'];
    return StartedCourse(
      enrollmentId: d['enrollment_id'] as String,
      status: d['status'] as String? ?? 'pending_payment',
      learner: name('learner'),
      subject: name('subject'),
      teacher: name('teacher'),
      planName: plan?['name'] as String? ?? '',
      group: plan?['format'] == 'group' || group != null,
      weeklyTimes: [for (final t in _list(d['weekly_times'])) t['label'] as String? ?? ''],
      firstLesson: LocalTime.fromJson(d['first_lesson']),
      joinedPackage: d['joined_package'] == true,
      groupForming: group?['status'] == 'forming',
      groupStarted: group?['started'] == true,
      invoice: invoice is Map<String, dynamic> ? PayerInvoice.fromJson(invoice) : null,
      payUrl: _text(d['pay_url']),
      offerState: offer['state'] as String? ?? 'none',
      offerMessage: _text(offer['message']),
      payBy: LocalTime.fromJson(payment['pay_by']),
      payText: _text(payment['text']),
      renews: _text(payment['renews']),
      canCancel: d['can_cancel'] == true,
    );
  }

  final String enrollmentId;

  /// pending_payment until the first invoice is paid; active at once in a paid shared package.
  final String status;
  final String learner;
  final String subject;
  final String teacher;
  final String planName;
  final bool group;

  /// The weekly times as the API wrote them ("الثلاثاء 6:00 م").
  final List<String> weeklyTimes;
  final LocalTime? firstLesson;
  final bool joinedPackage;
  final bool groupForming;
  final bool groupStarted;
  final PayerInvoice? invoice;
  final String? payUrl;

  /// applied, ended, family_discount_bigger, not_for_plan, package or none.
  final String offerState;
  final String? offerMessage;
  final LocalTime? payBy;
  final String? payText;
  final String? renews;
  final bool canCancel;

  bool get active => status == 'active';

  @override
  List<Object?> get props => [enrollmentId, status, invoice, offerState, canCancel];
}

/// Why a start was refused (`start-refused`, or `guardian-consent-needed`): what the screen does about it.
enum StartReason {
  timeTaken,
  priceChanged,
  consent,
  courseExists,
  coursePendingOwn,
  coursePending,
  teacherUnavailable,
  groupGone,
  planGone,
  timesWrong,
  closed,
  other;

  static StartReason of(String type, Object? reason) {
    if (type == 'guardian-consent-needed') return consent;
    return switch (reason) {
      'time_taken' => timeTaken,
      'price_changed' => priceChanged,
      'consent' => consent,
      'course_pending_own' => coursePendingOwn,
      'course_pending' => coursePending,
      'has_course' || 'course_paused' || 'course_ending' => courseExists,
      'teacher_unavailable' => teacherUnavailable,
      'group_full' || 'group_unavailable' => groupGone,
      'no_plan' || 'no_price' => planGone,
      'times_count' || 'times_needed' || 'group_needed' => timesWrong,
      'family_only' || 'self_serve_off' || 'academy_full' => closed,
      _ => other,
    };
  }
}
