import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/time/formats.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/journey.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// The profile as `GET me` gives it, on [zone] (the phone is on Riyadh in tests).
Map<String, Object?> _me({String zone = 'Africa/Cairo', String? shown, bool mismatch = false}) {
  const labels = {'Africa/Cairo': 'القاهرة (UTC+2)', 'Asia/Riyadh': 'الرياض (UTC+3)', 'Asia/Dubai': 'دبي (UTC+4)'};
  final display = shown ?? zone;
  return {
    ...meJson(mismatch: mismatch),
    'timezone': zone,
    'display_timezone': display,
    'timezone_label': labels[display],
    'academy': {
      'id': 'a1',
      'slug': 'demo',
      'name_ar': 'أكاديمية النور',
      'name_en': 'Noor Academy',
      'default_timezone': 'Asia/Riyadh',
      'timezone_label': 'الرياض (UTC+3)',
      'time_format': 'h12',
    },
  };
}

final _zones = {
  'data': [
    {'id': 'Africa/Cairo', 'city': 'القاهرة', 'offset': 'UTC+2', 'label': 'القاهرة (UTC+2)', 'region': 'أفريقيا'},
    {'id': 'Asia/Riyadh', 'city': 'الرياض', 'offset': 'UTC+3', 'label': 'الرياض (UTC+3)', 'region': 'آسيا'},
    {'id': 'Asia/Dubai', 'city': 'دبي', 'offset': 'UTC+4', 'label': 'دبي (UTC+4)', 'region': 'آسيا'},
    {'id': 'Europe/London', 'city': 'لندن', 'offset': 'UTC', 'label': 'لندن (UTC)', 'region': 'أوروبا'},
  ],
};

/// A guardian whose profile is on [zone]; PATCH me moves it, and the next
/// `GET me` answers on the zone asked in (`X-Timezone`).
Future<TestApp> _open(
  WidgetTester tester, {
  String zone = 'Africa/Cairo',
  String? shown,
  bool mismatch = false,
  String at = '/family/lessons',
}) async {
  latinDigitsInDates();
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  var profile = zone;
  var first = true;
  app.api
    ..onWith('GET', 'me', (r) {
      final asked = r.headers['X-Timezone'] as String? ?? profile;
      final answer = first
          ? _me(zone: profile, shown: shown, mismatch: mismatch)
          : _me(zone: profile, shown: asked, mismatch: asked != profile);
      first = false;
      return (200, {'data': answer});
    })
    ..onWith('PATCH', 'me', (r) {
      profile = (r.data as Map<String, dynamic>)['timezone'] as String? ?? profile;
      return (200, {'data': _me(zone: profile)});
    })
    ..on('GET', 'timezones', _zones);
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1000));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go(at);
  await frames(tester, 12);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

/// The time now on a zone [hours] from UTC, written as the app writes it (this minute or the next).
List<String> _nowOn(int hours) => [
  for (final m in [0, 1])
    () {
      final t = DateTime.now().toUtc().add(Duration(hours: hours, minutes: m));
      return wallClock('${t.hour}:${t.minute.toString().padLeft(2, '0')}', 'ar');
    }(),
];

void main() {
  testWidgets('the zone chip says the city times are on and its time now, with a dot when the phone differs', (tester) async {
    await _open(tester, shown: 'Africa/Cairo', mismatch: true);

    final chip = find.byKey(const Key('zone-chip'));
    expect(chip, findsWidgets);
    expect(find.descendant(of: chip.first, matching: find.text('القاهرة')), findsOneWidget);
    final time = tester.widget<Text>(find.byKey(const Key('zone-chip-time')).first).data;
    expect(_nowOn(2), contains(time), reason: "the API's offset, on the phone's clock");
    expect(find.byKey(const Key('zone-chip-dot')), findsWidgets);
    await _close(tester);
  });

  testWidgets('no dot when the phone and the account agree; the phone\'s own clock is used for its zone', (tester) async {
    await _open(tester, zone: 'Asia/Riyadh');
    expect(find.byKey(const Key('zone-chip')), findsWidgets);
    expect(find.byKey(const Key('zone-chip-dot')), findsNothing);
    final now = DateTime.now();
    final time = tester.widget<Text>(find.byKey(const Key('zone-chip-time')).first).data;
    expect([
      for (final m in [0, 1])
        wallClock('${now.add(Duration(minutes: m)).hour}:${now.add(Duration(minutes: m)).minute.toString().padLeft(2, '0')}', 'ar'),
    ], contains(time));
    await _close(tester);
  });

  testWidgets('the kids\' look has no zone chip; middle school has it', (tester) async {
    for (final (stage, at) in [('kids', '/kids/lessons'), ('middle', '/middle/lessons')]) {
      final app = await TestApp.create(accounts: [fxStudent(stage)]);
      fxStudentRoutes(app.api, stage, fxJourney(stage: stage));
      await app.session.start();
      await tester.binding.setSurfaceSize(const Size(420, 1000));
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 15);
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go(at);
      await frames(tester, 12);
      expect(find.byKey(const Key('zone-chip')), stage == 'kids' ? findsNothing : findsWidgets, reason: stage);
      await _close(tester);
    }
  });

  testWidgets("the card shows the account's, the academy's and the phone's zones, and takes the phone's", (tester) async {
    final app = await _open(tester, shown: 'Africa/Cairo', mismatch: true);

    await tester.tap(find.byKey(const Key('zone-chip')).first);
    await frames(tester, 8);
    expect(find.text('المواعيد في التطبيق بتوقيت القاهرة.'), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('zone-yours')), matching: find.text('القاهرة (UTC+2)')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('zone-academy')), matching: find.text('الرياض (UTC+3)')), findsOneWidget);
    expect(find.byKey(const Key('zone-device')), findsOneWidget);
    for (final t in _nowOn(2)) {
      if (find.descendant(of: find.byKey(const Key('zone-yours')), matching: find.text(t)).evaluate().isNotEmpty) break;
      if (t == _nowOn(2).last) fail('the account\'s time is not shown');
    }

    await tester.tap(find.byKey(const Key('zone-use-device')));
    await frames(tester, 8);
    expect(app.api.sent('PATCH', 'me').single.data, {'timezone': 'Asia/Riyadh'});
    expect(app.session.state.clock, isNull);
    expect(app.session.state.me?.timezone, 'Asia/Riyadh');
    // The account is on the phone's zone now: no phone line, no dot.
    expect(find.byKey(const Key('zone-device')), findsNothing);
    expect(find.byKey(const Key('zone-chip-dot')), findsNothing);
    await _close(tester);
  });

  testWidgets('"Change my time zone" lists the zones by region, searches, and saves the one chosen', (tester) async {
    final app = await _open(tester, zone: 'Asia/Riyadh');

    await tester.tap(find.byKey(const Key('zone-chip')).first);
    await frames(tester, 8);
    await tester.tap(find.byKey(const Key('zone-change')));
    await frames(tester, 8);
    expect(find.text('اختر توقيتك'), findsOneWidget);
    expect(find.text('أفريقيا'), findsOneWidget);
    expect(find.text('آسيا'), findsOneWidget);
    expect(find.text('UTC+4'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('zone-search')), 'دبي');
    await frames(tester, 4);
    expect(find.byKey(const Key('zone-Africa/Cairo')), findsNothing);
    await tester.tap(find.byKey(const Key('zone-Asia/Dubai')));
    await frames(tester, 10);

    expect(app.api.sent('PATCH', 'me').single.data, {'timezone': 'Asia/Dubai'});
    // This phone asks for times on the zone chosen, though it sits on Riyadh.
    expect(app.session.state.clock, 'Asia/Dubai');
    expect(app.api.sent('GET', 'me').last.headers['X-Timezone'], 'Asia/Dubai');
    expect(find.text('صارت مواعيدك بتوقيت دبي.'), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('zone-yours')), matching: find.text('دبي (UTC+4)')), findsOneWidget);
    await _close(tester);
  });

  testWidgets('the zone in settings says what it governs and can be changed', (tester) async {
    final app = await _open(tester, zone: 'Asia/Riyadh', at: '/family/me/settings');

    final row = find.byKey(const Key('zone-setting'));
    expect(find.descendant(of: row, matching: find.text('الرياض (UTC+3)')), findsOneWidget);
    expect(find.descendant(of: row, matching: find.text('تظهر مواعيد حصصك وتذكيراتها بهذا التوقيت، واضغط لتغييره.')), findsOneWidget);

    await tester.tap(row);
    await frames(tester, 8);
    await tester.tap(find.byKey(const Key('zone-Africa/Cairo')));
    await frames(tester, 10);
    expect(app.api.sent('PATCH', 'me').single.data, {'timezone': 'Africa/Cairo'});
    expect(find.descendant(of: row, matching: find.text('القاهرة (UTC+2)')), findsOneWidget);
    await _close(tester);
  });
}
