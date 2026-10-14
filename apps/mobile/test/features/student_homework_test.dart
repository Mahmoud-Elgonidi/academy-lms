import 'package:academy_app/app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/journey.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// Two waiting (one handed in by words, one by its round), one handed in, one marked.
Map<String, Object?> _homework() => {
  'data': [
    {...homeworkJson('h1'), 'title': 'استخرج 10 أمثلة للإظهار', 'instructions': 'اكتب كل مثال ومعاه الحرف.'},
    {...homeworkJson('h2', accepts: 'practice'), 'title': 'الإظهار والإدغام', 'practice': null},
    {...homeworkJson('h3', status: 'submitted', body: 'تم'), 'title': 'سمّع سورة النصر'},
    {
      ...homeworkJson('h4', status: 'reviewed', body: 'تم'),
      'title': 'أمثلة الإخفاء',
      'status_label': 'اتصحّح',
      'grade': 4,
      'feedback': 'شغل نضيف، راجع حرف الكاف',
    },
  ],
};

Future<TestApp> _open(WidgetTester tester, String stage, String at) async {
  final app = await TestApp.create(accounts: [fxStudent(stage)]);
  fxStudentRoutes(app.api, stage, fxJourney(stage: stage, style: stage == 'teen' ? 'calm' : 'playful'));
  app.api.on('GET', 'learning/homework', _homework());
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1600));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go(at);
  await frames(tester, 12);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  testWidgets('secondary school\'s homework: waiting, handed in and marked with counts, hand in by words, the last marked', (tester) async {
    final app = await _open(tester, 'teen', '/teen/homework');
    app.api.on('POST', 'learning/homework/h1/submissions', {'data': homeworkJson('h1', status: 'submitted')}, status: 201);

    expect(find.text('واجبين مستنيين منك'), findsOneWidget);
    expect(find.text('الواجبات'), findsWidgets);
    expect(find.text('مستني · 2'), findsOneWidget);
    expect(find.text('اتسلّم · 1'), findsOneWidget);
    expect(find.text('اتصحّح · 1'), findsOneWidget);
    // What waits: one handed in by words, one by its round.
    expect(find.text('استخرج 10 أمثلة للإظهار'), findsOneWidget);
    expect(find.text('الإظهار والإدغام'), findsOneWidget);
    expect(find.text('ابدأ الجولة'), findsOneWidget);
    expect(find.text('سمّع سورة النصر'), findsNothing);
    // The last marked, with its grade; it opens the marked ones.
    expect(find.text('آخر تصحيح'), findsOneWidget);
    expect(find.text('4/5'), findsOneWidget);

    await tester.tap(find.byKey(const Key('hand-in-h1')));
    await frames(tester, 8);
    await tester.enterText(find.byType(TextField), 'السماء، أنعمت');
    await frames(tester, 2);
    await tester.tap(find.widgetWithText(FilledButton, 'ابعت'));
    await frames(tester, 20);
    expect(app.api.sent('POST', 'learning/homework/h1/submissions').single.data, {'body': 'السماء، أنعمت'});

    await tester.tap(find.byKey(const Key('homework-last-marked')));
    await frames(tester, 10);
    expect(find.text('أمثلة الإخفاء'), findsOneWidget);
    expect(find.text('الدرجة: 4 من 5'), findsOneWidget);
    expect(find.text('شغل نضيف، راجع حرف الكاف'), findsOneWidget);
    expect(find.text('استخرج 10 أمثلة للإظهار'), findsNothing);

    await tester.tap(find.text('اتسلّم · 1'));
    await frames(tester, 10);
    expect(find.text('سمّع سورة النصر'), findsOneWidget);
    expect(find.text('أمثلة الإخفاء'), findsNothing);

    await _close(tester);
  });

  testWidgets('middle school opens its homework from More, over the tabs, with the same three lists', (tester) async {
    await _open(tester, 'middle', '/middle/more');
    await tester.tap(find.byKey(const Key('more-homework')));
    await frames(tester, 12);
    expect(find.byType(BackButton), findsOneWidget);
    expect(find.text('مستني · 2'), findsOneWidget);
    expect(find.text('استخرج 10 أمثلة للإظهار'), findsOneWidget);

    await _close(tester);
  });
}
