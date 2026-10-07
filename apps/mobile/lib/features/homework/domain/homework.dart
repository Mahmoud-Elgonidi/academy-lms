import '../../../core/time/local_time.dart';

/// A piece of homework and what was handed in.
class Homework {
  const Homework({
    required this.id,
    required this.title,
    required this.accepts,
    required this.status,
    required this.statusLabel,
    required this.student,
    required this.teacher,
    required this.submissions,
    this.instructions,
    this.dueAt,
    this.grade,
    this.feedback,
  });

  factory Homework.fromJson(Map<String, dynamic> json) => Homework(
    id: json['id'] as String,
    title: json['title'] as String? ?? '',
    instructions: json['instructions'] as String?,
    accepts: json['accepts'] as String? ?? 'any',
    status: json['status'] as String? ?? 'assigned',
    statusLabel: json['status_label'] as String? ?? '',
    student: (json['student'] as Map<String, dynamic>?)?['name'] as String? ?? '',
    teacher: (json['teacher'] as Map<String, dynamic>?)?['name'] as String? ?? '',
    dueAt: LocalTime.fromJson(json['due_at']),
    grade: (json['grade'] as num?)?.toInt(),
    feedback: json['feedback'] as String?,
    submissions: [
      for (final s in json['submissions'] as List<dynamic>? ?? const [])
        (
          body: (s as Map<String, dynamic>)['body'] as String?,
          file: (s['file'] as Map<String, dynamic>?)?['name'] as String?,
          at: LocalTime.fromJson(s['submitted_at']),
        ),
    ],
  );

  final String id;
  final String title;
  final String? instructions;

  /// any, text, audio or file.
  final String accepts;

  /// assigned, submitted, reviewed, missed.
  final String status;
  final String statusLabel;
  final String student;
  final String teacher;
  final LocalTime? dueAt;
  final int? grade;
  final String? feedback;
  final List<({String? body, String? file, LocalTime? at})> submissions;

  bool get open => status == 'assigned' || status == 'missed';

  /// The app hands in text; a recording or a file goes through the website for now.
  bool get takesText => accepts == 'text' || accepts == 'any';
}
