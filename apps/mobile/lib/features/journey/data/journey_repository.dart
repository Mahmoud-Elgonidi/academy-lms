import '../../../core/network/api_client.dart';
import '../domain/journey.dart';

/// The learner's journey: the map built from their curriculum, and opening
/// the week's chest.
class JourneyRepository {
  JourneyRepository(this._api);

  final ApiClient _api;

  /// The student's own journey ([learnerId] null) or a guardian's child's.
  Future<JourneyMap> map({String? learnerId}) async =>
      JourneyMap.fromJson((await _api.get('learning/journey/map', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  /// Opens the week's chest ([period]: its Saturday), once (the key is kept across retries).
  Future<ChestOpened> openChest(String period) async => ChestOpened.fromJson(
    (await _api.post('learning/journey/chest', body: {'period': period}, idempotent: true))['data'] as Map<String, dynamic>,
  );
}
