import '../../../core/network/api_client.dart';
import '../domain/join_ticket.dart';

class ClassroomRepository {
  ClassroomRepository(this._api);

  final ApiClient _api;

  Future<JoinTicket> join(String lessonId) async =>
      JoinTicket.fromJson((await _api.post('lessons/$lessonId/join'))['data'] as Map<String, dynamic>);
}
