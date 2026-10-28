import 'package:academy_app/app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// One of the teacher's lessons: [with] is the student.
Map<String, Object?> _lesson(
  String id, {
  String status = 'scheduled',
  String start = '16:00',
  String day = '2099-10-11',
  bool dispute = false,
}) {
  final h = int.parse(start.substring(0, 2));
  return {
    ...lesson(id, canCancel: false, status: status),
    'starts_at': time('${day}T$start:00Z', '${day}T${(h + 3).toString().padLeft(2, '0')}:${start.substring(3)}:00+03:00'),
    'ends_at': time('${day}T${h.toString().padLeft(2, '0')}:45:00Z', '${day}T${(h + 3).toString().padLeft(2, '0')}:45:00+03:00'),
    'with': 'يوسف خالد',
    'status_label': status == 'completed' ? 'اتعملت' : 'محجوزة',
    'can_dispute': dispute,
    'dispute_until': dispute ? time('2099-10-13T16:45:00Z', '2099-10-13T19:45:00+03:00') : null,
    'dispute_reasons': dispute
        ? [
            {'key': 'student_was_there', 'label': 'الطالب كان موجود'},
          ]
        : <Object>[],
  };
}

Future<void> _open(WidgetTester tester, TestApp app, [String? location]) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  if (location != null) {
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go(location);
    await frames(tester, 12);
  }
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  testWidgets('today: the count, the next lesson, what waits, and disputing a lesson recorded wrongly', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final waiting = {'status': 'open', 'status_label': 'مستني قرار', 'reason': 'الطالب كان موجود', 'answer': null};
    var disputed = false;
    app.api
      ..onWith(
        'GET',
        'teacher/lessons',
        (_) => (
          200,
          {
            'from': '2099-10-11',
            'to': '2099-10-11',
            'count': 2,
            'minutes': 90,
            'data': [
              {
                ..._lesson('l0', status: disputed ? 'needs_review' : 'student_absent', start: '06:00', dispute: !disputed),
                'status_label': disputed ? 'تحت المراجعة' : 'الطالب غاب',
                'dispute': disputed ? waiting : null,
              },
              _lesson('l1'),
            ],
          },
        ),
      )
      ..onWith('POST', 'teacher/lessons/l0/dispute', (_) {
        disputed = true;
        return (200, {'data': _lesson('l0', status: 'needs_review')});
      });
    await _open(tester, app);

    expect(find.text('النهارده: حصتين'), findsOneWidget);
    expect(find.text('الحصة الجاية'), findsOneWidget);
    // What is done today is listed apart, with what it was recorded as.
    expect(find.text('خلصت النهارده'), findsOneWidget);
    expect(find.text('الطالب غاب'), findsOneWidget);
    // What waits: a report to write and a homework to mark, each opening its place.
    expect(find.descendant(of: find.byKey(const Key('today-reports')), matching: find.text('1')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('today-marking')), matching: find.text('1')), findsOneWidget);

    // The lesson's own sheet offers the dispute.
    await tester.tap(find.byKey(const Key('lesson-l0')));
    await frames(tester, 8);
    await tester.tap(find.text('اعترض'));
    await frames(tester, 8);
    expect(find.text('اعتراض على نتيجة الحصة'), findsOneWidget);
    await tester.tap(find.text('الطالب كان موجود'));
    await tester.enterText(find.byType(TextField).last, 'يوسف كان في الحصة والكاميرا مقفولة');
    await tester.tap(find.text('ابعت الاعتراض'));
    await frames(tester, 10);

    final sent = app.api.sent('POST', 'teacher/lessons/l0/dispute').single;
    expect(sent.data, {'reason': 'student_was_there', 'note': 'يوسف كان في الحصة والكاميرا مقفولة'});
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(find.text('الاعتراض: مستني قرار'), findsOneWidget);
    expect(find.text('اعترض'), findsNothing);
    await _close(tester);
  });

  testWidgets('today still shows the lessons when the reports cannot be read', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/lessons', {
        'from': '2099-10-11',
        'to': '2099-10-11',
        'count': 1,
        'minutes': 45,
        'data': [_lesson('l1')],
      })
      ..on('GET', 'teacher/reports', {'type': 'server', 'title': 'حصلت مشكلة.', 'status': 500}, status: 500);
    await _open(tester, app);

    expect(find.text('النهارده: حصة واحدة'), findsOneWidget);
    expect(find.text('الحصة الجاية'), findsOneWidget);
    expect(find.byKey(const Key('today-reports')), findsNothing);
    await _close(tester);
  });

  testWidgets('the week: Saturday to Friday around the teacher\'s own today, a day picked, and the weeks before and after', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    // Today on the teacher's clock is Sunday 11 October; the week starts on Saturday the 10th.
    app.api.onWith('GET', 'teacher/lessons', (r) {
      final from = r.queryParameters['from'] as String? ?? '2099-10-11';
      final to = DateTime.parse(from).add(const Duration(days: 6)).toIso8601String().substring(0, 10);
      final week = from == '2099-10-10';
      return (
        200,
        {
          'from': from,
          'to': to,
          'count': week ? 3 : 0,
          'minutes': week ? 135 : 0,
          'data': week ? [_lesson('l0', day: '2099-10-10'), _lesson('l1'), _lesson('l2', day: '2099-10-13', start: '14:00')] : <Object>[],
        },
      );
    });
    await _open(tester, app);
    await tester.tap(find.text('أسبوعي').last);
    await frames(tester, 12);
    expect(app.api.requests.where((r) => r.path == 'teacher/lessons').last.queryParameters['from'], '2099-10-10');

    expect(find.text('الأسبوع ده'), findsOneWidget);
    // Seven days on the strip, the ones without lessons with a dash.
    expect(find.text('—'), findsNWidgets(4));
    // From today on: Sunday and Tuesday; Saturday is a tap away.
    expect(find.textContaining('يوسف خالد'), findsNWidgets(2));
    await tester.tap(find.byKey(const Key('day-2099-10-10')));
    await frames(tester, 6);
    expect(find.textContaining('يوسف خالد'), findsNWidgets(3));

    await tester.tap(find.byTooltip('الأسبوع اللي فات'));
    await frames(tester, 10);
    expect(app.api.requests.where((r) => r.path == 'teacher/lessons').last.queryParameters['from'], '2099-10-03');
    expect(find.text('مفيش حصص الأسبوع ده.'), findsOneWidget);

    await tester.tap(find.byTooltip('الأسبوع الجاي'));
    await tester.pump();
    await tester.tap(find.byTooltip('الأسبوع الجاي'));
    await frames(tester, 10);
    expect(app.api.requests.where((r) => r.path == 'teacher/lessons').last.queryParameters['from'], '2099-10-17');
    await _close(tester);
  });

  testWidgets('my students: where each is, what waits to be marked, and no one\'s contact details', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api.on('GET', 'teacher/students', {
      'data': [
        {
          'enrollment_id': 'e1',
          'student': 'يوسف خالد',
          'subject': 'قرآن',
          'paused': false,
          'percent': 40,
          'here': 'سورة النصر',
          'to_mark': 2,
          'last_report_at': time('2099-10-06T16:00:00Z', '2099-10-06T19:00:00+03:00', label: 'الثلاثاء 6 أكتوبر'),
          'materials': <Object>[],
        },
        {'enrollment_id': 'e2', 'student': 'سلمى رامي', 'subject': 'لغة عربية', 'paused': true, 'percent': null, 'to_mark': 0},
      ],
    });
    await _open(tester, app);
    await tester.tap(find.text('الطلاب').last);
    await frames(tester, 12);

    expect(find.text('طالبين في مادتين'), findsOneWidget);
    expect(find.text('يوسف خالد'), findsOneWidget);
    expect(find.text('قرآن · سورة النصر'), findsOneWidget);
    expect(find.text('2 للتصحيح'), findsOneWidget);
    expect(find.text('متوقف مؤقتًا'), findsOneWidget);
    expect(find.textContaining('@'), findsNothing);
    expect(find.textContaining('+966'), findsNothing);

    // A subject narrows the list, and so does a name.
    await tester.tap(find.byKey(const Key('subject-لغة عربية')));
    await frames(tester, 4);
    expect(find.text('يوسف خالد'), findsNothing);
    expect(find.text('سلمى رامي'), findsOneWidget);
    await tester.tap(find.byKey(const Key('subject-all')));
    await tester.enterText(find.byKey(const Key('student-search')), 'يوسف');
    await frames(tester, 4);
    expect(find.text('سلمى رامي'), findsNothing);

    // The row opens the student's sheet: how far, where now, and what to do.
    await tester.tap(find.byKey(const Key('student-e1')));
    await frames(tester, 8);
    expect(find.text('40% من البرنامج'), findsOneWidget);
    expect(find.text('دلوقتي: سورة النصر'), findsOneWidget);
    expect(find.text('صحّح الواجبات (2)'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('earnings: the month so far, each lesson\'s line, and the month before (not after this one)', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    Map<String, Object?> money(String f) => {'minor': 0, 'currency': 'EGP', 'formatted': f};
    app.api.onWith('GET', 'teacher/earnings', (r) {
      final month = r.queryParameters['month'] as String? ?? '2099-10';
      return (
        200,
        {
          'month': month,
          'label': month == '2099-10' ? 'أكتوبر 2099' : 'سبتمبر 2099',
          'closed': month != '2099-10',
          'unpriced': month == '2099-10' ? 1 : 0,
          'totals': [
            {
              'lessons': 3,
              'hours': '2.25',
              'lesson_pay': money('450 ج.م.'),
              'bonuses': money('50 ج.م.'),
              'deductions': money('0 ج.م.'),
              'total': money('500 ج.م.'),
            },
          ],
          'lessons': [
            {
              'when': 'الثلاثاء 6 أكتوبر',
              'student': 'يوسف خالد',
              'subject': 'قرآن',
              'outcome': 'اتعملت',
              'share': '60%',
              'amount': '150 ج.م.',
              'negative': false,
              'corrected': false,
              'notes': <String>[],
            },
            {
              'when': 'الأربعاء 7 أكتوبر',
              'student': 'سلمى رامي',
              'subject': 'لغة عربية',
              'outcome': 'تحت المراجعة',
              'share': '',
              'amount': '-150 ج.م.',
              'negative': true,
              'corrected': true,
              'notes': ['اتراجعت بعد اعتراض'],
            },
          ],
          'adjustments': [
            {'kind': 'مكافأة', 'note': 'التزام', 'amount': '50 ج.م.', 'negative': false, 'at': '1 أكتوبر', 'reversed': false},
            {'kind': 'خصم', 'note': 'اتلغى', 'amount': '-20 ج.م.', 'negative': true, 'at': '2 أكتوبر', 'reversed': true},
          ],
        },
      );
    });
    // Earnings sit under More now; their old place leads there.
    await _open(tester, app, '/teach/earnings');
    expect(GoRouter.of(tester.element(find.byType(Scaffold).first)).state.matchedLocation, '/teach/more/earnings');

    expect(find.text('أكتوبر 2099'), findsOneWidget);
    expect(find.text('500 ج.م.'), findsOneWidget);
    expect(find.text('الشهر لسه مفتوح'), findsOneWidget);
    expect(find.text('اتراجعت بعد اعتراض'), findsOneWidget);
    expect(find.text('اتصحّح'), findsOneWidget);
    expect(find.text('التزام'), findsOneWidget);
    // A reversed adjustment is not shown.
    expect(find.text('اتلغى'), findsNothing);
    final next = find.ancestor(of: find.byTooltip('الشهر الجاي'), matching: find.byType(IconButton));
    expect(tester.widget<IconButton>(next).onPressed, isNull);

    await tester.tap(find.byTooltip('الشهر اللي فات'));
    await frames(tester, 10);
    expect(app.api.requests.where((r) => r.path == 'teacher/earnings').last.queryParameters['month'], '2099-09');
    expect(find.text('سبتمبر 2099'), findsOneWidget);
    expect(find.text('الشهر اتقفل'), findsOneWidget);
    await tester.tap(find.byTooltip('الشهر الجاي'));
    await frames(tester, 10);
    expect(app.api.requests.where((r) => r.path == 'teacher/earnings').last.queryParameters['month'], '2099-10');
    expect(tester.widget<IconButton>(next).onPressed, isNull);
    await _close(tester);
  });

  testWidgets('time off: nothing goes without days and a reason; a request goes once; a pending one is withdrawn', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final requests = <Map<String, Object?>>[];
    app.api
      ..onWith('GET', 'teacher/time-off', (_) => (200, {'data': requests, 'holidays': <Object>[]}))
      ..onWith('POST', 'teacher/time-off', (r) {
        requests.add({
          'id': 't1',
          'period': 'يوم واحد',
          'reason_label': 'مرض',
          'note': null,
          'status': 'pending',
          'status_label': 'مستني الرد',
          'decision_note': null,
          'can_withdraw': true,
          'cover': null,
        });
        return (201, {'data': requests.last});
      })
      ..onWith('DELETE', 'teacher/time-off/t1', (_) {
        requests.clear();
        return (200, <String, Object?>{});
      });
    await _open(tester, app, '/teach/more/time-off');
    expect(find.text('اطلب إجازة'), findsWidgets);

    await tester.tap(find.text('ابعت الطلب'));
    await frames(tester, 3);
    expect(find.text('اختار الأيام والسبب الأول.'), findsOneWidget);
    expect(app.api.sent('POST', 'teacher/time-off'), isEmpty);

    // From today (the picker opens on it).
    await tester.tap(find.text('—').first);
    await frames(tester, 6);
    final ok = MaterialLocalizations.of(tester.element(find.byType(DatePickerDialog))).okButtonLabel;
    await tester.tap(find.text(ok));
    await frames(tester, 6);
    await tester.tap(find.text('مرض'));
    await tester.tap(find.text('ابعت الطلب'));
    await frames(tester, 10);

    final sent = app.api.sent('POST', 'teacher/time-off').single;
    final today = DateTime.now().toIso8601String().substring(0, 10);
    expect(sent.data, {'first_day': today, 'last_day': today, 'reason': 'sick'});
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(find.text('الطلب اتبعت. هيوصلك الرد.'), findsOneWidget);
    expect(find.text('مستني الرد'), findsOneWidget);

    await tester.tap(find.text('اسحبه'));
    await frames(tester, 10);
    expect(app.api.sent('DELETE', 'teacher/time-off/t1'), hasLength(1));
    expect(find.text('الطلب اتسحب.'), findsOneWidget);
    expect(find.text('مستني الرد'), findsNothing);
    await _close(tester);
  });

  testWidgets('time off: coming back to the app shows the academy\'s answer', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    var status = ('pending', 'مستني الرد');
    app.api.onWith(
      'GET',
      'teacher/time-off',
      (_) => (
        200,
        {
          'data': [
            {
              'id': 't1',
              'period': 'من 20 لحد 22 أكتوبر',
              'reason_label': 'إجازة سنوية',
              'status': status.$1,
              'status_label': status.$2,
              'can_withdraw': status.$1 == 'pending',
            },
          ],
          'holidays': <Object>[],
        },
      ),
    );
    await _open(tester, app, '/teach/more/time-off');
    expect(find.text('مستني الرد'), findsOneWidget);

    // The academy approves it while the teacher is in another app.
    status = ('approved', 'اتوافق عليه');
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    await frames(tester, 3);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await frames(tester, 10);
    expect(find.text('اتوافق عليه'), findsOneWidget);
    expect(find.text('اسحبه'), findsNothing);
    await _close(tester);
  });

  testWidgets('a teacher sets their own Zoom or Meet link from the phone, and is told when lessons need it', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    String? saved;
    Map<String, Object?> link() => {
      'url': saved,
      'used': true,
      'label': 'لينك Zoom أو Meet بتاعك',
      'hint': 'بيستخدم لو الأكاديمية شغالة على لينكات خارجية بدل الفصل.',
    };
    app.api
      ..onWith('GET', 'teacher/meeting-link', (_) => (200, link()))
      ..onWith('PUT', 'teacher/meeting-link', (r) {
        final url = ((r.data as Map)['url'] as String).trim();
        saved = url.isEmpty ? null : url;
        return (200, link());
      });
    await _open(tester, app, '/teach/more');
    await tester.tap(find.byKey(const Key('open-meeting-link')));
    await frames(tester, 12);
    expect(find.text('لينك الحصة بتاعك'), findsWidgets);
    expect(find.text('الأكاديمية شغالة على لينكات خارجية، ومفيش حصة هتفتح من غير لينكك.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('meeting-link')), ' https://meet.google.com/abc-defg-hij ');
    await tester.tap(find.text('احفظ اللينك'));
    await frames(tester, 10);
    expect(app.api.sent('PUT', 'teacher/meeting-link').single.data, {'url': 'https://meet.google.com/abc-defg-hij'});
    expect(find.text('اللينك اتحفظ.'), findsOneWidget);
    expect(find.text('الأكاديمية شغالة على لينكات خارجية، ومفيش حصة هتفتح من غير لينكك.'), findsNothing);
    await _close(tester);
  });

  testWidgets('the Reports tab: what waits counted on the tab, marking on top, then the reports to write and the ones sent', (
    tester,
  ) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/reports', {
        'data': [
          report('r1'),
          {
            ...report('r2', status: 'overdue'),
            'status_label': 'متأخر يوم',
            'student': {'id': 's2', 'name': 'ليلى عمرو'},
          },
          {
            ...report('r3', status: 'submitted'),
            'student': {'id': 's3', 'name': 'مريم خالد'},
          },
        ],
      })
      ..on('GET', 'teacher/homework', {
        'data': [homeworkJson('h1', status: 'submitted'), homeworkJson('h2', status: 'submitted')],
      });
    await _open(tester, app, '/teach/reports');

    // Two reports and two homework wait: the tab says 4, the line above says what.
    expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('4')), findsOneWidget);
    expect(find.text('مستنيك: تقريرين وواجبين'), findsOneWidget);
    expect(find.text('واجبات مستنية تصحيح'), findsOneWidget);
    // The overdue one comes first, with how late in its line.
    final rows = tester.widgetList<InkWell>(find.byWidgetPredicate((w) => w is InkWell && '${w.key}'.contains('report-r'))).toList();
    expect(rows.first.key, const Key('report-r2'));
    expect(find.textContaining('متأخر يوم'), findsOneWidget);
    expect(find.text('اكتب'), findsNWidgets(2));
    expect(find.text('اتبعتت'), findsOneWidget);
    expect(find.text('مريم خالد'), findsOneWidget);

    await tester.tap(find.byKey(const Key('open-marking')));
    await frames(tester, 12);
    expect(find.text('تصحيح الواجبات'), findsWidgets);
    await _close(tester);
  });

  testWidgets('More: teaching, work and the account, with the link\'s service, unread messages and earnings', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'messages/unread', {
        'data': {'count': 3},
      })
      ..on('GET', 'teacher/meeting-link', {'url': 'https://zoom.us/j/123', 'used': true, 'label': '', 'hint': ''});
    await _open(tester, app, '/teach/more');

    expect(find.text('التدريس'), findsOneWidget);
    expect(find.text('شغلي'), findsOneWidget);
    expect(find.text('حسابي'), findsOneWidget);
    expect(find.text('Zoom'), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('open-messages')), matching: find.text('3')), findsOneWidget);
    expect(find.byKey(const Key('more-who')), findsOneWidget);
    expect(find.text('مدرّس · أكاديمية النور'), findsOneWidget);
    // Earnings left the tabs for More.
    expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('أرباحي')), findsNothing);
    await tester.tap(find.byKey(const Key('open-earnings')));
    await frames(tester, 12);
    expect(GoRouter.of(tester.element(find.byType(Scaffold).first)).state.matchedLocation, '/teach/more/earnings');
    await _close(tester);
  });
}
