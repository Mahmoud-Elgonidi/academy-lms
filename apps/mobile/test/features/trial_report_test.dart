import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:academy_app/core/time/local_time.dart';
import 'package:academy_app/features/trial/data/trial_reports_repository.dart';
import 'package:academy_app/features/trial/domain/trial.dart';
import 'package:academy_app/features/trial/domain/trial_report.dart';
import 'package:academy_app/features/trial/presentation/reports/next_trial_cubit.dart';
import 'package:academy_app/features/trial/presentation/reports/offer_countdown.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/journey.dart';
import '../fixtures/start.dart';
import '../fixtures/trial_report.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _options({List<String> kinds = const ['complaint', 'billing', 'technical', 'other'], bool lesson = true}) => {
  'data': {
    'learner': {'id': 'c1', 'name': 'سلمى رامي'},
    'kinds': [
      for (final k in kinds) {'key': k, 'label': k},
    ],
    'lessons': [
      if (lesson) {'id': 's1', 'subject': 'التجويد', 'with': 'أ. منى', 'starts_at': null, 'status': 'completed'},
    ],
  },
};

Map<String, Object?> _refused(String why) => {
  'type': 'validation',
  'title': 'لا يمكن.',
  'status': 422,
  'errors': {
    'starts_at': [why],
  },
};

Future<TestApp> _open(
  WidgetTester tester,
  String at, {
  Map<String, Object?>? report,
  Map<String, Object?>? trials,
  bool calm = true,
  void Function(FakeApi api)? more,
}) async {
  if (calm) tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  app.api
    ..on('GET', 'family/trials', trials ?? fxTrials([fxTrial('s1', next: fxSecond())]))
    ..on('GET', 'family/trials/s1', report ?? fxReport(next: fxSecond()))
    ..on('GET', 'support/new', _options())
    ..on('GET', 'support/tickets', {'data': <Object>[]});
  more?.call(app.api);
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 2400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go(at);
  await frames(tester, 15);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
  tester.platformDispatcher.clearAccessibilityFeaturesTestValue();
}

String _where(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).first)).state.uri.toString();

Future<void> _tap(WidgetTester tester, Finder f, [int n = 12]) async {
  await tester.ensureVisible(f);
  await frames(tester, 2);
  await tester.tap(f);
  await frames(tester, n);
}

void main() {
  group('reading the API', () {
    test('a report: the teacher, their card, the plan, the price and the open offer', () {
      final r = TrialReport.fromJson(fxReport(next: fxSecond()));
      expect(r.classic, isTrue);
      expect(r.teacher, 'أ. منى');
      expect(r.card?.name, 'أ. منى إبراهيم');
      expect(r.recommendation.plan?.sessionsPerBlock, 8);
      expect(r.recommendation.price?.text, '300.00 ر.س');
      expect(r.offerState, OfferState.open);
      expect(r.offer?.percentText, '10');
      expect(r.offer?.saving?.minor, 3000);
      expect(r.openAt(DateTime.now()), isTrue);
      // Past its end by the phone's clock, it is no longer open, whatever the last answer said.
      expect(r.openAt(DateTime.now().add(const Duration(days: 3))), isFalse);
      expect(r.clockMoments, [r.offer!.endsAt!.utc]);
      expect(r.next?.second, isTrue);
      expect(r.next?.until?.label, contains('نوفمبر'));
    });

    test("a child's look: no money, no offer, no booking", () {
      final r = TrialReport.fromJson(fxReport(stage: 'kids'));
      expect(r.classic, isFalse);
      expect(r.kids, isTrue);
      expect(r.offer, isNull);
      expect(r.next, isNull);
      expect(r.recommendation.price, isNull);
      expect(r.openAt(DateTime.now()), isFalse);
    });

    test('a course begun reads as started; a withdrawn plan has no plan', () {
      expect(TrialReport.fromJson(fxReport(course: 'active')).started, isTrue);
      final w = TrialReport.fromJson(fxReport(withdrawn: true));
      expect(w.recommendation.plan, isNull);
      expect(w.recommendation.withdrawn, isTrue);
    });

    test('the list: how each went, and the home speaks only of a recent one with something to do', () {
      final list = FamilyTrials.fromJson(
        fxTrials([
          fxTrial('a', status: 'booked', ready: false, ago: const Duration(days: -2)),
          fxTrial(
            'b',
            status: 'missed',
            outcome: 'teacher_absent',
            ready: false,
            next: {'kind': 'rebook', 'available': true, 'why_not': null},
          ),
          fxTrial('c'),
        ]),
      );
      expect(list.trials.map((t) => t.status), [TrialStatus.booked, TrialStatus.missed, TrialStatus.attended]);
      expect(list.trials[1].notTheirs, isTrue);
      expect(list.headline(DateTime.now())?.id, 'b');
      // An old report no longer comes to the home.
      final old = FamilyTrials.fromJson(fxTrials([fxTrial('c', ago: const Duration(days: 40))]));
      expect(old.headline(DateTime.now()), isNull);
    });

    test('a notification about a trial opens it in each look', () {
      expect(targetOf('family_trial', 's1', Area.family), '/family/me/trials/s1');
      expect(targetOf('family_trial', null, Area.family), '/family/me/trials');
      expect(targetOf('family_trial', 's1', Area.teen), '/teen/more/trials/s1');
      expect(targetOf('family_trial', 's1', Area.kids), '/kids/more/trials/s1');
      expect(opensOverTabs('/family/me/trials/s1'), isFalse);
    });

    test('time left in whole days, hours, minutes and seconds', () {
      expect(timeLeft(const Duration(days: 2, hours: 3, minutes: 4, seconds: 5)), (days: 2, hours: 3, minutes: 4, seconds: 5));
      expect(timeLeft(const Duration(seconds: -5)), (days: 0, hours: 0, minutes: 0, seconds: 0));
    });
  });

  group('NextTrialCubit', () {
    late TestApp app;
    late NextTrialCubit cubit;
    setUp(() async {
      app = await TestApp.create(accounts: [guardian]);
      await app.session.start();
      cubit = NextTrialCubit(TrialReportsRepository(app.services.api), 's1');
    });
    tearDown(() => cubit.close());

    test('loads the times and books the chosen one once', () async {
      app.api
        ..on('GET', 'family/trials/s1/slots', fxSlots())
        ..on('POST', 'family/trials/s1/book', {
          'data': {
            'id': 's2',
            'starts_at': time('2099-11-07T15:00:00Z', '2099-11-07T18:00:00+03:00', label: 'السبت'),
            'subject': 'التجويد',
            'teacher': 'أ. هدى',
            'minutes': 30,
          },
        }, status: 201);
      await cubit.load();
      expect(cubit.state.days.length, 2);
      expect(await cubit.book(), isFalse); // nothing chosen yet
      cubit.choose(cubit.state.slots!.first);
      expect(await cubit.book(), isTrue);
      expect(cubit.state.booked?.teacher, 'أ. هدى');
      final sent = app.api.sent('POST', 'family/trials/s1/book').single;
      expect(sent.headers['Idempotency-Key'], isNotNull);
      expect((sent.data as Map)['starts_at'], '2099-11-07T15:00:00.000Z');
    });

    test("a refusal for the times is the API's own sentence, with no times", () async {
      app.api.on('GET', 'family/trials/s1/slots', _refused('مضى أكثر من أسبوعين.'), status: 422);
      await cubit.load();
      expect(cubit.state.refusal, 'مضى أكثر من أسبوعين.');
      expect(cubit.state.problem, isNull);
    });

    test('a time taken meanwhile: fresh times with the words over them', () async {
      var taken = false;
      app.api
        ..onWith('GET', 'family/trials/s1/slots', (_) {
          final all = (fxSlots()['data']! as List).cast<Map<String, Object?>>();
          return (200, {'data': taken ? all.skip(1).toList() : all});
        })
        ..onWith('POST', 'family/trials/s1/book', (_) {
          taken = true;
          return (422, _refused('حُجز هذا الموعد للتو.'));
        });
      await cubit.load();
      cubit.choose(cubit.state.slots!.first);
      expect(await cubit.book(), isFalse);
      expect(cubit.state.note, 'حُجز هذا الموعد للتو.');
      expect(cubit.state.chosen, isNull);
      expect(cubit.state.slots!.length, 2);
      expect(cubit.state.refusal, isNull);
    });

    test('refused with the time still there: about the learner, said instead of the times', () async {
      app.api
        ..on('GET', 'family/trials/s1/slots', fxSlots())
        ..on('POST', 'family/trials/s1/book', _refused('لهذا الطالب حصة محجوزة.'), status: 422);
      await cubit.load();
      cubit.choose(cubit.state.slots!.first);
      await cubit.book();
      expect(cubit.state.refusal, 'لهذا الطالب حصة محجوزة.');
    });

    test('the network or the server: nothing decided, the choice kept to try again', () async {
      app.api
        ..on('GET', 'family/trials/s1/slots', fxSlots())
        ..on('POST', 'family/trials/s1/book', {'type': 'server', 'title': 'خطأ.', 'status': 500}, status: 500);
      await cubit.load();
      cubit.choose(cubit.state.slots!.first);
      expect(await cubit.book(), isFalse);
      expect(cubit.state.bookProblem?.status, 500);
      expect(cubit.state.chosen, isNotNull);
    });

    test('the times could not be read', () async {
      app.api.on('GET', 'family/trials/s1/slots', {'type': 'server', 'title': 'خطأ.', 'status': 500}, status: 500);
      await cubit.load();
      expect(cubit.state.problem?.status, 500);
    });
  });

  group('the report', () {
    testWidgets('open offer: the teacher, the level, their words, the plan in plain words, the price after it and the time left', (
      tester,
    ) async {
      await _open(tester, '/family/me/trials/s1');
      expect(find.text('تقرير الحصة التجريبية'), findsOneWidget);
      expect(find.text('التجويد · جنى'), findsOneWidget);
      expect(find.text('أ. منى إبراهيم'), findsOneWidget);
      expect(find.text('المستوى الأول: أحكام النون الساكنة'), findsOneWidget);
      expect(find.text('تقرأ بصوت واضح وثقة، وتحفظ بسرعة.'), findsOneWidget);
      // Lines the teacher wrote come as a line each.
      expect(find.text('المدود'), findsOneWidget);
      expect(find.text('— أ. منى إبراهيم'), findsOneWidget);
      expect(find.text('باقة التجويد الأساسية'), findsOneWidget);
      expect(find.text('حصتان في الأسبوع'), findsOneWidget);
      expect(find.text('8 حصص خلال 30 يومًا'), findsOneWidget);
      expect(find.text('خصم 10٪ لكم'), findsOneWidget);
      expect(find.text('270.00 ر.س'), findsOneWidget);
      expect(find.text('بدلًا من 300.00 ر.س'), findsOneWidget);
      expect(find.text('توفّرون 30.00 ر.س'), findsOneWidget);
      // Less motion: the time left stands still, without seconds.
      expect(find.byKey(const Key('offer-countdown')), findsOneWidget);
      expect(find.text('ثوانٍ'), findsNothing);
      expect(find.text('02'), findsOneWidget); // two days
      expect(find.text('ابدأ بهذه الباقة'), findsOneWidget);
      expect(find.text('لم يناسبكم المعلّم؟'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('the time left runs down second by second, and at its end the plain price shows', (tester) async {
      await _open(tester, '/family/me/trials/s1', report: fxReport(offerIn: const Duration(seconds: 3)), calm: false);
      expect(find.text('ثوانٍ'), findsOneWidget);
      expect(find.byKey(const Key('report-offer')), findsOneWidget);
      await frames(tester, 40);
      expect(find.byKey(const Key('report-offer')), findsNothing);
      expect(find.text('انتهى وقت العرض، وهذا سعر الباقة اليوم.'), findsOneWidget);
      expect(find.text('300.00 ر.س'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('ended: the plain price, no offer', (tester) async {
      await _open(tester, '/family/me/trials/s1', report: fxReport(offerState: 'ended'));
      expect(find.byKey(const Key('report-offer')), findsNothing);
      expect(find.byKey(const Key('report-offer-ended')), findsOneWidget);
      expect(find.text('300.00 ر.س'), findsOneWidget);
      expect(find.text('لكل 8 حصص'), findsOneWidget);
      expect(find.text('بأسعار السعودية'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('used, and none', (tester) async {
      await _open(tester, '/family/me/trials/s1', report: fxReport(offerState: 'used'));
      expect(find.text('استخدمتم العرض على أول فاتورة.'), findsOneWidget);
      await _close(tester);
      await _open(tester, '/family/me/trials/s1', report: fxReport(offerState: 'none'));
      expect(find.byKey(const Key('report-offer')), findsNothing);
      expect(find.byKey(const Key('report-offer-ended')), findsNothing);
      expect(find.text('300.00 ر.س'), findsOneWidget);
      expect(find.text('ابدأ بهذه الباقة'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('started: the course in words and the way to pay, never the offer or a start', (tester) async {
      await _open(tester, '/family/me/trials/s1', report: fxReport(course: 'pending_payment'));
      expect(find.text('الدورة بانتظار الدفع، وتجدون الفاتورة في «المدفوعات».'), findsOneWidget);
      expect(find.text('ابدأ بهذه الباقة'), findsNothing);
      expect(find.byKey(const Key('report-offer')), findsNothing);
      await _tap(tester, find.text('افتح المدفوعات'));
      expect(_where(tester), '/family/money');
      await _close(tester);
    });

    testWidgets('withdrawn plan: said plainly, and asking for one that suits', (tester) async {
      await _open(tester, '/family/me/trials/s1', report: fxReport(withdrawn: true));
      expect(find.byKey(const Key('report-withdrawn')), findsOneWidget);
      expect(find.text('اختاروا باقة وابدؤوا'), findsOneWidget);
      // The offer stands for any plan, without a price.
      expect(find.text('يُطبَّق الخصم على أول فاتورة من أي باقة تختارونها.'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('no price known: the plan without one', (tester) async {
      await _open(tester, '/family/me/trials/s1', report: fxReport(price: false, offerState: 'none'));
      expect(find.byKey(const Key('report-no-price')), findsOneWidget);
      await _close(tester);
    });

    testWidgets('the family discount is bigger: said, and the offer price is that one', (tester) async {
      await _open(tester, '/family/me/trials/s1', report: fxReport(familyBigger: true));
      expect(find.byKey(const Key('report-family-bigger')), findsOneWidget);
      expect(find.textContaining('توفّرون'), findsNothing);
      await _close(tester);
    });

    testWidgets("the second trial when it cannot be booked: the API's calm sentence", (tester) async {
      await _open(
        tester,
        '/family/me/trials/s1',
        report: fxReport(next: fxSecond(available: false, why: 'حجزتم الحصة الثانية من قبل.')),
      );
      expect(find.text('حجزتم الحصة الثانية من قبل.'), findsOneWidget);
      expect(find.byKey(const Key('report-next-book')), findsNothing);
      await _close(tester);
    });

    testWidgets("a child's own look: the teacher's part in simple words, no prices, offer or booking", (tester) async {
      tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
      final app = await TestApp.create(accounts: [fxStudent('kids')]);
      fxStudentRoutes(app.api, 'kids', quietJourney(viewer: 'student', stage: 'kids'));
      app.api
        ..on('GET', 'family/trials', fxTrials([fxTrial('s1')], stage: 'kids'))
        ..on('GET', 'family/trials/s1', fxReport(stage: 'kids'));
      await app.session.start();
      await tester.binding.setSurfaceSize(const Size(420, 2000));
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 15);
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/kids/more');
      await frames(tester, 15);
      await _tap(tester, find.byKey(const Key('more-trials')));
      expect(find.text('حصصي التجريبية'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('trial-read-s1')));
      expect(find.text('ماذا قال معلّمك؟ ⭐'), findsOneWidget);
      expect(find.text('ما تُحسنه 💪'), findsOneWidget);
      expect(find.text('رسالة من معلّمك 💌'), findsOneWidget);
      expect(find.byKey(const Key('report-plan-card')), findsNothing);
      expect(find.textContaining('ر.س'), findsNothing);
      expect(find.text('لم يناسبكم المعلّم؟'), findsNothing);
      await _close(tester);
    });
  });

  group('one more trial', () {
    testWidgets('not the right teacher: the times with another teacher, booked once, then what follows', (tester) async {
      final app = await _open(tester, '/family/me/trials/s1');
      app.api
        ..on('GET', 'family/trials/s1/slots', fxSlots())
        ..on('POST', 'family/trials/s1/book', {
          'data': {
            'id': 's2',
            'starts_at': time('2099-11-07T16:00:00Z', '2099-11-07T19:00:00+03:00', label: 'السبت 7 نوفمبر · 7:00 م'),
            'subject': 'التجويد',
            'teacher': 'أ. هدى',
            'minutes': 30,
          },
        }, status: 201);
      await _tap(tester, find.byKey(const Key('report-next-book')));
      expect(_where(tester), startsWith('/family/me/trials/s1/next?kind=second'));
      expect(find.text('اختر موعد الحصة الثانية'), findsOneWidget);
      expect(find.textContaining('غير أ. منى'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('next-slot-2099-11-07T19:00:00+03:00')));
      expect(find.text('اخترت: السبت 7 نوفمبر · 7:00 م'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('next-book')), 15);
      expect(app.api.sent('POST', 'family/trials/s1/book').single.headers['Idempotency-Key'], isNotNull);
      expect(find.text('حُجزت الحصة الثانية مع معلّم آخر'), findsOneWidget);
      expect(find.text('مع أ. هدى · 30 دقيقة'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('next-done')));
      expect(_where(tester), '/family/me/trials');
      await _close(tester);
    });

    testWidgets('no times in two weeks: say so, and a way to write to the academy', (tester) async {
      await _open(tester, '/family/me/trials', more: (api) => api.on('GET', 'family/trials/s1/slots', {'data': <Object>[]}));
      await _tap(tester, find.byKey(const Key('trial-next-s1')));
      expect(find.byKey(const Key('next-no-times')), findsOneWidget);
      await _tap(tester, find.text('اكتب إلى الأكاديمية'));
      expect(_where(tester), '/family/me/help');
      await _close(tester);
    });

    testWidgets("refused when picking: the API's sentence instead of times", (tester) async {
      await _open(
        tester,
        '/family/me/trials/s1/next?kind=second',
        more: (api) => api.on('GET', 'family/trials/s1/slots', _refused('لهذا الطالب حصة تجريبية محجوزة.'), status: 422),
      );
      expect(find.byKey(const Key('next-refused')), findsOneWidget);
      expect(find.text('لهذا الطالب حصة تجريبية محجوزة.'), findsOneWidget);
      expect(find.byKey(const Key('next-book')), findsNothing);
      await _close(tester);
    });
  });

  group('starting', () {
    testWidgets('start with this plan: the start flow opens on the plan, the recommended one and the trial teacher chosen', (tester) async {
      final app = await _open(tester, '/family/me/trials/s1', more: (api) => startRoutes(api));
      await _tap(tester, find.byKey(const Key('report-start')), 20);
      expect(_where(tester), '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1');
      expect(find.text('اختاروا الباقة'), findsOneWidget);
      expect(find.text('اقترحها المعلّم'), findsOneWidget);
      final asked = app.api.sent('GET', 'family/start/options').last.queryParameters;
      expect(asked, {'learner_id': 'c1', 'subject_id': 'sub1'});
      await _close(tester);
    });

    testWidgets("why not, in the API's words, in place of the button; the academy enrolling itself: the way to message it", (tester) async {
      await _open(
        tester,
        '/family/me/trials/s1',
        report: fxReport(
          start: fxStart(available: false, reason: 'self_serve_off', why: 'تسجّل الأكاديمية الكورسات بنفسها.'),
        ),
      );
      expect(find.byKey(const Key('report-start')), findsNothing);
      expect(find.text('تسجّل الأكاديمية الكورسات بنفسها.'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('report-start-help')));
      expect(_where(tester), '/family/me/help');
      await _close(tester);
    });

    testWidgets('a course the family started and has not paid: pay it, or call it off after asking', (tester) async {
      final app = await _open(
        tester,
        '/family/me/trials/s1',
        report: fxReport(
          course: 'pending_payment',
          start: fxStart(available: false, reason: 'course_pending_own', enrollment: 'e9'),
        ),
      );
      app.api.on('POST', 'family/courses/e9/cancel', {
        'data': {
          'enrollment_id': 'e9',
          'status': 'ended',
          'lessons_cancelled': 4,
          'invoice_number': 'INV-9',
          'message': 'أُلغيت الدورة ولم يُحسب شيء.',
        },
      });
      await _tap(tester, find.byKey(const Key('report-cancel')));
      expect(find.text('أتلغون هذه الدورة؟'), findsOneWidget);
      await _tap(tester, find.text('نعم، ألغوها'), 20);
      final sent = app.api.sent('POST', 'family/courses/e9/cancel').single;
      expect(sent.headers['Idempotency-Key'], isNotNull);
      await _close(tester);
    });
  });

  group('the list and the ways in', () {
    testWidgets('each trial in words: coming, report ready, being written, missed (and not counted), with its way on', (tester) async {
      await _open(
        tester,
        '/family/me',
        trials: fxTrials([
          fxTrial('a', status: 'booked', ready: false, ago: const Duration(days: -2)),
          fxTrial('b', status: 'attended', ready: false),
          fxTrial(
            'c',
            status: 'missed',
            outcome: 'teacher_absent',
            ready: false,
            next: {'kind': 'rebook', 'available': false, 'why_not': 'يحجز فريق الأكاديمية الحصص التجريبية.'},
          ),
          fxTrial('s1', next: fxSecond()),
        ]),
      );
      await _tap(tester, find.byKey(const Key('open-trials')));
      expect(_where(tester), '/family/me/trials');
      expect(find.text('قادمة'), findsOneWidget);
      expect(find.text('التقرير قيد الكتابة'), findsOneWidget);
      expect(find.text('تغيّب المعلّم'), findsOneWidget);
      expect(find.text('لا تُحسب من حصتكم التجريبية المجانية.'), findsOneWidget);
      expect(find.text('يحجز فريق الأكاديمية الحصص التجريبية.'), findsOneWidget);
      expect(find.text('التقرير جاهز'), findsOneWidget);
      expect(find.byKey(const Key('trial-next-s1')), findsOneWidget);
      await _tap(tester, find.byKey(const Key('trial-read-s1')));
      expect(_where(tester), '/family/me/trials/s1');
      expect(find.byKey(const Key('trial-report')), findsOneWidget);
      await _close(tester);
    });

    testWidgets('no trials yet: what this place is for', (tester) async {
      await _open(tester, '/family/me/trials', trials: fxTrials([]));
      expect(find.text('لا توجد حصص تجريبية'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('a trial that did not take place opens as itself, with booking again', (tester) async {
      await _open(
        tester,
        '/family/me/trials/s1',
        trials: fxTrials([
          fxTrial(
            's1',
            status: 'missed',
            outcome: 'student_absent',
            ready: false,
            next: {'kind': 'rebook', 'available': true, 'why_not': null},
          ),
        ]),
        more: (api) => api.on('GET', 'family/trials/s1', {'type': 'not-found', 'title': 'غير موجود.', 'status': 404}, status: 404),
      );
      expect(find.byKey(const Key('trial-not-ready')), findsOneWidget);
      expect(find.text('لم يحضرها الطالب'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('trial-next-s1')));
      expect(_where(tester), '/family/me/trials/s1/next?kind=rebook&teacher=%D8%A3.+%D9%85%D9%86%D9%89');
      expect(find.text('اختر موعدًا جديدًا'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('a report not written yet and no trial found: a calm word and the way to all trials', (tester) async {
      await _open(tester, '/family/me/trials/zz', trials: fxTrials([]));
      expect(find.text('التقرير لم يجهز بعد'), findsOneWidget);
      await _tap(tester, find.text('كل الحصص التجريبية'));
      expect(_where(tester), '/family/me/trials');
      await _close(tester);
    });

    testWidgets("the home leads to a fresh trial's report", (tester) async {
      await _open(tester, '/family/home', trials: fxTrials([fxTrial('s1')]));
      expect(find.byKey(const Key('home-trial')), findsOneWidget);
      expect(find.text('تقرير الحصة التجريبية جاهز'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('home-trial')));
      expect(_where(tester), '/family/me/trials/s1');
      await _close(tester);
    });
  });

  test('a booked time from the API reads as written', () {
    final b = NextTrialBooked.fromJson({
      'data': {
        'id': 's2',
        'starts_at': time('2099-11-07T16:00:00Z', '2099-11-07T19:00:00+03:00', label: 'x'),
        'subject': 'ت',
        'teacher': '',
        'minutes': 0,
      },
    });
    expect(b.teacher, isNull);
    expect(b.minutes, isNull);
    expect(b.startsAt, isA<LocalTime>());
    expect(TrialSlot(b.startsAt!).day, '2099-11-07');
  });
}
