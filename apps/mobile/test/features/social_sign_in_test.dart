import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/session_cubit.dart';
import 'package:academy_app/core/social/social_sign_in.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// The phone's Google and Apple sign-in, as far as the tests go: both on offer, each giving an ID token.
class FakeSocialSignIn implements SocialSignIn {
  final asked = <String>[];

  @override
  bool available(String provider) => true;

  @override
  Future<SocialCredential?> signIn(String provider) async {
    asked.add(provider);
    return SocialCredential(provider: provider, idToken: 'id-token-$provider', nonce: provider == 'apple' ? 'raw-nonce' : null);
  }
}

Map<String, Object?> _method(String provider, {bool linked = false, String? email}) => {
  'provider': provider,
  'label': provider == 'apple' ? 'أبل' : 'جوجل',
  'linked': linked,
  'email': email,
  'linked_at': null,
  'last_used_at': null,
};

void main() {
  testWidgets('Google finds the accounts like a code does, and the chosen one signs in', (tester) async {
    final social = FakeSocialSignIn();
    final app = await TestApp.create(social: social);
    familyRoutes(app.api);
    app.api
      ..on('POST', 'accounts/social', {
        'discovery_token': 'disc-g',
        'expires_at': null,
        'accounts': [
          {
            'academy': {'id': 'a1', 'slug': 'demo', 'name_ar': 'أكاديمية النور', 'name_en': 'Noor', 'logo_url': null, 'colour': '#1D5FB8'},
            'user': {
              'id': 'u-guardian',
              'name': 'رامي فؤاد',
              'roles': ['guardian'],
            },
          },
        ],
      })
      ..on('POST', 'accounts/sign-in', {
        'token': 'tok-g',
        'token_type': 'Bearer',
        'expires_at': null,
        'user': {
          'id': 'u-guardian',
          'name': 'رامي فؤاد',
          'roles': ['guardian'],
          'academy': {'id': 'a1', 'slug': 'demo', 'name_ar': 'أكاديمية النور', 'name_en': 'Noor', 'default_timezone': 'Asia/Riyadh'},
        },
      });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1200));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester);

    expect(find.byKey(const Key('social-apple')), findsOneWidget);
    await tester.tap(find.byKey(const Key('social-google')));
    await frames(tester);

    expect(social.asked, ['google']);
    final asked = app.api.sent('POST', 'accounts/social').single;
    expect(asked.data, {'provider': 'google', 'id_token': 'id-token-google'});
    expect(asked.headers['X-Academy'], isNull);
    // One account: found, with its academy and a button straight in.
    expect(find.text('وجدناك!'), findsOneWidget);
    expect(find.text('أكاديمية النور'), findsWidgets);

    await tester.tap(find.text('ادخل أكاديمية النور'));
    await frames(tester, 20);
    expect(app.api.sent('POST', 'accounts/sign-in').single.data, containsPair('discovery_token', 'disc-g'));
    expect(app.session.state.status, SessionStatus.signedIn);

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
    await tester.binding.setSurfaceSize(null);
  });

  testWidgets('sign-in and security links Apple with the phone\'s own sign-in, and unlinks Google after asking', (tester) async {
    final social = FakeSocialSignIn();
    final app = await TestApp.create(accounts: [guardian], social: social);
    familyRoutes(app.api);
    app.api
      ..on('GET', 'me/sessions', {'data': []})
      ..on('GET', 'me/social', {
        'data': [_method('google', linked: true, email: 'rami@gmail.com'), _method('apple')],
      })
      ..on('POST', 'me/social', {
        'data': [
          _method('google', linked: true, email: 'rami@gmail.com'),
          _method('apple', linked: true, email: 'x7@privaterelay.appleid.com'),
        ],
      })
      ..on('DELETE', 'me/social/google', {
        'data': [_method('google'), _method('apple', linked: true)],
      });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 2400));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 20);
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/me');
    await frames(tester, 20);
    // More › Sign-in and security.
    await tester.tap(find.byKey(const Key('open-security')));
    await frames(tester, 20);

    expect(find.text('الدخول بجوجل وأبل'), findsOneWidget);
    expect(find.text('مربوط: rami@gmail.com'), findsOneWidget);

    await tester.tap(find.descendant(of: find.byKey(const Key('sign-in-method-apple')), matching: find.text('اربط')));
    await frames(tester);
    expect(social.asked, ['apple']);
    expect(app.api.sent('POST', 'me/social').single.data, {'provider': 'apple', 'id_token': 'id-token-apple', 'nonce': 'raw-nonce'});
    expect(find.text('رُبط حساب أبل.'), findsOneWidget);

    await tester.tap(find.descendant(of: find.byKey(const Key('sign-in-method-google')), matching: find.text('افصل')));
    await frames(tester, 3);
    await tester.tap(find.descendant(of: find.byType(AlertDialog), matching: find.text('افصل')));
    await frames(tester);
    expect(app.api.sent('DELETE', 'me/social/google'), hasLength(1));

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
    await tester.binding.setSurfaceSize(null);
  });
}
