import '../../../core/network/api_client.dart';
import '../domain/messages.dart';

/// Talking with a teacher or a student on the platform (`messages/*`): the
/// same as "Messages" in /teach and /my. A family names the learner; the API
/// checks each step again (only conversations the person is in).
class MessagesRepository {
  MessagesRepository(this._api);

  final ApiClient _api;

  Future<ConversationList> conversations({String? learnerId}) async =>
      ConversationList.fromJson(await _api.get('messages/conversations', query: {'learner_id': ?learnerId}));

  /// Messages from others waiting in all the person's conversations.
  Future<int> unread() async => ((await _api.get('messages/unread'))['data'] as Map<String, dynamic>)['count'] as int? ?? 0;

  /// Opens the conversation with someone on the list (made the first time).
  Future<Conversation> open(String withId, {String? learnerId}) async => Conversation.fromJson(
    (await _api.post('messages/conversations', body: {'with_id': withId, 'learner_id': ?learnerId}))['data'] as Map<String, dynamic>,
  );

  /// One conversation; opening it marks it read.
  Future<Conversation> conversation(String id) async =>
      Conversation.fromJson((await _api.get('messages/conversations/$id'))['data'] as Map<String, dynamic>);

  /// Writes; what was hidden from the words comes back. One key per send.
  Future<ChatMessage> send(String id, String body) async => ChatMessage.fromJson(
    (await _api.post('messages/conversations/$id/messages', idempotent: true, body: {'body': body}))['data'] as Map<String, dynamic>,
  );
}
