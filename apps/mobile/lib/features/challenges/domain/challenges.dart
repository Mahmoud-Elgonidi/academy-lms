import '../../../core/time/local_time.dart';

List<Map<String, dynamic>> _list(Object? raw) => [for (final x in raw as List<dynamic>? ?? const []) x as Map<String, dynamic>];

int _int(Object? v) => (v as num?)?.toInt() ?? 0;

/// A rank of the points ladder (beginner, explorer, star, hero, legend).
class Rank {
  const Rank({required this.key, required this.label, required this.points, required this.progress, this.next, this.nextLabel});

  factory Rank.fromJson(Map<String, dynamic>? json) => Rank(
    key: json?['key'] as String? ?? 'beginner',
    label: json?['label'] as String? ?? '',
    points: _int(json?['points']),
    progress: _int(json?['progress']),
    next: (json?['next'] as num?)?.toInt(),
    nextLabel: json?['next_label'] as String?,
  );

  final String key;
  final String label;
  final int points;

  /// 0..100 of the way to [next].
  final int progress;
  final int? next;
  final String? nextLabel;
}

class Sticker {
  const Sticker({required this.key, required this.name, this.earned = true});

  static Sticker? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    return Sticker(key: json['key'] as String, name: json['name'] as String? ?? '', earned: json['earned'] != false);
  }

  final String key;
  final String name;
  final bool earned;
}

/// One challenge of the board: its target, how far along, and its state.
class Challenge {
  const Challenge({
    required this.key,
    required this.period,
    required this.group,
    required this.title,
    required this.hint,
    required this.icon,
    required this.target,
    required this.progress,
    required this.state,
    required this.stateLabel,
    required this.claimable,
    required this.reward,
    required this.rewardLabel,
    this.sticker,
    this.world,
    this.endsAt,
  });

  factory Challenge.fromJson(Map<String, dynamic> json) {
    final world = json['world'] as Map<String, dynamic>?;
    return Challenge(
      key: json['key'] as String,
      period: json['period'] as String,
      group: json['group'] as String? ?? 'week',
      title: json['title'] as String? ?? '',
      hint: json['hint'] as String? ?? '',
      icon: json['icon'] as String? ?? 'star',
      target: _int(json['target']),
      progress: _int(json['progress']),
      state: json['state'] as String? ?? 'active',
      stateLabel: json['state_label'] as String? ?? '',
      claimable: json['claimable'] == true,
      reward: _int(json['reward']),
      rewardLabel: json['reward_label'] as String? ?? '',
      sticker: Sticker.fromJson(json['sticker']),
      world: world == null
          ? null
          : (index: _int(world['index']), title: world['title'] as String? ?? '', subject: world['subject'] as String? ?? ''),
      endsAt: LocalTime.fromJson(json['ends_at']),
    );
  }

  final String key;
  final String period;

  /// week, last_week, month, last_month or world.
  final String group;
  final String title;
  final String hint;

  /// calendar, homework, clock, star, bolt, trophy, crown or chest.
  final String icon;
  final int target;
  final int progress;

  /// active, missed, done (ready to collect) or claimed.
  final String state;
  final String stateLabel;

  /// Done, and the viewer is the student (a guardian only watches).
  final bool claimable;
  final int reward;
  final String rewardLabel;

  /// The kids' chest: the sticker it holds (a surprise until it is opened).
  final Sticker? sticker;
  final ({int index, String title, String subject})? world;
  final LocalTime? endsAt;

  String get id => '$key|$period';
  bool get done => state == 'done';
  bool get claimed => state == 'claimed';
  bool get missed => state == 'missed';

  /// Finished (collected or not).
  bool get finished => done || claimed;

  /// From last week or last month: collect it before it goes.
  bool get earlier => group == 'last_week' || group == 'last_month';
  double get fraction => target == 0 ? 0 : (progress / target).clamp(0, 1).toDouble();
}

/// A cheer a guardian sent, not seen yet.
class CheerNote {
  const CheerNote({required this.id, required this.kind, required this.from});

  factory CheerNote.fromJson(Map<String, dynamic> json) =>
      CheerNote(id: json['id'] as String, kind: json['kind'] as String? ?? 'clap', from: json['from'] as String? ?? '');

  final String id;

  /// clap, star, heart or fire.
  final String kind;
  final String from;
}

/// `GET learning/challenges`: a learner's board for their stage.
class ChallengeBoard {
  const ChallengeBoard({
    required this.learnerId,
    required this.stage,
    required this.canClaim,
    required this.points,
    required this.rank,
    required this.challenges,
    required this.cheers,
    required this.cheersLeft,
    this.stickers,
    this.stickersEarned = 0,
    this.weekEndsAt,
  });

  factory ChallengeBoard.fromJson(Map<String, dynamic> json) {
    final album = json['stickers'] as Map<String, dynamic>?;
    final week = json['week'] as Map<String, dynamic>?;
    return ChallengeBoard(
      learnerId: json['learner_id'] as String,
      stage: json['stage'] as String? ?? 'classic',
      canClaim: json['can_claim'] == true,
      points: _int(json['points']),
      rank: Rank.fromJson(json['rank'] as Map<String, dynamic>?),
      challenges: _list(json['challenges']).map(Challenge.fromJson).toList(),
      stickers: album == null ? null : [for (final s in _list(album['items'])) Sticker.fromJson(s)!],
      stickersEarned: _int(album?['earned']),
      cheers: _list(json['cheers']).map(CheerNote.fromJson).toList(),
      cheersLeft: _int(json['cheers_left']),
      weekEndsAt: LocalTime.fromJson(week?['ends_at']),
    );
  }

  final String learnerId;
  final String stage;
  final bool canClaim;
  final int points;
  final Rank rank;
  final List<Challenge> challenges;

  /// The kids' album, in order (null for other stages).
  final List<Sticker>? stickers;
  final int stickersEarned;
  final List<CheerNote> cheers;
  final int cheersLeft;
  final LocalTime? weekEndsAt;

  /// Rewards waiting to be collected.
  int get ready => challenges.where((c) => c.claimable).length;

  List<Challenge> inGroups(Set<String> groups) => challenges.where((c) => groups.contains(c.group)).toList();
}

/// `POST learning/challenges/claim`: what the reward brought.
class ClaimResult {
  const ClaimResult({
    required this.reward,
    required this.rewardLabel,
    required this.pointsBefore,
    required this.points,
    required this.rankedUp,
    required this.rank,
    this.sticker,
  });

  factory ClaimResult.fromJson(Map<String, dynamic> json) => ClaimResult(
    reward: _int(json['reward']),
    rewardLabel: json['reward_label'] as String? ?? '',
    sticker: Sticker.fromJson(json['sticker']),
    pointsBefore: _int(json['points_before']),
    points: _int(json['points']),
    rankedUp: json['ranked_up'] == true,
    rank: Rank.fromJson(json['rank'] as Map<String, dynamic>?),
  );

  final int reward;
  final String rewardLabel;
  final Sticker? sticker;
  final int pointsBefore;
  final int points;
  final bool rankedUp;
  final Rank rank;
}
