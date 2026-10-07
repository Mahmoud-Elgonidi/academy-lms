import 'package:intl/intl.dart';

import '../../../core/network/api_client.dart';
import '../../../core/time/local_time.dart';
import '../domain/teaching.dart';

/// The teacher's side: lessons, students, pay, time off.
class TeachingRepository {
  TeachingRepository(this._api);

  final ApiClient _api;

  /// [from] a day on the teacher's calendar (default today), for [days] days.
  Future<TeacherLessons> lessons({DateTime? from, int days = 7}) async => TeacherLessons.fromJson(
    await _api.get('teacher/lessons', query: {if (from != null) 'from': DateFormat('yyyy-MM-dd').format(from), 'days': days}),
  );

  Future<List<TeacherStudent>> students() async => [
    for (final s in (await _api.get('teacher/students'))['data'] as List<dynamic>) TeacherStudent.fromJson(s as Map<String, dynamic>),
  ];

  Future<TeacherTodo> todo() async {
    final reports = (await _api.get('teacher/reports'))['data'] as List<dynamic>;
    final open = [
      for (final r in reports.cast<Map<String, dynamic>>())
        if (r['status'] == 'due' || r['status'] == 'overdue') r,
    ];
    final homework = (await _api.get('teacher/homework'))['data'] as List<dynamic>;
    final first = open.firstOrNull;
    return TeacherTodo(
      reports: open.length,
      homework: homework.length,
      firstReport: first == null
          ? null
          : (student: (first['student'] as Map<String, dynamic>?)?['name'] as String? ?? '', dueAt: LocalTime.fromJson(first['due_at'])),
    );
  }

  /// [month] "YYYY-MM" (default: this month on the academy's clock).
  Future<Earnings> earnings({String? month}) async => Earnings.fromJson(await _api.get('teacher/earnings', query: {'month': ?month}));

  Future<TimeOffList> timeOff() async => TimeOffList.fromJson(await _api.get('teacher/time-off'));

  Future<void> requestTimeOff({
    required DateTime firstDay,
    required String reason,
    DateTime? lastDay,
    String? from,
    String? to,
    String? note,
  }) async {
    final day = DateFormat('yyyy-MM-dd');
    await _api.post(
      'teacher/time-off',
      idempotent: true,
      body: {
        'first_day': day.format(firstDay),
        if (lastDay != null) 'last_day': day.format(lastDay),
        'from': ?from,
        'to': ?to,
        'reason': reason,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      },
    );
  }

  Future<void> withdrawTimeOff(String id) => _api.delete('teacher/time-off/$id');
}
