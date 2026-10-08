/// The academy's own questions in a subject's lesson report (its report
/// template) and their answers, as the API sends them: the teacher's form
/// gets the template (null when the subject has none), every report its
/// answers (for the family, only those it may read).
library;

/// One question: rating (1-5), number, choice, yes_no or text.
class ReportQuestion {
  const ReportQuestion({
    required this.key,
    required this.label,
    required this.type,
    this.required = false,
    this.family = true,
    this.options = const [],
    this.min,
    this.max,
    this.unit,
  });

  factory ReportQuestion.fromJson(Map<String, dynamic> json) => ReportQuestion(
    key: json['key'] as String,
    label: json['label'] as String? ?? '',
    type: json['type'] as String? ?? 'text',
    required: json['required'] == true,
    family: json['family'] != false,
    options: [
      for (final o in json['options'] as List<dynamic>? ?? const [])
        (key: (o as Map<String, dynamic>)['key'] as String, label: o['label'] as String? ?? ''),
    ],
    min: (json['min'] as num?)?.toInt(),
    max: (json['max'] as num?)?.toInt(),
    unit: json['unit'] as String?,
  );

  final String key;
  final String label;
  final String type;
  final bool required;

  /// False: only the academy reads the answer.
  final bool family;
  final List<({String key, String label})> options;
  final int? min;
  final int? max;
  final String? unit;
}

class ReportTemplate {
  const ReportTemplate({required this.id, required this.version, required this.questions});

  static ReportTemplate? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    return ReportTemplate(
      id: json['id'] as String,
      version: (json['version'] as num?)?.toInt() ?? 1,
      questions: [for (final q in json['fields'] as List<dynamic>? ?? const []) ReportQuestion.fromJson(q as Map<String, dynamic>)],
    );
  }

  final String id;
  final int version;
  final List<ReportQuestion> questions;

  /// What goes to the API: empty answers left out, numbers as numbers, texts trimmed.
  Map<String, Object> payload(Map<String, Object?> values) => {
    for (final q in questions)
      if (_clean(q, values[q.key]) case final Object v) q.key: v,
  };

  /// The required questions still without an answer.
  List<ReportQuestion> missing(Map<String, Object?> values) {
    final given = payload(values);
    return [
      for (final q in questions)
        if (q.required && !given.containsKey(q.key)) q,
    ];
  }

  static Object? _clean(ReportQuestion q, Object? value) {
    if (value == null || (value is String && value.trim().isEmpty)) return null;
    if (q.type == 'number' || q.type == 'rating') return value is num ? value : num.tryParse('$value'.trim());
    return value is String ? value.trim() : value;
  }
}

/// An answer as a report shows it: `text` is written out by the API ("4/5", "3 pages", the choice's words, yes or no).
class ReportAnswer {
  const ReportAnswer({
    required this.key,
    required this.label,
    required this.type,
    required this.value,
    required this.text,
    this.family = true,
  });

  factory ReportAnswer.fromJson(Map<String, dynamic> json) => ReportAnswer(
    key: json['key'] as String,
    label: json['label'] as String? ?? '',
    type: json['type'] as String? ?? 'text',
    value: json['value'],
    text: json['text'] as String? ?? '',
    family: json['family'] != false,
  );

  static List<ReportAnswer> listOf(Object? json) => [
    if (json is List)
      for (final a in json) ReportAnswer.fromJson(a as Map<String, dynamic>),
  ];

  final String key;
  final String label;
  final String type;
  final Object? value;
  final String text;
  final bool family;

  /// Stars for a rating, else null.
  int? get stars => type == 'rating' && value is num ? (value as num).toInt() : null;
}
