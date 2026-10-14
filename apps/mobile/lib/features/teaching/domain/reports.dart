import '../../../core/time/local_time.dart';
import '../../homework/domain/homework.dart';
import '../../roadmap/domain/report_answers.dart';

/// A lesson report or absence note in the teacher's list.
class ReportSummary {
  const ReportSummary({
    required this.id,
    required this.kind,
    required this.status,
    required this.statusLabel,
    required this.student,
    required this.late,
    this.lessonAt,
    this.dueAt,
    this.submittedAt,
  });

  factory ReportSummary.fromJson(Map<String, dynamic> json) => ReportSummary(
    id: json['id'] as String,
    kind: json['kind'] as String? ?? 'lesson',
    status: json['status'] as String? ?? 'due',
    statusLabel: json['status_label'] as String? ?? '',
    student: (json['student'] as Map<String, dynamic>?)?['name'] as String? ?? '',
    late: json['late'] == true,
    lessonAt: LocalTime.fromJson(json['lesson_at']),
    dueAt: LocalTime.fromJson(json['due_at']),
    submittedAt: LocalTime.fromJson(json['submitted_at']),
  );

  final String id;

  /// lesson (a report) or absence (the student did not come).
  final String kind;

  /// due, overdue, submitted or reviewed.
  final String status;
  final String statusLabel;
  final String student;
  final bool late;
  final LocalTime? lessonAt;
  final LocalTime? dueAt;
  final LocalTime? submittedAt;

  bool get open => status == 'due' || status == 'overdue';
  bool get absence => kind == 'absence';
}

/// One lesson of the curriculum in a report: today's plan suggests them,
/// the teacher says what happened (outcome), a score and a note.
class ReportItem {
  ReportItem({
    required this.nodeId,
    required this.title,
    required this.path,
    this.reason,
    this.objectives = const [],
    this.outcome,
    this.score,
    this.note,
  });

  factory ReportItem.fromJson(Map<String, dynamic> json) {
    final objectives = json['objectives'];
    return ReportItem(
      nodeId: json['node_id'] as String,
      title: json['title'] as String? ?? '',
      path: json['path'] as String? ?? '',
      reason: json['reason'] as String?,
      objectives: objectives is List
          ? [for (final o in objectives) '$o']
          : (objectives is String && objectives.isNotEmpty ? [objectives] : const []),
      outcome: json['outcome'] as String?,
      score: (json['score'] as num?)?.toInt(),
      note: json['note'] as String?,
    );
  }

  final String nodeId;
  final String title;
  final String path;

  /// next or review: why today's plan has it (null when added by hand).
  final String? reason;
  final List<String> objectives;

  /// introduced, practiced, mastered or needs_review.
  String? outcome;
  int? score;
  String? note;

  Map<String, dynamic> toJson() => {
    'node_id': nodeId,
    'outcome': outcome,
    'score': score,
    'note': (note ?? '').trim().isEmpty ? null : note!.trim(),
  };

  /// All of it, for a draft kept on the phone ([ReportItem.fromJson] reads it back).
  Map<String, dynamic> toDraft() => {...toJson(), 'title': title, 'path': path, 'reason': reason, 'objectives': objectives};
}

/// `GET teacher/reports/{id}`: the report with today's plan, the lessons
/// that can be added, and the homework given with it.
class ReportForm {
  const ReportForm({
    required this.summary,
    required this.items,
    required this.lessonOptions,
    this.engagement,
    this.overall,
    this.notesForStudent,
    this.notesInternal,
    this.absenceNote,
    this.reviewNote,
    this.homework,
    this.template,
    this.answers = const [],
  });

  factory ReportForm.fromJson(Map<String, dynamic> json) => ReportForm(
    summary: ReportSummary.fromJson(json),
    engagement: (json['engagement'] as num?)?.toInt(),
    overall: (json['overall'] as num?)?.toInt(),
    notesForStudent: json['notes_for_student'] as String?,
    notesInternal: json['notes_internal'] as String?,
    absenceNote: json['absence_note'] as String?,
    reviewNote: json['review_note'] as String?,
    items: [for (final i in json['items'] as List<dynamic>? ?? const []) ReportItem.fromJson(i as Map<String, dynamic>)],
    lessonOptions: [
      for (final o in json['lesson_options'] as List<dynamic>? ?? const [])
        (id: (o as Map<String, dynamic>)['id'] as String, label: o['label'] as String? ?? ''),
    ],
    homework: json['homework'] == null ? null : Homework.fromJson(json['homework'] as Map<String, dynamic>),
    template: ReportTemplate.fromJson(json['template']),
    answers: ReportAnswer.listOf(json['answers']),
  );

  final ReportSummary summary;
  final int? engagement;
  final int? overall;
  final String? notesForStudent;
  final String? notesInternal;
  final String? absenceNote;

  /// The academy's words when it reviewed the report.
  final String? reviewNote;
  final List<ReportItem> items;
  final List<({String id, String label})> lessonOptions;
  final Homework? homework;

  /// The subject's own questions; null when it has none.
  final ReportTemplate? template;
  final List<ReportAnswer> answers;

  /// Reviewed by the academy: it no longer changes.
  bool get locked => summary.status == 'reviewed';
}
