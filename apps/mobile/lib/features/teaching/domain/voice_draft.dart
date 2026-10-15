import 'reports.dart';

/// How a field shows it came from a voice note's draft.
enum DraftMark { suggested, check }

/// `POST teacher/reports/{id}/draft`: what the teacher's voice note fills in
/// the open form. The API fills only what the form it was sent left empty
/// and saves nothing. [filled] names what it filled (a lesson as
/// `items.{id}`, a lesson's part as `items.{id}.outcome|score|note`, a
/// question as `answers.{key}`), [unsure] those to check first, [left] what
/// the report still needs.
class VoiceDraft {
  const VoiceDraft({required this.heard, required this.report, required this.filled, required this.unsure, required this.left});

  factory VoiceDraft.fromJson(Map<String, dynamic> json) {
    List<String> names(Object? list) => [for (final f in list as List<dynamic>? ?? const []) '$f'];
    return VoiceDraft(
      heard: json['heard'] as String? ?? '',
      report: (json['report'] as Map<String, dynamic>?) ?? const {},
      filled: names(json['filled']),
      unsure: names(json['unsure']),
      left: names(json['left']),
    );
  }

  /// The words heard, for the teacher to see.
  final String heard;
  final Map<String, dynamic> report;
  final List<String> filled;
  final List<String> unsure;
  final List<String> left;

  bool fills(String field) => filled.contains(field);

  /// The text the draft filled for [field] (notes_for_student, notes_internal, absence_note), else null.
  String? text(String field) => fills(field) ? report[field] as String? : null;

  /// The stars the draft filled for [field] (engagement, overall), else null.
  int? stars(String field) => fills(field) ? (report[field] as num?)?.toInt() : null;

  static String _row(String field) => field.startsWith('items.') ? field.split('.').take(2).join('.') : field;

  /// What each field shows: a lesson by its row (`items.{id}`).
  Map<String, DraftMark> get marks => {
    for (final f in filled) _row(f): unsure.any((u) => _row(u) == _row(f)) ? DraftMark.check : DraftMark.suggested,
  };

  /// The lessons with the draft in them: each row keeps its place (and the
  /// plan's reason) and takes only what was filled, so what the teacher set
  /// meanwhile stays; a lesson spoken of that is not there comes after them.
  List<ReportItem> items(List<ReportItem> current) {
    final drafted = [for (final i in report['items'] as List<dynamic>? ?? const []) i as Map<String, dynamic>];
    final said = {for (final d in drafted) d['node_id'] as String: d};
    final kept = [
      for (final i in current)
        if (said[i.nodeId] case final d?)
          ReportItem(
            nodeId: i.nodeId,
            title: i.title,
            path: i.path,
            reason: i.reason,
            objectives: i.objectives,
            outcome: fills('items.${i.nodeId}.outcome') ? d['outcome'] as String? : i.outcome,
            score: fills('items.${i.nodeId}.score') ? (d['score'] as num?)?.toInt() : i.score,
            note: fills('items.${i.nodeId}.note') ? d['note'] as String? : i.note,
          )
        else
          i,
    ];
    final known = {for (final i in current) i.nodeId};
    return [
      ...kept,
      for (final d in drafted)
        if (!known.contains(d['node_id']) && fills('items.${d['node_id']}'))
          () {
            final parts = (d['label'] as String? ?? '').split(' › ');
            return ReportItem(
              nodeId: d['node_id'] as String,
              title: parts.last.isEmpty ? d['node_id'] as String : parts.last,
              path: parts.take(parts.length - 1).join(' › '),
              outcome: d['outcome'] as String?,
              score: (d['score'] as num?)?.toInt(),
              note: d['note'] as String?,
            );
          }(),
    ];
  }

  /// The answers it filled, by question key.
  Map<String, Object> get answers {
    final all = (report['answers'] as Map<String, dynamic>?) ?? const {};
    return {
      for (final f in filled)
        if (f.startsWith('answers.') && all[f.substring(8)] != null) f.substring(8): all[f.substring(8)] as Object,
    };
  }

  /// The homework it suggested, when it filled it.
  ({String title, String? instructions, String accepts, int dueDays})? get homework {
    final h = report['homework'];
    if (!fills('homework') || h is! Map<String, dynamic> || h['title'] is! String) return null;
    return (
      title: h['title'] as String,
      instructions: h['instructions'] as String?,
      accepts: h['accepts'] as String? ?? 'any',
      dueDays: ((h['due_days'] as num?)?.toInt() ?? 3).clamp(1, 30),
    );
  }
}
