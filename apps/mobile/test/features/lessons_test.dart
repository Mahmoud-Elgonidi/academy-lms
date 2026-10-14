import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// A lesson a substitute teaches while the family's own teacher is on leave.
Map<String, Object?> _covered(String id) => {
  ...lesson(id),
  'with': 'أ. هبة سمير',
  'substitute_for': 'أ. منى إبراهيم',
  'can_refuse_substitute': true,
};

/// A lesson that took place, for the past list.
Map<String, Object?> _held(String id, {bool canDispute = true}) => {
  ...lesson(id, canCancel: false, status: 'completed'),
  'status_label': 'اتعملت',
  'can_dispute': canDispute,
  'dispute_until': canDispute ? time('2099-10-13T16:45:00Z', '2099-10-13T19:45:00+03:00') : null,
  'dispute_reasons': canDispute
      ? [
          {'key': 'cut_short', 'label': 'الحصة خلصت بدري'},
        ]
      : <Object>[],
};

Map<String, Object?> _list(List<Map<String, Object?>> lessons) => {
  'learner': {'id': 'c1', 'name': 'سلمى رامي'},
  'data': lessons,
  'notices': <String>[],
};

Future<void> _open(WidgetTester tester, TestApp app) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1000));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
}

/// Opens a lesson's own sheet from its row.
Future<void> _sheet(WidgetTester tester, String id) async {
  await tester.tap(find.byKey(Key('lesson-$id')));
  await frames(tester, 8);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  testWidgets('a guardian turns down a substitute after reading what happens, once, with an idempotency key', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    var refused = false;
    app.api
      ..onWith(
        'GET',
        'family/lessons',
        (_) => (200, _list([refused ? lesson('l1', canCancel: false, status: 'cancelled') : _covered('l1')])),
      )
      ..onWith('POST', 'lessons/l1/refuse-substitute', (_) {
        refused = true;
        return (200, {'makeup_days': 30});
      });
    await _open(tester, app);
    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);

    // The row says who covers; its sheet says why and offers to turn it down.
    expect(find.text('بديل بدل أ. منى إبراهيم'), findsOneWidget);
    await _sheet(tester, 'l1');
    expect(find.text('مدرّس بديل: أ. منى إبراهيم في إجازة'), findsOneWidget);
    await tester.tap(find.text('مش موافقين على البديل'));
    await frames(tester, 6);
    expect(find.text('الحصة هتتلغي على حساب الأكاديمية، ودقايقها هترجعلك حصة تعويض تحجزها بعدين.'), findsOneWidget);

    // Keeping it sends nothing.
    await tester.tap(find.text('لأ، خليها'));
    await frames(tester, 6);
    expect(app.api.sent('POST', 'lessons/l1/refuse-substitute'), isEmpty);

    await _sheet(tester, 'l1');
    await tester.tap(find.text('مش موافقين على البديل'));
    await frames(tester, 6);
    await tester.tap(find.text('أيوه، ألغيها وعوّضوني'));
    await frames(tester, 10);
    final sent = app.api.sent('POST', 'lessons/l1/refuse-substitute').single;
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(find.text('اتلغت. عندك 30 يوم تحجز فيهم حصة التعويض.'), findsOneWidget);
    // The list comes back without the choice.
    expect(find.text('بديل بدل أ. منى إبراهيم'), findsNothing);
    await _sheet(tester, 'l1');
    expect(find.text('مش موافقين على البديل'), findsNothing);
    await _close(tester);
  });

  testWidgets('a substitute the academy can no longer change: the API\'s words, and the choice stays', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..on('GET', 'family/lessons', _list([_covered('l1')]))
      ..on('POST', 'lessons/l1/refuse-substitute', {'type': 'conflict', 'title': 'الحصة دي مبقاش ينفع تتغير.', 'status': 409}, status: 409);
    await _open(tester, app);
    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);

    await _sheet(tester, 'l1');
    await tester.tap(find.text('مش موافقين على البديل'));
    await frames(tester, 6);
    await tester.tap(find.text('أيوه، ألغيها وعوّضوني'));
    await frames(tester, 10);
    expect(find.text('الحصة دي مبقاش ينفع تتغير.'), findsOneWidget);
    await _sheet(tester, 'l1');
    expect(find.text('مش موافقين على البديل'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a cancel the policy no longer allows keeps the sheet open with the API\'s words', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..on('GET', 'lessons/l1/cancellation', {
        'outcome': 'الإلغاء قبل الحصة بأقل من 12 ساعة: الحصة هتتحسب.',
        'reasons': [
          {'value': 'illness', 'label': 'تعبان'},
        ],
      })
      ..on('POST', 'lessons/l1/cancel', {
        'type': 'validation',
        'title': 'البيانات المرسلة غير صحيحة.',
        'status': 422,
        'errors': {
          'lesson': ['الحصة بدأت خلاص، مينفعش تتلغي.'],
        },
      }, status: 422);
    await _open(tester, app);
    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);

    await _sheet(tester, 'l1');
    await tester.tap(find.text('إلغاء الحصة'));
    await frames(tester, 10);
    expect(find.text('الإلغاء قبل الحصة بأقل من 12 ساعة: الحصة هتتحسب.'), findsOneWidget);
    await tester.tap(find.text('تعبان'));
    await tester.tap(find.text('أيوه، ألغيها'));
    await frames(tester, 10);
    expect(find.text('الحصة بدأت خلاص، مينفعش تتلغي.'), findsOneWidget);
    expect(find.text('الحصة اتلغت.'), findsNothing);
    // Still possible to go back, and to try again.
    final yes = tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'أيوه، ألغيها'));
    expect(yes.onPressed, isNotNull);
    await tester.tap(find.text('لأ، خليها'));
    await frames(tester, 6);
    expect(find.text('حصص سلمى'), findsOneWidget);
    expect(app.api.sent('POST', 'lessons/l1/cancel'), hasLength(1));
    await _close(tester);
  });

  testWidgets('a guardian rates a past lesson with stars, what went well and a word', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..onWith(
        'GET',
        'family/lessons',
        (r) => (200, _list([if (r.queryParameters['when'] == 'past') _held('l9', canDispute: false) else lesson('l1')])),
      )
      ..on('GET', 'lessons/l9/rating', {
        'can_rate': true,
        'why': null,
        'stars': null,
        'tags': <String>[],
        'comment': null,
        'choices': {
          'good': [
            {'value': 'patient', 'label': 'صبور'},
            {'value': 'on_time', 'label': 'في معاده'},
          ],
          'bad': [
            {'value': 'late', 'label': 'اتأخر'},
          ],
        },
      })
      ..on('PUT', 'lessons/l9/rating', {
        'can_rate': true,
        'stars': 5,
        'tags': ['patient'],
        'comment': 'ممتاز',
        'choices': <String, Object>{},
      });
    await _open(tester, app);
    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);
    await tester.tap(find.text('اللي فاتت'));
    await frames(tester, 10);

    await _sheet(tester, 'l9');
    await tester.tap(find.text('قيّم الحصة'));
    await frames(tester, 10);
    expect(find.text('الحصة كانت إزاي؟'), findsOneWidget);
    // Nothing to send before the stars.
    expect(tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'ابعت التقييم')).onPressed, isNull);

    await tester.tap(find.byTooltip('5 من 5'));
    await frames(tester, 3);
    await tester.tap(find.text('صبور'));
    await tester.enterText(find.byType(TextField).last, 'ممتاز');
    await tester.tap(find.text('ابعت التقييم'));
    await frames(tester, 10);

    expect(app.api.sent('PUT', 'lessons/l9/rating').single.data, {
      'stars': 5,
      'tags': ['patient'],
      'comment': 'ممتاز',
    });
    expect(find.text('شكرًا! وصل تقييمك.'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a lesson that can no longer be rated says why', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..on('GET', 'family/lessons', _list([_held('l9', canDispute: false)]))
      ..on('GET', 'lessons/l9/rating', {'can_rate': false, 'why': 'التقييم بيبقى خلال 14 يوم من الحصة.', 'choices': <String, Object>{}});
    await _open(tester, app);
    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);
    await tester.tap(find.text('اللي فاتت'));
    await frames(tester, 10);
    await _sheet(tester, 'l9');
    await tester.tap(find.text('قيّم الحصة'));
    await frames(tester, 10);
    expect(find.text('التقييم بيبقى خلال 14 يوم من الحصة.'), findsOneWidget);
    expect(find.text('ابعت التقييم'), findsNothing);
    expect(app.api.sent('PUT', 'lessons/l9/rating'), isEmpty);
    await _close(tester);
  });

  testWidgets('a child in the kids\' look joins and sees their lessons, but cannot cancel, refuse, dispute or rate', (tester) async {
    const kid = Account(
      academySlug: 'demo',
      academyNameAr: 'أكاديمية النور',
      academyNameEn: 'Noor Academy',
      userId: 'u-kid',
      userName: 'يوسف خالد',
      roles: ['student'],
      token: 'token-kid',
      stage: 'kids',
    );
    final app = await TestApp.create(accounts: [kid]);
    familyRoutes(app.api);
    app.api
      ..on('GET', 'family/learners', {
        'data': [
          {'id': 'u-kid', 'name': 'يوسف خالد', 'is_self': true, 'stage': 'kids'},
        ],
        'viewer_stage': 'kids',
      })
      ..on('GET', 'learning/journey/map', quietJourney(viewer: 'student', stage: 'kids'))
      // Even were the API to offer them, the kids' look leaves them out.
      ..onWith(
        'GET',
        'family/lessons',
        (r) => (
          200,
          _list([
            if (r.queryParameters['when'] == 'past') _held('l9') else ...[_covered('l1'), lesson('l2')],
          ]),
        ),
      );
    await _open(tester, app);
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/kids/lessons');
    await frames(tester, 12);

    expect(find.text('أ. هبة سمير'), findsOneWidget);
    expect(find.text('إلغاء الحصة'), findsNothing);
    expect(find.text('مش موافقين على البديل'), findsNothing);

    await tester.tap(find.text('اللي فاتت'));
    await frames(tester, 10);
    expect(find.text('اتعملت'), findsOneWidget);
    expect(find.text('اعترض'), findsNothing);
    expect(find.text('قيّم الحصة'), findsNothing);
    await _close(tester);
  });
}
