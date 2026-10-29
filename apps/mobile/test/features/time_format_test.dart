import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/time/clock_style.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Future<TestApp> _open(WidgetTester tester, {String? timeFormat}) async {
  latinDigitsInDates();
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  app.api
    ..on('GET', 'me', me(timeFormat: timeFormat))
    ..onWith('PATCH', 'me', (r) {
      final choice = (r.data as Map<String, dynamic>)['time_format'] as String?;
      return (200, {'data': meJson(timeFormat: choice)});
    });
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  return app;
}

Future<void> _go(WidgetTester tester, String location) async {
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go(location);
  await frames(tester, 12);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

bool _use24(WidgetTester tester) => MediaQuery.of(tester.element(find.byType(Scaffold).first)).alwaysUse24HourFormat;

void main() {
  testWidgets('the clock setting sends time_format and writes every clock that way at once', (tester) async {
    final app = await _open(tester);

    // 12 hours, the academy's way, until the person chooses.
    await _go(tester, '/family/lessons');
    expect(find.text('7:00'), findsOneWidget);
    expect(find.text('م · 45 د'), findsOneWidget);
    expect(_use24(tester), isFalse);

    await _go(tester, '/family/me/settings');
    expect(find.text('شكل الساعة'), findsOneWidget);
    expect(find.text('مثل الأكاديمية (12)'), findsOneWidget);
    expect(find.text('تظهر المواعيد هكذا: 5:00 م'), findsOneWidget);

    final loads = app.api.sent('GET', 'family/lessons').length;
    await tester.tap(find.text('24 ساعة'));
    await frames(tester, 6);
    expect(app.api.sent('PATCH', 'me').single.data, {'time_format': 'h24'});
    expect(find.text('تظهر المواعيد هكذا: 17:00'), findsOneWidget);
    expect(ClockStyle.format, TimeFormat.h24);
    expect(_use24(tester), isTrue, reason: "Flutter's time pickers write 24 hours too");
    // Every open screen reloads (the lessons kept behind their tab too), so the API's own labels follow.
    expect(app.api.sent('GET', 'family/lessons').length, greaterThan(loads));

    await _go(tester, '/family/lessons');
    expect(find.text('19:00'), findsOneWidget);
    expect(find.text('45 د'), findsOneWidget, reason: 'no day\'s half on a 24-hour clock');

    // Back to the academy's way: null is sent.
    await _go(tester, '/family/me/settings');
    await tester.tap(find.text('مثل الأكاديمية (12)'));
    await frames(tester, 6);
    expect(app.api.sent('PATCH', 'me').last.data, {'time_format': null});
    expect(find.text('تظهر المواعيد هكذا: 5:00 م'), findsOneWidget);
    expect(ClockStyle.format, TimeFormat.h12);

    await _close(tester);
  });

  testWidgets('a person on 24 hours sees 19:00 from the first screen', (tester) async {
    await _open(tester, timeFormat: 'h24');
    await _go(tester, '/family/lessons');
    expect(find.text('19:00'), findsOneWidget);
    expect(find.text('7:00'), findsNothing);
    expect(_use24(tester), isTrue);

    await _go(tester, '/family/me/settings');
    final chosen = tester.widget<SegmentedButton<String>>(find.byKey(const Key('time-format')));
    expect(chosen.selected, {'h24'});
    await _close(tester);
  });
}
