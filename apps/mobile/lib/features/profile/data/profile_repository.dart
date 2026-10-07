import '../../../core/network/api_client.dart';
import '../../../core/time/local_time.dart';

/// A device signed in to this account (`GET me/sessions`).
class DeviceSession {
  const DeviceSession({required this.id, required this.device, required this.current, this.lastUsedAt});

  factory DeviceSession.fromJson(Map<String, dynamic> json) => DeviceSession(
    id: (json['id'] as num).toInt(),
    device: json['device'] as String? ?? '',
    current: json['current'] == true,
    lastUsedAt: LocalTime.fromJson(json['last_used_at']),
  );

  final int id;
  final String device;
  final bool current;
  final LocalTime? lastUsedAt;
}

class ProfileRepository {
  ProfileRepository(this._api);

  final ApiClient _api;

  Future<List<DeviceSession>> devices() async => [
    for (final d in (await _api.get('me/sessions'))['data'] as List<dynamic>) DeviceSession.fromJson(d as Map<String, dynamic>),
  ];

  Future<void> signOutDevice(int id) => _api.delete('me/sessions/$id');
}
