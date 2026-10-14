import 'package:intl/intl.dart';

import '../../../core/network/api_client.dart';
import '../../../core/time/local_time.dart';
import '../../homework/domain/homework.dart';
import '../../schedule/domain/lesson.dart';
import '../domain/reports.dart';
import '../domain/shifts.dart';
import '../domain/teaching.dart';

/// The teacher's side: lessons, students, reports, homework to mark, hours, pay, time off.
class TeachingRepository {
  TeachingRepository(this._api);

  final ApiClient _api;

  /// [from] a day on the teacher's calendar (default today), for [days] days.
  Future<TeacherLessons> lessons({DateTime? from, int days = 7}) async => TeacherLessons.fromJson(
    await _api.get('teacher/lessons', query: {if (from != null) 'from': DateFormat('yyyy-MM-dd').format(from), 'days': days}),
  );

  /// Disputes what one of my lessons was recorded as; it comes back under review, with the dispute.
  Future<Lesson> dispute(String lessonId, {required String reason, required String note}) async => Lesson.fromJson(
    (await _api.post('teacher/lessons/$lessonId/dispute', idempotent: true, body: {'reason': reason, 'note': note.trim()}))['data']
        as Map<String, dynamic>,
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

  /// Reports still to write first, then the ones sent in the last two weeks.
  Future<List<ReportSummary>> reports() async => [
    for (final r in (await _api.get('teacher/reports'))['data'] as List<dynamic>) ReportSummary.fromJson(r as Map<String, dynamic>),
  ];

  Future<ReportForm> report(String id) async =>
      ReportForm.fromJson((await _api.get('teacher/reports/$id'))['data'] as Map<String, dynamic>);

  /// Sends (or corrects) a report; the API checks it as the web form does.
  Future<void> submitReport(String id, Map<String, dynamic> payload) => _api.put('teacher/reports/$id', body: payload);

  /// [status] submitted (waiting for a mark) or reviewed (marked).
  Future<List<Homework>> homeworkToMark({String status = 'submitted'}) async => [
    for (final h in (await _api.get('teacher/homework', query: {'status': status}))['data'] as List<dynamic>)
      Homework.fromJson(h as Map<String, dynamic>),
  ];

  Future<void> mark(String homeworkId, {int? grade, String? feedback}) =>
      _api.post('teacher/homework/$homeworkId/review', body: {'grade': grade, 'feedback': feedback});

  Future<MyShifts> shifts() async => MyShifts.fromJson((await _api.get('teacher/shifts'))['data'] as Map<String, dynamic>);

  /// Saves the proposal: a draft ([submit] false) or sent to the academy.
  Future<ShiftProposal> saveShifts({
    required List<ShiftBlock> blocks,
    required String effectiveFrom,
    String? note,
    double? minHours,
    double? maxHours,
    bool submit = true,
  }) async => ShiftProposal.fromJson(
    (await _api.put(
          'teacher/shifts/proposal',
          body: {
            'blocks': [for (final b in blocks) b.toJson()],
            'effective_from': effectiveFrom,
            'note': note,
            'min_weekly_hours': minHours,
            'max_weekly_hours': maxHours,
            'submit': submit,
          },
        ))['data']
        as Map<String, dynamic>,
  );

  Future<void> withdrawShifts() => _api.post('teacher/shifts/proposal/withdraw');

  /// Takes the academy's counter-offer as it is.
  Future<void> acceptShifts() => _api.post('teacher/shifts/proposal/accept');

  Future<ShiftProposal> commentOnShifts(String body) async =>
      ShiftProposal.fromJson((await _api.post('teacher/shifts/proposal/comments', body: {'body': body}))['data'] as Map<String, dynamic>);

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

  /// The teacher's own Zoom or Meet link (lessons on outside links).
  Future<MeetingLinkInfo> meetingLink() async => MeetingLinkInfo.fromJson(await _api.get('teacher/meeting-link'));

  /// Saves the teacher's link; an empty one removes it.
  Future<MeetingLinkInfo> saveMeetingLink(String url) async =>
      MeetingLinkInfo.fromJson(await _api.put('teacher/meeting-link', body: {'url': url.trim()}));
}
