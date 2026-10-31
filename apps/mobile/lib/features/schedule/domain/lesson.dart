import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';

/// One lesson as the apps get it (`LessonPresenter`): times on the viewer's
/// clock, labels in their language, and what they may do with it.
class Lesson extends Equatable {
  const Lesson({
    required this.id,
    required this.startsAt,
    required this.endsAt,
    required this.minutes,
    required this.subject,
    required this.withName,
    required this.status,
    required this.statusLabel,
    required this.type,
    required this.joinOpen,
    this.typeLabel,
    this.unfunded = false,
    this.joinOpensAt,
    this.substituteFor,
    this.canCancel = false,
    this.canRefuseSubstitute = false,
    this.cancelledBecause,
    this.dispute,
    this.canDispute = false,
    this.disputeUntil,
    this.disputeReasons = const [],
    this.format = 'private',
    this.group,
  });

  factory Lesson.fromJson(Map<String, dynamic> json) {
    final join = json['join'] as Map<String, dynamic>? ?? const {};
    return Lesson(
      id: json['id'] as String,
      startsAt: LocalTime.fromJson(json['starts_at'])!,
      endsAt: LocalTime.fromJson(json['ends_at'])!,
      minutes: (json['minutes'] as num).toInt(),
      subject: json['subject'] as String? ?? '',
      withName: json['with'] as String? ?? '',
      status: json['status'] as String,
      statusLabel: json['status_label'] as String? ?? '',
      type: json['type'] as String? ?? 'regular',
      typeLabel: json['type_label'] as String?,
      unfunded: json['unfunded'] == true,
      joinOpen: join['open'] == true,
      joinOpensAt: LocalTime.fromJson(join['opens_at']),
      substituteFor: json['substitute_for'] as String?,
      canCancel: json['can_cancel'] == true,
      canRefuseSubstitute: json['can_refuse_substitute'] == true,
      cancelledBecause: json['cancelled_because'] as String?,
      dispute: LessonDispute.fromJson(json['dispute']),
      canDispute: json['can_dispute'] == true,
      disputeUntil: LocalTime.fromJson(json['dispute_until']),
      disputeReasons: [
        for (final r in json['dispute_reasons'] as List<dynamic>? ?? const [])
          (key: (r as Map<String, dynamic>)['key'] as String, label: r['label'] as String),
      ],
      format: json['format'] as String? ?? (json['group'] is Map ? 'group' : 'private'),
      group: LessonGroup.fromJson(json['group']),
    );
  }

  final String id;
  final LocalTime startsAt;
  final LocalTime endsAt;
  final int minutes;
  final String subject;

  /// The teacher (family) or the student (teacher).
  final String withName;

  /// scheduled, in_progress, completed, student_absent, teacher_absent, needs_review, cancelled.
  final String status;
  final String statusLabel;

  /// regular, trial, makeup, extra, assessment.
  final String type;
  final String? typeLabel;
  final bool unfunded;
  final bool joinOpen;
  final LocalTime? joinOpensAt;

  /// The family's own teacher, while a substitute has the lesson.
  final String? substituteFor;
  final bool canCancel;
  final bool canRefuseSubstitute;
  final String? cancelledBecause;

  /// The viewer's own dispute of what the lesson was recorded as, and the academy's answer.
  final LessonDispute? dispute;

  /// Recorded as held or missed, within 48 hours of its end: the viewer may dispute it with one of [disputeReasons].
  final bool canDispute;
  final LocalTime? disputeUntil;
  final List<({String key, String label})> disputeReasons;

  /// private (one student) or group.
  final String format;

  /// A group lesson's seats and who else is in it, as the viewer may see them; null for a one-to-one lesson.
  final LessonGroup? group;

  bool get isGroup => format == 'group';

  bool get ahead => status == 'scheduled' || status == 'in_progress';

  /// Still to come or on now: booked, and not yet over by the clock.
  bool get upcoming => ahead && endsAt.utc.isAfter(DateTime.now().toUtc());
  bool get cancelled => status == 'cancelled';
  bool get tookPlace => status == 'completed' || status == 'student_absent';

  @override
  List<Object?> get props => [id, status, joinOpen, canCancel, unfunded, canDispute, dispute, format, group];

  /// The room opens 10 minutes before and closes 10 minutes after (Classroom).
  static const roomCloses = Duration(minutes: 10);

  /// The moments the clock alone changes what this lesson shows: the room opening, the start, the room closing.
  Iterable<DateTime> get clockMoments => [?joinOpensAt?.utc, startsAt.utc, endsAt.utc.add(roomCloses)];
}

/// A group lesson as the viewer may see it (`LessonPresenter::group`): its
/// seats, and the others in it. A family reads its classmates' first names
/// ([classmates]; null in the kids' look, which reads only [classmatesCount])
/// and what its own seat came to ([seat]); the teacher reads everyone seated
/// ([students]).
class LessonGroup extends Equatable {
  const LessonGroup({
    required this.capacity,
    required this.seatsTaken,
    this.classmates,
    this.classmatesCount,
    this.seat,
    this.seatLabel,
    this.seatNumber,
    this.students,
  });

  static LessonGroup? fromJson(Object? raw) {
    if (raw is! Map<String, dynamic>) return null;
    int number(Object? v) => v is num ? v.toInt() : int.tryParse('$v') ?? 0;
    final classmates = raw['classmates'];
    final students = raw['students'];
    return LessonGroup(
      capacity: number(raw['capacity']),
      seatsTaken: number(raw['seats_taken']),
      classmates: classmates is List ? [for (final n in classmates) '$n'] : null,
      classmatesCount: (raw['classmates_count'] as num?)?.toInt(),
      seat: raw['seat'] as String?,
      seatLabel: raw['seat_label'] as String?,
      seatNumber: (raw['seat_number'] as num?)?.toInt(),
      students: students is List
          ? [for (final s in students.whereType<Map<String, dynamic>>()) (id: '${s['id'] ?? ''}', name: '${s['name'] ?? ''}')]
          : null,
    );
  }

  final int capacity;
  final int seatsTaken;

  /// The family: the classmates' first names (null in the kids' look).
  final List<String>? classmates;

  /// The family: how many classmates (the kids' look reads only this).
  final int? classmatesCount;

  /// The family: what the learner's own seat came to (attended, absent, excused, makeup), once decided.
  final String? seat;
  final String? seatLabel;

  /// The family: the learner's seat in the group by when they joined ("seat 4 of 5": 4); null for the teacher.
  final int? seatNumber;

  /// The teacher: every student seated; null for a family.
  final List<({String id, String name})>? students;

  /// The teacher's view (it lists everyone seated).
  bool get forTeacher => students != null;

  /// The learner's own seat is excused ("I won't attend").
  bool get excused => seat == 'excused';

  @override
  List<Object?> get props => [capacity, seatsTaken, classmates, classmatesCount, seat, seatNumber, students];
}

/// A course that is a seat in a group (the journey's courses, the balance):
/// its group, its seats and the learner's own seat by when they joined.
class CourseGroup extends Equatable {
  const CourseGroup({required this.id, required this.capacity, required this.seatsTaken, required this.seat, this.status});

  /// Null for a one-to-one course (no `group`).
  static CourseGroup? fromJson(Object? raw) {
    if (raw is! Map<String, dynamic>) return null;
    int number(Object? v) => v is num ? v.toInt() : int.tryParse('$v') ?? 0;
    return CourseGroup(
      id: '${raw['id'] ?? ''}',
      capacity: number(raw['capacity']),
      seatsTaken: number(raw['seats_taken']),
      seat: number(raw['seat']),
      status: raw['status'] as String?,
    );
  }

  final String id;
  final int capacity;
  final int seatsTaken;

  /// The learner's seat ("seat 4 of 5": 4); 0 when not known.
  final int seat;

  /// forming (waits for its students) or running; null where the answer does not say.
  final String? status;

  bool get forming => status == 'forming';

  @override
  List<Object?> get props => [id, capacity, seatsTaken, seat, status];
}

/// A dispute of a lesson's outcome: open, upheld (the outcome changed) or
/// rejected, why it was raised, and the academy's answer once decided.
class LessonDispute extends Equatable {
  const LessonDispute({required this.status, required this.statusLabel, required this.reason, this.answer});

  static LessonDispute? fromJson(Object? raw) {
    if (raw is! Map<String, dynamic>) return null;
    return LessonDispute(
      status: raw['status'] as String? ?? 'open',
      statusLabel: raw['status_label'] as String? ?? '',
      reason: raw['reason'] as String? ?? '',
      answer: raw['answer'] as String?,
    );
  }

  final String status;
  final String statusLabel;
  final String reason;
  final String? answer;

  bool get open => status == 'open';

  @override
  List<Object?> get props => [status, answer];
}

/// A page of lessons: whose, and any notice about the coming weeks
/// (a holiday, a clock change).
class LessonList extends Equatable {
  const LessonList({required this.lessons, this.learnerName, this.notices = const []});

  /// When the clock alone changes these lessons (see [Lesson.clockMoments]).
  Iterable<DateTime> get clockMoments => lessons.expand((l) => l.clockMoments);

  factory LessonList.fromJson(Map<String, dynamic> json) => LessonList(
    learnerName: (json['learner'] as Map<String, dynamic>?)?['name'] as String?,
    lessons: (json['data'] as List<dynamic>).map((l) => Lesson.fromJson(l as Map<String, dynamic>)).toList(),
    notices: (json['notices'] as List<dynamic>? ?? const []).map((n) => '$n').toList(),
  );

  final String? learnerName;
  final List<Lesson> lessons;
  final List<String> notices;

  @override
  List<Object?> get props => [learnerName, lessons, notices];
}

/// What cancelling would do, by the academy's policy, and the reasons to pick.
class CancelPreview {
  const CancelPreview({required this.outcome, required this.reasons, this.excuseOnly = false});

  factory CancelPreview.fromJson(Map<String, dynamic> json) => CancelPreview(
    outcome: json['outcome'] as String? ?? '',
    excuseOnly: json['excuse_only'] == true,
    reasons: [
      for (final r in json['reasons'] as List<dynamic>)
        (value: (r as Map<String, dynamic>)['value'] as String, label: r['label'] as String),
    ],
  );

  final String outcome;
  final List<({String value, String label})> reasons;

  /// A group lesson: "I won't attend" excuses the learner's own seat; the lesson goes on for the others.
  final bool excuseOnly;
}

/// A lesson's rating by this person, and the tags to offer.
class Rating {
  const Rating({required this.canRate, required this.good, required this.bad, this.why, this.stars, this.tags = const [], this.comment});

  factory Rating.fromJson(Map<String, dynamic> json) {
    final choices = json['choices'] as Map<String, dynamic>? ?? const {};
    List<({String value, String label})> list(Object? raw) => [
      for (final c in raw as List<dynamic>? ?? const [])
        (value: (c as Map<String, dynamic>)['value'] as String, label: c['label'] as String),
    ];
    return Rating(
      canRate: json['can_rate'] == true,
      why: json['why'] as String?,
      stars: (json['stars'] as num?)?.toInt(),
      tags: (json['tags'] as List<dynamic>? ?? const []).map((t) => '$t').toList(),
      comment: json['comment'] as String?,
      good: list(choices['good']),
      bad: list(choices['bad']),
    );
  }

  final bool canRate;
  final String? why;
  final int? stars;
  final List<String> tags;
  final String? comment;
  final List<({String value, String label})> good;
  final List<({String value, String label})> bad;
}
