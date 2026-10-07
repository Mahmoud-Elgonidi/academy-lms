import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/account_store.dart';
import 'package:academy_app/core/auth/session_cubit.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

/// Steps through frames without waiting for the code page's countdown to stop.
Future<void> frames(WidgetTester tester, [int n = 10]) async {
  for (var i = 0; i < n; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

void main() {
  testWidgets('a code to an email finds the accounts, and the chosen one opens on its home', (tester) async {
    final app = await TestApp.create();
    familyRoutes(app.api);
    app.api
      ..on('POST', 'accounts/code', {
        'challenge_id': 'ch1',
        'channel': 'email',
        'sent_to': 'r•••@demo.test',
        'expires_at': '2099-01-01T00:00:00Z',
        'resend_after': 60,
        'channels': ['email'],
      }, status: 202)
      ..on('POST', 'accounts/code/verify', {
        'discovery_token': 'disc',
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
          {
            'academy': {'id': 'a2', 'slug': 'hq', 'name_ar': 'الإدارة', 'name_en': 'HQ', 'logo_url': null, 'colour': null},
            'user': {
              'id': 'u-owner',
              'name': 'رامي فؤاد',
              'roles': ['owner'],
            },
          },
        ],
      })
      ..on('POST', 'accounts/sign-in', {
        'token': 'tok-1',
        'token_type': 'Bearer',
        'expires_at': time('2099-11-06T09:00:00Z', '2099-11-06T12:00:00+03:00'),
        'user': {
          'id': 'u-guardian',
          'name': 'رامي فؤاد',
          'roles': ['guardian'],
          'academy': {'id': 'a1', 'slug': 'demo', 'name_ar': 'أكاديمية النور', 'name_en': 'Noor', 'default_timezone': 'Asia/Riyadh'},
        },
      });
    await app.session.start();
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester);

    expect(find.text('أهلًا بيك'), findsOneWidget);
    await tester.tap(find.text('الإيميل'));
    await frames(tester, 3);
    await tester.enterText(find.byKey(const Key('email')), 'rami@demo.test');
    await tester.tap(find.text('ابعتلي كود'));
    await frames(tester);

    expect(find.text('اكتب الكود'), findsOneWidget);
    expect(app.api.sent('POST', 'accounts/code').single.data, {'contact': 'rami@demo.test'});
    expect(app.api.sent('POST', 'accounts/code').single.headers['X-Academy'], isNull);

    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester);

    // Both accounts are listed; the staff-only one is not picked.
    expect(find.text('لقينا حسابين ليك'), findsOneWidget);
    expect(find.text('الإدارة'), findsOneWidget);
    await tester.tap(find.text('ادخل'));
    await frames(tester, 20);

    final signIn = app.api.sent('POST', 'accounts/sign-in');
    expect(signIn, hasLength(1));
    expect(signIn.single.headers['X-Academy'], 'demo');
    expect(signIn.single.data, containsPair('discovery_token', 'disc'));
    expect(app.session.state.status, SessionStatus.signedIn);
    expect(app.session.state.current?.academyColour, '#1D5FB8');
    expect((await AccountStore(app.secrets).load()).$1.single.token, 'tok-1');

    // The guardian's home, with the child's next lesson.
    expect(find.text('الحصة الجاية'), findsOneWidget);
    expect(app.api.requests.where((r) => r.path == 'family/lessons').first.headers['Authorization'], 'Bearer tok-1');

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
  });

  testWidgets('a wrong code shows the API\'s words under the field', (tester) async {
    final app = await TestApp.create();
    app.api
      ..on('POST', 'accounts/code', {
        'challenge_id': 'ch1',
        'channel': 'whatsapp',
        'sent_to': '+966•••••••04',
        'expires_at': '2099-01-01T00:00:00Z',
        'resend_after': 60,
        'channels': ['whatsapp', 'sms'],
      }, status: 202)
      ..on('POST', 'accounts/code/verify', {
        'type': 'validation',
        'title': 'البيانات المرسلة غير صحيحة.',
        'status': 422,
        'errors': {
          'code': ['الكود ده مش صح. فاضلك 4 محاولات.'],
        },
      }, status: 422);
    await app.session.start();
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester);
    await tester.enterText(find.byKey(const Key('phone')), '0501234504');
    await tester.tap(find.text('ابعتلي كود'));
    await frames(tester);
    expect(app.api.sent('POST', 'accounts/code').single.data, {'contact': '+966501234504'});
    expect(find.text('ابعته رسالة SMS بدل واتساب'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('code')), '000000');
    await frames(tester);
    expect(find.text('الكود ده مش صح. فاضلك 4 محاولات.'), findsOneWidget);
    expect(app.session.state.status, SessionStatus.signedOut);
    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
  });
}
