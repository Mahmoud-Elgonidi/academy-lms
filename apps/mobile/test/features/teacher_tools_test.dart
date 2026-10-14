import 'package:academy_app/app/app.dart';
import 'package:academy_app/shared/widgets/star_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Future<void> _open(WidgetTester tester, TestApp app) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 20);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

/// Taps the [n]th star (1-5) of the [which]th star picker on screen.
Future<void> _star(WidgetTester tester, int which, int n) async {
  await tester.tap(find.descendant(of: find.byType(StarPicker).at(which), matching: find.byType(InkResponse)).at(n - 1));
  await frames(tester, 2);
}

void main() {
  testWidgets('a teacher writes a report from the phone: what happened, ratings, words, and homework', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/reports/r1', {
        'data': {
          ...report('r1'),
          'items': [
            {
              'node_id': 'n2',
              'reason': 'next',
              'outcome': 'introduced',
              'score': null,
              'note': null,
              'title': 'النصر والكافرون',
              'path': 'قصار السور',
              'objectives': ['يقرأ السورة بالتجويد'],
            },
          ],
          'lesson_options': [
            {'id': 'n1', 'label': 'قصار السور › الإخلاص والمسد'},
            {'id': 'n2', 'label': 'قصار السور › النصر والكافرون'},
          ],
          'homework': null,
          'roadmap': null,
        },
      })
      ..on('PUT', 'teacher/reports/r1', {'data': report('r1', status: 'submitted')});
    await _open(tester, app);

    // Today's waiting tile opens the reports.
    await tester.tap(find.byKey(const Key('today-reports')));
    await frames(tester, 10);
    expect(find.text('تقارير مستنياك'), findsOneWidget);
    await tester.tap(find.text('يوسف خالد'));
    await frames(tester, 10);
    expect(find.text('خطة النهارده'), findsOneWidget);
    expect(find.text('• يقرأ السورة بالتجويد'), findsOneWidget);

    // No ratings yet: nothing is sent.
    await tester.tap(find.text('ابعت التقرير'));
    await frames(tester, 3);
    expect(find.text('اختار التركيز والمستوى العام.'), findsOneWidget);
    expect(app.api.sent('PUT', 'teacher/reports/r1'), isEmpty);

    await tester.tap(find.text('اتقن'));
    await frames(tester, 2);
    await _star(tester, 0, 4); // the lesson's score
    await _star(tester, 1, 5); // focus
    await _star(tester, 2, 4); // overall
    await tester.enterText(find.widgetWithText(TextField, 'ملاحظات للطالب وولي الأمر'), 'شاطر جدًا');
    await tester.tap(find.text('ادّي واجب'));
    await frames(tester, 3);
    await tester.enterText(find.widgetWithText(TextField, 'الواجب'), 'سمّع سورة النصر');
    await tester.tap(find.text('تسجيل صوت'));
    await frames(tester, 2);
    await tester.scrollUntilVisible(find.text('ابعت التقرير'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('ابعت التقرير'));
    await frames(tester, 5);
    // A quick moment says it went, then gets out of the way.
    expect(find.text('التقرير اتبعت.'), findsOneWidget);
    await frames(tester, 12);
    expect(find.text('التقرير اتبعت.'), findsNothing);

    final sent = app.api.sent('PUT', 'teacher/reports/r1').single.data as Map<String, dynamic>;
    expect(sent['engagement'], 5);
    expect(sent['overall'], 4);
    expect(sent['notes_for_student'], 'شاطر جدًا');
    expect(sent['items'], [
      {'node_id': 'n2', 'outcome': 'mastered', 'score': 4, 'note': null},
    ]);
    expect(sent['homework'], {'title': 'سمّع سورة النصر', 'instructions': '', 'accepts': 'audio', 'due_days': 3});
    // Back on the list, which loads again.
    expect(app.api.sent('GET', 'teacher/reports').length, greaterThanOrEqualTo(2));

    await _close(tester);
  });

  testWidgets('a teacher marks handed-in homework with stars and a word', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api.on('POST', 'teacher/homework/h1/review', {'data': homeworkJson('h1', status: 'reviewed')});
    await _open(tester, app);

    await tester.tap(find.byKey(const Key('today-marking')));
    await frames(tester, 10);
    expect(find.text('تصحيح الواجبات'), findsOneWidget);
    expect(find.text('قريت السورة 3 مرات'), findsOneWidget);

    await _star(tester, 0, 4);
    await tester.enterText(find.widgetWithText(TextField, 'كلمتك للطالب'), 'برافو!');
    await tester.tap(find.text('سجّل التصحيح'));
    await frames(tester, 8);
    expect(app.api.sent('POST', 'teacher/homework/h1/review').single.data, {'grade': 4, 'feedback': 'برافو!'});
    expect(find.text('اتسجّل التصحيح.'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('a teacher accepts the academy\'s change to their hours, talks it over, and proposes again', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    Map<String, Object?> block(int d, String s, String e) => {'weekday': d, 'start': s, 'end': e, 'duration_min': 0};
    Map<String, Object?> week(List<Map<String, Object?>> blocks) => {'timezone': 'Africa/Cairo', 'weekly_minutes': 0, 'blocks': blocks};
    var countered = true;
    app.api
      ..onWith(
        'GET',
        'teacher/shifts',
        (_) => (
          200,
          {
            'data': {
              'timezone': 'Africa/Cairo',
              'current': week([block(2, '17:00', '22:00'), block(4, '18:00', '21:00')]),
              'proposal': countered
                  ? {
                      'id': 'p1',
                      'status': 'countered',
                      'status_label': 'عرض مضاد من الإدارة',
                      'waiting_on': 'teacher',
                      'revision': 2,
                      'author_role': 'admin',
                      'effective_from': '2099-10-10',
                      'note': null,
                      'min_weekly_minutes': null,
                      'max_weekly_minutes': null,
                      'week': week([block(2, '17:00', '22:00'), block(6, '16:00', '20:00')]),
                      'previous_week': week([block(2, '17:00', '22:00'), block(4, '18:00', '21:00')]),
                      'submitted_at': null,
                      'comments': [
                        {'author_role': 'admin', 'mine': false, 'body': 'الخميس مليان، ينفع السبت؟', 'at': null},
                      ],
                    }
                  : null,
              'earliest_start': {'adding_time': '2099-10-08', 'removing_time': '2099-10-21'},
            },
          },
        ),
      )
      ..onWith('POST', 'teacher/shifts/proposal/accept', (_) {
        countered = false;
        return (200, {'data': <String, Object?>{}});
      })
      ..on('POST', 'teacher/shifts/proposal/comments', {
        'data': {
          'id': 'p1',
          'status': 'countered',
          'status_label': '',
          'effective_from': '2099-10-10',
          'week': week([]),
          'comments': [
            {'mine': false, 'body': 'الخميس مليان، ينفع السبت؟'},
            {'mine': true, 'body': 'تمام، السبت مناسب'},
          ],
        },
      }, status: 201)
      ..on('PUT', 'teacher/shifts/proposal', {
        'data': {
          'id': 'p2',
          'status': 'submitted',
          'status_label': '',
          'effective_from': '2099-10-08',
          'week': week([]),
          'comments': <Object>[],
        },
      });
    await _open(tester, app);

    await tester.tap(find.text('المزيد').last);
    await frames(tester, 8);
    await tester.tap(find.text('مواعيدي'));
    await frames(tester, 12);
    expect(find.text('الأكاديمية عدّلت مقترحك. ده اللي اتغير:'), findsOneWidget);
    // Saturday was added, Thursday's span removed.
    expect(find.text('16:00–20:00'), findsOneWidget);
    expect(find.text('الخميس مليان، ينفع السبت؟'), findsOneWidget);

    await tester.enterText(find.byType(TextField).last, 'تمام، السبت مناسب');
    await tester.tap(find.byTooltip('ابعت'));
    await frames(tester, 6);
    expect(app.api.sent('POST', 'teacher/shifts/proposal/comments').single.data, {'body': 'تمام، السبت مناسب'});
    expect(find.text('تمام، السبت مناسب'), findsOneWidget);

    await tester.tap(find.text('موافق على التعديل'));
    await frames(tester, 10);
    expect(app.api.sent('POST', 'teacher/shifts/proposal/accept'), hasLength(1));
    expect(find.text('مواعيدك اتعتمدت.'), findsOneWidget);

    // No open proposal now: propose again from the week in force, without Thursday.
    await tester.tap(find.text('اقترح مواعيد جديدة'));
    await frames(tester, 10);
    expect(find.text('عدّل مواعيدك'), findsOneWidget);
    await tester.tap(find.byTooltip('شيل').last);
    await frames(tester, 3);
    await tester.scrollUntilVisible(find.text('ابعت للأكاديمية'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('ابعت للأكاديمية'));
    await frames(tester, 10);
    final put = app.api.sent('PUT', 'teacher/shifts/proposal').single.data as Map<String, dynamic>;
    expect(put['blocks'], [
      {'weekday': 2, 'start': '17:00', 'end': '22:00'},
    ]);
    expect(put['effective_from'], '2099-10-08');
    expect(put['submit'], true);
    expect(find.text('المقترح اتبعت للأكاديمية.'), findsOneWidget);

    await _close(tester);
  });
}
