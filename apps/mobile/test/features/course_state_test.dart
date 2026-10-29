import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:academy_app/core/time/course_days.dart';
import 'package:academy_app/features/billing/domain/billing.dart';
import 'package:academy_app/features/journey/domain/journey.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// A course paused or ended (docs/architecture/02, section 3, "إيقاف الكورس
/// وإنهاؤه"): the money page and the journey say until when, as the
/// academy's calendar day the API wrote; a child in the kids' look reads of a
/// break, and nobody can buy lessons on a course that is not on.

// A day on the academy's calendar (Cairo): its midnight there, as the API writes it.
Map<String, Object?> _day(String date, String label) =>
    time('${date}T00:00:00Z', '${date}T00:00:00+03:00', zone: 'Africa/Cairo', label: label);

const _offer = {'price': '50 ر.س.', 'price_minor': 5000, 'currency': 'SAR', 'minutes': 30, 'max': 10, 'waiting_invoice': null};

Map<String, Object?> _course(
  String id,
  String subject, {
  String status = 'active',
  Map<String, Object?>? until,
  Map<String, Object?>? ends,
}) => {
  'enrollment_id': id,
  'subject': subject,
  'teacher': 'أ. نور الهدى',
  'program': null,
  'has_curriculum': false,
  'percent': null,
  'finished': false,
  'lessons': null,
  'eta_weeks': null,
  'finish_on': null,
  'current': null,
  'status': status,
  'paused_from': until == null ? null : _day('2099-10-13', 'الثلاثاء 13 أكتوبر'),
  'paused_until': until,
  'ends_on': ends,
  'islands': <Object>[],
};

Account _kid() => const Account(
  academySlug: 'demo',
  academyNameAr: 'أكاديمية النور',
  academyNameEn: 'Noor Academy',
  userId: 'u-kids',
  userName: 'يوسف خالد',
  roles: ['student'],
  token: 'token-kids',
  stage: 'kids',
);

Future<void> _open(WidgetTester tester, TestApp app, String place) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  if (place.isNotEmpty) {
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go(place);
    await frames(tester, 12);
  }
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(seconds: 10));
  await tester.binding.setSurfaceSize(null);
}

void main() {
  test('reads a course\'s status and days from the API, and says how it stands', () {
    final paused = Balance.fromJson({
      'enrollment_id': 'e1',
      'subject': 'قرآن',
      'sessions': 4,
      'minutes': 120,
      'status': 'paused',
      'paused_from': _day('2099-10-13', ''),
      'paused_until': _day('2099-10-24', ''),
      'ends_on': null,
    });
    expect(paused.days.stand, CourseStand.paused);
    expect(paused.days.pausedUntil?.localDate, '2099-10-24');
    expect(paused.days.isOn, isFalse);

    // An older API (no status): simply on.
    expect(Balance.fromJson({'enrollment_id': 'e2', 'subject': 'عربي', 'sessions': 2, 'minutes': 60}).days.stand, CourseStand.on);
    expect(const CourseDays(status: 'active').isOn, isTrue);
    expect(CourseDays.fromJson({'status': 'active', 'ends_on': _day('2099-11-01', '')}).stand, CourseStand.ending);
    expect(
      CourseDays.fromJson({'status': 'active', 'paused_from': _day('2099-11-03', ''), 'paused_until': _day('2099-11-07', '')}).stand,
      CourseStand.pauseAhead,
    );
    expect(const CourseDays(status: 'ended').stand, CourseStand.ended);
    expect(JourneyCourse.fromJson(_course('e1', 'قرآن', status: 'paused', until: _day('2099-10-24', ''))).days.stand, CourseStand.paused);
  });

  testWidgets('the money page says a course is paused until a day, or ended, and offers extra lessons only on one that is on', (
    tester,
  ) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('GET', 'family/balance', {
      'data': [
        {
          'enrollment_id': 'e1',
          'subject': 'قرآن',
          'sessions': 4,
          'minutes': 120,
          'extra': null,
          'status': 'paused',
          'paused_from': _day('2099-10-13', ''),
          'paused_until': _day('2099-10-24', ''),
          'ends_on': null,
        },
        {
          'enrollment_id': 'e2',
          'subject': 'عربي',
          'sessions': 2,
          'minutes': 60,
          'extra': null,
          'status': 'ended',
          'paused_from': null,
          'paused_until': null,
          'ends_on': _day('2099-10-05', ''),
        },
        {'enrollment_id': 'e3', 'subject': 'تجويد', 'sessions': 6, 'minutes': 180, 'extra': _offer, 'status': 'active'},
      ],
    });
    await _open(tester, app, '/family/money');

    expect(find.textContaining('متوقف حتى '), findsOneWidget);
    expect(find.textContaining('أكتوبر'), findsWidgets);
    expect(find.text('انتهى، والدقائق المتبقية ما زالت في الرصيد'), findsOneWidget);
    // Extra lessons: only on the course that is on.
    expect(find.byKey(const Key('extra-e1')), findsNothing);
    expect(find.byKey(const Key('extra-e2')), findsNothing);
    expect(find.byKey(const Key('extra-e3')), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a child in the kids\' look reads that their lessons are on a break, in a child\'s words', (tester) async {
    final app = await TestApp.create(accounts: [_kid()]);
    familyRoutes(app.api);
    final journey = quietJourney(viewer: 'self', stage: 'kids');
    (journey['data']! as Map<String, Object?>)['courses'] = [_course('e1', 'قرآن', status: 'paused', until: _day('2099-10-24', ''))];
    app.api
      ..on('GET', 'family/learners', {
        'data': [
          {'id': 'u-kids', 'name': 'يوسف خالد', 'is_self': true, 'stage': 'kids'},
        ],
        'viewer_stage': 'kids',
      })
      ..on('GET', 'learning/journey/map', journey)
      ..on('POST', 'learning/cheers/seen', null, status: 204);
    await _open(tester, app, '');

    expect(find.textContaining('في إجازة حتى '), findsOneWidget);
    expect(find.textContaining('موقوف'), findsNothing);
    await _close(tester);
  });

  testWidgets('a guardian\'s journey says until when a course is paused', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    final journey = quietJourney();
    (journey['data']! as Map<String, Object?>)['courses'] = [_course('e1', 'قرآن', status: 'paused', until: _day('2099-10-24', ''))];
    app.api.on('GET', 'learning/journey/map', journey);
    await _open(tester, app, '/family/journey');

    expect(find.byKey(const Key('course-state')), findsOneWidget);
    expect(find.textContaining('متوقف حتى '), findsOneWidget);
    await _close(tester);
  });
}
