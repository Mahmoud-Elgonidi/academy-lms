import '../../../core/network/api_client.dart';
import '../domain/games.dart';

/// The games on the journey: playing a round, hatching an egg, the
/// learner's creatures, and a guardian's limits.
class GamesRepository {
  GamesRepository(this._api);

  final ApiClient _api;

  /// Starts a round of [gameId] on a station of the journey (a lesson the learner may practise now), for a homework's
  /// practice, or on all of the learner's practice.
  Future<StartedRound> start(String gameId, {String? station, String? homeworkId, String? learnerId}) async => StartedRound.fromJson(
    (await _api.post(
          'games/plays',
          body: {'game': gameId, 'station': ?station, 'homework_id': ?homeworkId, 'learner_id': ?learnerId},
        ))['data']
        as Map<String, dynamic>,
  );

  /// Hatches the egg the journey's stars filled, on [island] (the world island the learner's level is drawn as),
  /// once (the key is kept across retries). Null: no egg was ready.
  Future<Hatched?> hatch({String? island}) async {
    final data = (await _api.post('games/hatch', body: {'island': ?island}, idempotent: true))['data'] as Map<String, dynamic>;
    final creature = data['creature'];
    return creature is Map<String, dynamic> ? Hatched.fromJson(creature) : null;
  }

  /// Ends a round with every try the game reported, once (the key is kept across retries).
  Future<RoundResult> finish(String playId, List<GameAnswer> answers) async => RoundResult.fromJson(
    (await _api.post(
          'games/plays/$playId/finish',
          body: {
            'answers': [for (final a in answers) a.toJson()],
          },
          idempotent: true,
        ))['data']
        as Map<String, dynamic>,
  );

  /// Every creature of the world, found or a shadow still.
  Future<List<Creature>> creatures({String? learnerId}) async => [
    for (final c in (await _api.get('games/creatures', query: {'learner_id': ?learnerId}))['data'] as List<dynamic>)
      Creature.fromJson(c as Map<String, dynamic>),
  ];

  /// The learner names a creature they hatched (empty: its own name back).
  Future<Creature> name(String key, String name) async =>
      Creature.fromJson((await _api.put('games/creatures/$key', body: {'name': name}))['data'] as Map<String, dynamic>);

  /// The learner keeps this creature with them.
  Future<Creature> chooseBuddy(String key) async =>
      Creature.fromJson((await _api.put('games/creatures/$key', body: {'buddy': true}))['data'] as Map<String, dynamic>);

  /// A guardian's choice for their child: on or off, minutes a day (null: the academy's), and the night lock.
  Future<GameLimits> setLimits(String learnerId, {required bool enabled, int? dailyMinutes, bool? nightLock}) async => GameLimits.fromJson(
    (await _api.put(
          'games/limits',
          body: {'learner_id': learnerId, 'enabled': enabled, 'daily_minutes': dailyMinutes, 'night_lock': ?nightLock},
        ))['data']
        as Map<String, dynamic>,
  );
}
