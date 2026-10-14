import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/journey.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Future<TestApp> _open(WidgetTester tester, String stage, String at) async {
  final app = await TestApp.create(accounts: [fxStudent(stage)]);
  fxStudentRoutes(app.api, stage, fxJourney(stage: stage, style: stage == 'teen' ? 'calm' : 'playful', stars: 90));
  app.api.on('GET', 'messages/unread', {
    'data': {'count': 1},
  });
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1400));
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

String _where(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).first)).state.matchedLocation;

void main() {
  testWidgets('middle school\'s More: who and where they stand, four tiles with the homework open, then help', (tester) async {
    await _open(tester, 'middle', '/middle/more');

    expect(find.text('بطل · 90 نجمة'), findsOneWidget);
    for (final tile in ['واجباتي', 'المستويات', 'كائناتي', 'تسجيلات حصصي']) {
      expect(find.text(tile), findsOneWidget);
    }
    // The one homework still open is counted on its tile.
    expect(find.descendant(of: find.byKey(const Key('more-homework')), matching: find.text('1')), findsOneWidget);
    expect(find.text('قولّنا لو فيه حاجة مضايقاك'), findsOneWidget);

    await tester.tap(find.byKey(const Key('more-levels')));
    await frames(tester, 12);
    expect(_where(tester), '/middle/journey/levels');
    expect(find.text('سور أطول'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('secondary school has More as a tab: the account, studying, help, and their messages under it', (tester) async {
    await _open(tester, 'teen', '/teen/journey');
    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('المزيد')));
    await frames(tester, 12);

    expect(find.text('حسابك وإعداداتك'), findsOneWidget);
    expect(find.text('الدراسة'), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('open-messages')), matching: find.text('1')), findsOneWidget);
    expect(find.text('2 من 20'), findsOneWidget);

    await tester.tap(find.byKey(const Key('open-messages')));
    await frames(tester, 12);
    expect(_where(tester), '/teen/more/messages');
    await _close(tester);
  });

  test('secondary school\'s notifications about the account, help and worries open under More', () {
    expect(targetOf('account', null, Area.teen), '/teen/more');
    expect(targetOf('ticket', 't1', Area.teen), '/teen/more/help/t1');
    expect(targetOf('my_concerns', null, Area.teen), '/teen/more/tell-us');
    // Middle school's stay under the account.
    expect(targetOf('ticket', 't1', Area.middle), '/account/help/t1');
  });
}
