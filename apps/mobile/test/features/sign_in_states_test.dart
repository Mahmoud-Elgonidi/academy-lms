import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/session_cubit.dart';
import 'package:academy_app/features/onboarding/presentation/welcome_moment.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames, tokenFor;

Map<String, Object?> _sent({String channel = 'whatsapp'}) => {
  'challenge_id': 'ch1',
  'channel': channel,
  'sent_to': channel == 'email' ? 'r•••@demo.test' : '+966•••••••04',
  'expires_at': '2099-01-01T00:00:00Z',
  'resend_after': 60,
  'channels': ['whatsapp', 'sms'],
};

Map<String, Object?> _found(List<(String, String, List<String>)> accounts) => {
  'discovery_token': 'disc',
  'expires_at': null,
  'accounts': [
    for (final (slug, id, roles) in accounts)
      {
        'academy': {'id': 'a-$slug', 'slug': slug, 'name_ar': 'أكاديمية $slug', 'name_en': slug, 'logo_url': null, 'colour': null},
        'user': {'id': id, 'name': 'رامي فؤاد', 'roles': roles},
      },
  ],
};

Future<TestApp> _open(WidgetTester tester) async {
  final app = await TestApp.create();
  app.api.on('GET', 'branding', branding());
  await app.session.start();
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
}

Future<void> _codeFor(WidgetTester tester, String contact) async {
  await tester.enterText(find.byKey(const Key('contact')), contact);
  await tester.tap(find.text('ابعتلي كود'));
  await frames(tester);
  await tester.enterText(find.byKey(const Key('code')), '123456');
  await frames(tester, 15);
}

void main() {
  testWidgets('a student waiting on a guardian\'s consent sees what to do, and can try again', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'accounts/code', _sent(), status: 202)
      ..on(
        'POST',
        'accounts/code/verify',
        _found([
          ('demo', 'u-1', ['student']),
        ]),
      )
      ..on('POST', 'accounts/sign-in', {
        'type': 'guardian-consent-required',
        'title': 'محتاجين موافقة ولي أمرك الأول.',
        'status': 403,
      }, status: 403);
    await _codeFor(tester, '0501234504');
    await tester.tap(find.text('ادخل أكاديمية demo'));
    await frames(tester, 6);

    expect(find.text('محتاجين موافقة ولي أمرك'), findsOneWidget);
    expect(find.text('ولي أمرك يفتح التطبيق بحسابه.'), findsOneWidget);
    expect(find.text('من «حسابي» ثم «خصوصية الأولاد» يوافق على حسابك.'), findsOneWidget);

    await tester.tap(find.text('جرّب تدخل تاني'));
    await frames(tester, 6);
    expect(app.api.sent('POST', 'accounts/sign-in'), hasLength(2));
    expect(find.text('محتاجين موافقة ولي أمرك'), findsOneWidget);

    await tester.tap(find.text('ابدأ من الأول'));
    await frames(tester, 6);
    expect(find.text('أهلًا بيك'), findsOneWidget);
    expect(app.session.state.status, SessionStatus.signedOut);
    await _close(tester);
  });

  testWidgets('an account that must turn on two-step sign-in first is told where', (tester) async {
    final app = await _open(tester);
    app.api.on('POST', 'auth/login', {
      'type': 'two-factor-setup-required',
      'title': 'لازم تفعّل الدخول بخطوتين.',
      'status': 403,
    }, status: 403);
    await tester.tap(find.text('عندك كلمة سر؟ ادخل بيها'));
    await frames(tester, 5);
    await tester.enterText(find.byKey(const Key('academy')), 'demo');
    await tester.enterText(find.byKey(const Key('login')), 'owner@demo.test');
    await tester.enterText(find.byKey(const Key('password')), 'secret');
    await tester.tap(find.text('ادخل'));
    await frames(tester, 6);

    expect(find.text('فعّل الدخول بخطوتين الأول'), findsOneWidget);
    await tester.tap(find.text('ابدأ من الأول'));
    await frames(tester, 6);
    expect(find.text('أهلًا بيك'), findsOneWidget);
    expect(app.api.sent('POST', 'auth/login'), hasLength(1));
    await _close(tester);
  });

  testWidgets('a password left out says which field wants it, and asks nothing', (tester) async {
    final app = await _open(tester);
    await tester.tap(find.text('عندك كلمة سر؟ ادخل بيها'));
    await frames(tester, 5);
    await tester.enterText(find.byKey(const Key('academy')), 'demo');
    await tester.tap(find.text('ادخل'));
    await frames(tester, 3);
    expect(find.text('اكتب إيميلك أو موبايلك أو اسم المستخدم.'), findsOneWidget);
    expect(find.text('اكتب كلمة السر.'), findsOneWidget);
    expect(find.text('اكتب كود الأكاديمية.'), findsNothing);
    expect(app.api.sent('POST', 'auth/login'), isEmpty);
    await _close(tester);
  });

  testWidgets('too many tries: a countdown from the API\'s wait, and the button back when it ends', (tester) async {
    final app = await _open(tester);
    app.api.on(
      'POST',
      'accounts/code',
      {'type': 'too-many-requests', 'title': 'استنى شوية.', 'status': 429},
      status: 429,
      headers: {'Retry-After': '30'},
    );
    await tester.enterText(find.byKey(const Key('contact')), '0501234504');
    await tester.tap(find.text('ابعتلي كود'));
    await frames(tester, 3);

    expect(find.text('جرّبت كتير'), findsOneWidget);
    expect(find.text('جرّب تاني بعد 0:30'), findsOneWidget);
    await tester.tap(find.text('ابعتلي كود'));
    await frames(tester, 3);
    expect(app.api.sent('POST', 'accounts/code'), hasLength(1), reason: 'the button waits too');

    await tester.pump(const Duration(seconds: 12));
    expect(find.text('جرّب تاني بعد 0:18'), findsOneWidget);
    await tester.pump(const Duration(seconds: 19));
    await frames(tester, 3);
    expect(find.text('جرّبت كتير'), findsNothing);

    app.api.on('POST', 'accounts/code', _sent(), status: 202);
    await tester.tap(find.text('ابعتلي كود'));
    await frames(tester);
    expect(find.text('اكتب الكود'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('nobody with this number: what to check, the email as another way, a change and a password', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'accounts/code', _sent(), status: 202)
      ..on('POST', 'accounts/code/verify', _found([]));
    await _codeFor(tester, '0501234504');

    expect(find.text('مالقيناش حساب بالرقم ده'), findsOneWidget);
    expect(find.text('+966501234504'), findsOneWidget);
    expect(find.text('جرّب بالإيميل'), findsOneWidget);
    await tester.tap(find.text('غيّره'));
    await frames(tester, 6);
    expect(find.text('أهلًا بيك'), findsOneWidget);
    expect(app.session.state.status, SessionStatus.signedOut);
    await _close(tester);
  });

  testWidgets('nobody with this email: no email suggested, and a password is one tap away', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'accounts/code', _sent(channel: 'email'), status: 202)
      ..on('POST', 'accounts/code/verify', _found([]));
    await _codeFor(tester, 'rami@demo.test');

    expect(find.text('مالقيناش حساب بالإيميل ده'), findsOneWidget);
    expect(find.text('جرّب بالإيميل'), findsNothing);
    await tester.tap(find.text('عندك كلمة سر؟ ادخل بيها'));
    await frames(tester, 6);
    expect(find.text('ادخل بكلمة السر'), findsOneWidget);
    expect(app.api.requests.where((r) => r.path == 'auth/login'), isEmpty);
    await _close(tester);
  });

  testWidgets('under less motion a wait keeps its real length', (tester) async {
    tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
    addTearDown(tester.platformDispatcher.clearAccessibilityFeaturesTestValue);
    final app = await _open(tester);
    app.api.on('POST', 'accounts/code', _sent(), status: 202);
    await tester.enterText(find.byKey(const Key('contact')), '0501234504');
    await tester.tap(find.text('ابعتلي كود'));
    await frames(tester);
    await tester.pump(const Duration(seconds: 10));
    expect(find.textContaining('ابعت كود جديد بعد'), findsOneWidget);
    expect(find.text('ابعت كود جديد'), findsNothing);
    await _close(tester);
  });

  group('the greeting after signing in', () {
    Future<TestApp> signIn(WidgetTester tester) async {
      final app = await _open(tester);
      familyRoutes(app.api);
      app.api
        ..on('POST', 'accounts/code', _sent(channel: 'email'), status: 202)
        ..on(
          'POST',
          'accounts/code/verify',
          _found([
            ('demo', 'u-1', ['guardian']),
          ]),
        )
        ..on('POST', 'accounts/sign-in', tokenFor('tok-1', 'u-1', 'demo'));
      await _codeFor(tester, 'rami@demo.test');
      await tester.tap(find.text('ادخل أكاديمية demo'));
      await frames(tester, 3);
      return app;
    }

    testWidgets('says hello by name over the home, then goes by itself', (tester) async {
      final app = await signIn(tester);
      expect(find.byType(WelcomeMoment), findsOneWidget);
      expect(find.descendant(of: find.byType(WelcomeMoment), matching: find.text('أهلًا يا رامي')), findsOneWidget);
      expect(find.text('إنت دلوقتي في أكاديمية demo'), findsOneWidget);
      // The home is already there under it.
      expect(find.text('الحصة الجاية'), findsOneWidget);

      await frames(tester, 20);
      expect(find.byType(WelcomeMoment), findsNothing);
      expect(app.session.state.greeting, isFalse);
      await _close(tester);
    });

    testWidgets('a tap ends it early and still reaches the screen', (tester) async {
      final app = await signIn(tester);
      expect(find.byType(WelcomeMoment), findsOneWidget);
      await tester.tapAt(const Offset(200, 300));
      await frames(tester, 5);
      expect(find.byType(WelcomeMoment), findsNothing);
      expect(app.session.state.greeting, isFalse);
      await _close(tester);
    });

    testWidgets('under less motion it stands still, says hello, and goes', (tester) async {
      tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
      addTearDown(tester.platformDispatcher.clearAccessibilityFeaturesTestValue);
      final app = await signIn(tester);
      expect(find.descendant(of: find.byType(WelcomeMoment), matching: find.text('أهلًا يا رامي')), findsOneWidget);
      await frames(tester, 14);
      expect(find.byType(WelcomeMoment), findsNothing);
      expect(app.session.state.greeting, isFalse);
      await _close(tester);
    });

    testWidgets('opening the app again with a saved account does not greet', (tester) async {
      final app = await TestApp.create(accounts: [guardian]);
      familyRoutes(app.api);
      await app.session.start();
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 3);
      expect(find.byType(WelcomeMoment), findsNothing);
      await _close(tester);
    });
  });
}
