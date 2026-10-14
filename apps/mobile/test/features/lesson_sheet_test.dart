import 'package:academy_app/app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Future<TestApp> _open(WidgetTester tester, List<Map<String, Object?>> lessons) async {
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  app.api.on('GET', 'family/lessons', {
    'learner': {'id': 'c1', 'name': 'سلمى رامي'},
    'data': lessons,
    'notices': <String>[],
  });
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1000));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  await tester.tap(find.text('الحصص').last);
  await frames(tester, 10);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  testWidgets('a lesson row opens its sheet: when on whose clock, with whom, when the room opens, and cancelling last', (tester) async {
    await _open(tester, [
      {
        ...lesson('l1'),
        'join': {'open': false, 'opens_at': time('2099-10-11T15:50:00Z', '2099-10-11T18:50:00+03:00')},
      },
    ]);
    // The row: the time in its column, whom with, its state; nothing to press but the row.
    expect(find.text('لغة عربية مع أ. منى إبراهيم'), findsOneWidget);
    expect(find.text('محجوزة'), findsOneWidget);
    expect(find.text('إلغاء الحصة'), findsNothing);

    await tester.tap(find.byKey(const Key('lesson-l1')));
    await frames(tester, 8);
    expect(find.text('لغة عربية · سلمى'), findsOneWidget);
    expect(find.textContaining('بتوقيت الرياض'), findsWidgets);
    expect(find.text('أ. منى إبراهيم'), findsOneWidget);
    expect(find.byKey(const Key('sheet-room-opens')), findsOneWidget);
    expect(find.text('قيّم الحصة'), findsNothing);
    expect(find.text('اعترض'), findsNothing);

    // Cancelling closes this sheet and opens its own question.
    await tester.tap(find.byKey(const Key('sheet-cancel')));
    await frames(tester, 10);
    expect(find.byKey(const Key('sheet-room-opens')), findsNothing);
    await _close(tester);
  });

  testWidgets('a room open now joins from the row itself, and a lesson that cannot be cancelled offers nothing in red', (tester) async {
    await _open(tester, [
      {
        ...lesson('l1', canCancel: false, status: 'in_progress'),
        'status_label': 'شغالة',
        'join': {'open': true, 'opens_at': null},
      },
    ]);
    expect(find.widgetWithText(FilledButton, 'ادخل الحصة'), findsOneWidget);
    await tester.tap(find.byKey(const Key('lesson-l1')));
    await frames(tester, 8);
    expect(find.byKey(const Key('sheet-cancel')), findsNothing);
    expect(find.widgetWithText(FilledButton, 'ادخل الحصة'), findsWidgets);
    await _close(tester);
  });
}
