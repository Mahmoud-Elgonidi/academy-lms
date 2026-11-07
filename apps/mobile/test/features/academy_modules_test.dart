import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:academy_app/core/auth/modules.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/journey.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// `GET me` with every area on but [off].
Map<String, Object?> _me(Set<Feature> off) => {
  'data': {
    ...meJson(),
    'modules': [for (final f in Feature.values) if (!off.contains(f)) f.value],
  },
};

Map<String, Object?> _offProblem(String feature, String by) => {
  'type': 'feature-not-in-plan',
  'title': 'هذا ليس ضمن باقة الأكاديمية.',
  'status': 403,
  'feature': feature,
  'by': by,
};

Future<TestApp> _open(WidgetTester tester, Account account, String? at, void Function(FakeApi api) routes) async {
  final app = await TestApp.create(accounts: [account]);
  routes(app.api);
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 2000));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  if (at != null) await _go(tester, at);
  return app;
}

Future<void> _go(WidgetTester tester, String to) async {
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go(to);
  await frames(tester, 12);
}

String _where(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).first)).state.matchedLocation;

Finder _tab(String label) => find.descendant(of: find.byType(NavigationBar), matching: find.text(label));

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  test('modules: what the API lists is on, an older server that lists nothing has everything on', () {
    final some = Modules.fromJson(['curriculum', 'quran']);
    expect(some.has(Feature.curriculum), isTrue);
    expect(some.has(Feature.messaging), isFalse);
    expect(Modules.fromJson(null).has(Feature.messaging), isTrue);
    expect(Modules.fromJson(<Object>[]).has(Feature.games), isFalse);

    expect(featureAt('/family/me/messages/c1'), Feature.messaging);
    expect(featureAt('/teen/more/help'), Feature.support);
    expect(featureAt('/kids/journey/album?new=x'), Feature.games);
    expect(featureAt('/kids/more/stickers'), Feature.curriculum);
    expect(featureAt('/teach/more/performance'), Feature.evaluation);
    expect(featureAt('/teach/more/card'), Feature.site);
    expect(featureAt('/family/journey/hifz'), Feature.quran);
    // The tabs themselves stay: the journey says calmly that it is off.
    expect(featureAt('/kids/journey'), isNull);
    expect(featureAt('/family/journey/homework'), isNull);
  });

  test('a notification of an area the academy has off opens the look\'s home', () {
    final noTalk = Modules.only(Feature.values.where((f) => f != Feature.messaging && f != Feature.support));
    expect(targetOf('conversation', 'c1', Area.family, noTalk), '/family/home');
    expect(targetOf('conversation', 'c1', Area.kids, noTalk), '/kids/journey');
    expect(targetOf('ticket', 't1', Area.teen, noTalk), '/teen/journey');
    expect(targetOf('teacher_performance', null, Area.teacher, Modules.only(const [])), '/teach/today');
    // Still where it was with the area on.
    expect(targetOf('conversation', 'c1', Area.family), '/family/me/messages/c1');
  });

  testWidgets('middle school without games and messages: no messages tab, no creatures, and their places lead home', (tester) async {
    await _open(tester, fxStudent('middle'), null, (api) {
      fxStudentRoutes(api, 'middle', fxJourney(stage: 'middle', stars: 90));
      api.on('GET', 'me', _me({Feature.games, Feature.messaging}));
    });

    expect(_tab('رحلتي'), findsOneWidget);
    expect(_tab('حصصي'), findsOneWidget);
    expect(_tab('المزيد'), findsOneWidget);
    expect(_tab('الرسائل'), findsNothing);

    // The bar's third place is More now.
    await tester.tap(_tab('المزيد'));
    await frames(tester, 12);
    expect(_where(tester), '/middle/more');
    expect(find.byKey(const Key('more-levels')), findsOneWidget);
    expect(find.byKey(const Key('more-homework')), findsOneWidget);
    expect(find.byKey(const Key('more-creatures')), findsNothing);
    expect(find.byKey(const Key('more-help')), findsOneWidget);

    await _go(tester, '/middle/messages');
    expect(_where(tester), '/middle/journey');
    await _go(tester, '/middle/journey/album');
    expect(_where(tester), '/middle/journey');
    await _close(tester);
  });

  testWidgets('a kid without games and messages: three tabs, and More without the creatures', (tester) async {
    await _open(tester, fxStudent('kids'), null, (api) {
      fxStudentRoutes(api, 'kids', fxJourney(stars: 30));
      api.on('GET', 'me', _me({Feature.games, Feature.messaging}));
    });

    expect(find.descendant(of: find.byType(NavigationBar), matching: find.byType(NavigationDestination)), findsNWidgets(3));
    expect(_tab('تحدّث مع معلّمك'), findsNothing);
    await tester.tap(_tab('المزيد'));
    await frames(tester, 12);
    expect(_where(tester), '/kids/more');
    expect(find.byKey(const Key('more-creatures')), findsNothing);
    expect(find.byKey(const Key('more-stickers')), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a family\'s More without messages and help keeps the rest, and a link to help leads home', (tester) async {
    await _open(tester, guardian, '/family/me', (api) {
      familyRoutes(api);
      api.on('GET', 'me', _me({Feature.messaging, Feature.support}));
    });

    expect(find.byKey(const Key('open-messages')), findsNothing);
    expect(find.byKey(const Key('open-help')), findsNothing);
    expect(find.byKey(const Key('open-tell-us')), findsOneWidget);
    expect(find.byKey(const Key('open-recordings')), findsOneWidget);

    await _go(tester, '/family/me/help');
    expect(_where(tester), '/family/home');
    await _close(tester);
  });

  testWidgets('a teacher\'s More without evaluation, the academy\'s page, messages and help', (tester) async {
    await _open(tester, teacher, '/teach/more', (api) {
      teacherRoutes(api);
      api.on('GET', 'me', _me({Feature.evaluation, Feature.site, Feature.messaging, Feature.support}));
    });

    for (final row in ['open-performance', 'open-card', 'open-messages', 'open-support']) {
      expect(find.byKey(Key(row)), findsNothing, reason: row);
    }
    expect(find.byKey(const Key('open-documents')), findsOneWidget);
    expect(find.byKey(const Key('open-earnings')), findsOneWidget);

    await _go(tester, '/teach/more/performance');
    expect(_where(tester), '/teach/today');
    await _close(tester);
  });

  testWidgets('the journey with the curriculum off by the academy says so calmly, with the lessons and homework', (tester) async {
    await _open(tester, guardian, '/family/journey', (api) {
      familyRoutes(api);
      api
        ..on('GET', 'me', _me({Feature.curriculum}))
        ..on('GET', 'learning/journey/map', _offProblem('curriculum', 'academy'), status: 403);
    });

    expect(find.byKey(const Key('module-off')), findsOneWidget);
    expect(find.text('هذا القسم غير متاح الآن'), findsOneWidget);
    expect(find.text('أوقفت الأكاديمية هذا القسم، وما فيه محفوظ ويعود متى أعادت تشغيله.'), findsOneWidget);
    // Nothing broke: nothing to try again, and no lock.
    expect(find.text('حاول مرة أخرى'), findsNothing);
    expect(find.text('لا يُسمح لك بعرض هذا'), findsNothing);
    expect(find.byKey(const Key('journey-off-lessons')), findsOneWidget);
    expect(find.byKey(const Key('journey-off-homework')), findsOneWidget);
    await _close(tester);
  });

  testWidgets('an area outside the academy\'s plan, from a server that lists no modules, says so on its screen', (tester) async {
    await _open(tester, teacher, '/teach/more/performance', (api) {
      teacherRoutes(api);
      api.on('GET', 'teacher/performance', _offProblem('evaluation', 'plan'), status: 403);
    });

    expect(_where(tester), '/teach/more/performance');
    expect(find.byKey(const Key('module-off')), findsOneWidget);
    expect(find.text('هذا القسم ليس ضمن باقة الأكاديمية الحالية. إن احتجت إليه فتواصل مع الأكاديمية.'), findsOneWidget);
    expect(find.text('حاول مرة أخرى'), findsNothing);
    await _close(tester);
  });
}
