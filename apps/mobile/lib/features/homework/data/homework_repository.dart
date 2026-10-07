import '../../../core/network/api_client.dart';
import '../domain/homework.dart';

class HomeworkRepository {
  HomeworkRepository(this._api);

  final ApiClient _api;

  Future<List<Homework>> list({String? studentId}) async => [
    for (final h in (await _api.get('learning/homework', query: {'student_id': ?studentId}))['data'] as List<dynamic>)
      Homework.fromJson(h as Map<String, dynamic>),
  ];

  Future<Homework> handIn(String homeworkId, String body) async => Homework.fromJson(
    (await _api.post('learning/homework/$homeworkId/submissions', body: {'body': body}))['data'] as Map<String, dynamic>,
  );
}
