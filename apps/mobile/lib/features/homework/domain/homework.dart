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
    this.reportId,
    this.reviewedAt,
    this.practice,
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
    submissions: [for (final s in json['submissions'] as List<dynamic>? ?? const []) Submission.fromJson(s as Map<String, dynamic>)],
    reportId: json['report_id'] as String?,
    reviewedAt: LocalTime.fromJson(json['reviewed_at']),
    practice: json['practice'] is Map<String, dynamic> ? HomeworkPractice.fromJson(json['practice'] as Map<String, dynamic>) : null,
  );

  final String id;
  final String title;
  final String? instructions;

  /// any, text, audio, file or practice (handed in by playing its round in the games).
  final String accepts;

  /// assigned, submitted, reviewed, missed.
  final String status;
  final String statusLabel;
  final String student;
  final String teacher;
  final LocalTime? dueAt;
  final int? grade;
  final String? feedback;
  final List<Submission> submissions;
  final String? reportId;
  final LocalTime? reviewedAt;

  /// Practice in the games on its lessons (null: none): how the best round went, or not yet.
  final HomeworkPractice? practice;

  bool get open => status == 'assigned' || status == 'missed';

  /// Handed in by playing only: no words, recording or file.
  bool get byPlaying => accepts == 'practice';

  /// What it can be handed in as: words, a voice recording, a file (`any` takes all three).
  bool get takesText => accepts == 'text' || accepts == 'any';
  bool get takesAudio => accepts == 'audio' || accepts == 'any';
  bool get takesFile => accepts == 'file' || accepts == 'any';
}

/// A homework's practice in the games: done or not, and its best round.
class HomeworkPractice {
  const HomeworkPractice({required this.done, required this.label, this.correct, this.total, this.stars, this.game});

  factory HomeworkPractice.fromJson(Map<String, dynamic> json) => HomeworkPractice(
    done: json['done'] == true,
    label: json['label'] as String? ?? '',
    correct: (json['correct'] as num?)?.toInt(),
    total: (json['total'] as num?)?.toInt(),
    stars: (json['stars'] as num?)?.toInt(),
    game: json['game'] as String?,
  );

  final bool done;

  /// "التدريب: 9 من 10 صح · 3 ⭐ (…)", in the viewer's language.
  final String label;
  final int? correct;
  final int? total;
  final int? stars;
  final String? game;
}

/// A file handed in with homework: a recording plays in the app, a picture shows.
class SubmittedFile {
  const SubmittedFile({required this.name, required this.mime, required this.size, required this.isAudio});

  final String name;
  final String mime;
  final int size;
  final bool isAudio;

  bool get isImage => mime.startsWith('image/');
}

/// One hand-in: words, a file, or both.
class Submission {
  const Submission({required this.id, this.body, this.file, this.at});

  factory Submission.fromJson(Map<String, dynamic> json) {
    final file = json['file'] as Map<String, dynamic>?;
    return Submission(
      id: json['id'] as String,
      body: json['body'] as String?,
      file: file == null
          ? null
          : SubmittedFile(
              name: file['name'] as String? ?? '',
              mime: file['mime'] as String? ?? '',
              size: (file['size'] as num?)?.toInt() ?? 0,
              isAudio: file['is_audio'] == true,
            ),
      at: LocalTime.fromJson(json['submitted_at']),
    );
  }

  final String id;
  final String? body;
  final SubmittedFile? file;
  final LocalTime? at;
}
