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
    expect(find.text('الطالب غاب'), findsOneWidget);
    expect(find.text('تقرير مستنيك'), findsOneWidget);
    expect(find.text('واجب تصحّحه'), findsOneWidget);

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
    expect(find.text('تقرير مستنيك'), findsNothing);
    await _close(tester);
  });

  testWidgets('the week: day by day, and the weeks before and after from the teacher\'s own today', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api.onWith('GET', 'teacher/lessons', (r) {
      final from = r.queryParameters['from'] as String? ?? '2099-10-11';
      final to = DateTime.parse(from).add(const Duration(days: 6)).toIso8601String().substring(0, 10);
      return (
        200,
        {
          'from': from,
          'to': to,
          'count': from == '2099-10-11' ? 2 : 0,
          'minutes': from == '2099-10-11' ? 90 : 0,
          'data': from == '2099-10-11' ? [_lesson('l1'), _lesson('l2', day: '2099-10-13', start: '14:00')] : <Object>[],
        },
      );
    });
    await _open(tester, app);
    await tester.tap(find.text('أسبوعي').last);
    await frames(tester, 12);

    expect(find.text('يوسف خالد'), findsNWidgets(2));
    // Seven days, the empty ones said so.
    expect(find.text('مفيش حصص'), findsNWidgets(5));

    await tester.tap(find.byTooltip('الأسبوع اللي فات'));
    await frames(tester, 10);
    expect(app.api.requests.where((r) => r.path == 'teacher/lessons').last.queryParameters['from'], '2099-10-04');
    expect(find.text('مفيش حصص الأسبوع ده.'), findsOneWidget);

    await tester.tap(find.byTooltip('الأسبوع الجاي'));
    await tester.pump();
    await tester.tap(find.byTooltip('الأسبوع الجاي'));
    await frames(tester, 10);
    expect(app.api.requests.where((r) => r.path == 'teacher/lessons').last.queryParameters['from'], '2099-10-18');
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
    await tester.tap(find.text('طلابي').last);
    await frames(tester, 12);

    expect(find.text('يوسف خالد'), findsOneWidget);
    expect(find.text('40%'), findsOneWidget);
    expect(find.text('دلوقتي: سورة النصر'), findsOneWidget);
    expect(find.text('2 للتصحيح'), findsOneWidget);
    expect(find.text('متوقف مؤقتًا'), findsOneWidget);
    expect(find.textContaining('@'), findsNothing);
    expect(find.textContaining('+966'), findsNothing);
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
    await _open(tester, app);
    await tester.tap(find.text('أرباحي').last);
    await frames(tester, 12);

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
}
