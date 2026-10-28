import 'package:equatable/equatable.dart';

/// One of the teacher's numbers, written by the API ("92%", "—").
class Kpi extends Equatable {
  const Kpi({
    required this.key,
    required this.label,
    required this.value,
    required this.target,
    required this.state,
    required this.hint,
    required this.group,
  });

  factory Kpi.fromJson(Map<String, dynamic> j) => Kpi(
    key: j['key'] as String? ?? '',
    label: j['label'] as String? ?? '',
    value: j['value'] as String? ?? '—',
    target: j['target'] as String?,
    state: j['state'] as String? ?? 'none',
    hint: j['hint'] as String?,
    group: j['group'] as String? ?? '',
  );

  final String key;
  final String label;
  final String value;
  final String? target;

  /// good | bad | none
  final String state;
  final String? hint;

  /// commitment | quality | professionalism | business
  final String group;

  @override
  List<Object?> get props => [key, value, state];
}

/// A lesson that moved the numbers; [lesson] is the subject and when, as the API wrote it.
class KpiEvent extends Equatable {
  const KpiEvent({required this.kind, required this.text, required this.lesson, this.comment, this.excluded, this.note});

  factory KpiEvent.fromJson(Map<String, dynamic> j) => KpiEvent(
    kind: j['kind'] as String? ?? '',
    text: j['text'] as String? ?? '',
    lesson: j['lesson'] as String? ?? '',
    comment: j['comment'] as String?,
    excluded: j['excluded'] as String?,
    note: j['note'] as String?,
  );

  final String kind;
  final String text;
  final String lesson;
  final String? comment;

  /// Why it does not count, in words; null when it counts.
  final String? excluded;
  final String? note;

  @override
  List<Object?> get props => [kind, text, lesson, excluded];
}

/// A family's rating of a lesson, without who gave it.
class LessonRatingLine extends Equatable {
  const LessonRatingLine({required this.stars, required this.tags, required this.lesson, this.comment});

  factory LessonRatingLine.fromJson(Map<String, dynamic> j) => LessonRatingLine(
    stars: (j['stars'] as num?)?.toInt() ?? 0,
    tags: [for (final t in j['tags'] as List<dynamic>? ?? const []) '$t'],
    lesson: j['lesson'] as String? ?? '',
    comment: j['comment'] as String?,
  );

  final int stars;
  final List<String> tags;
  final String lesson;
  final String? comment;

  @override
  List<Object?> get props => [stars, tags, lesson, comment];
}

/// `GET teacher/performance`: the last [days] days, as the academy sees them.
class Performance extends Equatable {
  const Performance({
    required this.days,
    required this.kpis,
    required this.events,
    required this.average,
    required this.count,
    required this.ratings,
  });

  factory Performance.fromJson(Map<String, dynamic> j) {
    final r = j['ratings'] as Map<String, dynamic>? ?? const {};
    return Performance(
      days: (j['days'] as num?)?.toInt() ?? 90,
      kpis: [for (final k in (j['kpis'] as List<dynamic>? ?? const []).cast<Map<String, dynamic>>()) Kpi.fromJson(k)],
      events: [for (final e in (j['events'] as List<dynamic>? ?? const []).cast<Map<String, dynamic>>()) KpiEvent.fromJson(e)],
      average: r['avg'] as String?,
      count: (r['count'] as num?)?.toInt() ?? 0,
      ratings: [for (final x in (r['list'] as List<dynamic>? ?? const []).cast<Map<String, dynamic>>()) LessonRatingLine.fromJson(x)],
    );
  }

  /// The order the groups are shown in.
  static const groups = ['commitment', 'quality', 'professionalism', 'business'];

  final int days;
  final List<Kpi> kpis;
  final List<KpiEvent> events;

  /// "4.6", or null with no ratings.
  final String? average;
  final int count;
  final List<LessonRatingLine> ratings;

  @override
  List<Object?> get props => [days, kpis, events, average, count, ratings];
}
