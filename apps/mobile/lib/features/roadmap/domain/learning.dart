import '../../../core/time/local_time.dart';
import 'report_answers.dart';

List<Map<String, dynamic>> _list(Object? raw) => [for (final x in raw as List<dynamic>? ?? const []) x as Map<String, dynamic>];

/// A course a learner is in, with where they are (`GET learning/students`).
class Course {
  const Course({
    required this.enrollmentId,
    required this.subject,
    required this.teacher,
    required this.homeworkOpen,
    this.program,
    this.percent,
    this.current,
    this.etaWeeks,
  });

  factory Course.fromJson(Map<String, dynamic> json) => Course(
    enrollmentId: json['id'] as String,
    subject: json['subject'] as String? ?? '',
    teacher: json['teacher'] as String? ?? '',
    program: json['program'] as String?,
    percent: (json['percent'] as num?)?.toInt(),
    current: (json['current'] as Map<String, dynamic>?)?['title'] as String?,
    etaWeeks: (json['eta_weeks'] as num?)?.round(),
    homeworkOpen: (json['homework_open'] as num?)?.toInt() ?? 0,
  );

  final String enrollmentId;
  final String subject;
  final String teacher;
  final String? program;
  final int? percent;
  final String? current;
  final int? etaWeeks;
  final int homeworkOpen;
}

class StudentCourses {
  const StudentCourses({required this.id, required this.name, required this.courses});

  factory StudentCourses.fromJson(Map<String, dynamic> json) => StudentCourses(
    id: json['id'] as String,
    name: json['name'] as String,
    courses: _list(json['enrollments']).map(Course.fromJson).toList(),
  );

  final String id;
  final String name;
  final List<Course> courses;
}

/// A lesson report as the family reads it (never the teacher's internal notes).
class Report {
  const Report({
    required this.id,
    required this.status,
    required this.teacher,
    required this.student,
    required this.items,
    this.lessonAt,
    this.engagement,
    this.overall,
    this.notesForStudent,
    this.absenceNote,
    this.dueAt,
    this.answers = const [],
  });

  factory Report.fromJson(Map<String, dynamic> json) => Report(
    id: json['id'] as String,
    status: json['status'] as String? ?? '',
    teacher: (json['teacher'] as Map<String, dynamic>?)?['name'] as String? ?? '',
    student: (json['student'] as Map<String, dynamic>?)?['name'] as String? ?? '',
    lessonAt: LocalTime.fromJson(json['lesson_at']),
    dueAt: LocalTime.fromJson(json['due_at']),
    engagement: (json['engagement'] as num?)?.toInt(),
    overall: (json['overall'] as num?)?.toInt(),
    notesForStudent: json['notes_for_student'] as String?,
    absenceNote: json['absence_note'] as String?,
    items: [
      for (final i in _list(json['items']))
        (
          title: i['title'] as String? ?? '',
          outcome: i['outcome'] as String? ?? '',
          label: i['outcome_label'] as String? ?? '',
          score: (i['score'] as num?)?.toInt(),
        ),
    ],
    answers: ReportAnswer.listOf(json['answers']),
  );

  final String id;
  final String status;
  final String teacher;
  final String student;
  final LocalTime? lessonAt;
  final LocalTime? dueAt;
  final int? engagement;
  final int? overall;
  final String? notesForStudent;
  final String? absenceNote;
  final List<({String title, String outcome, String label, int? score})> items;

  /// Answers to the academy's own questions (only those the family reads).
  final List<ReportAnswer> answers;

  bool get open => status == 'due' || status == 'overdue';
}
