import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../domain/support.dart';

/// Writing to the academy and following the answers (`support/*`): the same as
/// "Help" in /my and "Support" in /teach. A family names the learner; the API
/// checks each step again (only the person's own tickets).
class SupportRepository {
  SupportRepository(this._api);

  final ApiClient _api;

  Future<TicketOptions> options({String? learnerId}) async =>
      TicketOptions.fromJson((await _api.get('support/new', query: {'learner_id': ?learnerId}))['data'] as Map<String, dynamic>);

  Future<List<TicketSummary>> tickets({String? learnerId}) async => [
    for (final t in (await _api.get('support/tickets', query: {'learner_id': ?learnerId}))['data'] as List<dynamic>)
      TicketSummary.fromJson(t as Map<String, dynamic>),
  ];

  Future<Ticket> ticket(String id) async => Ticket.fromJson((await _api.get('support/tickets/$id'))['data'] as Map<String, dynamic>);

  /// Writes to the academy (multipart when a picture or PDF goes with it); one key per send.
  Future<Ticket> open({
    required String kind,
    required String subject,
    required String body,
    String? learnerId,
    String? lessonId,
    Attachment? file,
  }) async {
    final form = FormData.fromMap({
      'kind': kind,
      'subject': subject,
      'body': body,
      'learner_id': ?learnerId,
      'session_id': ?lessonId,
      if (file != null) 'file': MultipartFile.fromBytes(file.bytes, filename: file.name, contentType: DioMediaType.parse(file.mime)),
    });
    return Ticket.fromJson((await _api.post('support/tickets', idempotent: true, body: form))['data'] as Map<String, dynamic>);
  }

  /// Answers the academy: words, a picture or PDF, or both.
  Future<Ticket> reply(String id, {String? body, Attachment? file}) async {
    final form = FormData.fromMap({
      'body': ?body,
      if (file != null) 'file': MultipartFile.fromBytes(file.bytes, filename: file.name, contentType: DioMediaType.parse(file.mime)),
    });
    return Ticket.fromJson((await _api.post('support/tickets/$id/messages', idempotent: true, body: form))['data'] as Map<String, dynamic>);
  }

  /// "It is not solved": within a week of the academy resolving it.
  Future<void> reopen(String id) => _api.post('support/tickets/$id/reopen');

  Future<void> close(String id) => _api.post('support/tickets/$id/close');

  /// A picture on one of the person's own messages or the academy's answers.
  Future<Uint8List> file(String ticketId, String messageId) => _api.download('support/tickets/$ticketId/messages/$messageId/file');
}
