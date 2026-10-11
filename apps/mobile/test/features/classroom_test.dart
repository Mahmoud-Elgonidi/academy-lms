import 'dart:async';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/classroom/presentation/pages/classroom_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:url_launcher_platform_interface/url_launcher_platform_interface.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// A lesson whose room is open now.
Map<String, Object?> _open(String id) => {
  ...lesson(id, canCancel: false, status: 'in_progress'),
  'status_label': 'شغالة',
  'join': {'open': true, 'opens_at': null},
};

/// A seat on an outside link (Zoom, Meet): the app opens it in its own app.
Map<String, Object?> _outside(String url) => {
  'data': {'provider': 'external', 'url': url},
  'recording': {'mode': 'off', 'controls': false, 'running': false, 'missing': <String>[]},
};

Future<TestApp> _lessons(WidgetTester tester, {Object? ticket, int status = 200}) async {
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  app.api
    ..on('GET', 'family/lessons', {
      'learner': {'id': 'c1', 'name': 'سلمى رامي'},
      'data': [_open('l1')],
      'notices': <String>[],
    })
    ..on('POST', 'lessons/l1/join', ticket, status: status);
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1000));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  await tester.tap(find.text('الحصص').last);
  await frames(tester, 10);
  expect(find.text('حصص سلمى'), findsOneWidget);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

final _join = find.widgetWithText(FilledButton, 'ادخل الحصة');

void main() {
  testWidgets('an outside link opens in its own app, once, however many times join is tapped', (tester) async {
    final launcher = FakeLauncher.install();
    final app = await _lessons(tester, ticket: _outside('https://meet.example/abc'));

    await tester.tap(_join);
    await tester.pump();
    await tester.tap(_join);
    await frames(tester, 6);

    expect(app.api.sent('POST', 'lessons/l1/join'), hasLength(1));
    expect(app.api.sent('POST', 'lessons/l1/join').single.headers['Authorization'], 'Bearer token-guardian');
    expect(launcher.opened, [('https://meet.example/abc', PreferredLaunchMode.externalApplication)]);
    expect(find.byType(ClassroomPage), findsNothing);

    // Back from the meeting's app, joining again works.
    await tester.tap(_join);
    await frames(tester, 6);
    expect(app.api.sent('POST', 'lessons/l1/join'), hasLength(2));
    expect(launcher.opened, hasLength(2));
    await _close(tester);
  });

  testWidgets('an outside link that no app on the phone opens says so', (tester) async {
    final launcher = FakeLauncher.install()..opens = false;
    await _lessons(tester, ticket: _outside('https://meet.example/abc'));

    await tester.tap(_join);
    await frames(tester, 6);
    expect(launcher.urls, ['https://meet.example/abc']);
    expect(find.text('مفيش برنامج على الموبايل يفتح اللينك ده.'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a child waiting for a guardian\'s consent reads why the room stays shut, and can try again', (tester) async {
    final launcher = FakeLauncher.install();
    final app = await _lessons(
      tester,
      status: 403,
      ticket: {
        'type': 'guardian-consent-required',
        'title': 'مستنيين موافقة ولي الأمر.',
        'status': 403,
        'detail': 'سلمى محتاجة موافقة ولي أمرها قبل ما تدخل الحصة.',
      },
    );

    await tester.tap(_join);
    await frames(tester, 6);
    expect(find.text('سلمى محتاجة موافقة ولي أمرها قبل ما تدخل الحصة.'), findsOneWidget);
    expect(find.byType(ClassroomPage), findsNothing);
    expect(launcher.opened, isEmpty);

    // The guardian agreed meanwhile: the same button joins now.
    app.api.on('POST', 'lessons/l1/join', _outside('https://meet.example/abc'));
    await tester.tap(_join);
    await frames(tester, 6);
    expect(app.api.sent('POST', 'lessons/l1/join'), hasLength(2));
    expect(launcher.urls, ['https://meet.example/abc']);
    await _close(tester);
  });

  testWidgets('a room that closed before the tap: the API\'s words, nothing opened', (tester) async {
    final launcher = FakeLauncher.install();
    await _lessons(tester, status: 409, ticket: {'type': 'room-closed', 'title': 'الحصة خلصت.', 'status': 409});

    await tester.tap(_join);
    await frames(tester, 6);
    expect(find.text('الحصة خلصت.'), findsOneWidget);
    expect(launcher.opened, isEmpty);
    await _close(tester);
  });

  test('leaving a room lets it go even when the disconnect times out (no network, or never connected)', () async {
    final steps = <String>[];
    await closeRoom(
      disconnect: () async {
        steps.add('disconnect');
        throw TimeoutException('no answer');
      },
      dispose: () async => steps.add('dispose'),
    );
    expect(steps, ['disconnect', 'dispose']);

    steps.clear();
    await closeRoom(disconnect: () async => steps.add('disconnect'), dispose: () async => steps.add('dispose'));
    expect(steps, ['disconnect', 'dispose']);
  });
}
