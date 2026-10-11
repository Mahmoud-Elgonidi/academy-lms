import 'package:academy_app/app/app.dart';
import 'package:academy_app/shared/widgets/latin_digits.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:url_launcher_platform_interface/url_launcher_platform_interface.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

void main() {
  test('digits typed on an Arabic keyboard become Latin ones', () {
    expect(latinDigits('٠١٢٣٤٥٦٧٨٩'), '0123456789');
    expect(latinDigits('۰۱۲۳۴۵۶۷۸۹'), '0123456789');
    expect(latinDigits('٤٠٠٫٥٠', decimal: true), '400.50');
    expect(latinDigits('400,50', decimal: true), '400.50');
    expect(latinDigits('400,50'), '400,50');
    expect(latinDigits('AB12-cd'), 'AB12-cd');
  });

  testWidgets('before signing in, the platform\'s privacy notice and terms open in the browser from each step', (tester) async {
    final launcher = FakeLauncher.install();
    final app = await TestApp.create();
    app.api.on('GET', 'branding', branding());
    await app.session.start();
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester);

    expect(find.text('أهلًا بيك'), findsOneWidget);
    expect(find.text('سياسة الخصوصية'), findsOneWidget);
    expect(find.text('شروط الاستخدام'), findsOneWidget);
    await tester.tap(find.byKey(const Key('legal-privacy')));
    await frames(tester, 2);
    expect(launcher.opened, [('http://api.test/privacy?lang=ar', PreferredLaunchMode.inAppBrowserView)]);

    // The password step (an academy's code typed there) has them too.
    await tester.tap(find.text('عندك كلمة سر؟ ادخل بيها'));
    await frames(tester);
    await tester.tap(find.byKey(const Key('legal-terms')));
    await frames(tester, 2);
    expect(launcher.urls.last, 'http://api.test/terms?lang=ar');
    // Nothing is asked of the API to show them.
    expect(app.api.requests.where((r) => r.path.contains('privacy') || r.path.contains('terms')), isEmpty);

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
  });

  testWidgets('a phone with no browser says it cannot open the page', (tester) async {
    FakeLauncher.install().opens = false;
    final app = await TestApp.create();
    app.api.on('GET', 'branding', branding());
    await app.session.start();
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester);

    await tester.tap(find.byKey(const Key('legal-privacy')));
    await frames(tester, 3);
    expect(find.text('مفيش برنامج على الموبايل يفتح اللينك ده.'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
  });

  testWidgets('a mobile number and a code typed in Arabic digits are sent in Latin ones', (tester) async {
    final app = await TestApp.create();
    app.api
      ..on('GET', 'branding', branding())
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

    await tester.enterText(find.byKey(const Key('phone')), '٠٥٠١٢٣٤٥٠٤');
    expect(find.text('0501234504'), findsOneWidget);
    await tester.tap(find.text('ابعتلي كود'));
    await frames(tester);
    expect(app.api.sent('POST', 'accounts/code').single.data, {'contact': '+966501234504'});

    // Six Arabic digits are a whole code: it goes at once.
    await tester.enterText(find.byKey(const Key('code')), '١٢٣٤٥٦');
    await frames(tester);
    expect(app.api.sent('POST', 'accounts/code/verify').single.data, containsPair('code', '123456'));
    expect(find.text('الكود ده مش صح. فاضلك 4 محاولات.'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
  });
}
