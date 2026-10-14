import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/session_cubit.dart';
import 'package:academy_app/features/auth/domain/contact.dart';
import 'package:academy_app/features/auth/presentation/widgets/code_boxes.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames, tokenFor;

Map<String, Object?> _sent({String channel = 'whatsapp', int resendAfter = 60}) => {
  'challenge_id': 'ch1',
  'channel': channel,
  'sent_to': channel == 'email' ? 'r•••@demo.test' : '+966•••••••04',
  'expires_at': '2099-01-01T00:00:00Z',
  'resend_after': resendAfter,
  'channels': ['whatsapp', 'sms', 'email'],
};

Map<String, Object?> _found(List<(String, String, List<String>)> accounts) => {
  'discovery_token': 'disc',
  'expires_at': null,
  'accounts': [
    for (final (slug, id, roles) in accounts)
      {
        'academy': {
          'id': 'a-$slug',
          'slug': slug,
          'name_ar': 'أكاديمية $slug',
          'name_en': slug,
          'logo_url': null,
          'colour': null,
          'privacy_url': 'https://$slug.example.test/privacy',
          'terms_url': 'https://$slug.example.test/terms',
        },
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

/// Types a number in the one field and asks for the code.
Future<void> _toCode(WidgetTester tester, [String contact = '0501234504']) async {
  await tester.enterText(find.byKey(const Key('contact')), contact);
  await tester.tap(find.text('ابعتلي كود'));
  await frames(tester);
}

void main() {
  group('one field for a number or an email', () {
    test('tells them apart as they are typed, in any digits', () {
      expect(Contact.kindOf(''), ContactKind.none);
      expect(Contact.kindOf('05'), ContactKind.phone);
      expect(Contact.kindOf('٠٥٠١'), ContactKind.phone);
      expect(Contact.kindOf('+20 100'), ContactKind.phone);
      expect(Contact.kindOf('rami'), ContactKind.email);
      expect(Contact.kindOf('rami@'), ContactKind.email);
    });

    test('a number goes with its country, without the leading zero, unless it has its own code', () {
      expect(Contact.phone('050 123 4504', '+966'), '+966501234504');
      expect(Contact.phone('٠١٠٠١٢٣٤٥٦٧', '+20'), '+201001234567');
      expect(Contact.phone('+44 7700 900123', '+966'), '+447700900123');
      expect(Contact.phone('0020 100 123 4567', '+966'), '+201001234567');
      expect(Contact.international('0020100'), isTrue);
    });

    test('says when a number or an email is not whole yet', () {
      expect(Contact.phoneComplete('05012'), isFalse);
      expect(Contact.phoneComplete('0501234504'), isTrue);
      expect(Contact.phoneComplete('+2010'), isFalse);
      expect(Contact.emailComplete('rami@demo'), isFalse);
      expect(Contact.emailComplete('rami@demo.test'), isTrue);
      expect(Contact.emailComplete('rami@mail.co.uk'), isTrue);
    });

    testWidgets('an empty field, a short number or half an email says what is missing and asks nothing', (tester) async {
      final app = await _open(tester);
      await tester.tap(find.text('ابعتلي كود'));
      await frames(tester, 3);
      expect(find.text('اكتب رقم موبايلك أو إيميلك الأول.'), findsOneWidget);

      await tester.enterText(find.byKey(const Key('contact')), '05012');
      await frames(tester, 5);
      // Typing again clears what was wrong.
      expect(find.text('اكتب رقم موبايلك أو إيميلك الأول.'), findsNothing);
      await tester.tap(find.text('ابعتلي كود'));
      await frames(tester, 3);
      expect(find.text('الرقم ده ناقص، اكتبه كامل.'), findsOneWidget);

      await tester.enterText(find.byKey(const Key('contact')), 'rami@demo');
      await tester.tap(find.text('ابعتلي كود'));
      await frames(tester, 3);
      expect(find.text('الإيميل ده ناقص حاجة، زي name@mail.com'), findsOneWidget);
      expect(app.api.sent('POST', 'accounts/code'), isEmpty);
      await _close(tester);
    });

    testWidgets('a number gets its country from the phone\'s clock, and another from the list with a search', (tester) async {
      final app = await _open(tester);
      app.api.on('POST', 'accounts/code', _sent(), status: 202);
      await tester.enterText(find.byKey(const Key('contact')), '01001234567');
      await frames(tester, 3);
      expect(find.text('+966'), findsOneWidget);
      expect(find.text('الكود هيوصلك على واتساب.'), findsOneWidget);

      await tester.tap(find.byKey(const Key('country')));
      await frames(tester, 4);
      await tester.enterText(find.byKey(const Key('country-search')), 'مصر');
      await frames(tester, 2);
      expect(find.byKey(const Key('country-+966')), findsNothing);
      await tester.tap(find.byKey(const Key('country-+20')));
      await frames(tester, 4);

      expect(find.text('+20'), findsOneWidget);
      await tester.tap(find.text('ابعتلي كود'));
      await frames(tester);
      expect(app.api.sent('POST', 'accounts/code').single.data, {'contact': '+201001234567'});
      await _close(tester);
    });
  });

  group('the code in six boxes', () {
    testWidgets('a copied code is pasted and goes at once; a right one turns the boxes and finds you', (tester) async {
      final app = await _open(tester);
      app.api
        ..on('POST', 'accounts/code', _sent(), status: 202)
        ..on(
          'POST',
          'accounts/code/verify',
          _found([
            ('demo', 'u-1', ['guardian']),
          ]),
        );
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
        if (call.method == 'Clipboard.getData') return {'text': 'كودك هو ١٢٣٤٥٦ ومتقولوش لحد'};
        return null;
      });
      addTearDown(() => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null));
      await _toCode(tester);

      await tester.tap(find.byKey(const Key('paste-code')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      expect(app.api.sent('POST', 'accounts/code/verify').single.data, containsPair('code', '123456'));
      expect(find.text('تمام!'), findsOneWidget);
      expect(tester.widget<CodeBoxes>(find.byType(CodeBoxes)).look, CodeLook.right);

      await frames(tester, 12);
      // One account: found, with a button straight into its academy and its pages to read.
      expect(find.text('لقيناك!'), findsOneWidget);
      expect(find.text('ادخل أكاديمية demo'), findsOneWidget);
      expect(find.byKey(const Key('academy-privacy-demo')), findsOneWidget);
      await _close(tester);
    });

    testWidgets('a wrong code shakes the boxes red and empties them; typing again starts afresh', (tester) async {
      final app = await _open(tester);
      app.api
        ..on('POST', 'accounts/code', _sent(), status: 202)
        ..on('POST', 'accounts/code/verify', {
          'type': 'validation',
          'title': 'البيانات المرسلة غير صحيحة.',
          'status': 422,
          'errors': {
            'code': ['الكود ده مش صح. فاضلك 4 محاولات.'],
          },
        }, status: 422);
      await _toCode(tester);
      // Five digits do not go yet.
      await tester.enterText(find.byKey(const Key('code')), '00000');
      await frames(tester, 2);
      expect(app.api.sent('POST', 'accounts/code/verify'), isEmpty);

      await tester.enterText(find.byKey(const Key('code')), '000000');
      await frames(tester, 3);
      expect(find.text('الكود ده مش صح. فاضلك 4 محاولات.'), findsOneWidget);
      expect(tester.widget<CodeBoxes>(find.byType(CodeBoxes)).look, CodeLook.wrong);
      expect(tester.widget<TextField>(find.byKey(const Key('code'))).controller!.text, isEmpty);

      await tester.enterText(find.byKey(const Key('code')), '1');
      await frames(tester, 5);
      expect(find.text('الكود ده مش صح. فاضلك 4 محاولات.'), findsNothing);
      expect(tester.widget<CodeBoxes>(find.byType(CodeBoxes)).look, CodeLook.typing);
      await _close(tester);
    });

    testWidgets('a new code after the ring runs down, by text if asked; the clock is the screen\'s own', (tester) async {
      final app = await _open(tester);
      app.api.on('POST', 'accounts/code', _sent(resendAfter: 30), status: 202);
      await _toCode(tester);
      expect(find.textContaining('ابعت كود جديد بعد'), findsOneWidget);
      expect(find.text('ابعت كود جديد'), findsNothing);

      await tester.pump(const Duration(seconds: 31));
      await tester.pump();
      expect(find.text('ابعت كود جديد'), findsOneWidget);
      await tester.ensureVisible(find.text('ابعته رسالة SMS بدل واتساب'));
      await tester.tap(find.text('ابعته رسالة SMS بدل واتساب'));
      await frames(tester, 3);
      expect(app.api.sent('POST', 'accounts/code').last.data, {'contact': '+966501234504', 'channel': 'sms'});
      expect(find.text('بعتنالك كود جديد.'), findsOneWidget);
      // The ring starts again for the new code.
      expect(find.textContaining('ابعت كود جديد بعد'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('back from the accounts, the boxes are empty again', (tester) async {
      final app = await _open(tester);
      app.api
        ..on('POST', 'accounts/code', _sent(), status: 202)
        ..on(
          'POST',
          'accounts/code/verify',
          _found([
            ('demo', 'u-1', ['guardian']),
            ('hira', 'u-2', ['guardian']),
          ]),
        );
      await _toCode(tester);
      await tester.enterText(find.byKey(const Key('code')), '123456');
      await frames(tester, 15);
      expect(find.text('لقينا حسابين ليك'), findsOneWidget);

      await tester.tap(find.byTooltip('رجوع'));
      await frames(tester, 6);
      expect(find.text('اكتب الكود'), findsOneWidget);
      expect(tester.widget<TextField>(find.byKey(const Key('code'))).controller!.text, isEmpty);
      expect(tester.widget<CodeBoxes>(find.byType(CodeBoxes)).look, CodeLook.typing);
      await _close(tester);
    });

    testWidgets('the code step opened with nothing sent goes back to the start', (tester) async {
      await _open(tester);
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/welcome/code');
      await frames(tester, 6);
      expect(find.text('أهلًا بيك'), findsOneWidget);
      expect(find.text('اكتب الكود'), findsNothing);
      await _close(tester);
    });
  });

  testWidgets('an account its academy refuses does not stop the others: you get in and hear which', (tester) async {
    final app = await _open(tester);
    familyRoutes(app.api);
    app.api
      ..on('POST', 'accounts/code', _sent(channel: 'email'), status: 202)
      ..on(
        'POST',
        'accounts/code/verify',
        _found([
          ('demo', 'u-1', ['guardian']),
          ('hira', 'u-2', ['student']),
        ]),
      )
      ..onWith(
        'POST',
        'accounts/sign-in',
        (r) => r.headers['X-Academy'] == 'hira'
            ? (403, {'type': 'guardian-consent-required', 'title': 'محتاجين موافقة ولي أمرك الأول.', 'status': 403})
            : (200, tokenFor('tok-1', 'u-1', 'demo')),
      );
    await _toCode(tester, 'rami@demo.test');
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 15);
    await tester.tap(find.text('أضف الحسابين وادخل'));
    await frames(tester, 20);

    expect(app.api.sent('POST', 'accounts/sign-in'), hasLength(2));
    expect(app.session.state.status, SessionStatus.signedIn);
    expect(app.session.state.accounts.map((a) => a.academySlug), ['demo']);
    expect(find.textContaining('دخلت، بس فيه حساب ماتضافش:'), findsOneWidget);
    expect(find.textContaining('رامي فؤاد في أكاديمية hira: محتاجين موافقة ولي أمرك الأول.'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('every account refused: the step stays, with the academy\'s words', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'accounts/code', _sent(channel: 'email'), status: 202)
      ..on(
        'POST',
        'accounts/code/verify',
        _found([
          ('hira', 'u-2', ['student']),
        ]),
      )
      ..on('POST', 'accounts/sign-in', {
        'type': 'account-inactive',
        'title': 'الحساب ده موقوف. كلّم الأكاديمية.',
        'status': 403,
      }, status: 403);
    await _toCode(tester, 'rami@demo.test');
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 15);
    await tester.tap(find.text('ادخل أكاديمية hira'));
    await frames(tester, 6);

    expect(app.session.state.status, SessionStatus.signedOut);
    expect(find.text('الحساب ده موقوف. كلّم الأكاديمية.'), findsOneWidget);
    // "Not you?" goes back to the start, the problem left behind.
    await tester.tap(find.text('مش حسابك؟ استخدم رقم أو إيميل تاني'));
    await frames(tester, 6);
    expect(find.text('أهلًا بيك'), findsOneWidget);
    expect(find.text('الحساب ده موقوف. كلّم الأكاديمية.'), findsNothing);
    await _close(tester);
  });
}
