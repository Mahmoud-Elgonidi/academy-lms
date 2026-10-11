import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher_platform_interface/url_launcher_platform_interface.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// "My data" as the API answers it: whom one may ask about (none for a younger student), the requests, the pages.
Map<String, Object?> _mine({required List<Map<String, Object?>> subjects, List<Map<String, Object?>> requests = const []}) => {
  'data': {
    'subjects': subjects,
    'choices': <String, Object>{},
    'says_min': 10,
    'says_max': 1000,
    'requests': requests,
    'goes': ['الحساب وبيانات الدخول'],
    'stays': ['الفواتير'],
    'stays_note': 'اللي بيفضل بيفضل من غير اسم.',
    'grace_hours': 24,
    'answer_days': 30,
    'links': {
      'privacy': 'https://demo.lms.example/privacy',
      'terms': 'https://demo.lms.example/terms',
      'deletion': 'https://demo.lms.example/account-deletion',
    },
  },
};

Map<String, Object?> _self(String id, String name) => {
  'id': id,
  'name': name,
  'self': true,
  'can_copy': true,
  'details': [
    {'key': 'name', 'label': 'الاسم', 'value': name, 'shown': name},
  ],
  'correction': null,
};

Map<String, Object?> _erasure(String id, String subject, String name) => {
  'id': id,
  'kind': {'key': 'erasure', 'label': 'مسح الحساب'},
  'status': {'key': 'pending', 'label': 'مستني الأكاديمية'},
  'subject': {'id': subject, 'name': name, 'self': true},
  'requester': name,
  'reason': null,
  'answer': null,
  'created_at': time('2099-10-09T07:00:00Z', '2099-10-09T10:00:00+03:00', label: 'الجمعة 9 أكتوبر'),
  'due_at': time('2099-11-08T07:00:00Z', '2099-11-08T10:00:00+03:00', label: 'الأحد 8 نوفمبر'),
  'erase_after': null,
  'done_at': null,
  'expires_at': null,
  'size': null,
  'download_url': null,
  'held_back': <String>[],
  'can_cancel': true,
};

Future<void> _open(WidgetTester tester, TestApp app) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 2200));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  testWidgets('a teacher asks from the app for their account to be deleted, and finds the web page about it', (tester) async {
    final launcher = FakeLauncher.install();
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final requests = <Map<String, Object?>>[];
    app.api
      ..onWith('GET', 'me/data-requests', (_) => (200, _mine(subjects: [_self('u-teacher', 'أ. نور الهدى')], requests: requests)))
      ..onWith('POST', 'me/data-requests', (_) {
        requests.add(_erasure('e1', 'u-teacher', 'أ. نور الهدى'));
        return (201, {'data': requests.last});
      });
    await _open(tester, app);

    await tester.tap(find.text('المزيد').last);
    await frames(tester, 8);
    await tester.tap(find.byKey(const Key('open-my-data')));
    await frames(tester, 12);
    expect(find.text('بياناتي'), findsWidgets);

    await tester.ensureVisible(find.byKey(const Key('erase-u-teacher')));
    await tester.tap(find.byKey(const Key('erase-u-teacher')));
    await frames(tester, 8);
    await tester.tap(find.widgetWithText(FilledButton, 'امسح الحساب ده'));
    await frames(tester, 12);
    final asked = app.api.sent('POST', 'me/data-requests').single;
    expect(asked.data, {'kind': 'erasure', 'subject_id': 'u-teacher', 'reason': null});
    expect(asked.headers['Idempotency-Key'], isNotNull);
    expect(find.text('الطلب وصل للأكاديمية.'), findsOneWidget);
    // Asked once: the button gives way to taking it back.
    expect(find.byKey(const Key('erase-u-teacher')), findsNothing);
    expect(find.byKey(const Key('cancel-u-teacher')), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('page-deletion')));
    await tester.tap(find.byKey(const Key('page-deletion')));
    await frames(tester, 3);
    expect(launcher.opened, [('https://demo.lms.example/account-deletion', PreferredLaunchMode.inAppBrowserView)]);
    await _close(tester);
  });

  testWidgets('a younger student reads that their guardian asks, and still has the privacy and deletion pages', (tester) async {
    final launcher = FakeLauncher.install();
    const middle = Account(
      academySlug: 'demo',
      academyNameAr: 'أكاديمية النور',
      academyNameEn: 'Noor Academy',
      userId: 'u-middle',
      userName: 'زياد',
      roles: ['student'],
      token: 'token-middle',
      stage: 'middle',
    );
    final app = await TestApp.create(accounts: [middle]);
    familyRoutes(app.api);
    app.api
      ..on('GET', 'family/learners', {
        'data': [
          {'id': 'u-middle', 'name': 'زياد', 'is_self': true, 'stage': 'middle'},
        ],
        'viewer_stage': 'middle',
      })
      ..on('GET', 'learning/journey/map', quietJourney(viewer: 'student', stage: 'middle'))
      ..on('GET', 'me/data-requests', _mine(subjects: []));
    await _open(tester, app);
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/account/my-data');
    await frames(tester, 12);

    expect(find.text('ولي أمرك هو اللي بيطلب بخصوص بياناتك من حسابه.'), findsOneWidget);
    expect(find.text('امسح الحساب ده'), findsNothing);
    await tester.tap(find.byKey(const Key('page-privacy')));
    await frames(tester, 3);
    expect(launcher.urls, ['https://demo.lms.example/privacy']);
    expect(find.byKey(const Key('page-deletion')), findsOneWidget);
    await _close(tester);
  });
}
