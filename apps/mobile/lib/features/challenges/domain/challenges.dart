List<Map<String, dynamic>> _list(Object? raw) => [for (final x in raw as List<dynamic>? ?? const []) x as Map<String, dynamic>];

int _int(Object? v) => (v as num?)?.toInt() ?? 0;

/// A sticker of the kids' album (one comes in each week's chest).
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

/// The kids' sticker album, read from their board (`GET learning/challenges`,
/// kept for it: the journey lists only the week's chest).
class ChallengeBoard {
  const ChallengeBoard({required this.learnerId, this.stickers, this.stickersEarned = 0});

  factory ChallengeBoard.fromJson(Map<String, dynamic> json) {
    final album = json['stickers'] as Map<String, dynamic>?;
    return ChallengeBoard(
      learnerId: json['learner_id'] as String? ?? '',
      stickers: album == null ? null : [for (final s in _list(album['items'])) Sticker.fromJson(s)!],
      stickersEarned: _int(album?['earned']),
    );
  }

  final String learnerId;

  /// The kids' album, in order (null for other stages).
  final List<Sticker>? stickers;
  final int stickersEarned;
}
