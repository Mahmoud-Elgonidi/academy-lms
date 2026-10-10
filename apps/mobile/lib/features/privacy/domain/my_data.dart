import '../../../core/time/local_time.dart';

/// A detail one may put right on the spot: the name, and a student's gender, country, grade and who should teach them.
class DataDetail {
  const DataDetail({required this.key, required this.label, required this.shown, this.value});

  factory DataDetail.fromJson(Map<String, dynamic> json) => DataDetail(
    key: json['key'] as String,
    label: json['label'] as String? ?? '',
    value: json['value'] as String?,
    shown: json['shown'] as String? ?? '',
  );

  /// name | gender | country | school_grade | teacher_gender
  final String key;
  final String label;

  /// What is held (a choice's value; null: not set, or no preference).
  final String? value;

  /// Ready to show.
  final String shown;
}

/// One value a detail can take, as the academy words it.
class DetailChoice {
  const DetailChoice(this.value, this.label);

  final String value;
  final String label;
}

/// Someone this person may ask about: themselves, or a learner they follow.
class DataSubject {
  const DataSubject({
    required this.id,
    required this.name,
    required this.self,
    required this.canCopy,
    this.details = const [],
    this.correction,
  });

  factory DataSubject.fromJson(Map<String, dynamic> json) => DataSubject(
    id: json['id'] as String,
    name: json['name'] as String? ?? '',
    self: json['self'] == true,
    canCopy: json['can_copy'] == true,
    details: [for (final d in json['details'] as List<dynamic>? ?? const []) DataDetail.fromJson(d as Map<String, dynamic>)],
    correction: json['correction'] is Map<String, dynamic> ? DataRequest.fromJson(json['correction'] as Map<String, dynamic>) : null,
  );

  final String id;
  final String name;
  final bool self;

  /// A copy can be asked for now (none being prepared, none ready from the last day).
  final bool canCopy;

  /// What can be put right here; empty: nothing (an erased account).
  final List<DataDetail> details;

  /// The latest correction asked of the academy about this person (not taken back).
  final DataRequest? correction;
}

/// A request about someone's data: a copy (export), a correction, or erasure.
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
    this.reason,
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
      reason: json['reason'] as String?,
      answer: json['answer'] as String?,
      createdAt: LocalTime.fromJson(json['created_at']),
      dueAt: LocalTime.fromJson(json['due_at']),
      eraseAfter: LocalTime.fromJson(json['erase_after']),
      expiresAt: LocalTime.fromJson(json['expires_at']),
      downloadUrl: json['download_url'] as String?,
    );
  }

  final String id;

  /// export | erasure | correction
  final String kind;
  final String kindLabel;

  /// export: pending | ready | expired | failed; erasure: pending | approved | done | refused | cancelled;
  /// correction: pending | done | refused | cancelled
  final String status;
  final String statusLabel;
  final String subjectId;
  final String subjectName;
  final bool canCancel;

  /// Why (erasure), or what should be corrected (correction).
  final String? reason;

  /// The academy's reason when it refused; what it changed when it corrected (optional).
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

/// "My data": whom one may ask about and what may be put right for each, the requests (newest first), what an erasure
/// removes and keeps, and the academy's pages.
class MyData {
  const MyData({
    required this.subjects,
    required this.requests,
    required this.goes,
    required this.stays,
    required this.staysNote,
    required this.answerDays,
    required this.links,
    this.choices = const {},
    this.saysMin = 10,
    this.saysMax = 1000,
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
      choices: {
        for (final e in (d['choices'] as Map<String, dynamic>? ?? const {}).entries)
          e.key: [
            for (final c in e.value as List<dynamic>)
              DetailChoice((c as Map<String, dynamic>)['value'] as String? ?? '', c['label'] as String? ?? ''),
          ],
      },
      saysMin: (d['says_min'] as num?)?.toInt() ?? 10,
      saysMax: (d['says_max'] as num?)?.toInt() ?? 1000,
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

  /// The values each detail can take (gender, country, school_grade, teacher_gender; an empty value: no preference).
  final Map<String, List<DetailChoice>> choices;

  /// Shortest and longest correction request.
  final int saysMin;
  final int saysMax;

  /// The latest copy of [subject]'s data.
  DataRequest? copyOf(String subject) => requests.where((r) => r.subjectId == subject && r.isExport).firstOrNull;

  /// [subject]'s erasure: the open one, or the last answered (a refusal with its reason).
  DataRequest? erasureOf(String subject) =>
      requests.where((r) => r.subjectId == subject && r.kind == 'erasure' && r.status != 'cancelled').firstOrNull;
}
