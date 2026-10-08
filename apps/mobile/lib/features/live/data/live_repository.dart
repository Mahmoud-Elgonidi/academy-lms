import '../../../core/network/api_client.dart';
import '../../../core/realtime/live.dart';

/// Where the app listens for the person's own signals, and the API's
/// signature for their channel (the token signs it; only their own).
class LiveRepository implements LiveSource {
  LiveRepository(this._api);

  final ApiClient _api;

  @override
  Future<RealtimeConfig> where() async {
    final json = await _api.get('realtime');
    return RealtimeConfig.fromJson(json['data'] as Map<String, dynamic>);
  }

  @override
  Future<String> authorize(String socketId, String channel) async {
    final json = await _api.post('realtime/auth', body: {'socket_id': socketId, 'channel_name': channel});
    return json['auth'] as String;
  }
}
