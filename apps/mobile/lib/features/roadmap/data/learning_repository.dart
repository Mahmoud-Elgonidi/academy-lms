import '../../../core/network/api_client.dart';
import '../domain/learning.dart';

/// Courses, where each learner is, and the reports their teachers wrote.
class LearningRepository {
  LearningRepository(this._api);

  final ApiClient _api;

  Future<List<StudentCourses>> students() async => [
    for (final s in (await _api.get('learning/students'))['data'] as List<dynamic>) StudentCourses.fromJson(s as Map<String, dynamic>),
  ];

  Future<List<Report>> reports(String enrollmentId) async => [
    for (final r in (await _api.get('learning/enrollments/$enrollmentId/reports'))['data'] as List<dynamic>)
      Report.fromJson(r as Map<String, dynamic>),
  ];

  /// Every course's reports for the learners this person follows, latest first.
  Future<List<Report>> allReports({String? learnerId}) async {
    final students = await this.students();
    final courses = [
      for (final s in students)
        if (learnerId == null || s.id == learnerId) ...s.courses,
    ];
    final lists = await Future.wait(courses.map((c) => reports(c.enrollmentId)));
    final all = lists.expand((r) => r).toList()..sort((a, b) => (b.lessonAt?.utc ?? DateTime(0)).compareTo(a.lessonAt?.utc ?? DateTime(0)));
    return all;
  }
}
