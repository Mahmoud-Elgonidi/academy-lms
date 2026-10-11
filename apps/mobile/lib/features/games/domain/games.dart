import '../../../core/time/local_time.dart';

List<Map<String, dynamic>> _list(Object? raw) => [for (final x in raw as List<dynamic>? ?? const []) x as Map<String, dynamic>];

int _int(Object? v) => (v as num?)?.toInt() ?? 0;

Map<String, dynamic> _map(Object? raw) => raw is Map<String, dynamic> ? raw : const {};

/// How a creature is drawn: its kind (bird, turtle, crab, …) and colours,
/// from the platform's catalog. The app draws it ([CreatureArt]); unknown
/// keys are ignored.
class Look {
  const Look(this.values);

  factory Look.fromJson(Object? json) => Look(_map(json));

  final Map<String, dynamic> values;

  String get kind => values['kind'] as String? ?? 'bird';

  /// A colour of the look, when it is a plain `#RRGGBB`.
  String? colour(String key) {
    final v = values[key];
    return v is String && RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(v) ? v : null;
  }

  bool has(String key) => values.containsKey(key);

  /// The pitch of its little voice (1 is a bird's).
  double get pitch => (values['pitch'] as num?)?.toDouble() ?? 1;
}

/// A creature of the world: found by this learner or not yet.
class Creature {
  const Creature({
    required this.key,
    required this.name,
    required this.emoji,
    required this.rarity,
    required this.look,
    required this.story,
    required this.found,
    this.foundAt,
    this.source,
    this.nickname,
    this.buddy = false,
    this.island,
    this.islandName,
    this.names = const [],
  });

  factory Creature.fromJson(Map<String, dynamic> json) => Creature(
    key: json['key'] as String,
    name: json['name'] as String? ?? '',
    emoji: json['emoji'] as String? ?? '',
    rarity: json['rarity'] as String? ?? 'common',
    look: Look.fromJson(json['look']),
    story: json['story'] as String? ?? '',
    found: json['found'] == true,
    foundAt: LocalTime.fromJson(json['found_at']),
    source: json['source'] as String?,
    nickname: json['nickname'] as String?,
    buddy: json['buddy'] == true,
    island: json['island'] as String?,
    islandName: json['island_name'] as String?,
    names: [for (final n in json['names'] as List<dynamic>? ?? const []) '$n'],
  );

  final String key;
  final String name;
  final String emoji;

  /// common, rare or legendary.
  final String rarity;
  final Look look;
  final String story;
  final bool found;
  final LocalTime? foundAt;

  /// stars (an egg from the games) or mastery (a lesson the teacher closed).
  final String? source;
  final String? nickname;
  final bool buddy;
  final String? island;
  final String? islandName;

  /// Names to choose from for a hatched creature (its own first).
  final List<String> names;

  /// What the learner calls it.
  String get shownName => (nickname?.isNotEmpty ?? false) ? nickname! : name;
}

/// Whether the learner may start a round now, and why not.
class Allowance {
  const Allowance({required this.may, required this.used, this.limit, this.left, this.reason, this.reasonLabel, this.nightLock = false});

  factory Allowance.fromJson(Object? json) {
    final j = _map(json);
    return Allowance(
      may: j['may'] != false,
      used: _int(j['used']),
      limit: (j['limit'] as num?)?.toInt(),
      left: (j['left'] as num?)?.toInt(),
      reason: j['reason'] as String?,
      reasonLabel: j['reason_label'] as String?,
      nightLock: j['night_lock'] == true,
    );
  }

  final bool may;
  final int used;

  /// Minutes a day (null: no limit).
  final int? limit;
  final int? left;

  /// off (the academy), paused (a guardian), night (the night lock), limit (today's minutes).
  final String? reason;
  final String? reasonLabel;
  final bool nightLock;
}

/// A finished round: when, how many right first time, the stars.
class PastRound {
  const PastRound({required this.stars, required this.share, this.at, this.game = '', this.correct = 0, this.total = 0});

  factory PastRound.fromJson(Map<String, dynamic> json) => PastRound(
    stars: _int(json['stars']),
    share: (json['share'] as num?)?.toDouble() ?? (_int(json['total']) == 0 ? 0 : _int(json['correct']) / _int(json['total'])),
    at: LocalTime.fromJson(json['at']),
    game: json['game'] as String? ?? '',
    correct: _int(json['correct']),
    total: _int(json['total']),
  );

  final int stars;

  /// 0..1 right first time.
  final double share;
  final LocalTime? at;
  final String game;
  final int correct;
  final int total;
}

/// What a guardian chooses for their child.
class GameLimits {
  const GameLimits({required this.enabled, required this.nightLock, required this.choices, this.dailyMinutes, this.academyMinutes});

  factory GameLimits.fromJson(Map<String, dynamic> json) => GameLimits(
    enabled: json['enabled'] != false,
    nightLock: json['night_lock'] == true,
    choices: [for (final c in json['choices'] as List<dynamic>? ?? const []) (c as num).toInt()],
    dailyMinutes: (json['daily_minutes'] as num?)?.toInt(),
    academyMinutes: (json['academy_minutes'] as num?)?.toInt(),
  );

  final bool enabled;
  final bool nightLock;

  /// Minutes a guardian can pick from.
  final List<int> choices;

  /// The guardian's minutes (null: the academy's).
  final int? dailyMinutes;
  final int? academyMinutes;

  /// The choices that do not go over the academy's own minutes.
  List<int> get allowed => [
    for (final c in choices)
      if (academyMinutes == null || c <= academyMinutes!) c,
  ];
}

/// A guardian's look at the child's week.
class Glance {
  const Glance({
    required this.rounds,
    this.practisedTitle,
    this.practisedRounds = 0,
    this.practisedGames = 0,
    this.hardest,
    this.bestCorrect,
    this.bestTotal,
    this.bestGame,
    this.hatched,
    this.hatchedFound = 0,
  });

  factory Glance.fromJson(Map<String, dynamic> json) {
    final p = json['practised'] is Map<String, dynamic> ? json['practised'] as Map<String, dynamic> : null;
    final h = json['hardest'] is Map<String, dynamic> ? json['hardest'] as Map<String, dynamic> : null;
    final b = json['best'] is Map<String, dynamic> ? json['best'] as Map<String, dynamic> : null;
    final c = json['hatched'] is Map<String, dynamic> ? json['hatched'] as Map<String, dynamic> : null;
    return Glance(
      rounds: _int(json['rounds']),
      practisedTitle: p?['title'] as String?,
      practisedRounds: _int(p?['rounds']),
      practisedGames: _int(p?['games']),
      hardest: h?['text'] as String?,
      bestCorrect: (b?['correct'] as num?)?.toInt(),
      bestTotal: (b?['total'] as num?)?.toInt(),
      bestGame: b?['game'] as String?,
      hatched: c == null
          ? null
          : Creature(
              key: c['key'] as String,
              name: c['name'] as String? ?? '',
              emoji: '',
              rarity: 'common',
              look: Look.fromJson(c['look']),
              story: '',
              found: true,
              source: c['source'] as String?,
              islandName: c['island'] as String?,
            ),
      hatchedFound: _int(c?['found']),
    );
  }

  final int rounds;
  final String? practisedTitle;
  final int practisedRounds;
  final int practisedGames;

  /// The question hardest first time, as "question → answer".
  final String? hardest;
  final int? bestCorrect;
  final int? bestTotal;
  final String? bestGame;
  final Creature? hatched;
  final int hatchedFound;

  bool get quiet => practisedTitle == null && hardest == null && bestCorrect == null;
}

/// A round to play: the page to open and what to hand it.
class StartedRound {
  const StartedRound({
    required this.playId,
    required this.pageUrl,
    required this.gameId,
    required this.gameKey,
    required this.title,
    required this.howToPlay,
    required this.island,
    required this.round,
    this.focusTitle,
  });

  factory StartedRound.fromJson(Map<String, dynamic> json) {
    final game = _map(json['game']);
    final focus = json['focus'] is Map<String, dynamic> ? json['focus'] as Map<String, dynamic> : null;
    return StartedRound(
      playId: json['play_id'] as String,
      pageUrl: json['page_url'] as String,
      gameId: game['id'] as String? ?? '',
      gameKey: game['key'] as String? ?? '',
      title: game['title'] as String? ?? '',
      howToPlay: game['how_to_play'] as String? ?? '',
      island: game['island'] as String? ?? '',
      round: _map(json['round']),
      focusTitle: focus?['title'] as String?,
    );
  }

  final String playId;

  /// The game's page, served by the platform with nothing personal in it.
  final String pageUrl;
  final String gameId;
  final String gameKey;
  final String title;
  final String howToPlay;
  final String island;

  /// What the page is handed: `{lms: 1, type: "start", round: this}` (items, stage, locale, dir, title; muted is added).
  final Map<String, dynamic> round;
  final String? focusTitle;

  /// The ids of the round's items: only answers about these count.
  Set<String> get itemIds => {for (final i in _list(round['items'])) '${i['id']}'};
}

/// One try the game reported.
class GameAnswer {
  const GameAnswer(this.item, this.correct);

  final String item;
  final bool correct;

  Map<String, dynamic> toJson() => {'item': item, 'correct': correct};
}

/// A creature that just hatched, with three names to choose from.
class Hatched {
  const Hatched({
    required this.key,
    required this.name,
    required this.look,
    required this.island,
    required this.names,
    required this.found,
    required this.total,
    this.story = '',
  });

  factory Hatched.fromJson(Map<String, dynamic> json) => Hatched(
    key: json['key'] as String,
    name: json['name'] as String? ?? '',
    look: Look.fromJson(json['look']),
    island: json['island'] as String? ?? '',
    names: [for (final n in json['names'] as List<dynamic>? ?? const []) n as String],
    found: _int(json['found']),
    total: _int(json['total']),
    story: json['story'] as String? ?? '',
  );

  final String key;
  final String name;
  final Look look;

  /// The island's key.
  final String island;
  final List<String> names;
  final int found;
  final int total;
  final String story;
}

/// What a finished round earned.
class RoundResult {
  const RoundResult({
    required this.stars,
    required this.correct,
    required this.total,
    required this.eggHave,
    required this.eggNeed,
    this.seconds,
    this.creature,
    this.homeworkTitle,
    this.handedIn = false,
  });

  factory RoundResult.fromJson(Map<String, dynamic> json) {
    final hw = json['homework'] is Map<String, dynamic> ? json['homework'] as Map<String, dynamic> : null;
    return RoundResult(
      stars: _int(json['stars']),
      correct: _int(json['correct']),
      total: _int(json['total']),
      eggHave: _int(_map(json['egg'])['have']),
      eggNeed: _int(_map(json['egg'])['need']) == 0 ? 12 : _int(_map(json['egg'])['need']),
      seconds: (json['seconds'] as num?)?.toInt(),
      creature: json['creature'] is Map<String, dynamic> ? Hatched.fromJson(json['creature'] as Map<String, dynamic>) : null,
      homeworkTitle: hw?['title'] as String?,
      handedIn: hw?['handed_in'] == true,
    );
  }

  final int stars;
  final int correct;
  final int total;
  final int eggHave;
  final int eggNeed;

  /// How long the round took.
  final int? seconds;
  final Hatched? creature;
  final String? homeworkTitle;
  final bool handedIn;
}
