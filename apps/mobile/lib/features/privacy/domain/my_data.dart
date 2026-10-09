import '../../../core/time/local_time.dart';

/// Someone this person may ask about: themselves, or a learner they follow.
class DataSubject {
  const DataSubject({required this.id, required this.name, required this.self, required this.canCopy});

  factory DataSubject.fromJson(Map<String, dynamic> json) => DataSubject(
    id: json['id'] as String,
    name: json['name'] as String? ?? '',
    self: json['self'] == true,
    canCopy: json['can_copy'] == true,
  );

  final String id;
  final String name;
  final bool self;

  /// A copy can be asked for now (none being prepared, none ready from the last day).
  final bool canCopy;
}

/// A request about someone's data: a copy (export) or erasure.
class DataRequest {
  const DataRequest({
    required this.id,
    required this.kind,
    required this.kindLabel,
    required this.status,
    required this.statusLabel,
    required this.subjectId,
    required this.subjectName,
    required this.canCancel,
    this.answer,
    this.createdAt,
    this.dueAt,
    this.eraseAfter,
    this.expiresAt,
    this.downloadUrl,
  });

  factory DataRequest.fromJson(Map<String, dynamic> json) {
    final kind = json['kind'] as Map<String, dynamic>;
    final status = json['status'] as Map<String, dynamic>;
    final subject = json['subject'] as Map<String, dynamic>;
    return DataRequest(
      id: json['id'] as String,
      kind: kind['key'] as String,
      kindLabel: kind['label'] as String? ?? '',
      status: status['key'] as String,
      statusLabel: status['label'] as String? ?? '',
      subjectId: subject['id'] as String,
      subjectName: subject['name'] as String? ?? '',
      canCancel: json['can_cancel'] == true,
      answer: json['answer'] as String?,
      createdAt: LocalTime.fromJson(json['created_at']),
      dueAt: LocalTime.fromJson(json['due_at']),
      eraseAfter: LocalTime.fromJson(json['erase_after']),
      expiresAt: LocalTime.fromJson(json['expires_at']),
      downloadUrl: json['download_url'] as String?,
    );
  }

  final String id;

  /// export | erasure
  final String kind;
  final String kindLabel;

  /// export: pending | ready | expired | failed; erasure: pending | approved | done | refused | cancelled
  final String status;
  final String statusLabel;
  final String subjectId;
  final String subjectName;
  final bool canCancel;
  final String? answer;
  final LocalTime? createdAt;
  final LocalTime? dueAt;
  final LocalTime? eraseAfter;
  final LocalTime? expiresAt;

  /// A ready copy's signed address (an hour; it opens without signing in).
  final String? downloadUrl;

  bool get isExport => kind == 'export';
  bool get isOpenErasure => kind == 'erasure' && (status == 'pending' || status == 'approved');
}

/// "My data": whom one may ask about, the requests (newest first), what an erasure removes and keeps, and the academy's pages.
class MyData {
  const MyData({
    required this.subjects,
    required this.requests,
    required this.goes,
    required this.stays,
    required this.staysNote,
    required this.answerDays,
    required this.links,
  });

  factory MyData.fromJson(Map<String, dynamic> json) {
    final d = json['data'] as Map<String, dynamic>;
    final links = d['links'] as Map<String, dynamic>;
    return MyData(
      subjects: [for (final s in d['subjects'] as List<dynamic>) DataSubject.fromJson(s as Map<String, dynamic>)],
      requests: [for (final r in d['requests'] as List<dynamic>) DataRequest.fromJson(r as Map<String, dynamic>)],
      goes: [for (final g in d['goes'] as List<dynamic>) g as String],
      stays: [for (final g in d['stays'] as List<dynamic>) g as String],
      staysNote: d['stays_note'] as String? ?? '',
      answerDays: (d['answer_days'] as num?)?.toInt() ?? 30,
      links: {for (final e in links.entries) e.key: e.value as String},
    );
  }

  final List<DataSubject> subjects;
  final List<DataRequest> requests;
  final List<String> goes;
  final List<String> stays;
  final String staysNote;
  final int answerDays;

  /// privacy, terms, deletion: the academy's public pages.
  final Map<String, String> links;

  /// The latest copy of [subject]'s data.
  DataRequest? copyOf(String subject) => requests.where((r) => r.subjectId == subject && r.isExport).firstOrNull;

  /// [subject]'s erasure: the open one, or the last answered (a refusal with its reason).
  DataRequest? erasureOf(String subject) =>
      requests.where((r) => r.subjectId == subject && r.kind == 'erasure' && r.status != 'cancelled').firstOrNull;
}
