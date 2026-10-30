import 'package:academy_app/app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _block(int d, String s, String e) => {'weekday': d, 'start': s, 'end': e, 'duration_min': 0};
Map<String, Object?> _week(List<Map<String, Object?>> blocks) => {'timezone': 'Africa/Cairo', 'weekly_minutes': 0, 'blocks': blocks};
Map<String, Object?> _at(String local, String label) => {
  'utc': '${local.substring(0, 19)}Z',
  'local': local,
  'timezone': 'Africa/Cairo',
  'label': label,
};

Map<String, Object?> _proposal(String status, {List<Map<String, Object?>> comments = const [], Map<String, Object?>? submittedAt}) => {
  'id': 'p1',
  'status': status,
  'status_label': {'draft': 'مسودة', 'submitted': 'قيد المراجعة', 'changes_requested': 'مطلوب تعديل'}[status] ?? status,
  'waiting_on': status == 'submitted' ? 'admin' : 'teacher',
  'revision': 1,
  'author_role': 'teacher',
  'effective_from': '2099-10-10',
  'note': null,
  'min_weekly_minutes': null,
  'max_weekly_minutes': null,
  'week': _week([_block(2, '17:00', '22:00')]),
  'previous_week': null,
  'submitted_at': submittedAt,
  'comments': comments,
};

Map<String, Object?> _shifts({
  Map<String, Object?>? proposal,
  Map<String, Object?>? upcoming,
  List<Map<String, Object?>> history = const [],
}) => {
  'data': {
    'timezone': 'Africa/Cairo',
    'current': _week([_block(2, '17:00', '22:00'), _block(4, '18:00', '21:00')]),
    'upcoming': upcoming,
    'proposal': proposal,
    'history': history,
    'earliest_start': {'adding_time': '2099-10-08', 'removing_time': '2099-10-21'},
  },
};

Future<TestApp> _openHours(WidgetTester tester, Map<String, Object?> shifts) async {
  latinDigitsInDates();
  final app = await TestApp.create(accounts: [teacher]);
  teacherRoutes(app.api);
  app.api.on('GET', 'teacher/shifts', shifts);
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1600));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 20);
  await tester.tap(find.text('المزيد').last);
  await frames(tester, 8);
  await tester.tap(find.text('مواعيدي'));
  await frames(tester, 12);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  testWidgets('an approved schedule that starts later shows with its day and how the hours change', (tester) async {
    await _openHours(
      tester,
      _shifts(
        upcoming: {
          'starts_on': _at('2099-11-01T00:00:00+02:00', 'الأحد 1 نوفمبر'),
          'version': 2,
          'timezone': 'Africa/Cairo',
          'weekly_minutes': 540,
          'blocks': [_block(2, '17:00', '22:00'), _block(6, '16:00', '20:00')],
        },
        history: [
          {
            'id': 'p0',
            'status': 'approved',
            'status_label': 'معتمد',
            'effective_from': '2099-11-01',
            'decided_at': _at('2099-10-01T10:00:00+02:00', '1 أكتوبر · 10:00 ص'),
            'reason': null,
          },
        ],
      ),
    );

    expect(find.text('جدولك الحالي'), findsOneWidget);
    expect(find.byKey(const Key('shifts-upcoming')), findsOneWidget);
    expect(find.text('جدول جديد يبدأ الأحد 1 نوفمبر'), findsOneWidget);
    expect(find.text('من 8 ساعات إلى 9 ساعات في الأسبوع'), findsOneWidget);
    // Saturday is new in it.
    expect(find.descendant(of: find.byKey(const Key('shifts-upcoming')), matching: find.text('4:00 م – 8:00 م')), findsOneWidget);
    // No open proposal: one way on, a new one; and the decisions so far.
    expect(find.text('اقترح مواعيد جديدة'), findsOneWidget);
    expect(find.text('السابقة'), findsOneWidget);
    expect(find.text('طُلب أن يبدأ الأحد 1 نوفمبر'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('each step of a proposal has its one thing to do', (tester) async {
    // A draft: edit and send.
    await _openHours(tester, _shifts(proposal: _proposal('draft')));
    expect(find.text('مسودة'), findsOneWidget);
    expect(find.text('المسودة محفوظة على حسابك ولم تُرسل بعد.'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'عدّل وأرسل'), findsOneWidget);
    expect(find.text('اسحب المقترح'), findsNothing);
    expect(find.byKey(const Key('shifts-conversation')), findsNothing);
    await _close(tester);

    // Sent and in review: withdraw, and nothing else.
    final app = await _openHours(tester, _shifts(proposal: _proposal('submitted')));
    expect(find.text('قيد المراجعة'), findsOneWidget);
    expect(find.text('اسحب المقترح'), findsOneWidget);
    expect(find.text('عدّل وأرسل'), findsNothing);
    expect(find.text('المحادثة (0)'), findsOneWidget);
    app.api.on('POST', 'teacher/shifts/proposal/withdraw', {'data': _proposal('submitted')});
    await tester.tap(find.text('اسحب المقترح'));
    await frames(tester, 8);
    await tester.tap(find.widgetWithText(FilledButton, 'اسحبه'));
    await frames(tester, 8);
    expect(app.api.sent('POST', 'teacher/shifts/proposal/withdraw'), hasLength(1));
    await _close(tester);

    // Changes asked: edit and send first, withdrawing stays a small link.
    await _openHours(tester, _shifts(proposal: _proposal('changes_requested')));
    expect(find.text('طُلب تعديل'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'عدّل وأرسل'), findsOneWidget);
    expect(find.widgetWithText(TextButton, 'اسحب المقترح'), findsOneWidget);
    await _close(tester);

    // Rejected lately: where it ended, the reason, and a new proposal.
    await _openHours(
      tester,
      _shifts(
        history: [
          {
            'id': 'p0',
            'status': 'rejected',
            'status_label': 'مرفوض',
            'effective_from': '2099-11-01',
            'decided_at': _at('${DateTime.now().toUtc().toIso8601String().substring(0, 19)}+00:00', 'اليوم'),
            'reason': 'الأوقات مزدحمة',
          },
        ],
      ),
    );
    expect(find.text('لم تعتمد الأكاديمية مقترحك، ويبقى جدولك كما هو.\nالسبب: الأوقات مزدحمة'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'اقترح مواعيد جديدة'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('the conversation opens on its own screen: bubbles, times, and the steps between them', (tester) async {
    await _openHours(
      tester,
      _shifts(
        proposal: _proposal(
          'changes_requested',
          submittedAt: _at('2099-10-01T09:00:00+02:00', '1 أكتوبر · 9:00 ص'),
          comments: [
            {
              'author_role': 'teacher',
              'mine': true,
              'body': 'أقترح يوم الثلاثاء',
              'at': _at('2099-10-01T08:00:00+02:00', '1 أكتوبر · 8:00 ص'),
            },
            {
              'author_role': 'admin',
              'mine': false,
              'body': 'نحتاج يوم السبت أيضًا',
              'at': _at('2099-10-02T10:00:00+02:00', '2 أكتوبر · 10:00 ص'),
            },
            {
              'author_role': 'system',
              'mine': false,
              'body': 'طُلب تعديل المقترح',
              'at': _at('2099-10-02T10:01:00+02:00', '2 أكتوبر · 10:01 ص'),
            },
          ],
        ),
      ),
    );

    expect(find.text('نحتاج يوم السبت أيضًا'), findsNothing);
    expect(find.text('المحادثة (2)'), findsOneWidget);
    expect(find.text('الأكاديمية: نحتاج يوم السبت أيضًا'), findsOneWidget);
    await tester.tap(find.text('المحادثة (2)'));
    await frames(tester, 12);

    expect(find.text('المحادثة مع الأكاديمية'), findsOneWidget);
    expect(find.text('أقترح يوم الثلاثاء'), findsOneWidget);
    expect(find.text('أنت · 1 أكتوبر · 8:00 ص'), findsOneWidget);
    expect(find.text('الأكاديمية · 2 أكتوبر · 10:00 ص'), findsOneWidget);
    // The steps are small lines, in time order: sent after the first message.
    expect(find.text('أُرسل المقترح · 1 أكتوبر · 9:00 ص'), findsOneWidget);
    expect(find.text('طُلب تعديل المقترح · 2 أكتوبر · 10:01 ص'), findsOneWidget);
    final mine = tester.getTopLeft(find.text('أقترح يوم الثلاثاء')).dy;
    final sent = tester.getTopLeft(find.text('أُرسل المقترح · 1 أكتوبر · 9:00 ص')).dy;
    final theirs = tester.getTopLeft(find.text('نحتاج يوم السبت أيضًا')).dy;
    expect(mine < sent && sent < theirs, isTrue);
    expect(find.byKey(const Key('shift-message')), findsOneWidget);

    await _close(tester);
  });
}
