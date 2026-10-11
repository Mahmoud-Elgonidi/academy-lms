import '../../../core/time/local_time.dart';
import '../../games/domain/games.dart';

List<Map<String, dynamic>> _list(Object? raw) => [for (final x in raw as List<dynamic>? ?? const []) x as Map<String, dynamic>];

Map<String, dynamic>? _map(Object? raw) => raw is Map<String, dynamic> ? raw : null;

int _int(Object? v) => (v as num?)?.toInt() ?? 0;

int? _intOrNull(Object? v) => (v as num?)?.toInt();

/// `GET learning/journey/map`: one learner's journey, built from their
/// academy's own curriculum. Each course's levels are islands, its lessons
/// stations on them; the missions are what to do now (the next lesson, the
/// teacher's homework, a review that is due); stars are the one currency.
class JourneyMap {
  const JourneyMap({
    required this.learnerId,
    required this.stage,
    required this.style,
    required this.viewer,
    required this.stars,
    required this.streak,
    required this.courses,
    required this.missions,
    required this.cheers,
    required this.cheersLeft,
    this.lastLesson,
    this.chest,
    this.games,
  });

  factory JourneyMap.fromJson(Map<String, dynamic> json) => JourneyMap(
    learnerId: json['learner_id'] as String? ?? '',
    stage: json['stage'] as String? ?? 'classic',
    style: json['style'] as String? ?? 'playful',
    viewer: json['viewer'] as String? ?? 'self',
    stars: JourneyStars.fromJson(_map(json['stars']) ?? const {}),
    streak: _int(json['streak']),
    lastLesson: _map(json['last_lesson']) == null ? null : LastLesson.fromJson(_map(json['last_lesson'])!),
    courses: [for (final c in _list(json['courses'])) JourneyCourse.fromJson(c)],
    missions: [for (final m in _list(json['missions'])) Mission.fromJson(m)],
    chest: _map(json['chest']) == null ? null : Chest.fromJson(_map(json['chest'])!),
    games: _map(json['games']) == null ? null : JourneyGames.fromJson(_map(json['games'])!),
    cheers: [for (final c in _list(json['cheers'])) JourneyCheer.fromJson(c)],
    cheersLeft: _int(json['cheers_left']),
  );

  final String learnerId;

  /// kids, middle, teen or classic: the learner's own look.
  final String stage;

  /// playful (each stage's look), calm (levels and missions for everyone) or plain (no stars, chest, egg or games).
  final String style;

  /// self (the student) or guardian (a guardian looking at their child).
  final String viewer;
  final JourneyStars stars;

  /// Lessons attended in a row.
  final int streak;
  final LastLesson? lastLesson;
  final List<JourneyCourse> courses;
  final List<Mission> missions;
  final Chest? chest;

  /// The games layer (null: the academy has no games).
  final JourneyGames? games;

  /// Cheers from a guardian the student has not seen yet.
  final List<JourneyCheer> cheers;

  /// For a guardian: the cheers they may still send today.
  final int cheersLeft;

  bool get guardian => viewer == 'guardian';
  bool get plain => style == 'plain';
  bool get calm => style == 'calm';

  /// Stars, the chest and the egg are shown (not in the plain style).
  bool get rewards => !plain;

  /// The games are on for this learner: the academy has them and the style keeps them.
  bool get playable => games != null && !plain;

  Mission? mission(String key) => missions.where((m) => m.key == key).firstOrNull;

  /// The open missions on a station, in the API's order.
  List<Mission> missionsOn(JourneyStation station) => [for (final key in station.missions) ?mission(key)];

  /// The next lesson to join (the lesson mission), if any.
  Mission? get nextLesson => missions.where((m) => m.kind == 'lesson').firstOrNull;

  /// What the clock alone changes on this screen: the room of the next lesson opening, starting and ending, and the
  /// week's chest closing.
  Iterable<DateTime> get clockMoments => [
    for (final m in missions)
      if (m.kind == 'lesson') ...[?m.joinOpensAt?.utc, ?m.startsAt?.utc, ?m.endsAt?.utc],
    ?chest?.endsAt?.utc,
  ];

  /// The station with [id] on any course, with its island and course.
  (JourneyCourse, JourneyIsland, JourneyStation)? find(String? id) {
    if (id == null) return null;
    for (final c in courses) {
      for (final i in c.islands) {
        for (final s in i.stations) {
          if (s.id == id) return (c, i, s);
        }
      }
    }
    return null;
  }

  /// The game a homework's practice is played with: the teacher's choice, else the best game on its station.
  String? gameFor(Mission homework) {
    if (homework.game != null && homework.game!.isNotEmpty) return homework.game;
    final at = find(homework.stationId);
    final station = at?.$3;
    if (station == null || station.games.isEmpty) return null;
    return (station.games.where((g) => g.chosen).firstOrNull ?? station.games.first).id;
  }
}

/// The stars: the total, the rank they reach, and where they came from.
class JourneyStars {
  const JourneyStars({required this.total, required this.rank, required this.from, required this.perEgg});

  factory JourneyStars.fromJson(Map<String, dynamic> json) => JourneyStars(
    total: _int(json['total']),
    rank: JourneyRank.fromJson(_map(json['rank']) ?? const {}),
    from: {for (final e in (_map(json['from']) ?? const <String, dynamic>{}).entries) e.key: _int(e.value)},
    perEgg: _int(json['per_egg']) == 0 ? 12 : _int(json['per_egg']),
  );

  final int total;
  final JourneyRank rank;

  /// lessons, practice, homework, mastered, chest.
  final Map<String, int> from;
  final int perEgg;
}

class JourneyRank {
  const JourneyRank({required this.key, required this.label, required this.progress, this.next, this.nextLabel});

  factory JourneyRank.fromJson(Map<String, dynamic> json) => JourneyRank(
    key: json['key'] as String? ?? '',
    label: json['label'] as String? ?? '',
    progress: _int(json['progress']),
    next: _intOrNull(json['next']),
    nextLabel: json['next_label'] as String?,
  );

  final String key;
  final String label;

  /// 0..100 of the way to [next].
  final int progress;

  /// The stars the next rank starts at (null: the top rank).
  final int? next;
  final String? nextLabel;
}

/// The latest lesson of the last week and the stars it brought (on time, staying to the end).
class LastLesson {
  const LastLesson({
    required this.id,
    required this.stars,
    required this.labels,
    this.startsAt,
    this.lateMin,
    this.onTime = false,
    this.stayed = false,
  });

  factory LastLesson.fromJson(Map<String, dynamic> json) => LastLesson(
    id: json['id'] as String? ?? '',
    startsAt: LocalTime.fromJson(json['starts_at']),
    stars: _int(json['stars']),
    lateMin: _intOrNull(json['late_min']),
    onTime: json['on_time'] == true,
    stayed: json['stayed'] == true,
    labels: [for (final l in json['labels'] as List<dynamic>? ?? const []) '$l'],
  );

  final String id;
  final LocalTime? startsAt;
  final int stars;
  final int? lateMin;
  final bool onTime;
  final bool stayed;

  /// "في الميعاد", "فضلت لآخر الحصة", … in the viewer's language.
  final List<String> labels;
}

/// A course: its program, how far, and its curriculum's levels as islands.
class JourneyCourse {
  const JourneyCourse({
    required this.enrollmentId,
    required this.subject,
    required this.teacher,
    required this.hasCurriculum,
    required this.percent,
    required this.finished,
    required this.mastered,
    required this.lessons,
    required this.islands,
    this.program,
    this.etaWeeks,
    this.finishOn,
    this.currentId,
    this.currentTitle,
  });

  factory JourneyCourse.fromJson(Map<String, dynamic> json) {
    final lessons = _map(json['lessons']);
    final current = _map(json['current']);
    return JourneyCourse(
      enrollmentId: json['enrollment_id'] as String? ?? '',
      subject: json['subject'] as String? ?? '',
      teacher: json['teacher'] as String? ?? '',
      program: json['program'] as String?,
      hasCurriculum: json['has_curriculum'] != false,
      percent: _int(json['percent']),
      finished: json['finished'] == true,
      mastered: _int(lessons?['mastered']),
      lessons: _int(lessons?['total']),
      etaWeeks: (json['eta_weeks'] as num?)?.toDouble(),
      finishOn: LocalTime.fromJson(json['finish_on']),
      currentId: current?['id'] as String?,
      currentTitle: current?['title'] as String?,
      islands: [for (final i in _list(json['islands'])) JourneyIsland.fromJson(i)],
    );
  }

  final String enrollmentId;
  final String subject;
  final String teacher;
  final String? program;

  /// false: the course follows no published curriculum yet (its missions only).
  final bool hasCurriculum;
  final int percent;
  final bool finished;
  final int mastered;
  final int lessons;
  final double? etaWeeks;
  final LocalTime? finishOn;
  final String? currentId;
  final String? currentTitle;
  final List<JourneyIsland> islands;

  String get name => (program?.isNotEmpty ?? false) ? program! : subject;

  /// The island the learner is on: the one marked here, else the first not done, else the last.
  JourneyIsland? get here =>
      islands.where((i) => i.state == 'here').firstOrNull ?? islands.where((i) => i.state != 'done').firstOrNull ?? islands.lastOrNull;

  /// The island before [island] (what to finish for it to open).
  JourneyIsland? before(JourneyIsland island) {
    final at = islands.indexOf(island);
    return at > 0 ? islands[at - 1] : null;
  }
}

/// How a level is drawn: one of the world's islands in turn.
class IslandLook {
  const IslandLook({required this.key, required this.name, required this.emoji, required this.colors, required this.music});

  factory IslandLook.fromJson(Map<String, dynamic> json) => IslandLook(
    key: json['key'] as String? ?? '',
    name: json['name'] as String? ?? '',
    emoji: json['emoji'] as String? ?? '',
    colors: {for (final e in (_map(json['colors']) ?? const <String, dynamic>{}).entries) e.key: '${e.value}'},
    music: json['music'] as String? ?? 'major',
  );

  final String key;
  final String name;
  final String emoji;
  final Map<String, String> colors;
  final String music;
}

/// A level of the curriculum, drawn as an island: done, here or ahead.
class JourneyIsland {
  const JourneyIsland({
    required this.id,
    required this.index,
    required this.title,
    required this.percent,
    required this.state,
    required this.lessons,
    required this.done,
    required this.creatures,
    required this.stations,
    this.art,
    this.next,
  });

  factory JourneyIsland.fromJson(Map<String, dynamic> json) => JourneyIsland(
    id: json['id'] as String? ?? '',
    index: _int(json['index']),
    title: json['title'] as String? ?? '',
    percent: _int(json['percent']),
    state: json['state'] as String? ?? 'ahead',
    lessons: _int(json['lessons']),
    done: _int(json['done']),
    art: _map(json['art']) == null ? null : IslandLook.fromJson(_map(json['art'])!),
    creatures: [for (final c in _list(json['creatures'])) Creature.fromJson(c)],
    next: _map(json['next_creature']) == null ? null : Look.fromJson(_map(json['next_creature'])!['look']),
    stations: [for (final s in _list(json['stations'])) JourneyStation.fromJson(s)],
  );

  final String id;

  /// 1, 2, … in the curriculum's order.
  final int index;
  final String title;
  final int percent;

  /// done, here or ahead.
  final String state;
  final int lessons;
  final int done;

  /// The world island it is drawn as (null without the games' world).
  final IslandLook? art;
  final List<Creature> creatures;

  /// How the next creature to hatch here looks.
  final Look? next;
  final List<JourneyStation> stations;

  bool get open => state != 'ahead';
  String get artKey => art?.key ?? '';
  Map<String, String> get colors => art?.colors ?? const {};
  int get found => creatures.where((c) => c.found).length;

  /// Where the learner stands on it: the here station, else the first not done.
  JourneyStation? get here => stations.where((s) => s.state == 'here').firstOrNull ?? stations.where((s) => s.state == 'todo').firstOrNull;
}

/// A lesson on an island: done, review (the teacher asked for more), here or todo.
class JourneyStation {
  const JourneyStation({
    required this.id,
    required this.title,
    required this.state,
    required this.dueReview,
    required this.playable,
    required this.missions,
    required this.games,
    this.unit,
    this.practice,
    this.race,
  });

  factory JourneyStation.fromJson(Map<String, dynamic> json) => JourneyStation(
    id: json['id'] as String? ?? '',
    title: json['title'] as String? ?? '',
    unit: json['unit'] as String?,
    state: json['state'] as String? ?? 'todo',
    dueReview: json['due_review'] == true,
    playable: json['playable'] == true,
    practice: _map(json['practice']) == null ? null : StationPractice.fromJson(_map(json['practice'])!),
    missions: [for (final m in json['missions'] as List<dynamic>? ?? const []) '$m'],
    games: [for (final g in _list(json['games'])) StationGame.fromJson(g)],
    race: _map(json['race']) == null ? null : Race.fromJson(_map(json['race'])!),
  );

  final String id;
  final String title;

  /// The unit it belongs to (a sign on the way), if the curriculum has units.
  final String? unit;
  final String state;

  /// Mastered and due to be practised again.
  final bool dueReview;

  /// The learner may practise it now (a lesson still ahead is not).
  final bool playable;
  final StationPractice? practice;

  /// The keys of the open missions on it.
  final List<String> missions;

  /// The games that can play its items, best first (empty without games).
  final List<StationGame> games;

  /// The race against yourself on it.
  final Race? race;

  bool get done => state == 'done' || state == 'review';
  bool get here => state == 'here';
  bool get todo => state == 'todo';

  /// To review: the teacher asked for more, or it is due again.
  bool get review => state == 'review' || dueReview;
}

/// How the learner practised a station: rounds, and how much was right.
class StationPractice {
  const StationPractice({required this.rounds, required this.answered, required this.correct, required this.percent});

  factory StationPractice.fromJson(Map<String, dynamic> json) => StationPractice(
    rounds: _int(json['rounds']),
    answered: _int(json['answered']),
    correct: _int(json['correct']),
    percent: _int(json['percent']),
  );

  final int rounds;
  final int answered;
  final int correct;
  final int percent;
}

/// The race against yourself on a station: rounds, best and last time, best stars.
class Race {
  const Race({required this.rounds, this.bestSeconds, this.lastSeconds, this.bestStars = 0});

  factory Race.fromJson(Map<String, dynamic> json) => Race(
    rounds: _int(json['rounds']),
    bestSeconds: _intOrNull(json['best_seconds']),
    lastSeconds: _intOrNull(json['last_seconds']),
    bestStars: _int(json['best_stars']),
  );

  final int rounds;
  final int? bestSeconds;
  final int? lastSeconds;
  final int bestStars;
}

/// A game that can play a station's own practice items.
class StationGame {
  const StationGame({
    required this.id,
    required this.key,
    required this.island,
    required this.title,
    required this.summary,
    required this.howToPlay,
    required this.kinds,
    required this.items,
    this.chosen = false,
  });

  factory StationGame.fromJson(Map<String, dynamic> json) => StationGame(
    id: json['id'] as String? ?? '',
    key: json['key'] as String? ?? '',
    island: json['island'] as String? ?? '',
    title: json['title'] as String? ?? '',
    summary: json['summary'] as String? ?? '',
    howToPlay: json['how_to_play'] as String? ?? '',
    kinds: [for (final k in json['kinds'] as List<dynamic>? ?? const []) '$k'],
    items: _int(json['items']),
    chosen: json['chosen'] == true,
  );

  final String id;
  final String key;

  /// The world island the game belongs to.
  final String island;
  final String title;
  final String summary;
  final String howToPlay;

  /// choice, order, pair.
  final List<String> kinds;

  /// How many of the station's items it can play.
  final int items;

  /// The teacher chose it for a homework.
  final bool chosen;
}

/// Something to do now: the next lesson, the teacher's homework, or a review that is due.
class Mission {
  const Mission({
    required this.key,
    required this.kind,
    required this.kindLabel,
    required this.state,
    required this.title,
    this.enrollmentId,
    this.islandId,
    this.stationId,
    this.lessonId,
    this.startsAt,
    this.endsAt,
    this.joinOpen = false,
    this.joinOpensAt,
    this.stars,
    this.homeworkId,
    this.dueAt,
    this.accepts,
    this.practice = false,
    this.game,
    this.result,
  });

  factory Mission.fromJson(Map<String, dynamic> json) {
    final join = _map(json['join']);
    final result = _map(json['result']);
    return Mission(
      key: json['key'] as String? ?? '',
      kind: json['kind'] as String? ?? 'review',
      kindLabel: json['kind_label'] as String? ?? '',
      state: json['state'] as String? ?? 'open',
      enrollmentId: json['enrollment_id'] as String?,
      islandId: json['island_id'] as String?,
      stationId: json['station_id'] as String?,
      title: json['title'] as String? ?? '',
      lessonId: json['lesson_id'] as String?,
      startsAt: LocalTime.fromJson(json['starts_at']),
      endsAt: LocalTime.fromJson(json['ends_at']),
      joinOpen: join?['open'] == true,
      joinOpensAt: LocalTime.fromJson(join?['opens_at']),
      stars: _intOrNull(json['stars']),
      homeworkId: json['homework_id'] as String?,
      dueAt: LocalTime.fromJson(json['due_at']),
      accepts: json['accepts'] as String?,
      practice: json['practice'] == true,
      game: json['game'] as String?,
      result: result == null ? null : PracticeResult.fromJson(result),
    );
  }

  final String key;

  /// lesson, homework or review.
  final String kind;
  final String kindLabel;

  /// open, late (homework past its due time) or done.
  final String state;
  final String? enrollmentId;
  final String? islandId;
  final String? stationId;
  final String title;

  // A lesson's.
  final String? lessonId;
  final LocalTime? startsAt;
  final LocalTime? endsAt;
  final bool joinOpen;
  final LocalTime? joinOpensAt;

  /// The stars it brought (homework done) or can bring.
  final int? stars;

  // A homework's.
  final String? homeworkId;
  final LocalTime? dueAt;

  /// any, text, audio, file or practice (handed in by playing).
  final String? accepts;

  /// It asks for practice in the games.
  final bool practice;

  /// The game the teacher chose for it (a key), if any.
  final String? game;
  final PracticeResult? result;

  bool get done => state == 'done';
  bool get late => state == 'late';

  /// Handed in by playing only.
  bool get byPlaying => accepts == 'practice';
}

/// How a homework's practice round went.
class PracticeResult {
  const PracticeResult({required this.stars, required this.correct, required this.total, this.game});

  factory PracticeResult.fromJson(Map<String, dynamic> json) =>
      PracticeResult(game: json['game'] as String?, stars: _int(json['stars']), correct: _int(json['correct']), total: _int(json['total']));

  final String? game;
  final int stars;
  final int correct;
  final int total;
}

/// The week's chest: every lesson of the week attended and every homework due in it handed in.
class Chest {
  const Chest({
    required this.period,
    required this.target,
    required this.progress,
    required this.state,
    required this.stars,
    required this.canOpen,
    this.sticker,
    this.endsAt,
  });

  factory Chest.fromJson(Map<String, dynamic> json) {
    final sticker = _map(json['sticker']);
    return Chest(
      period: json['period'] as String? ?? '',
      target: _int(json['target']),
      progress: _int(json['progress']),
      state: json['state'] as String? ?? 'active',
      stars: _int(json['stars']),
      sticker: sticker == null ? null : ChestSticker(sticker['key'] as String? ?? '', sticker['name'] as String? ?? ''),
      endsAt: LocalTime.fromJson(json['ends_at']),
      canOpen: json['can_open'] == true,
    );
  }

  /// The week's Saturday (what opening it sends).
  final String period;
  final int target;
  final int progress;

  /// active, missed, done (ready to open) or opened.
  final String state;
  final int stars;
  final ChestSticker? sticker;
  final LocalTime? endsAt;

  /// The student may open it now.
  final bool canOpen;

  bool get opened => state == 'opened';
  bool get missed => state == 'missed';
  bool get ready => state == 'done';
}

class ChestSticker {
  const ChestSticker(this.key, this.name);

  final String key;
  final String name;
}

/// The games layer of the journey: may the learner play now, the egg, their buddy and creatures, and a guardian's
/// limits and glance.
class JourneyGames {
  const JourneyGames({
    required this.allowance,
    required this.eggHave,
    required this.eggNeed,
    required this.eggReady,
    required this.found,
    required this.total,
    required this.recent,
    this.buddy,
    this.limits,
    this.glance,
  });

  factory JourneyGames.fromJson(Map<String, dynamic> json) {
    final egg = _map(json['egg']);
    return JourneyGames(
      allowance: Allowance.fromJson(json['allowance']),
      eggHave: _int(egg?['have']),
      eggNeed: _int(egg?['need']) == 0 ? 12 : _int(egg?['need']),
      eggReady: egg?['ready'] == true,
      buddy: _map(json['buddy']) == null ? null : Creature.fromJson(_map(json['buddy'])!),
      found: _int(json['creatures_found']),
      total: _int(json['creatures_total']),
      recent: [for (final r in _list(json['recent'])) PastRound.fromJson(r)],
      limits: _map(json['limits']) == null ? null : GameLimits.fromJson(_map(json['limits'])!),
      glance: _map(json['glance']) == null ? null : Glance.fromJson(_map(json['glance'])!),
    );
  }

  final Allowance allowance;
  final int eggHave;
  final int eggNeed;

  /// An egg can hatch now.
  final bool eggReady;
  final Creature? buddy;
  final int found;
  final int total;
  final List<PastRound> recent;

  /// A guardian's limits for this child (null for the student).
  final GameLimits? limits;

  /// A guardian's look at the child's week (null for the student).
  final Glance? glance;
}

/// A cheer a guardian sent that the student has not seen yet.
class JourneyCheer {
  const JourneyCheer({required this.id, required this.kind, this.at});

  factory JourneyCheer.fromJson(Map<String, dynamic> json) =>
      JourneyCheer(id: json['id'] as String? ?? '', kind: json['kind'] as String? ?? 'clap', at: LocalTime.fromJson(json['at']));

  final String id;

  /// clap, star, heart or fire.
  final String kind;
  final LocalTime? at;
}

/// The week's chest, opened: its stars, the sticker inside (kids), and the new total and rank.
class ChestOpened {
  const ChestOpened({required this.stars, required this.totalBefore, required this.total, required this.rankedUp, this.rank, this.sticker});

  factory ChestOpened.fromJson(Map<String, dynamic> json) {
    final sticker = _map(json['sticker']);
    final rank = _map(json['rank']);
    return ChestOpened(
      stars: _int(json['stars']),
      sticker: sticker == null ? null : ChestSticker(sticker['key'] as String? ?? '', sticker['name'] as String? ?? ''),
      totalBefore: _int(json['total_before']),
      total: _int(json['total']),
      rankedUp: json['ranked_up'] == true,
      rank: rank?['label'] as String?,
    );
  }

  final int stars;
  final ChestSticker? sticker;
  final int totalBefore;
  final int total;
  final bool rankedUp;

  /// The rank's name now.
  final String? rank;
}
