import 'dart:typed_data';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:academy_app/core/app_policy.dart';
import 'package:academy_app/features/homework/data/homework_repository.dart';
import 'package:academy_app/features/notifications/presentation/widgets/notification_bell.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Future<void> _open(WidgetTester tester, TestApp app) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1000));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 20);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

Map<String, Object?> _notification(String id, String title, String screen, {bool read = false}) => {
  'id': id,
  'category': 'billing',
  'template': 'billing.invoice_issued',
  'title': title,
  'body': 'الفاتورة جاهزة للدفع.',
  'action': {'screen': screen, 'id': 'x-$id'},
  'urgent': false,
  'read': read,
  'created_at': time('2099-10-06T10:00:00Z', '2099-10-06T13:00:00+03:00'),
};

void main() {
  test('each notification opens where it belongs in each look', () {
    expect(targetOf('write_report', 'r9', Area.teacher), '/teach/reports/r9');
    expect(targetOf('shift_proposal', null, Area.teacher), '/teach/hours');
    expect(targetOf('homework_to_mark', 'h1', Area.teacher), '/teach/marking');
    expect(targetOf('invoice', 'i1', Area.family), '/family/money');
    expect(targetOf('session', 's1', Area.kids), '/kids/lessons');
    expect(targetOf('challenges', null, Area.middle), '/middle/journey');
    expect(targetOf('homework', 'h1', Area.teen), '/teen/homework');
    // Something a look has no screen for opens its home.
    expect(targetOf('invoice', 'i1', Area.kids), '/kids/journey');
    expect(targetOf('live_board', null, Area.teacher), '/teach/today');
    // An academy's announcement is read in full in the inbox, over the tabs, in every look.
    expect(targetOf('announcement', 'a1', Area.family), '/inbox');
    expect(targetOf('announcement', 'a1', Area.kids), '/inbox');
    expect(targetOf('announcement', 'a1', Area.teacher), '/inbox');
    expect(opensOverTabs('/inbox'), isTrue);
    expect(readsInPlace('announcement'), isTrue);
    expect(readsInPlace('invoice'), isFalse);
  });

  test('versions compare number by number', () {
    const policy = AppPolicy(minVersion: '1.10.0');
    expect(policy.tooOld('1.9.9'), isTrue);
    expect(policy.tooOld('1.10.0'), isFalse);
    expect(policy.tooOld('1.10.0+7'), isFalse);
    expect(policy.tooOld('2.0'), isFalse);
  });

  test('a recording goes up as a multipart file with the words beside it', () async {
    final app = await TestApp.create(accounts: [guardian]);
    app.api.on('POST', 'learning/homework/h1/submissions', {'data': homeworkJson('h1', status: 'submitted')}, status: 201);
    await app.session.start();
    await HomeworkRepository(app.client).handIn(
      'h1',
      body: 'سمّعت',
      file: HandInFile(bytes: Uint8List.fromList([1, 2, 3]), name: 'recording.wav', mime: 'audio/wav'),
    );
    final form = app.api.sent('POST', 'learning/homework/h1/submissions').single.data as FormData;
    expect(form.fields.single.key, 'body');
    expect(form.fields.single.value, 'سمّعت');
    expect(form.files.single.key, 'file');
    expect(form.files.single.value.filename, 'recording.wav');
    expect(form.files.single.value.contentType.toString(), 'audio/wav');
  });

  test('this phone gets the open account\'s pushes, follows a new token, and is forgotten on sign-out', () async {
    final push = FakePush('fcm-1');
    final app = await TestApp.create(accounts: [guardian], push: push, appVersion: '1.2.0');
    familyRoutes(app.api);
    app.api
      ..on('POST', 'devices', {'data': <String, Object?>{}}, status: 201)
      ..on('DELETE', 'devices', null, status: 204)
      ..on('POST', 'auth/logout', null, status: 204);
    await app.session.start();
    await Future<void>.delayed(const Duration(milliseconds: 250));
    expect(app.api.sent('POST', 'devices').single.data, {'platform': 'android', 'token': 'fcm-1', 'app_version': '1.2.0'});

    push.rotate('fcm-2');
    await Future<void>.delayed(const Duration(milliseconds: 100));
    expect(app.api.sent('POST', 'devices').last.data, {'platform': 'android', 'token': 'fcm-2', 'app_version': '1.2.0'});

    await app.session.signOut();
    await Future<void>.delayed(const Duration(milliseconds: 100));
    final forget = app.api.sent('DELETE', 'devices').single;
    expect(forget.data, {'token': 'fcm-2'});
    expect(forget.headers['Authorization'], 'Bearer ${guardian.token}');
    // Forgotten before the token ends, while it still works.
    expect(app.api.requests.indexOf(forget), lessThan(app.api.requests.indexOf(app.api.sent('POST', 'auth/logout').single)));
  });

  testWidgets('a link into the app (academyapp://app/open/…) lands where the notification belongs', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    await _open(tester, app);
    final before = app.api.sent('GET', 'family/invoices').length;

    GoRouter.of(tester.element(find.byType(NotificationBell).first)).go('/open/invoice/i1');
    await frames(tester, 12);
    expect(app.api.sent('GET', 'family/invoices').length, greaterThan(before));
    expect(GoRouter.of(tester.element(find.byType(Scaffold).last)).state.matchedLocation, '/family/money');

    await _close(tester);
  });

  testWidgets('a tapped push opens its own account first (one phone, several accounts), then its place', (tester) async {
    final app = await TestApp.create(accounts: [teacher, guardian], current: teacher.id);
    teacherRoutes(app.api);
    familyRoutes(app.api);
    await _open(tester, app);
    expect(app.session.state.current?.id, teacher.id);
    final router = GoRouter.of(tester.element(find.byType(NotificationBell).first));

    // For someone no longer on this phone: nothing opens.
    await openMessage(app.session, router, {'academy': 'demo', 'user': 'u-gone', 'screen': 'invoice', 'id': 'i1'});
    await frames(tester, 6);
    expect(app.session.state.current?.id, teacher.id);
    expect(router.state.matchedLocation, '/teach/today');

    await openMessage(app.session, router, {'academy': 'demo', 'user': 'u-guardian', 'screen': 'invoice', 'id': 'i1'});
    await frames(tester, 12);
    expect(app.session.state.current?.id, guardian.id);
    expect(router.state.matchedLocation, '/family/money');

    await _close(tester);
  });

  testWidgets('the bell counts the unread, the inbox opens a message where it belongs and marks it read', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..onWith(
        'GET',
        'notifications',
        (r) => (
          200,
          {
            'data': r.queryParameters['unread'] == 1
                ? <Object>[]
                : [
                    _notification('n1', 'فاتورة جديدة NR-000010', 'invoice'),
                    _notification('n2', 'إجازة الأكاديمية', 'holiday', read: true),
                  ],
            'meta': {'unread': 1, 'next_cursor': null},
          },
        ),
      )
      ..on('POST', 'notifications/read', {
        'data': {'marked': 1},
      })
      ..on('GET', 'family/invoices', {'owed': null, 'data': <Object>[]});
    await _open(tester, app);

    // One unread on the bell.
    expect(find.descendant(of: find.byType(Badge), matching: find.text('1')), findsWidgets);
    await tester.tap(find.byIcon(Icons.notifications_active_rounded));
    await frames(tester, 10);
    expect(find.text('الإشعارات'), findsOneWidget);
    expect(find.text('فاتورة جديدة NR-000010'), findsOneWidget);

    await tester.tap(find.text('فاتورة جديدة NR-000010'));
    await frames(tester, 12);
    expect(app.api.sent('POST', 'notifications/read').single.data, {
      'ids': ['n1'],
    });
    // The invoice opens the money tab.
    expect(app.api.sent('GET', 'family/invoices').length, greaterThanOrEqualTo(1));

    await _close(tester);
  });

  testWidgets('an announcement opens in full where it is, and is marked read', (tester) async {
    const body =
        'الأكاديمية في إجازة من الثلاثاء للخميس.\nالحصص بترجع يوم الجمعة.\nدقايق الحصص اللي في الإجازة بترجع لرصيدكم.\nكل سنة وانتم طيبين!';
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..onWith(
        'GET',
        'notifications',
        (r) => (
          200,
          {
            'data': r.queryParameters['unread'] == 1
                ? <Object>[]
                : [
                    {
                      ..._notification('n3', 'إعلان: إجازة العيد', 'announcement'),
                      'body': body,
                      'category': 'lessons',
                      'template': 'announcements.families',
                    },
                  ],
            'meta': {'unread': 1, 'next_cursor': null},
          },
        ),
      )
      ..on('POST', 'notifications/read', {
        'data': {'marked': 1},
      });
    await _open(tester, app);

    await tester.tap(find.byIcon(Icons.notifications_active_rounded));
    await frames(tester, 10);
    expect(find.byIcon(Icons.expand_more_rounded), findsOneWidget);
    expect(tester.widget<Text>(find.text(body)).maxLines, 3);

    await tester.tap(find.text('إعلان: إجازة العيد'));
    await frames(tester, 6);
    expect(app.api.sent('POST', 'notifications/read').single.data, {
      'ids': ['n3'],
    });
    // Still the inbox, the whole announcement shown.
    expect(find.text('الإشعارات'), findsOneWidget);
    expect(find.byIcon(Icons.expand_less_rounded), findsOneWidget);
    expect(tester.widget<Text>(find.text(body)).maxLines, isNull);

    await _close(tester);
  });

  testWidgets('an app older than the oldest served asks to be updated before anything else', (tester) async {
    final app = await TestApp.create(accounts: [guardian], appVersion: '1.0.0');
    familyRoutes(app.api);
    app.api.on('GET', 'branding', {
      ...branding(),
      'app': {'min_version': '1.2.0', 'android_url': 'https://play.example/academy', 'ios_url': null},
    });
    await _open(tester, app);

    expect(find.text('فيه نسخة جديدة من التطبيق'), findsOneWidget);
    expect(find.text('حدّث التطبيق'), findsOneWidget);
    expect(find.text('حصص سلمى'), findsNothing);

    await _close(tester);
  });
}
