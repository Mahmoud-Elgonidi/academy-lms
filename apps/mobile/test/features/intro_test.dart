import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/prefs/app_prefs.dart';
import 'package:academy_app/features/onboarding/presentation/intro_page.dart';
import 'package:academy_app/features/onboarding/presentation/splash_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Future<TestApp> _firstOpening(WidgetTester tester, {bool start = true}) async {
  final app = await TestApp.create(seenIntro: false);
  app.api.on('GET', 'branding', branding());
  if (start) await app.session.start();
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
}

void main() {
  testWidgets('while the app opens: the launch screen carried on, then the slides the first time', (tester) async {
    final app = await _firstOpening(tester, start: false);
    expect(find.byType(SplashPage), findsOneWidget);
    expect(find.bySemanticsLabel('الأكاديمية'), findsOneWidget);

    await app.session.start();
    await frames(tester);
    expect(find.byType(SplashPage), findsNothing);
    expect(find.text('حصص لايف مع مدرّسك'), findsOneWidget);
    expect(find.bySemanticsLabel('شريحة 1 من 3'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('skipping goes to signing in, and the slides never come again', (tester) async {
    final app = await _firstOpening(tester);
    await tester.tap(find.text('تخطّي'));
    await frames(tester);

    expect(find.text('أهلًا بيك'), findsOneWidget);
    expect(find.byType(IntroPage), findsNothing);
    expect(app.session.state.seenIntro, isTrue);
    expect(await AppPrefs(SharedPreferencesAsync()).seenIntro(), isTrue);
    await _close(tester);
  });

  testWidgets('Next and a swipe go through the slides; the last one starts', (tester) async {
    await _firstOpening(tester);
    await tester.tap(find.text('التالي'));
    await frames(tester, 6);
    expect(find.text('تابع التقدّم خطوة بخطوة'), findsOneWidget);
    expect(find.bySemanticsLabel('شريحة 2 من 3'), findsOneWidget);

    // In Arabic the next slide is to the left: the finger moves right.
    await tester.fling(find.byType(PageView), const Offset(300, 0), 1200);
    await frames(tester, 6);
    expect(find.text('المواعيد والواجبات والفلوس في مكان واحد'), findsOneWidget);
    expect(find.text('الحصة الجاية'), findsOneWidget);
    expect(find.text('يلا نبدأ'), findsOneWidget);

    await tester.tap(find.text('يلا نبدأ'));
    await frames(tester);
    expect(find.text('أهلًا بيك'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('the language can be chosen before anything else, and stays', (tester) async {
    final app = await _firstOpening(tester);
    await tester.tap(find.bySemanticsLabel('English'));
    await frames(tester, 6);

    expect(find.text('Live lessons with your teacher'), findsOneWidget);
    expect(find.text('Skip'), findsOneWidget);
    expect(app.session.state.locale, 'en');
    expect(await AppPrefs(SharedPreferencesAsync()).locale(), 'en');

    // In English the next slide is to the right: the finger moves left.
    await tester.fling(find.byType(PageView), const Offset(-300, 0), 1200);
    await frames(tester, 6);
    expect(find.text('Follow progress step by step'), findsOneWidget);
    await _close(tester);
  });

  test('someone who signed in on this phone before the slides came never sees them, even signed out later', () async {
    final app = await TestApp.create(accounts: [guardian], seenIntro: false);
    await app.session.start();
    expect(app.session.state.seenIntro, isTrue);
    expect(await AppPrefs(SharedPreferencesAsync()).seenIntro(), isTrue);
  });

  testWidgets('under less motion the slides stand still and still work', (tester) async {
    tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
    addTearDown(tester.platformDispatcher.clearAccessibilityFeaturesTestValue);
    await _firstOpening(tester);
    // Nothing loops or waits: the screen settles at once.
    await tester.pumpAndSettle();
    expect(find.text('حصص لايف مع مدرّسك'), findsOneWidget);

    await tester.tap(find.text('التالي'));
    await tester.pumpAndSettle();
    expect(find.text('تابع التقدّم خطوة بخطوة'), findsOneWidget);
    await tester.tap(find.text('تخطّي'));
    await tester.pumpAndSettle();
    expect(find.text('أهلًا بيك'), findsOneWidget);
    await _close(tester);
  });
}
