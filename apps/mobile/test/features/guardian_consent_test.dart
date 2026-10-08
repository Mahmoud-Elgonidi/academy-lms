import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/consent/domain/consent.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _child({required bool waiting}) => {
  'student': {'id': 'c1', 'name': 'سلمى رامي'},
  'state': waiting ? 'waiting' : 'granted',
  'granted_at': waiting ? null : time('2026-10-05T07:00:00Z', '2026-10-05T10:00:00+03:00'),
  'by': waiting ? null : 'وافقت يوم 5 أكتوبر 2026',
  'can_withdraw': !waiting,
  'text': {
    'version': 1,
    'title': 'موافقتك على حساب سلمى رامي',
    'intro': 'عشان سلمى تدخل حسابها، محتاجين موافقتك.',
    'sections': [
      {'title': 'الحصص المباشرة', 'body': 'الحصص مابتتسجلش.'},
    ],
    'agree': 'أنا ولي أمر سلمى رامي، وموافق.',
  },
};

void main() {
  test('reads a guardian\'s children and the wording to agree to', () {
    final consents = Consents.fromJson({
      'age': 18,
      'children': [_child(waiting: true)],
    });
    expect(consents.age, 18);
    expect(consents.waiting.single.name, 'سلمى رامي');
    expect(consents.children.single.text.version, 1);
    expect(consents.children.single.text.sections.single.title, 'الحصص المباشرة');
  });

  testWidgets('a guardian sees a child waiting at home, reads, agrees with the wording\'s version, and can take it back', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    var granted = false;
    app.api
      ..onWith(
        'GET',
        'family/consents',
        (_) => (
          200,
          {
            'age': 18,
            'children': [_child(waiting: !granted)],
          },
        ),
      )
      ..onWith('POST', 'family/consents/c1', (_) {
        granted = true;
        return (200, {'id': 'gc1', 'state': 'granted'});
      })
      ..onWith('POST', 'family/consents/c1/withdraw', (_) {
        granted = false;
        return (200, {'state': 'waiting'});
      });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1100));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    // Home: the child waiting for this guardian.
    expect(find.byKey(const Key('consent-nudge')), findsOneWidget);
    expect(find.text('سلمى رامي مستني موافقتك عشان يدخل حسابه.'), findsOneWidget);
    await tester.tap(find.byKey(const Key('consent-nudge')));
    await frames(tester, 12);

    expect(find.text('خصوصية الأولاد'), findsWidgets);
    expect(find.text('الحصص مابتتسجلش.'), findsOneWidget);
    // Nothing to send before the box is ticked.
    final agree = find.widgetWithText(FilledButton, 'موافق');
    expect(tester.widget<FilledButton>(agree).onPressed, isNull);
    await tester.tap(find.byKey(const Key('agree-c1')));
    await frames(tester, 3);
    await tester.tap(agree);
    await frames(tester, 12);
    expect(app.api.sent('POST', 'family/consents/c1').single.data, {'agree': true, 'version': 1});
    expect(find.text('وافقت يوم 5 أكتوبر 2026'), findsOneWidget);

    await tester.tap(find.text('اسحب الموافقة'));
    await frames(tester, 6);
    expect(find.textContaining('هيخرج من حسابه على كل الأجهزة'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'اسحب الموافقة'));
    await frames(tester, 12);
    expect(app.api.sent('POST', 'family/consents/c1/withdraw'), hasLength(1));
    expect(find.text('مستني موافقتك'), findsOneWidget);

    // A message about it opens the same page.
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/home');
    await frames(tester, 10);
    await tester.binding.setSurfaceSize(null);
  });
}
