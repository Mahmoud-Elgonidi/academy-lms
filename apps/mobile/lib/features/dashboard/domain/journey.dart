import '../../../core/time/local_time.dart';

List<Map<String, dynamic>> _list(Object? raw) => [for (final x in raw as List<dynamic>? ?? const []) x as Map<String, dynamic>];

/// A learner the person follows (themself, or a guardian's children).
class Learner {
  const Learner({required this.id, required this.name, required this.isSelf, required this.stage});

  factory Learner.fromJson(Map<String, dynamic> json) => Learner(
    id: json['id'] as String,
    name: json['name'] as String,
    isSelf: json['is_self'] == true,
    stage: json['stage'] as String? ?? 'classic',
  );

  final String id;
  final String name;
  final bool isSelf;
  final String stage;

  String get firstName => name.split(' ').first;
}

/// One lesson of a course's map: done, review, here or todo.
class MapStep {
  const MapStep({required this.id, required this.title, required this.state});

  factory MapStep.fromJson(Map<String, dynamic> json) =>
      MapStep(id: json['id'] as String?, title: json['title'] as String? ?? '', state: json['state'] as String? ?? 'todo');

  final String? id;
  final String title;
  final String state;

  bool get done => state == 'done' || state == 'review';
}

/// One level of a course (a "world"): done, here or ahead.
class World {
  const World({
    required this.index,
    required this.title,
    required this.percent,
    required this.state,
    required this.done,
    required this.lessons,
  });

  factory World.fromJson(Map<String, dynamic> json) => World(
    index: (json['index'] as num).toInt(),
    title: json['title'] as String? ?? '',
    percent: (json['percent'] as num?)?.toInt() ?? 0,
    state: json['state'] as String? ?? 'ahead',
    done: (json['done'] as num?)?.toInt() ?? 0,
    lessons: _list(json['lessons']).map(MapStep.fromJson).toList(),
  );

  final int index;
  final String title;
  final int percent;
  final String state;
  final int done;
  final List<MapStep> lessons;
}

class JourneyCourse {
  const JourneyCourse({
    required this.enrollmentId,
    required this.subject,
    required this.teacher,
    required this.finished,
    required this.stars,
    required this.streak,
    required this.points,
    required this.worlds,
    required this.next,
    this.program,
    this.percent,
    this.lessonsPerWeek,
    this.finishOn,
    this.level,
  });

  factory JourneyCourse.fromJson(Map<String, dynamic> json) {
    final level = json['level'] as Map<String, dynamic>?;
    return JourneyCourse(
      enrollmentId: json['enrollment_id'] as String,
      subject: json['subject'] as String? ?? '',
      teacher: json['teacher'] as String? ?? '',
      program: json['program'] as String?,
      percent: (json['percent'] as num?)?.toInt(),
      finished: json['finished'] == true,
      lessonsPerWeek: (json['lessons_per_week'] as num?)?.toInt(),
      finishOn: LocalTime.fromJson(json['finish_on']),
      stars: (json['stars'] as num?)?.toInt() ?? 0,
      streak: (json['streak'] as num?)?.toInt() ?? 0,
      points: (json['points'] as num?)?.toInt() ?? 0,
      worlds: _list(json['worlds']).map(World.fromJson).toList(),
      level: level == null
          ? null
          : (
              index: (level['index'] as num).toInt(),
              title: level['title'] as String? ?? '',
              count: (level['count'] as num?)?.toInt() ?? 0,
              weeksLeft: (level['weeks_left'] as num?)?.round(),
            ),
      next: _list(json['next']).map(MapStep.fromJson).toList(),
    );
  }

  final String enrollmentId;
  final String subject;
  final String teacher;
  final String? program;
  final int? percent;
  final bool finished;
  final int? lessonsPerWeek;
  final LocalTime? finishOn;
  final int stars;
  final int streak;
  final int points;
  final List<World> worlds;
  final ({int index, String title, int count, int? weeksLeft})? level;
  final List<MapStep> next;

  World? get currentWorld => worlds.where((w) => w.state == 'here').firstOrNull ?? worlds.lastOrNull;
}

class Achievement {
  const Achievement({required this.key, required this.earned, required this.name, required this.hint});

  factory Achievement.fromJson(Map<String, dynamic> json) => Achievement(
    key: json['key'] as String,
    earned: json['earned'] == true,
    name: json['name'] as String? ?? '',
    hint: json['hint'] as String? ?? '',
  );

  final String key;
  final bool earned;
  final String name;
  final String hint;
}

/// A student's own journey (`GET learning/journey`): stars, points and rank,
/// streak, badges, and each course's map.
class Journey {
  const Journey({
    required this.stars,
    required this.streak,
    required this.points,
    required this.rank,
    required this.badges,
    required this.courses,
  });

  factory Journey.fromJson(Map<String, dynamic> json) {
    final rank = json['rank'] as Map<String, dynamic>? ?? const {};
    return Journey(
      stars: (json['stars'] as num?)?.toInt() ?? 0,
      streak: (json['streak'] as num?)?.toInt() ?? 0,
      points: (json['points'] as num?)?.toInt() ?? 0,
      rank: (
        label: rank['label'] as String? ?? '',
        points: (rank['points'] as num?)?.toInt() ?? 0,
        next: (rank['next'] as num?)?.toInt(),
        nextLabel: rank['next_label'] as String?,
        progress: (rank['progress'] as num?)?.toInt() ?? 0,
      ),
      badges: _list(json['badges']).map(Achievement.fromJson).toList(),
      courses: _list(json['courses']).map(JourneyCourse.fromJson).toList(),
    );
  }

  final int stars;
  final int streak;
  final int points;
  final ({String label, int points, int? next, String? nextLabel, int progress}) rank;
  final List<Achievement> badges;
  final List<JourneyCourse> courses;
}
