import 'dart:typed_data';

import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';

/// Telling the academy's safeguarding leads that something is wrong
/// (`safeguarding/*`), the same as "Tell us" in /my and "Raise a concern" in
/// /teach. Each person sees only what they raised and where it stands, never
/// the leads' notes or what was done.
class ConcernOptions extends Equatable {
  const ConcernOptions({
    required this.look,
    required this.categories,
    required this.textRequired,
    required this.urgentAllowed,
    required this.lessons,
    required this.students,
    required this.danger,
    this.learnerName,
  });

  factory ConcernOptions.fromJson(Map<String, dynamic> json) => ConcernOptions(
    look: json['look'] as String,
    learnerName: (json['learner'] as Map<String, dynamic>?)?['name'] as String?,
    categories: [
      for (final c in json['categories'] as List<dynamic>) (key: (c as Map<String, dynamic>)['key'] as String, label: c['label'] as String),
    ],
    textRequired: json['text_required'] as bool? ?? true,
    urgentAllowed: json['urgent_allowed'] as bool? ?? true,
    lessons: [
      for (final l in json['lessons'] as List<dynamic>)
        (
          id: (l as Map<String, dynamic>)['id'] as String,
          label: [
            l['subject'] as String?,
            LocalTime.fromJson(l['starts_at'])?.label,
            l['with'] as String?,
          ].whereType<String>().where((s) => s.isNotEmpty).join(' · '),
        ),
    ],
    students: [
      for (final s in json['students'] as List<dynamic>) (id: (s as Map<String, dynamic>)['id'] as String, label: s['name'] as String),
    ],
    danger: json['danger'] as String? ?? '',
  );

  /// kids (a child's words, the text optional, no lesson, no "danger now"), student, classic, teacher.
  final String look;
  final String? learnerName;
  final List<({String key, String label})> categories;
  final bool textRequired;
  final bool urgentAllowed;
  final List<({String id, String label})> lessons;

  /// A teacher's students of the last months.
  final List<({String id, String label})> students;

  /// What to do first when someone is in danger now.
  final String danger;

  bool get kids => look == 'kids';
  bool get teacher => look == 'teacher';

  @override
  List<Object?> get props => [look, categories.map((c) => c.key).toList(), lessons.map((l) => l.id).toList()];
}

class MyConcern extends Equatable {
  const MyConcern({
    required this.id,
    required this.number,
    required this.categoryLabel,
    required this.status,
    required this.statusLabel,
    required this.text,
    this.reply,
    this.createdAt,
  });

  factory MyConcern.fromJson(Map<String, dynamic> json) => MyConcern(
    id: json['id'] as String,
    number: json['number'] as String,
    categoryLabel: (json['category'] as Map<String, dynamic>)['label'] as String,
    status: (json['status'] as Map<String, dynamic>)['key'] as String,
    statusLabel: (json['status'] as Map<String, dynamic>)['label'] as String,
    text: json['text'] as String? ?? '',
    reply: json['reply'] as String?,
    createdAt: LocalTime.fromJson(json['created_at']),
  );

  final String id;
  final String number;
  final String categoryLabel;

  /// new (received) | open (being looked into) | closed
  final String status;
  final String statusLabel;
  final String text;

  /// The academy's reply, once closed.
  final String? reply;
  final LocalTime? createdAt;

  @override
  List<Object?> get props => [id, status, reply];
}

/// A teacher's own vetting documents (`teacher/checks`): where their criminal record check stands, and each one sent.
class MyDocuments extends Equatable {
  const MyDocuments({required this.required, required this.state, required this.stateLabel, required this.kinds, required this.checks});

  factory MyDocuments.fromJson(Map<String, dynamic> json) => MyDocuments(
    required: json['required'] as bool? ?? false,
    state: json['state'] as String,
    stateLabel: json['state_label'] as String? ?? '',
    kinds: [
      for (final k in json['kinds'] as List<dynamic>) (key: (k as Map<String, dynamic>)['key'] as String, label: k['label'] as String),
    ],
    checks: [for (final c in json['checks'] as List<dynamic>) TeacherDocument.fromJson(c as Map<String, dynamic>)],
  );

  /// The academy asks for a valid criminal record check before a teacher teaches under-18s.
  final bool required;

  /// verified | pending | expired | missing | not_required
  final String state;
  final String stateLabel;
  final List<({String key, String label})> kinds;
  final List<TeacherDocument> checks;

  @override
  List<Object?> get props => [required, state, checks];
}

class TeacherDocument extends Equatable {
  const TeacherDocument({
    required this.id,
    required this.kindLabel,
    required this.status,
    required this.statusLabel,
    this.reference,
    this.issuedOn,
    this.expiresOn,
    this.reason,
    this.fileName,
  });

  factory TeacherDocument.fromJson(Map<String, dynamic> json) => TeacherDocument(
    id: json['id'] as String,
    kindLabel: (json['kind'] as Map<String, dynamic>)['label'] as String,
    status: (json['status'] as Map<String, dynamic>)['key'] as String,
    statusLabel: (json['status'] as Map<String, dynamic>)['label'] as String,
    reference: json['reference'] as String?,
    issuedOn: json['issued_on'] as String?,
    expiresOn: json['expires_on'] as String?,
    reason: json['reason'] as String?,
    fileName: (json['file'] as Map<String, dynamic>?)?['name'] as String?,
  );

  final String id;
  final String kindLabel;

  /// pending | verified | rejected
  final String status;
  final String statusLabel;
  final String? reference;

  /// Calendar days (YYYY-MM-DD), as the academy recorded them.
  final String? issuedOn;
  final String? expiresOn;

  /// Why the academy turned it down.
  final String? reason;
  final String? fileName;

  @override
  List<Object?> get props => [id, status];
}

/// A picture or PDF picked to send.
class PickedFile {
  const PickedFile({required this.bytes, required this.name, required this.mime});

  final Uint8List bytes;
  final String name;
  final String mime;
}
