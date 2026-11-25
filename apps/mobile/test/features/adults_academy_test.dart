import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/theme/stage.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:academy_app/core/auth/audience.dart';
import 'package:academy_app/core/auth/session_cubit.dart';
import 'package:academy_app/core/l10n/l10n.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/trial.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// An academy for adults and professionals (`audience: adults`): no guardians,
/// no children, no kids' looks, and the training words in place of a school's.
Map<String, Object?> _branding({String audience = 'adults'}) => {...branding(), 'audience': audience};

Map<String, Object?> _me({String audience = 'adults'}) {
  final json = meJson();
  return {
    'data': {
      ...json,
      'academy': {...json['academy']! as Map<String, Object?>, 'audience': audience},
    },
  };
}

/// The academy's page for adults (its own `settings.audience`).
Map<String, Object?> _adultsPage() {
  final page = sitePage(booking: 'staff');
  final data = page['data']! as Map<String, Object?>;
  return {
    'data': {
      ...data,
      'page': {
        'settings': {'audience': 'adults'},
        'blocks': <Object>[],
      },
    },
  };
}

const _trainee = Account(
  academySlug: 'demo',
  academyNameAr: 'مكتب التدريب',
  academyNameEn: 'Training Office',
  userId: 'u-trainee',
  userName: 'هالة منصور',
  roles: ['student', 'guardian'],
  token: 'token-trainee',
  stage: 'kids',
);

Future<TestApp> _open(WidgetTester tester, {String audience = 'adults'}) async {
  final app = await TestApp.create(accounts: [_trainee]);
  familyRoutes(app.api);
  app.api
    ..on('GET', 'branding', _branding(audience: audience))
    ..on('GET', 'me', _me(audience: audience))
    ..on('GET', 'family/learners', {
      'data': [
        {'id': 'u-trainee', 'name': 'هالة منصور', 'is_self': true, 'stage': 'classic'},
        {'id': 'c1', 'name': 'سلمى رامي', 'is_self': false, 'stage': 'classic'},
      ],
      'viewer_stage': 'classic',
    });
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 2400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  return app;
}

Future<void> _go(WidgetTester tester, String to) async {
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go(to);
  await frames(tester, 12);
}

String _where(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).first)).state.matchedLocation;

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

Future<void> _tap(WidgetTester tester, Finder f, [int n = 6]) async {
  await tester.ensureVisible(f);
  await frames(tester, 6);
  await tester.tap(f);
  await frames(tester, n);
}

void main() {
  test('the audience as the API writes it; anything else is families, as before', () {
    expect(Audience.parse('adults'), Audience.adults);
    expect(Audience.parse('families'), Audience.families);
    expect(Audience.parse(null), Audience.families);
  });

  test('in an academy for adults everyone has the classic look, and nobody is a guardian', () {
    const teen = Account(
      academySlug: 'demo',
      academyNameAr: 'مكتب التدريب',
      academyNameEn: 'Training Office',
      userId: 'u1',
      userName: 'هالة',
      roles: ['student'],
      token: 't',
      stage: 'teen',
      followsChildren: false,
    );
    const parent = Account(
      academySlug: 'demo',
      academyNameAr: 'مكتب التدريب',
      academyNameEn: 'Training Office',
      userId: 'u2',
      userName: 'رامي',
      roles: ['guardian'],
      token: 't',
    );
    expect(const SessionState(current: teen).stage, Stage.teen);
    expect(const SessionState(current: teen, audience: Audience.adults).stage, Stage.classic);
    expect(const SessionState(current: parent).guardian, isTrue);
    expect(const SessionState(current: parent, audience: Audience.adults).guardian, isFalse);
  });

  test('an academy for adults reads the training words; what never shows there keeps its own', () {
    final adults = wordsFor('ar', adults: true);
    final families = wordsFor('ar');
    expect(families.navLessons, 'الحصص');
    expect(adults.navLessons, 'الجلسات');
    expect(adults.roleTeacher, 'مدرّب');
    expect(adults.roleStudent, 'متدرّب');
    expect(adults.navHomework, 'المهام');
    expect(adults.roomOpen, 'قاعة الجلسة مفتوحة');
    expect(adults.startPlanTitle, 'اختر الباقة');
    expect(families.startPlanTitle, 'اختاروا الباقة');
    expect(adults.lessonsCount(3), '3 جلسات');
    // A string with nothing of a school's falls back to the language's own.
    expect(adults.retry, families.retry);
    expect(wordsFor('en', adults: true).navLessons, 'Sessions');
    expect(wordsFor('en', adults: true).homeworkCount(2), '2 tasks');
    // The app speaks two languages: the adults' wording is not a third.
    expect(appLocales.map((l) => l.toLanguageTag()), ['ar', 'en']);
  });

  testWidgets('a trainee: the training words, the classic look, and no children, whatever the role and learners say', (tester) async {
    final app = await _open(tester);
    final s = app.session.state;
    expect(s.adults, isTrue);
    expect(s.stage, Stage.classic);
    expect(s.guardian, isFalse);

    // The tabs in the training words.
    expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('الجلسات')), findsOneWidget);

    await _go(tester, '/family/me');
    expect(find.text('الأبناء'), findsNothing);
    expect(find.byKey(const Key('open-add-child')), findsNothing);
    expect(find.byKey(const Key('open-privacy')), findsNothing);
    // Their own course, trials and recordings are theirs.
    expect(find.byKey(const Key('open-start')), findsOneWidget);
    expect(find.byKey(const Key('open-trials')), findsOneWidget);

    // A guardian's own places lead home.
    await _go(tester, '/family/children/new');
    expect(_where(tester), '/family/home');
    await _go(tester, '/family/me/privacy');
    expect(_where(tester), '/family/home');
    await _close(tester);
  });

  testWidgets('the same account in a families academy: a guardian of the child the learners list shows', (tester) async {
    final app = await _open(tester, audience: 'families');
    final s = app.session.state;
    expect(s.adults, isFalse);
    expect(s.guardian, isTrue);
    expect(s.current?.followsChildren, isTrue);
    expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('الحصص')), findsOneWidget);
    await _go(tester, '/family/me');
    expect(find.text('الأبناء'), findsOneWidget);
    expect(find.byKey(const Key('open-add-child')), findsOneWidget);
    await _close(tester);
  });

  test('a student who follows nobody else is no guardian, even with the role', () async {
    final app = await TestApp.create(accounts: [_trainee]);
    familyRoutes(app.api);
    app.api.on('GET', 'family/learners', {
      'data': [
        {'id': 'u-trainee', 'name': 'هالة منصور', 'is_self': true, 'stage': 'teen'},
      ],
      'viewer_stage': 'teen',
    });
    await app.session.start();
    await app.session.refresh();
    expect(app.session.state.current?.followsChildren, isFalse);
    expect(app.session.state.guardian, isFalse);
  });

  testWidgets('a free trial at an academy for adults: no "for my child", the name alone, in the training words', (tester) async {
    final app = await TestApp.create();
    app.api
      ..on('GET', 'branding', _branding())
      ..on('GET', 'site', _adultsPage())
      ..on('POST', 'site/requests', {
        'data': {'received': true},
      }, status: 201);
    await app.session.start();
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester);

    await _tap(tester, find.byKey(const Key('book-trial')));
    await tester.enterText(find.byKey(const Key('trial-academy')), 'demo');
    await _tap(tester, find.text('التالي'));

    expect(find.text('ما اسمك؟'), findsOneWidget);
    expect(find.byKey(const Key('trial-who-child')), findsNothing);
    expect(find.byKey(const Key('trial-who-me')), findsNothing);
    await tester.enterText(find.byKey(const Key('trial-name')), 'هالة منصور');
    await _tap(tester, find.text('التالي'));
    expect(find.text('اختر البرنامج'), findsOneWidget);

    await _tap(tester, find.byKey(const Key('trial-subject-sub-arabic')));
    await _tap(tester, find.text('التالي'));
    await tester.enterText(find.byKey(const Key('trial-phone')), '0501234504');
    await _tap(tester, find.text('أرسل الطلب'), 60);
    final sent = app.api.sent('POST', 'site/requests').single.data as Map<String, dynamic>;
    expect(sent['for_child'], isFalse);
    expect(sent.containsKey('child_name'), isFalse);
    expect(find.text('وصل طلبك'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
  });
}
