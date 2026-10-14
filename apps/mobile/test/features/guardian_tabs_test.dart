import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// Two children, each with their own lessons.
void _twoChildren(FakeApi api) => api
  ..on('GET', 'family/learners', {
    'data': [
      {'id': 'c1', 'name': 'سلمى رامي', 'is_self': false, 'stage': 'classic'},
      {'id': 'c2', 'name': 'عمر رامي', 'is_self': false, 'stage': 'classic'},
    ],
    'viewer_stage': 'classic',
  })
  ..onWith(
    'GET',
    'family/lessons',
    (r) => (
      200,
      {
        'learner': {'id': r.queryParameters['learner_id'] ?? 'c1', 'name': 'سلمى رامي'},
        'data': [
          {...lesson('l1'), 'subject': r.queryParameters['learner_id'] == 'c2' ? 'رياضيات' : 'لغة عربية'},
        ],
        'notices': <String>[],
      },
    ),
  );

Map<String, Object?> _invoice() => {
  'id': 'i1',
  'number': 'NR-1',
  'for': 'سلمى · قرآن',
  'currency': 'SAR',
  'total': '400 ر.س.',
  'outstanding': '400 ر.س.',
  'outstanding_minor': 40000,
  'outstanding_decimal': '400.00',
  'status': 'issued',
  'status_label': 'مستحقة',
  'issued_at': time('2099-10-01T09:00:00Z', '2099-10-01T12:00:00+03:00'),
  'due_at': time('2099-10-08T09:00:00Z', '2099-10-08T12:00:00+03:00'),
  'document_url': null,
  'pay_with': <Object>[],
  'proof': null,
  'can_send_proof': false,
  'lines': <Object>[],
};

Future<TestApp> _open(WidgetTester tester, {void Function(FakeApi api)? more}) async {
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  more?.call(app.api);
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

Finder _tab(String label) => find.descendant(of: find.byType(NavigationBar), matching: find.text(label));

void main() {
  testWidgets('a guardian\'s five tabs end in More, each with the bell, and the children under it switch the tabs', (tester) async {
    final app = await _open(
      tester,
      more: (api) {
        _twoChildren(api);
        api.on('GET', 'messages/unread', {
          'data': {'count': 2},
        });
      },
    );

    for (final label in ['الرئيسية', 'الحصص', 'الرحلة', 'الفلوس', 'المزيد']) {
      expect(_tab(label), findsOneWidget);
    }
    expect(_tab('حسابي'), findsNothing);

    // Home: the academy's line, the greeting, the bell, both children.
    expect(find.text('أهلًا يا رامي'), findsOneWidget);
    expect(find.byKey(const Key('bell')), findsOneWidget);
    expect(find.byKey(const Key('learner-c1')), findsOneWidget);
    expect(find.byKey(const Key('learner-c2')), findsOneWidget);
    expect(find.text('لغة عربية مع أ. منى إبراهيم · 45 دقيقة'), findsOneWidget);

    // Picking Omar moves the home to his lessons.
    await tester.tap(find.byKey(const Key('learner-c2')));
    await frames(tester, 10);
    expect(app.api.sent('GET', 'family/lessons').any((r) => r.queryParameters['learner_id'] == 'c2'), isTrue);
    expect(find.text('رياضيات مع أ. منى إبراهيم · 45 دقيقة'), findsOneWidget);

    // The lessons and money tabs: the same bell, the same children, Omar still picked.
    await tester.tap(_tab('الحصص'));
    await frames(tester, 10);
    expect(find.text('الحصص'), findsWidgets);
    expect(find.byKey(const Key('bell')), findsOneWidget);
    expect(find.byKey(const Key('learner-c2')), findsOneWidget);
    expect(find.text('رياضيات مع أ. منى إبراهيم'), findsOneWidget);

    await tester.tap(_tab('الفلوس'));
    await frames(tester, 10);
    expect(find.text('الفواتير والرصيد'), findsOneWidget);
    expect(find.byKey(const Key('bell')), findsOneWidget);
    expect(find.byKey(const Key('learner-c1')), findsOneWidget);

    // More: no children to switch, the groups, the messages with their count.
    await tester.tap(_tab('المزيد'));
    await frames(tester, 10);
    expect(find.byKey(const Key('learner-c1')), findsNothing);
    for (final group in ['الأولاد', 'التواصل', 'حسابي']) {
      expect(find.text(group), findsOneWidget);
    }
    expect(find.text('ولي أمر سلمى، عمر · أكاديمية النور'), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('open-messages')), matching: find.text('2')), findsOneWidget);
    await _close(tester);
  });

  testWidgets('the home: when the room opens, what is left, what is owed with Pay, and the homework', (tester) async {
    await _open(
      tester,
      more: (api) => api
        ..on('GET', 'family/lessons', {
          'learner': {'id': 'c1', 'name': 'سلمى رامي'},
          'data': [
            {
              ...lesson('l1'),
              'join': {'open': false, 'opens_at': time('2099-10-11T15:50:00Z', '2099-10-11T18:50:00+03:00')},
            },
          ],
          'notices': <String>[],
        })
        ..on('GET', 'family/balance', {
          'data': [
            {'enrollment_id': 'e1', 'subject': 'قرآن', 'sessions': 2, 'minutes': 90},
          ],
        })
        ..on('GET', 'family/invoices', {
          'owed': '400 ر.س.',
          'manual': null,
          'data': [_invoice()],
        })
        ..on('GET', 'learning/homework', {
          'data': [homeworkJson('h2', status: 'submitted'), homeworkJson('h1')],
        }),
    );

    // The one surface in the brand's colour: the time the room opens, nothing to press yet.
    expect(find.byKey(const Key('room-opens')), findsOneWidget);
    expect(find.textContaining('الفصل بيفتح'), findsOneWidget);
    expect(tester.widget<OutlinedButton>(find.byKey(const Key('room-opens'))).onPressed, isNull);

    expect(find.descendant(of: find.byKey(const Key('home-balance')), matching: find.text('رصيد سلمى')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('home-balance')), matching: find.text('حصتين')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('home-owed')), matching: find.text('400 ر.س.')), findsOneWidget);

    // What is due comes first.
    await tester.scrollUntilVisible(find.byKey(const Key('home-homework-h2')), 200);
    final due = tester.getTopLeft(find.byKey(const Key('home-homework-h1'))).dy;
    final handedIn = tester.getTopLeft(find.byKey(const Key('home-homework-h2'))).dy;
    expect(due, lessThan(handedIn));

    await tester.tap(find.byKey(const Key('home-homework-h1')));
    await frames(tester, 10);
    expect(find.text('الواجبات'), findsWidgets);

    await tester.tap(_tab('الرئيسية'));
    await frames(tester, 6);
    await tester.scrollUntilVisible(find.byKey(const Key('home-pay')), -200);
    await tester.tap(find.byKey(const Key('home-pay')));
    await frames(tester, 10);
    expect(find.text('الفواتير والرصيد'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('More opens each part on its own and asks before signing out, in red', (tester) async {
    final app = await _open(tester);
    await tester.tap(_tab('المزيد'));
    await frames(tester, 10);

    expect(find.descendant(of: find.byKey(const Key('open-settings')), matching: find.text('العربي · الرياض')), findsOneWidget);
    await tester.tap(find.byKey(const Key('open-settings')));
    await frames(tester, 10);
    expect(find.text('اللغة والتوقيت'), findsOneWidget);
    expect(find.text('الأصوات'), findsOneWidget);
    expect(find.text('أجهزتي'), findsNothing);
    await tester.tap(find.byTooltip('رجوع'));
    await frames(tester, 10);

    await tester.tap(find.byKey(const Key('open-accounts')));
    await frames(tester, 10);
    expect(find.text('ضيف حساب تاني'), findsOneWidget);
    await tester.tap(find.byTooltip('رجوع'));
    await frames(tester, 10);

    await tester.scrollUntilVisible(find.byKey(const Key('sign-out')), 200);
    await tester.tap(find.byKey(const Key('sign-out')));
    await frames(tester, 6);
    expect(find.text('تخرج من أكاديمية النور على الموبايل ده؟'), findsOneWidget);
    final yes = tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'خرّجه'));
    expect(yes.style?.backgroundColor?.resolve({}), isNot(isNull));
    await tester.tap(find.text('رجوع'));
    await frames(tester, 6);
    expect(app.session.state.current, isNotNull);
    await _close(tester);
  });

  testWidgets('messages are about one child: the chip says whom, and another is a tap away', (tester) async {
    final app = await _open(
      tester,
      more: (api) {
        _twoChildren(api);
        api.onWith(
          'GET',
          'messages/conversations',
          (r) => (
            200,
            {
              'data': <Object>[],
              'meta': {'learner': null, 'notice': ''},
            },
          ),
        );
      },
    );
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/me/messages');
    await frames(tester, 12);
    expect(find.text('عن سلمى'), findsOneWidget);
    expect(app.api.sent('GET', 'messages/conversations').last.queryParameters['learner_id'], 'c1');

    await tester.tap(find.byKey(const Key('learner-chip')));
    await frames(tester, 8);
    await tester.tap(find.byKey(const Key('pick-c2')));
    await frames(tester, 10);
    expect(find.text('عن عمر'), findsOneWidget);
    expect(app.api.sent('GET', 'messages/conversations').last.queryParameters['learner_id'], 'c2');
    await _close(tester);
  });

  testWidgets('what is owed leads the money tab, in the academy\'s colour, with where to transfer under its heading', (tester) async {
    await _open(
      tester,
      more: (api) => api.on('GET', 'family/invoices', {
        'owed': '400 ر.س.',
        'manual': {
          'note': null,
          'takes_proofs': true,
          'methods': [
            {'value': 'bank_transfer', 'label': 'تحويل بنكي'},
          ],
        },
        'data': [
          {..._invoice(), 'can_send_proof': true, 'transfer_details': 'بنك الراجحي\nSA00 0000'},
        ],
      }),
    );
    await tester.tap(_tab('الفلوس'));
    await frames(tester, 10);
    expect(find.descendant(of: find.byKey(const Key('money-owed')), matching: find.text('400 ر.س.')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('money-owed')), matching: find.text('دفعت بالتحويل')), findsOneWidget);
    // The transfer details sit under their own heading, said once.
    expect(find.text('بيانات التحويل'), findsOneWidget);
    expect(find.byKey(const Key('transfer-copy')), findsOneWidget);
    await _close(tester);
  });

  test('a notification still opens where it did: More keeps /family/me', () {
    expect(targetOf('account', 'u1', Area.family), '/family/me');
    expect(targetOf('conversation', 'k1', Area.family), '/family/me/messages/k1');
    expect(targetOf('guardian_consent', null, Area.family), '/family/me/privacy');
    expect(targetOf('my_data', 'r1', Area.family), '/family/me/my-data');
    expect(targetOf('invoice', 'i1', Area.family), '/family/money');
  });
}
