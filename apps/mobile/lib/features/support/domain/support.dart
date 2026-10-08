import 'dart:typed_data';

import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';

/// A family's or a teacher's tickets to the academy (`support/*`), the same as
/// "Help" in /my and the portal. Each person sees only their own, never the
/// academy's internal notes or decisions.
class TicketLesson extends Equatable {
  const TicketLesson({required this.id, required this.subject, required this.withName, this.startsAt, this.status = ''});

  factory TicketLesson.fromJson(Map<String, dynamic> json) => TicketLesson(
    id: json['id'] as String,
    subject: json['subject'] as String,
    withName: json['with'] as String? ?? '',
    startsAt: LocalTime.fromJson(json['starts_at']),
    status: json['status'] as String? ?? '',
  );

  final String id;
  final String subject;

  /// The other person in the lesson: the teacher for a family, the student for a teacher.
  final String withName;
  final LocalTime? startsAt;
  final String status;

  @override
  List<Object?> get props => [id];
}

/// What a new ticket can be about (`GET support/new`): the kinds this person may choose and the lessons.
class TicketOptions extends Equatable {
  const TicketOptions({required this.kinds, required this.lessons});

  factory TicketOptions.fromJson(Map<String, dynamic> json) => TicketOptions(
    kinds: [
      for (final k in json['kinds'] as List<dynamic>) (key: (k as Map<String, dynamic>)['key'] as String, label: k['label'] as String),
    ],
    lessons: [for (final l in json['lessons'] as List<dynamic>) TicketLesson.fromJson(l as Map<String, dynamic>)],
  );

  final List<({String key, String label})> kinds;
  final List<TicketLesson> lessons;

  /// Nobody may write here (a child in the kids' look: their guardian writes).
  bool get closed => kinds.isEmpty;

  @override
  List<Object?> get props => [kinds.map((k) => k.key).toList(), lessons];
}

class TicketSummary extends Equatable {
  const TicketSummary({
    required this.id,
    required this.reference,
    required this.subject,
    required this.kind,
    required this.kindLabel,
    required this.status,
    required this.statusLabel,
    this.learnerName,
    this.lastMessageAt,
    this.yourTurn = false,
  });

  factory TicketSummary.fromJson(Map<String, dynamic> json) => TicketSummary(
    id: json['id'] as String,
    reference: json['reference'] as String,
    subject: json['subject'] as String,
    kind: (json['kind'] as Map<String, dynamic>)['key'] as String,
    kindLabel: (json['kind'] as Map<String, dynamic>)['label'] as String,
    status: (json['status'] as Map<String, dynamic>)['key'] as String,
    statusLabel: (json['status'] as Map<String, dynamic>)['label'] as String,
    learnerName: (json['learner'] as Map<String, dynamic>?)?['name'] as String?,
    lastMessageAt: LocalTime.fromJson(json['last_message_at']),
    yourTurn: json['your_turn'] as bool? ?? false,
  );

  final String id;

  /// "#1024": what to quote when calling the academy.
  final String reference;
  final String subject;
  final String kind;
  final String kindLabel;

  /// open (the academy's turn) | waiting (the requester's) | resolved | closed
  final String status;
  final String statusLabel;
  final String? learnerName;
  final LocalTime? lastMessageAt;
  final bool yourTurn;

  @override
  List<Object?> get props => [id, status, lastMessageAt?.utc];
}

class TicketLine extends Equatable {
  const TicketLine({required this.id, required this.side, required this.kind, this.author, this.body, this.event, this.file, this.at});

  factory TicketLine.fromJson(Map<String, dynamic> json) {
    final file = json['file'] as Map<String, dynamic>?;
    return TicketLine(
      id: json['id'] as String,
      side: json['side'] as String,
      kind: json['kind'] as String,
      author: json['author'] as String?,
      body: json['body'] as String?,
      event: json['event'] as String?,
      file: file == null ? null : (name: file['name'] as String, mime: file['mime'] as String),
      at: LocalTime.fromJson(json['at']),
    );
  }

  final String id;

  /// requester (this person) | staff (the academy) | system
  final String side;

  /// message | event (resolved, reopened, closed)
  final String kind;
  final String? author;
  final String? body;
  final String? event;
  final ({String name, String mime})? file;
  final LocalTime? at;

  bool get mine => side == 'requester';
  bool get isEvent => kind == 'event';

  @override
  List<Object?> get props => [id];
}

class Ticket extends Equatable {
  const Ticket({
    required this.summary,
    required this.lines,
    this.lesson,
    this.canReply = false,
    this.canReopen = false,
    this.canClose = false,
    this.reopenUntil,
  });

  factory Ticket.fromJson(Map<String, dynamic> json) => Ticket(
    summary: TicketSummary.fromJson(json),
    lesson: json['lesson'] == null ? null : TicketLesson.fromJson(json['lesson'] as Map<String, dynamic>),
    canReply: json['can_reply'] as bool? ?? false,
    canReopen: json['can_reopen'] as bool? ?? false,
    canClose: json['can_close'] as bool? ?? false,
    reopenUntil: LocalTime.fromJson(json['reopen_until']),
    lines: [for (final m in json['messages'] as List<dynamic>) TicketLine.fromJson(m as Map<String, dynamic>)],
  );

  final TicketSummary summary;
  final TicketLesson? lesson;
  final bool canReply;
  final bool canReopen;
  final bool canClose;
  final LocalTime? reopenUntil;
  final List<TicketLine> lines;

  @override
  List<Object?> get props => [summary, lines, canReply, canReopen, canClose];
}

/// A picture or PDF picked to send.
class Attachment {
  const Attachment({required this.bytes, required this.name, required this.mime});

  final Uint8List bytes;
  final String name;
  final String mime;
}
