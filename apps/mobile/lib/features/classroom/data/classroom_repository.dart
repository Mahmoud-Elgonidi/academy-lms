import '../../../core/network/api_client.dart';
import '../domain/join_ticket.dart';

class ClassroomRepository {
  ClassroomRepository(this._api);

  final ApiClient _api;

  Future<JoinTicket> join(String lessonId) async {
    final json = await _api.post('lessons/$lessonId/join');
    return JoinTicket.fromJson(json['data'] as Map<String, dynamic>, recording: json['recording']);
  }
}
