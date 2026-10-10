import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';

/// Conversations between a teacher and a student (`messages/*`), the same as
/// "Messages" in /teach and /my: the student's guardians are in every one,
/// and a phone number, an email, a link or an account name is hidden before
/// anyone reads it.
class ConversationRow extends Equatable {
  const ConversationRow({
    required this.withId,
    required this.withName,
    this.id,
    this.lastBody,
    this.lastMine = false,
    this.lastAt,
    this.unread = 0,
    this.canWrite = true,
    this.whyNot,
  });

  factory ConversationRow.fromJson(Map<String, dynamic> json) {
    final last = json['last_message'] as Map<String, dynamic>?;
    final other = json['with'] as Map<String, dynamic>;
    return ConversationRow(
      id: json['id'] as String?,
      withId: other['id'] as String,
      withName: other['name'] as String? ?? '',
      lastBody: last?['body'] as String?,
      lastMine: last?['mine'] as bool? ?? false,
      lastAt: LocalTime.fromJson(last?['at']),
      unread: json['unread'] as int? ?? 0,
      canWrite: json['can_write'] as bool? ?? true,
      whyNot: json['why_not'] as String?,
    );
  }

  /// Null until someone opens it.
  final String? id;

  /// Who it is with: a student for a teacher, a teacher for a family.
  final String withId;
  final String withName;
  final String? lastBody;
  final bool lastMine;
  final LocalTime? lastAt;
  final int unread;
  final bool canWrite;
  final String? whyNot;

  @override
  List<Object?> get props => [id, withId, lastBody, lastAt, unread, canWrite];
}

/// A list with what everyone in a conversation should know (guardians read it, the academy reviews it).
class ConversationList extends Equatable {
  const ConversationList({required this.rows, required this.notice});

  factory ConversationList.fromJson(Map<String, dynamic> json) => ConversationList(
    rows: [for (final r in json['data'] as List<dynamic>) ConversationRow.fromJson(r as Map<String, dynamic>)],
    notice: (json['meta'] as Map<String, dynamic>?)?['notice'] as String? ?? '',
  );

  final List<ConversationRow> rows;
  final String notice;

  @override
  List<Object?> get props => [rows, notice];
}

class ChatMember extends Equatable {
  const ChatMember({required this.id, required this.name, required this.side, required this.label, this.me = false});

  factory ChatMember.fromJson(Map<String, dynamic> json) => ChatMember(
    id: json['id'] as String,
    name: json['name'] as String? ?? '',
    side: json['side'] as String? ?? '',
    label: json['label'] as String? ?? '',
    me: json['me'] as bool? ?? false,
  );

  final String id;
  final String name;

  /// teacher | student | guardian
  final String side;
  final String label;
  final bool me;

  @override
  List<Object?> get props => [id, side, me];
}

class ChatMessage extends Equatable {
  const ChatMessage({
    required this.id,
    required this.side,
    required this.sideLabel,
    required this.author,
    required this.body,
    this.mine = false,
    this.hidden,
    this.at,
  });

  factory ChatMessage.fromJson(Map<String, dynamic> json) => ChatMessage(
    id: json['id'] as String,
    side: json['side'] as String? ?? '',
    sideLabel: json['side_label'] as String? ?? '',
    author: json['author'] as String? ?? '',
    mine: json['mine'] as bool? ?? false,
    body: json['body'] as String? ?? '',
    hidden: json['hidden'] as String?,
    at: LocalTime.fromJson(json['at']),
  );

  final String id;
  final String side;
  final String sideLabel;
  final String author;
  final bool mine;

  /// As everyone reads it: anything hidden shows as [•••].
  final String body;

  /// What was hidden from it, in words.
  final String? hidden;
  final LocalTime? at;

  @override
  List<Object?> get props => [id];
}

class Conversation extends Equatable {
  const Conversation({
    required this.id,
    required this.withId,
    required this.withName,
    required this.members,
    required this.messages,
    required this.notice,
    this.canWrite = true,
    this.whyNot,
  });

  factory Conversation.fromJson(Map<String, dynamic> json) {
    final other = json['with'] as Map<String, dynamic>;
    return Conversation(
      id: json['id'] as String,
      withId: other['id'] as String,
      withName: other['name'] as String? ?? '',
      members: [for (final m in json['members'] as List<dynamic>) ChatMember.fromJson(m as Map<String, dynamic>)],
      messages: [for (final m in json['messages'] as List<dynamic>) ChatMessage.fromJson(m as Map<String, dynamic>)],
      canWrite: json['can_write'] as bool? ?? false,
      whyNot: json['why_not'] as String?,
      notice: json['notice'] as String? ?? '',
    );
  }

  final String id;
  final String withId;
  final String withName;
  final List<ChatMember> members;
  final List<ChatMessage> messages;
  final bool canWrite;
  final String? whyNot;
  final String notice;

  /// The others in it besides the person it is with and the reader: the guardians, or the student.
  List<ChatMember> get others => [
    for (final m in members)
      if (m.id != withId && !m.me) m,
  ];

  @override
  List<Object?> get props => [id, messages, canWrite, whyNot];
}
