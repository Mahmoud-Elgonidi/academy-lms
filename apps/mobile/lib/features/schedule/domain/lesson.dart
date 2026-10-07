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

  bool get ahead => status == 'scheduled' || status == 'in_progress';

  /// Still to come or on now: booked, and not yet over by the clock.
  bool get upcoming => ahead && endsAt.utc.isAfter(DateTime.now().toUtc());
  bool get cancelled => status == 'cancelled';
  bool get tookPlace => status == 'completed' || status == 'student_absent';

  @override
  List<Object?> get props => [id, status, joinOpen, canCancel, unfunded];
}

/// A page of lessons: whose, and any notice about the coming weeks
/// (a holiday, a clock change).
class LessonList extends Equatable {
  const LessonList({required this.lessons, this.learnerName, this.notices = const []});

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
  const CancelPreview({required this.outcome, required this.reasons});

  factory CancelPreview.fromJson(Map<String, dynamic> json) => CancelPreview(
    outcome: json['outcome'] as String? ?? '',
    reasons: [
      for (final r in json['reasons'] as List<dynamic>)
        (value: (r as Map<String, dynamic>)['value'] as String, label: r['label'] as String),
    ],
  );

  final String outcome;
  final List<({String value, String label})> reasons;
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
