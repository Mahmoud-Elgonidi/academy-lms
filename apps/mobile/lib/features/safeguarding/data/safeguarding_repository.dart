import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../domain/safeguarding.dart';

/// Concerns about a child's safety (`safeguarding/*`) and a teacher's own vetting
/// documents (`teacher/checks`). A family names the learner; the API checks each
/// step again (only one's own concerns and documents).
class SafeguardingRepository {
  SafeguardingRepository(this._api);

  final ApiClient _api;

  Future<ConcernOptions> options({String? learnerId}) async =>
      ConcernOptions.fromJson((await _api.get('safeguarding/new', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  Future<List<MyConcern>> mine({String? learnerId}) async => [
    for (final c in (await _api.get('safeguarding/concerns', query: {'learner_id': ?learnerId}))['data'] as List<dynamic>)
      MyConcern.fromJson(c as Map<String, dynamic>),
  ];

  /// Raises a concern; one key per send. Answers the thanks in the reporter's own words.
  Future<String> raise({
    required String category,
    required String text,
    required bool urgent,
    String? learnerId,
    String? lessonId,
    String? studentId,
  }) async {
    final made = await _api.post(
      'safeguarding/concerns',
      idempotent: true,
      body: {
        'category': category,
        'text': text.trim().isEmpty ? null : text.trim(),
        'urgent': urgent,
        'learner_id': ?learnerId,
        'session_id': ?lessonId,
        'student_id': ?studentId,
      },
    );
    return made['message'] as String? ?? '';
  }

  Future<MyDocuments> documents() async => MyDocuments.fromJson((await _api.get('teacher/checks'))['data'] as Map<String, dynamic>);

  /// Sends a document for the academy to check (multipart); one key per send.
  Future<void> sendDocument({
    required String kind,
    required PickedFile file,
    String? reference,
    String? issuedOn,
    String? expiresOn,
  }) async {
    final form = FormData.fromMap({
      'kind': kind,
      'reference': ?reference,
      'issued_on': ?issuedOn,
      'expires_on': ?expiresOn,
      'file': MultipartFile.fromBytes(file.bytes, filename: file.name, contentType: DioMediaType.parse(file.mime)),
    });
    await _api.post('teacher/checks', idempotent: true, body: form);
  }

  /// The scan of one of the teacher's own documents.
  Future<Uint8List> documentFile(String id) => _api.download('teacher/checks/$id/file');
}
