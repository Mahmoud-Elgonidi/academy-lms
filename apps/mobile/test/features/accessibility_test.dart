import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/journey.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// Every grown-up look's main screens: whatever can be tapped is at least
/// 48 by 48 and says what it is, and all of it fits a small phone with large
/// text (the kids' look keeps its own, checked by its pictures).
void main() {
  final looks = <(String, Account, void Function(FakeApi), List<String>)>[
    ('guardian', guardian, familyRoutes, ['/family/home', '/family/lessons', '/family/money', '/family/me']),
    ('teacher', teacher, teacherRoutes, ['/teach/today', '/teach/week', '/teach/reports', '/teach/students', '/teach/more']),
    (
      'middle',
      fxStudent('middle'),
      (api) => fxStudentRoutes(api, 'middle', fxJourney(stage: 'middle', twoReviews: true)),
      ['/middle/journey', '/middle/more', '/middle/more/homework'],
    ),
    (
      'teen',
      fxStudent('teen'),
      (api) => fxStudentRoutes(api, 'teen', fxJourney(stage: 'teen', style: 'calm', twoReviews: true)),
      ['/teen/journey', '/teen/homework', '/teen/more'],
    ),
  ];

  for (final (name, account, routes, places) in looks) {
    testWidgets('$name: every tap target is 48 or more and labelled', (tester) async {
      final semantics = tester.ensureSemantics();
      final app = await TestApp.create(accounts: [account]);
      routes(app.api);
      await app.session.start();
      await tester.binding.setSurfaceSize(const Size(390, 844));
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 20);
      for (final at in places) {
        GoRouter.of(tester.element(find.byType(Scaffold).first)).go(at);
        await frames(tester, 20);
        await expectLater(tester, meetsGuideline(androidTapTargetGuideline), reason: at);
        await expectLater(tester, meetsGuideline(labeledTapTargetGuideline), reason: at);
      }
      await tester.pumpWidget(const SizedBox());
      await frames(tester, 2);
      await tester.binding.setSurfaceSize(null);
      semantics.dispose();
    });

    testWidgets('$name: the same screens fit a small phone with large text', (tester) async {
      tester.platformDispatcher.textScaleFactorTestValue = 1.4;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      final app = await TestApp.create(accounts: [account]);
      routes(app.api);
      await app.session.start();
      await tester.binding.setSurfaceSize(const Size(360, 740));
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 20);
      for (final at in places) {
        GoRouter.of(tester.element(find.byType(Scaffold).first)).go(at);
        await frames(tester, 20);
        expect(tester.takeException(), isNull, reason: at);
      }
      await tester.pumpWidget(const SizedBox());
      await frames(tester, 2);
      await tester.binding.setSurfaceSize(null);
    });
  }
}
