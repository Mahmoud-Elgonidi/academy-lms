import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/teaching/presentation/pages/shifts_page.dart';
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

final _tueThu = [_block(2, '17:00', '22:00'), _block(4, '18:00', '21:00')];

Map<String, Object?> _shifts({
  List<Map<String, Object?>>? current,
  Map<String, Object?>? proposal,
  Map<String, Object?>? upcoming,
  List<Map<String, Object?>> history = const [],
}) => {
  'data': {
    'timezone': 'Africa/Cairo',
    'current': _week(current ?? _tueThu),
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
  // Approved to start on Saturday 7 November: Tuesday stays, Saturday is new, Thursday goes.
  Map<String, Object?> upcoming() => {
    'starts_on': _at('2099-11-07T00:00:00+02:00', 'السبت 7 نوفمبر'),
    'version': 2,
    'timezone': 'Africa/Cairo',
    'weekly_minutes': 540,
    'blocks': [_block(2, '17:00', '22:00'), _block(6, '16:00', '20:00')],
  };
  Map<String, Object?> decided(String id, String status, String label) => {
    'id': id,
    'status': status,
    'status_label': label,
    'effective_from': '2099-11-07',
    'decided_at': _at('2099-10-01T10:00:00+02:00', '1 أكتوبر · 10:00 ص'),
    'reason': null,
  };

  testWidgets('nothing yet: no card on top, the empty week said once, and one way to propose', (tester) async {
    await _openHours(tester, _shifts(current: const []));

    expect(find.byKey(const Key('shifts-proposal')), findsNothing);
    expect(find.byKey(const Key('shifts-upcoming')), findsNothing);
    expect(find.byKey(const Key('shifts-refused')), findsNothing);
    expect(find.text('لا توجد مواعيد معتمدة بعد.'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'اقترح مواعيد جديدة'), findsOneWidget);
    expect(find.byKey(const Key('shifts-history')), findsNothing);

    await _close(tester);
  });

  testWidgets('a week in force alone: the week first, its hours, and a quiet way to propose a change', (tester) async {
    await _openHours(tester, _shifts());

    expect(find.byKey(const Key('shifts-proposal')), findsNothing);
    expect(find.byKey(const Key('shifts-upcoming')), findsNothing);
    expect(find.byKey(const Key('shifts-week-next')), findsNothing);
    expect(find.text('جدولك الحالي'), findsOneWidget);
    expect(find.text('8 ساعات في الأسبوع'), findsOneWidget);
    expect(find.text('5:00 م – 10:00 م'), findsOneWidget);
    expect(find.byType(DayBar), findsNWidgets(2));
    expect(find.widgetWithText(OutlinedButton, 'اقترح تغييرًا'), findsOneWidget);
    expect(find.textContaining('سيُبنى على جدولك'), findsNothing);

    await _close(tester);
  });

  testWidgets('approved to start later with nothing before: the day on top, no empty week, no steps', (tester) async {
    await _openHours(
      tester,
      _shifts(
        current: const [],
        upcoming: upcoming(),
        history: [decided('p1', 'approved', 'معتمد'), decided('p0', 'superseded', 'مستبدل')],
      ),
    );

    final hero = find.byKey(const Key('shifts-upcoming'));
    expect(hero, findsOneWidget);
    expect(find.descendant(of: hero, matching: find.text('جدولك الجديد يبدأ السبت 7 نوفمبر')), findsOneWidget);
    expect(find.descendant(of: hero, matching: find.textContaining('بعد ')), findsOneWidget);
    expect(find.descendant(of: hero, matching: find.text('9 ساعات في الأسبوع')), findsOneWidget);
    // Nothing reads as an empty schedule above the coming one, and the steps are done with.
    expect(find.textContaining('0 ساعة'), findsNothing);
    expect(find.text('لا توجد مواعيد معتمدة بعد.'), findsNothing);
    expect(find.byType(ShiftStepper), findsNothing);
    expect(find.text('لا مواعيد قبل 7 نوفمبر'), findsOneWidget);
    expect(find.byKey(const Key('shifts-week-now')), findsNothing);
    expect(find.text('4:00 م – 8:00 م'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'اقترح تغييرًا'), findsOneWidget);
    expect(find.text('سيُبنى على جدولك من 7 نوفمبر'), findsOneWidget);

    // The history is folded; a replaced decision shows only inside it, said plainly.
    expect(find.text('السجل (2)'), findsOneWidget);
    expect(find.text('حلّ محلّه مقترح أحدث'), findsNothing);
    await tester.tap(find.byKey(const Key('shifts-history-toggle')));
    await frames(tester, 8);
    expect(find.text('حلّ محلّه مقترح أحدث'), findsOneWidget);
    expect(find.text('طُلب أن يبدأ السبت 7 نوفمبر'), findsNWidgets(2));

    await _close(tester);
  });

  testWidgets('a week in force and one coming: the hours from one to the other, and a switch between the weeks', (tester) async {
    await _openHours(tester, _shifts(upcoming: upcoming(), history: [decided('p1', 'approved', 'معتمد')]));

    final hero = find.byKey(const Key('shifts-upcoming'));
    expect(find.descendant(of: hero, matching: find.text('8 ساعات')), findsOneWidget);
    expect(find.descendant(of: hero, matching: find.text('9 ساعات في الأسبوع')), findsOneWidget);
    expect(find.byType(ShiftStepper), findsNothing);

    // Now first: Tuesday and Thursday, nothing marked.
    expect(find.text('الآن'), findsOneWidget);
    expect(find.text('من 7 نوفمبر'), findsOneWidget);
    expect(find.text('جدولك الحالي'), findsOneWidget);
    expect(find.text('6:00 م – 9:00 م'), findsOneWidget);
    expect(find.text('جديد'), findsNothing);

    await tester.tap(find.byKey(const Key('shifts-week-next')));
    await frames(tester, 8);
    expect(find.text('جدولك الحالي'), findsNothing);
    expect(find.text('يبدأ السبت 7 نوفمبر'), findsOneWidget);
    // Saturday is new, Thursday changed (its span crossed out), Tuesday calm.
    expect(find.text('جديد'), findsOneWidget);
    expect(find.text('تغيّر'), findsOneWidget);
    expect(find.text('4:00 م – 8:00 م'), findsOneWidget);
    final gone = tester.widget<Text>(find.text('6:00 م – 9:00 م'));
    expect(gone.style?.decoration, TextDecoration.lineThrough);

    await tester.tap(find.byKey(const Key('shifts-week-now')));
    await frames(tester, 8);
    expect(find.text('جدولك الحالي'), findsOneWidget);
    expect(find.text('جديد'), findsNothing);

    await _close(tester);
  });

  testWidgets('everything stands still when the phone asks for less motion', (tester) async {
    tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
    addTearDown(tester.platformDispatcher.clearAccessibilityFeaturesTestValue);
    await _openHours(tester, _shifts(upcoming: upcoming()));
    await tester.tap(find.byKey(const Key('shifts-week-next')));
    await tester.pump();
    // Switched at once, with no crossfade left running.
    expect(find.text('جدولك الحالي'), findsNothing);
    expect(find.text('يبدأ السبت 7 نوفمبر'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('each step of a proposal has its one thing to do', (tester) async {
    // A draft: continue and send, on top of the page.
    await _openHours(tester, _shifts(proposal: _proposal('draft')));
    expect(find.byKey(const Key('shifts-proposal')), findsOneWidget);
    expect(find.text('مسودة لم تُرسل بعد'), findsOneWidget);
    expect(find.text('مسودة'), findsOneWidget);
    expect(find.text('المسودة محفوظة على حسابك ولم تُرسل بعد.'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'أكمل وأرسل'), findsOneWidget);
    // While a proposal is open, nothing else proposes.
    expect(find.byKey(const Key('shifts-propose')), findsNothing);
    expect(find.text('اسحب المقترح'), findsNothing);
    expect(find.byKey(const Key('shifts-conversation')), findsNothing);
    await _close(tester);

    // Sent and in review: waiting, the conversation, and withdrawing as a small link.
    final app = await _openHours(tester, _shifts(proposal: _proposal('submitted')));
    expect(find.text('في انتظار الأكاديمية'), findsOneWidget);
    expect(find.text('قيد المراجعة'), findsOneWidget);
    expect(find.byType(FilledButton), findsNothing);
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
    final refused = find.byKey(const Key('shifts-refused'));
    expect(find.descendant(of: refused, matching: find.text('لم تُعتمد مواعيدك')), findsOneWidget);
    expect(find.descendant(of: refused, matching: find.text('السبب: الأوقات مزدحمة')), findsOneWidget);
    expect(find.byType(ShiftStepper), findsNothing);
    expect(find.widgetWithText(OutlinedButton, 'اقترح تغييرًا'), findsOneWidget);
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
