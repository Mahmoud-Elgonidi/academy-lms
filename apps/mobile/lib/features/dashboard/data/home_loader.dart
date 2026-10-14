import '../../billing/data/billing_repository.dart';
import '../../billing/domain/billing.dart';
import '../../homework/data/homework_repository.dart';
import '../../homework/domain/homework.dart';
import '../../roadmap/data/learning_repository.dart';
import '../../roadmap/domain/learning.dart';
import '../../schedule/data/lessons_repository.dart';
import '../../schedule/domain/lesson.dart';
import '../../journey/data/journey_repository.dart';
import '../../journey/domain/journey.dart';

/// What the guardian's home shows about one child.
class FamilyHome {
  const FamilyHome({
    required this.lessons,
    required this.balance,
    this.invoices,
    this.report,
    this.reportCourse,
    this.homework = const [],
    this.journey,
  });

  final LessonList lessons;
  final List<Balance> balance;
  final Invoices? invoices;
  final Report? report;
  final Course? reportCourse;

  /// What is due first, then what was handed in last (three at most).
  final List<Homework> homework;

  /// The learner's journey, for its card ("Salma's journey").
  final JourneyMap? journey;

  Lesson? get next => lessons.lessons.where((l) => l.upcoming).firstOrNull;
}

/// Loads a home in one go; the parts that are only extras (money, the last
/// report, homework, the journey) never stop the home from showing.
class HomeLoader {
  HomeLoader({required this.lessons, required this.billing, required this.learning, required this.homework, required this.journey});

  final LessonsRepository lessons;
  final BillingRepository billing;
  final LearningRepository learning;
  final HomeworkRepository homework;
  final JourneyRepository journey;

  static Future<T?> _optional<T>(Future<T> future) async {
    try {
      return await future;
    } on Exception {
      return null;
    }
  }

  Future<FamilyHome> familyHome(String? learnerId) async {
    final results = await Future.wait<Object?>([
      lessons.family(learnerId: learnerId),
      _optional(billing.balance(learnerId: learnerId)),
      _optional(billing.invoices()),
      _optional(learning.students()),
      _optional(homework.list(studentId: learnerId)),
      _optional(journey.map(learnerId: learnerId)),
    ]);
    final students = results[3] as List<StudentCourses>?;
    final courses = students?.where((s) => learnerId == null || s.id == learnerId).expand((s) => s.courses).toList() ?? const <Course>[];
    Report? report;
    Course? reportCourse;
    for (final course in courses) {
      final reports = await _optional(learning.reports(course.enrollmentId));
      final latest = reports?.firstOrNull;
      if (latest != null && (report == null || (latest.lessonAt?.utc.isAfter(report.lessonAt?.utc ?? DateTime(0)) ?? false))) {
        report = latest;
        reportCourse = course;
      }
    }
    final all = results[4] as List<Homework>? ?? const <Homework>[];
    final work = [...all.where((h) => h.open), ...all.where((h) => !h.open)].take(3).toList();
    return FamilyHome(
      lessons: results[0]! as LessonList,
      balance: results[1] as List<Balance>? ?? const [],
      invoices: results[2] as Invoices?,
      report: report,
      reportCourse: reportCourse,
      homework: work,
      journey: results[5] as JourneyMap?,
    );
  }
}
