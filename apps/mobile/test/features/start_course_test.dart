import 'dart:async';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/billing/data/billing_repository.dart';
import 'package:academy_app/features/consent/data/consent_repository.dart';
import 'package:academy_app/features/start/data/start_repository.dart';
import 'package:academy_app/features/start/domain/start.dart';
import 'package:academy_app/features/start/presentation/pay/pay_cubit.dart';
import 'package:academy_app/features/start/presentation/start_cubit.dart';
import 'package:academy_app/features/start/presentation/steps/times_step.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/journey.dart';
import '../fixtures/start.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _consents() => {
  'age': 13,
  'children': [
    {
      'student': {'id': 'c1', 'name': 'جنى'},
      'state': 'waiting',
      'granted_at': null,
      'by': null,
      'can_withdraw': false,
      'text': {
        'version': 3,
        'title': 'موافقة وليّ الأمر',
        'intro': 'نحتاج إلى موافقتكم قبل أن تدرس جنى.',
        'sections': [
          {'title': 'ما نحفظه', 'body': 'الاسم والحصص والتقارير.'},
        ],
        'agree': 'أوافق على ما سبق.',
      },
    },
  ],
};

Future<(TestApp, StartCubit)> _cubit({void Function(FakeApi api)? more, String? subject, String? plan, String? teacher}) async {
  final app = await TestApp.create(accounts: [guardian]);
  await app.session.start();
  startRoutes(app.api);
  more?.call(app.api);
  final cubit = StartCubit(
    StartRepository(app.services.api),
    ConsentRepository(app.services.api),
    initialLearner: subject == null ? null : 'c1',
    initialSubject: subject,
    initialPlan: plan,
    initialTeacher: teacher,
  );
  return (app, cubit);
}

/// From the report's button: the plan step with the recommended plan and the trial teacher, then the times suggested.
Future<(TestApp, StartCubit)> _atReview({void Function(FakeApi api)? more}) async {
  final (app, cubit) = await _cubit(subject: 'sub1', plan: 'p1', teacher: 't1', more: more);
  await cubit.load();
  await cubit.planNext();
  await cubit.teacherNext();
  cubit.timesNext();
  return (app, cubit);
}

Future<TestApp> _open(WidgetTester tester, String at, {void Function(FakeApi api)? more, Size size = const Size(420, 1800)}) async {
  tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  startRoutes(app.api);
  more?.call(app.api);
  await app.session.start();
  await tester.binding.setSurfaceSize(size);
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  unawaited(GoRouter.of(tester.element(find.byType(Scaffold).first)).push(at));
  await frames(tester, 15);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
  tester.platformDispatcher.clearAccessibilityFeaturesTestValue();
}

String _where(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).last)).state.uri.toString();

Future<void> _tap(WidgetTester tester, Finder f, [int n = 12]) async {
  await tester.ensureVisible(f);
  await frames(tester, 2);
  await tester.tap(f);
  await frames(tester, n);
}

void main() {
  group('reading the API', () {
    test('options: learners, subjects with a course, plans with the first invoice, teachers and the offer', () {
      final o = StartOptions.fromJson(fxStartOptions(consentWaiting: true));
      expect(o.selfServe, isTrue);
      expect(o.learners.single.consentWaiting, isTrue);
      expect(o.subject('sub2')?.canStart, isFalse);
      expect(o.subject('sub2')?.courseStatus, 'active');
      final p = o.plan('p1')!;
      expect(p.price.minor, 30000);
      expect(p.price.currency, 'SAR');
      expect(p.perLesson?.text, '37.50 ر.س');
      expect(p.discounted, isTrue);
      expect(p.firstTotal?.minor, 27000);
      expect(p.offerApplied, isTrue);
      expect(p.renewsAt, 2);
      expect(o.plan('p2')!.group, isTrue);
      expect(o.privateTeachers.first.trialTeacher, isTrue);
      expect(o.offerOpen, isTrue);
      expect(o.offerPercentText, '10');
      expect(o.unpaidCancelHours, 24);
    });

    test('weeks a time starts without: read as they come, malformed ones left out, in date order', () {
      final slots = StartSlots.fromJson(fxStartSlots(skips: true));
      final saturday = slots.time('6-17:00')!;
      expect(saturday.skips.single.text, 'لا حصة يوم السبت 14 نوفمبر: إجازة الأكاديمية');
      expect(saturday.skips.single.holiday, isTrue);
      expect(saturday.skips.single.at?.label, 'السبت 14 نوفمبر · 17:00');
      expect(slots.time('6-18:00')!.skips, isEmpty);
      expect(slots.time('1-17:00')!.skips.single.reason, 'teacher_leave');
      // Untyped in the OpenAPI document: anything without a day and a sentence is left out.
      final odd = WeeklyTime.fromJson({
        'key': '6-17:00',
        'start': '17:00',
        'skips': [
          null,
          3,
          {'date': '2099-11-14'},
          {'text': 'بلا يوم'},
          {'date': '2099-11-14', 'text': '  '},
          fxSkip(date: '2099-11-21'),
          fxSkip(),
          fxSkip(),
        ],
      });
      expect(odd.skips.map((w) => w.date), ['2099-11-14', '2099-11-21']);
      expect(WeeklyTime.fromJson({'key': 'k', 'start': '17:00', 'skips': 'nope'}).skips, isEmpty);
      expect(StartSlots.fromJson(fxStartSlots()).time('6-17:00')!.skips, isEmpty);

      final started = StartedCourse.fromJson(fxStarted(skips: true));
      expect(started.skipped.map((w) => w.reason), ['holiday', 'teacher_leave']);
      expect(started.skippedDates, ['2099-11-14', '2099-11-23']);
      expect(StartedCourse.fromJson(fxStarted()).skipped, isEmpty);
    });

    test('a refusal reads as what the screen does about it', () {
      expect(StartReason.of('start-refused', 'time_taken'), StartReason.timeTaken);
      expect(StartReason.of('guardian-consent-needed', null), StartReason.consent);
      expect(StartReason.of('start-refused', 'course_paused'), StartReason.courseExists);
      expect(StartReason.of('start-refused', 'academy_full'), StartReason.closed);
      expect(StartReason.of('start-refused', 'group_full'), StartReason.groupGone);
      expect(StartReason.of('start-refused', 'something_new'), StartReason.other);
    });

    test('the invoice: lines, the offer as a discount, VAT added, the personal coupon', () {
      final i = PayerInvoice.fromJson(fxInvoice());
      expect(i.lines.last.isDiscount, isTrue);
      expect(i.hasVat, isTrue);
      expect(i.vatRateText, '15');
      expect(i.total.minor, 31050);
      expect(i.couponPersonal, isTrue);
      expect(i.isPaid, isFalse);
      expect(PayerInvoice.fromJson(fxInvoice(paid: true)).isPaid, isTrue);
    });
  });

  group('StartCubit', () {
    test('one learner: straight to the subject, the taken one says why', () async {
      final (_, cubit) = await _cubit();
      await cubit.load();
      expect(cubit.state.step, StartStep.subject);
      expect(cubit.state.learnerId, 'c1');
      expect(cubit.state.path, [StartStep.subject, StartStep.plan, StartStep.teacher, StartStep.times, StartStep.review]);
      await cubit.close();
    });

    test('from the report: the plan step, the recommended plan and (next) the trial teacher chosen', () async {
      final (app, cubit) = await _cubit(subject: 'sub1', plan: 'p1', teacher: 't1');
      await cubit.load();
      expect(cubit.state.step, StartStep.plan);
      expect(cubit.state.planId, 'p1');
      expect(cubit.state.recommendedPlanId, 'p1');
      expect(app.api.sent('GET', 'family/start/options').single.queryParameters, {'learner_id': 'c1', 'subject_id': 'sub1'});
      await cubit.planNext();
      expect(cubit.state.step, StartStep.teacher);
      expect(cubit.state.teacherId, 't1');
      await cubit.close();
    });

    test('two children: who first, and choosing one reads their options', () async {
      final (app, cubit) = await _cubit(more: (api) => api.on('GET', 'family/start/options', fxStartOptions(twoLearners: true)));
      await cubit.load();
      expect(cubit.state.step, StartStep.learner);
      await cubit.pickLearner('c2');
      expect(cubit.state.step, StartStep.subject);
      expect(app.api.sent('GET', 'family/start/options').last.queryParameters['learner_id'], 'c2');
      expect(cubit.back(), isTrue);
      expect(cubit.state.step, StartStep.learner);
      expect(cubit.back(), isFalse);
      await cubit.close();
    });

    test('the academy enrols itself: its words, nothing else', () async {
      final (_, cubit) = await _cubit(more: (api) => api.on('GET', 'family/start/options', fxStartOptions(selfServe: false)));
      await cubit.load();
      expect(cubit.state.step, StartStep.off);
      expect(cubit.state.options?.whyOff, startsWith('تسجّل الأكاديمية'));
      await cubit.close();
    });

    test("a child's own look: a calm refusal, never the flow", () async {
      final (_, cubit) = await _cubit(
        more: (api) => api.on(
          'GET',
          'family/start/options',
          fxRefused('family_only', 'يبدأ وليّ الأمر الكورس من حسابه. اطلبوا منه ذلك.', status: 403),
          status: 403,
        ),
      );
      await cubit.load();
      expect(cubit.state.step, StartStep.blocked);
      expect(cubit.state.block?.reason, StartReason.closed);
      expect(cubit.state.block?.code, 'family_only');
      await cubit.close();
    });

    test('weekly times: the suggested chosen, one a day, never more than the plan has', () async {
      final (_, cubit) = await _cubit(subject: 'sub1', plan: 'p1');
      await cubit.load();
      await cubit.planNext();
      await cubit.teacherNext();
      expect(cubit.state.step, StartStep.times);
      expect(cubit.state.picked, ['6-17:00', '1-17:00']);
      expect(cubit.state.timesDone, isTrue);
      // Another time on Saturday takes the first's place.
      cubit.toggleTime('6-18:00');
      expect(cubit.state.picked, ['1-17:00', '6-18:00']);
      // A third day is one too many: said, nothing changes.
      cubit.toggleTime('3-19:00');
      expect(cubit.state.dayFull, isTrue);
      expect(cubit.state.picked.length, 2);
      cubit.toggleTime('1-17:00');
      expect(cubit.state.timesDone, isFalse);
      cubit.toggleTime('3-19:00');
      expect(cubit.state.timesDone, isTrue);
      expect(cubit.state.firstPicked?.key, '6-18:00');
      await cubit.close();
    });

    test('a group plan: the open groups, the only one chosen, then the summary', () async {
      final (_, cubit) = await _cubit(subject: 'sub1', plan: 'p2', more: (api) => api.on('GET', 'family/start/slots', fxGroupSlots()));
      await cubit.load();
      await cubit.planNext();
      expect(cubit.state.step, StartStep.group);
      expect(cubit.state.groupId, 'g1');
      expect(cubit.state.group?.toStart, 1);
      cubit.groupNext();
      expect(cubit.state.step, StartStep.review);
      expect(cubit.state.path, [StartStep.subject, StartStep.plan, StartStep.group, StartStep.review]);
      await cubit.close();
    });

    test('starting: the price seen, the chosen times, sent once with an Idempotency-Key', () async {
      final (app, cubit) = await _atReview();
      expect(cubit.state.step, StartStep.review);
      await cubit.confirm();
      expect(cubit.state.started?.enrollmentId, 'e1');
      expect(cubit.state.started?.invoice?.id, 'i1');
      final sent = app.api.sent('POST', 'family/courses').single;
      expect(sent.headers['Idempotency-Key'], isNotNull);
      expect(sent.data, {
        'learner_id': 'c1',
        'subject_id': 'sub1',
        'plan_id': 'p1',
        'price_minor': 30000,
        'currency': 'SAR',
        'teacher_id': 't1',
        'times': ['6-17:00', '1-17:00'],
      });
      await cubit.close();
    });

    test('a time taken meanwhile: the times again, fresh, with the others kept and the API\'s words', () async {
      final (app, cubit) = await _atReview(
        more: (api) => api.on('POST', 'family/courses', fxRefused('time_taken', 'سبقكم أحد إلى موعد اخترتموه.'), status: 409),
      );
      // Saturday 17:00 is gone now.
      app.api.on('GET', 'family/start/slots', fxStartSlots(suggested: ['6-18:00', '3-19:00']));
      await cubit.confirm();
      expect(cubit.state.step, StartStep.times);
      expect(cubit.state.note?.text, 'سبقكم أحد إلى موعد اخترتموه.');
      expect(cubit.state.picked, ['6-17:00', '1-17:00'], reason: 'both still offered in the fixture, so both kept');
      expect(app.api.sent('GET', 'family/start/slots'), hasLength(2));
      await cubit.close();
    });

    test('a time taken and gone: only the times still free stay chosen', () async {
      final (app, cubit) = await _atReview(
        more: (api) => api.on('POST', 'family/courses', fxRefused('time_taken', 'سبقكم أحد.'), status: 409),
      );
      final fresh = fxStartSlots();
      (((fresh['data']! as Map)['days'] as List).first as Map)['times'] = [fxTime('6-18:00', 6, '18:00')];
      app.api.on('GET', 'family/start/slots', fresh);
      await cubit.confirm();
      expect(cubit.state.picked, ['1-17:00']);
      expect(cubit.state.timesDone, isFalse);
      await cubit.close();
    });

    test('the price changed: the summary at the new price, confirmed again at it', () async {
      final (app, cubit) = await _atReview(
        more: (api) => api.on(
          'POST',
          'family/courses',
          fxRefused('price_changed', 'تغيّر سعر هذه الباقة إلى 320.00 ر.س.', extra: {'price': fxMoney('320.00 ر.س', 32000)}),
          status: 409,
        ),
      );
      await cubit.confirm();
      expect(cubit.state.step, StartStep.review);
      expect(cubit.state.priceChanged, isTrue);
      expect(cubit.state.plan?.price.minor, 32000);
      app.api.on('POST', 'family/courses', fxStarted(), status: 201);
      await cubit.confirm();
      expect((app.api.sent('POST', 'family/courses').last.data as Map)['price_minor'], 32000);
      expect(cubit.state.started, isNotNull);
      await cubit.close();
    });

    test('a child waiting for consent: the wording, agreed, then the same start again', () async {
      final (app, cubit) = await _atReview(
        more: (api) => api
          ..on('POST', 'family/courses', fxRefused('consent', 'يحتاج حساب جنى إلى موافقتكم.', extra: {'student_id': 'c1'}), status: 409)
          ..on('GET', 'family/consents', _consents())
          ..on('POST', 'family/consents/c1', {'data': {}}),
      );
      await cubit.confirm();
      expect(cubit.state.step, StartStep.consent);
      expect(cubit.state.consent?.text.version, 3);
      app.api.on('POST', 'family/courses', fxStarted(), status: 201);
      await cubit.agree();
      expect(app.api.sent('POST', 'family/consents/c1').single.data, {'agree': true, 'version': 3});
      expect(cubit.state.started?.enrollmentId, 'e1');
      expect(app.api.sent('POST', 'family/courses'), hasLength(2));
      await cubit.close();
    });

    test('a course of their own waiting for payment: its screen, and calling it off starts again from the plan', () async {
      final (app, cubit) = await _atReview(
        more: (api) => api
          ..on(
            'POST',
            'family/courses',
            fxRefused(
              'course_pending_own',
              'بدأتم كورس التجويد لجنى وهو بانتظار الدفع.',
              extra: {'enrollment_id': 'e7', 'status': 'pending_payment'},
            ),
            status: 409,
          )
          ..on('POST', 'family/courses/e7/cancel', {
            'data': {
              'enrollment_id': 'e7',
              'status': 'ended',
              'lessons_cancelled': 3,
              'invoice_number': 'INV-7',
              'message': 'أُلغيت الدورة.',
            },
          }),
      );
      await cubit.confirm();
      expect(cubit.state.step, StartStep.blocked);
      expect(cubit.state.block?.reason, StartReason.coursePendingOwn);
      expect(cubit.state.block?.enrollmentId, 'e7');
      expect(await cubit.cancelPending('e7'), 'أُلغيت الدورة.');
      expect(app.api.sent('POST', 'family/courses/e7/cancel').single.headers['Idempotency-Key'], isNotNull);
      expect(cubit.state.step, StartStep.plan);
      expect(cubit.state.note?.warning, isFalse);
      await cubit.close();
    });

    test('the teacher stopped taking students: back to the teachers, none chosen', () async {
      final (_, cubit) = await _atReview(
        more: (api) => api.on(
          'POST',
          'family/courses',
          fxRefused('teacher_unavailable', 'لم يعد هذا المعلّم يستقبل طلابًا.', status: 422),
          status: 422,
        ),
      );
      await cubit.confirm();
      expect(cubit.state.step, StartStep.teacher);
      expect(cubit.state.teacherId, isNull);
      expect(cubit.state.note?.text, 'لم يعد هذا المعلّم يستقبل طلابًا.');
      await cubit.close();
    });

    test('anything else (a dropped network, the server): kept on the summary with why', () async {
      final (_, cubit) = await _atReview(
        more: (api) => api.on('POST', 'family/courses', {'type': 'server', 'title': 'خطأ.', 'status': 500}, status: 500),
      );
      await cubit.confirm();
      expect(cubit.state.step, StartStep.review);
      expect(cubit.state.submitProblem?.status, 500);
      expect(cubit.state.busy, isFalse);
      await cubit.close();
    });

    test('changing a choice from the summary goes back to it, keeping the rest', () async {
      final (_, cubit) = await _atReview();
      cubit.edit(StartStep.teacher);
      expect(cubit.state.step, StartStep.teacher);
      expect(cubit.state.picked, isNotEmpty);
      expect(cubit.back(), isTrue);
      expect(cubit.state.step, StartStep.plan);
      await cubit.close();
    });
  });

  group('PayCubit', () {
    Future<(TestApp, PayCubit)> pay({void Function(FakeApi api)? more}) async {
      final app = await TestApp.create(accounts: [guardian]);
      await app.session.start();
      startRoutes(app.api);
      more?.call(app.api);
      final cubit = PayCubit(
        StartRepository(app.services.api),
        BillingRepository(app.services.api),
        'i1',
        invoice: PayerInvoice.fromJson(fxInvoice()),
        enrollmentId: 'e1',
      );
      return (app, cubit);
    }

    test('the invoice, the gateways that take it, and paying on the gateway coming back to the app', () async {
      final (app, cubit) = await pay(
        more: (api) => api.on('POST', 'family/invoices/i1/checkout', {
          'checkout_id': 'k1',
          'url': 'https://gateway.test/pay/k1',
          'expires_at': null,
        }, status: 201),
      );
      await cubit.load();
      await Future<void>.delayed(Duration.zero);
      expect(cubit.state.phase, PayPhase.pay);
      expect(cubit.state.payWith?.single.id, 'g1');
      final url = await cubit.checkout(accountId: 'g1');
      expect(url.toString(), 'https://gateway.test/pay/k1');
      final sent = app.api.sent('POST', 'family/invoices/i1/checkout').single;
      expect(sent.data, {'account_id': 'g1', 'return': 'app'});
      expect(sent.headers['Idempotency-Key'], isNotNull);
      // Back in the app, the gateway still confirming: a calm wait.
      app.api.on('GET', 'family/invoices/i1', fxInvoiceView(checkout: 'open'));
      await cubit.load();
      expect(cubit.state.phase, PayPhase.waiting);
      expect(app.api.sent('GET', 'family/invoices/i1').last.queryParameters, {'checkout': 'k1'});
      // Then paid: the celebration once, and the welcome.
      app.api.on('GET', 'family/invoices/i1', fxInvoiceView(paid: true, checkout: 'paid'));
      await cubit.load();
      expect(cubit.state.phase, PayPhase.paid);
      expect(cubit.state.justPaid, isTrue);
      cubit.celebrated();
      expect(cubit.state.justPaid, isFalse);
      await cubit.close();
    });

    test('the payment failed: back to paying, with why', () async {
      final (app, cubit) = await pay();
      app.api.on('GET', 'family/invoices/i1', fxInvoiceView(checkout: 'failed'));
      await cubit.load();
      expect(cubit.state.phase, PayPhase.pay);
      expect(cubit.state.retry, isTrue);
      await cubit.close();
    });

    test('a coupon: the invoice comes again under a new id, and paying goes to that one', () async {
      final (app, cubit) = await pay(
        more: (api) => api
          ..on('POST', 'family/invoices/i1/coupon', {
            'data': fxInvoice(id: 'i2', offer: false, coupon: {'code': 'SPRING', 'personal': false}),
            'message': 'طُبّق الكوبون SPRING.',
          })
          ..on('POST', 'family/invoices/i2/checkout', {'checkout_id': 'k2', 'url': 'https://gateway.test/pay/k2'}, status: 201),
      );
      await cubit.load();
      expect(await cubit.applyCoupon(' SPRING '), isTrue);
      expect((app.api.sent('POST', 'family/invoices/i1/coupon').single.data as Map)['code'], 'SPRING');
      expect(cubit.invoiceId, 'i2');
      expect(cubit.state.invoice?.couponCode, 'SPRING');
      await cubit.checkout();
      expect(app.api.sent('POST', 'family/invoices/i2/checkout'), hasLength(1));
      await cubit.close();
    });

    test("a coupon refused: the API's words, the invoice as it was", () async {
      final (_, cubit) = await pay(
        more: (api) => api.on('POST', 'family/invoices/i1/coupon', {
          'type': 'validation',
          'title': 'x',
          'status': 422,
          'errors': {
            'coupon': ['لا يوجد كوبون بالرمز NOPE.'],
          },
        }, status: 422),
      );
      await cubit.load();
      expect(await cubit.applyCoupon('NOPE'), isFalse);
      expect(cubit.state.couponProblem?.message, 'لا يوجد كوبون بالرمز NOPE.');
      expect(cubit.invoiceId, 'i1');
      await cubit.close();
    });

    test('taking a coupon off sends DELETE once, idempotent', () async {
      final (app, cubit) = await pay(
        more: (api) =>
            api.on('DELETE', 'family/invoices/i1/coupon', {'data': fxInvoice(id: 'i3', offer: false, coupon: {}), 'message': 'أُزيل.'}),
      );
      await cubit.load();
      await cubit.removeCoupon();
      expect(app.api.sent('DELETE', 'family/invoices/i1/coupon').single.headers['Idempotency-Key'], isNotNull);
      expect(cubit.invoiceId, 'i3');
      await cubit.close();
    });

    test('calling the course off before paying', () async {
      final (_, cubit) = await pay(
        more: (api) => api.on('POST', 'family/courses/e1/cancel', {
          'data': {
            'enrollment_id': 'e1',
            'status': 'ended',
            'lessons_cancelled': 8,
            'invoice_number': 'INV-2026-0042',
            'message': 'أُلغيت الدورة.',
          },
        }),
      );
      await cubit.load();
      await cubit.cancelCourse();
      expect(cubit.state.phase, PayPhase.cancelled);
      expect(cubit.state.cancelled, 'أُلغيت الدورة.');
      await cubit.close();
    });
  });

  group('the screens', () {
    testWidgets('the whole way: subject, plan, teacher, times, summary, the invoice, the gateway, then paid and welcomed', (tester) async {
      final launcher = FakeLauncher.install();
      final app = await _open(
        tester,
        '/family/start',
        more: (api) =>
            api.on('POST', 'family/invoices/i1/checkout', {'checkout_id': 'k1', 'url': 'https://gateway.test/pay/k1'}, status: 201),
      );
      // The subject: Arabic is taken and says why, with the way to its lessons.
      expect(find.text('ماذا سيدرس جنى؟'), findsOneWidget);
      expect(find.text('جنى تدرس اللغة العربية بالفعل. افتحوا الكورس من صفحة الحصص.'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('subject-sub1')), 15);

      // The plans: one to one and group apart, the price struck through under the offer, the renewal in words.
      expect(find.byKey(const Key('start-offer')), findsOneWidget);
      expect(find.byKey(const Key('private-pill')), findsOneWidget);
      expect(find.byKey(const Key('group-pill')), findsOneWidget);
      expect(find.byKey(const Key('plan-was-p1')), findsOneWidget);
      expect(find.text('270.00 ر.س'), findsOneWidget);
      expect(find.textContaining('حين تبقى حصتان'), findsNWidgets(2));
      await _tap(tester, find.byKey(const Key('plan-p1')));
      await _tap(tester, find.byKey(const Key('plan-next')), 15);

      // The teacher: the trial's first with its badge, chosen.
      expect(find.byKey(const Key('trial-teacher-badge')), findsOneWidget);
      await _tap(tester, find.byKey(const Key('teacher-t1')));
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);

      // The times: the suggested two chosen, each day's first lesson.
      expect(find.text('اختاروا موعدين أسبوعيين'), findsOneWidget);
      expect(find.byKey(const Key('times-suggested')), findsOneWidget);
      expect(find.text('اخترتم 2 من 2'), findsOneWidget);
      expect(find.byKey(const Key('first-6-17:00')), findsOneWidget);
      await _tap(tester, find.byKey(const Key('times-next')), 15);

      // The summary: the times as the API wrote them, the money, when to pay and what happens if not.
      expect(find.text('راجعوا قبل التأكيد'), findsOneWidget);
      expect(find.text('السبت 17:00\nالاثنين 17:00'), findsOneWidget);
      expect(find.text('-30.00 ر.س'), findsOneWidget);
      expect(find.textContaining('بـ24 ساعة على الأقل'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('start-confirm')), 25);

      // The invoice: what to pay by when, the offer kept, line by line with VAT.
      expect(_where(tester), '/family/pay/i1');
      expect(find.byKey(const Key('pay-hero')), findsOneWidget);
      expect(find.text('310.50 ر.س'), findsWidgets);
      expect(find.byKey(const Key('pay-by')), findsOneWidget);
      // The offer is said once, on the coupon's line.
      expect(find.byKey(const Key('pay-offer')), findsNothing);
      expect(find.text('ضريبة القيمة المضافة 15٪'), findsOneWidget);
      expect(find.text('عرض الحصة التجريبية مطبّق، ويبقى على الفاتورة.'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('pay-now')), 15);
      expect(launcher.urls, ['https://gateway.test/pay/k1']);

      // Back in the app: paid, a celebration and the welcome.
      app.api.on('GET', 'family/invoices/i1', fxInvoiceView(paid: true, checkout: 'paid'));
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await frames(tester, 20);
      expect(find.byKey(const Key('pay-paid')), findsOneWidget);
      expect(find.text('تم الدفع، وبدأت الدورة!'), findsOneWidget);
      expect(find.text('أ. منى'), findsOneWidget);
      expect(find.text('السبت 5:00 م\nالاثنين 5:00 م'), findsOneWidget);
      expect(find.text('السبت 7 نوفمبر · 5:00 م'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('paid-lessons')), 15);
      expect(_where(tester), '/family/lessons');
      await _close(tester);
    });

    testWidgets('a week off: marked on the time and said under it, listed in the review and in the paid welcome', (tester) async {
      final app = await _open(
        tester,
        '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1',
        more: (api) => api
          ..on('GET', 'family/start/slots', fxStartSlots(skips: true))
          ..on('POST', 'family/courses', fxStarted(skips: true), status: 201),
      );
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);

      // The times: the API's sentence under each day with a week off, the time it belongs to marked and heard with it.
      expect(find.byKey(const Key('skip-6-2099-11-14')), findsOneWidget);
      expect(find.text('لا حصة يوم السبت 14 نوفمبر: إجازة الأكاديمية'), findsOneWidget);
      expect(find.text('لا حصة يوم الاثنين 23 نوفمبر: إجازة المعلّم'), findsOneWidget);
      expect(find.descendant(of: find.byKey(const Key('time-6-17:00')), matching: find.byIcon(Icons.event_busy_rounded)), findsOneWidget);
      expect(find.descendant(of: find.byKey(const Key('time-6-18:00')), matching: find.byIcon(Icons.event_busy_rounded)), findsNothing);
      expect(
        tester.getSemantics(find.byKey(const Key('time-6-17:00'))).label,
        allOf(contains('السبت 17:00'), contains('لا حصة يوم السبت 14 نوفمبر: إجازة الأكاديمية')),
      );
      await _tap(tester, find.byKey(const Key('times-next')), 15);

      // The review: the chosen times' weeks off, in date order, and that the times carry on after them.
      final review = find.byKey(const Key('review-skips'));
      expect(review, findsOneWidget);
      expect(
        find.descendant(
          of: review,
          matching: find.text('لا حصة يوم السبت 14 نوفمبر: إجازة الأكاديمية\nلا حصة يوم الاثنين 23 نوفمبر: إجازة المعلّم'),
        ),
        findsOneWidget,
      );
      expect(find.descendant(of: review, matching: find.text('أيام بلا حصة')), findsOneWidget);
      expect(find.descendant(of: review, matching: find.textContaining('وتستمر المواعيد بعدها كالمعتاد')), findsOneWidget);
      await _tap(tester, find.byKey(const Key('start-confirm')), 25);

      // Paid: the welcome lists them as the start's answer said.
      expect(_where(tester), '/family/pay/i1');
      app.api.on('GET', 'family/invoices/i1', fxInvoiceView(paid: true, checkout: 'paid'));
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await frames(tester, 20);
      expect(find.byKey(const Key('pay-paid')), findsOneWidget);
      final paid = find.byKey(const Key('paid-skips'));
      await tester.ensureVisible(paid);
      expect(
        find.descendant(
          of: paid,
          matching: find.text('لا حصة يوم السبت 14 نوفمبر: إجازة الأكاديمية\nلا حصة يوم الاثنين 23 نوفمبر: إجازة المعلّم'),
        ),
        findsOneWidget,
      );
      await _close(tester);
    });

    testWidgets('no week off: nothing about it on the times, the review or the welcome', (tester) async {
      await _open(tester, '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1');
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);
      expect(find.byIcon(Icons.event_busy_rounded), findsNothing);
      expect(find.byType(SkipLine), findsNothing);
      await _tap(tester, find.byKey(const Key('times-next')), 15);
      expect(find.byKey(const Key('review-skips')), findsNothing);
      await _close(tester);
    });

    testWidgets('the entries: Home and More open the flow', (tester) async {
      await _open(tester, '/family/home');
      await _tap(tester, find.byKey(const Key('home-start')), 15);
      expect(find.text('ماذا سيدرس جنى؟'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('a time taken while confirming: the times again, the API\'s words over them', (tester) async {
      await _open(
        tester,
        '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1',
        more: (api) => api.on('POST', 'family/courses', fxRefused('time_taken', 'سبقكم أحد إلى موعد اخترتموه.'), status: 409),
      );
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);
      await _tap(tester, find.byKey(const Key('times-next')), 15);
      await _tap(tester, find.byKey(const Key('start-confirm')), 20);
      expect(find.byKey(const Key('start-note')), findsOneWidget);
      expect(find.text('سبقكم أحد إلى موعد اخترتموه.'), findsOneWidget);
      expect(find.byKey(const Key('time-6-18:00')), findsOneWidget);
      await _close(tester);
    });

    testWidgets('one more weekly time than the plan has: said under the times', (tester) async {
      await _open(tester, '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1');
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);
      await _tap(tester, find.byKey(const Key('time-3-19:00')));
      expect(find.text('اخترتم كل المواعيد. ألغوا موعدًا أولًا لتختاروا يومًا آخر.'), findsOneWidget);
      await _close(tester);
    });

    testWidgets("fewer free days than the plan's lessons a week: said at once, with another teacher or plan", (tester) async {
      final one = fxStartSlots();
      (one['data']! as Map)['days'] = [((one['data']! as Map)['days'] as List).first];
      (one['data']! as Map)['suggested'] = null;
      await _open(
        tester,
        '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1',
        more: (api) => api.on('GET', 'family/start/slots', one),
      );
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);
      expect(find.byKey(const Key('times-too-few')), findsOneWidget);
      await _tap(tester, find.text('اختاروا باقة أخرى'));
      expect(find.text('اختاروا الباقة'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('the offer ended meanwhile: the course at its usual price, said on the invoice', (tester) async {
      final started = fxStarted(offer: 'ended');
      (started['data']! as Map)['invoice'] = fxInvoice(offer: false);
      await _open(
        tester,
        '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1',
        more: (api) => api
          ..on('POST', 'family/courses', started, status: 201)
          ..on('GET', 'family/invoices/i1', fxInvoiceView(offer: false)),
      );
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);
      await _tap(tester, find.byKey(const Key('times-next')), 15);
      await _tap(tester, find.byKey(const Key('start-confirm')), 25);
      expect(find.byKey(const Key('pay-offer')), findsOneWidget);
      expect(find.textContaining('بسعره العادي'), findsOneWidget);
      // Nothing on it yet: a coupon can be typed.
      expect(find.byKey(const Key('pay-coupon-field')), findsOneWidget);
      await _close(tester);
    });

    testWidgets('no times with this teacher: said, with the way to another', (tester) async {
      await _open(
        tester,
        '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1',
        more: (api) => api.on('GET', 'family/start/slots', fxStartSlots(empty: true)),
      );
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);
      expect(find.byKey(const Key('start-no-times')), findsOneWidget);
      await _tap(tester, find.text('اختاروا معلّمًا آخر'));
      expect(find.text('اختاروا المعلّم'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('a group plan: the group with its teacher, times, seats as dots, and how many it still needs', (tester) async {
      await _open(
        tester,
        '/family/start?learner=c1&subject=sub1&plan=p2',
        more: (api) => api.on('GET', 'family/start/slots', fxGroupSlots()),
      );
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      expect(find.text('مجموعة التجويد للبنات'), findsOneWidget);
      expect(find.text('مع أ. منى'), findsOneWidget);
      expect(find.byKey(const Key('group-pill')), findsWidgets);
      expect(find.textContaining('تبدأ حين ينضم طالب واحد آخر'), findsOneWidget);
      expect(find.text('3 مقاعد متاحة'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('consent asked on confirming: the wording in full, agreed, and the course made', (tester) async {
      final app = await _open(
        tester,
        '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1',
        more: (api) => api
          ..on(
            'POST',
            'family/courses',
            fxRefused('consent', 'يحتاج حساب جنى إلى موافقتكم قبل بدء الكورس.', extra: {'student_id': 'c1'}),
            status: 409,
          )
          ..on('GET', 'family/consents', _consents())
          ..on('POST', 'family/consents/c1', {'data': {}}),
      );
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);
      await _tap(tester, find.byKey(const Key('times-next')), 15);
      await _tap(tester, find.byKey(const Key('start-confirm')), 20);
      expect(find.byKey(const Key('consent-text')), findsOneWidget);
      expect(find.text('الاسم والحصص والتقارير.'), findsOneWidget);
      app.api.on('POST', 'family/courses', fxStarted(), status: 201);
      await _tap(tester, find.byKey(const Key('consent-agree')), 25);
      expect(_where(tester), '/family/pay/i1');
      await _close(tester);
    });

    testWidgets('a course of their own waiting for payment: pay it, or call it off after asking', (tester) async {
      await _open(
        tester,
        '/family/start?learner=c1&subject=sub1&plan=p1&teacher=t1',
        more: (api) => api
          ..on(
            'POST',
            'family/courses',
            fxRefused('course_pending_own', 'بدأتم كورس التجويد لجنى وهو بانتظار الدفع.', extra: {'enrollment_id': 'e7'}),
            status: 409,
          )
          ..on('POST', 'family/courses/e7/cancel', {
            'data': {
              'enrollment_id': 'e7',
              'status': 'ended',
              'lessons_cancelled': 3,
              'invoice_number': 'INV-7',
              'message': 'أُلغيت الدورة ولم يُحسب شيء.',
            },
          }),
      );
      await _tap(tester, find.byKey(const Key('plan-next')), 15);
      await _tap(tester, find.byKey(const Key('teacher-next')), 15);
      await _tap(tester, find.byKey(const Key('times-next')), 15);
      await _tap(tester, find.byKey(const Key('start-confirm')), 20);
      expect(find.text('لا يمكن البدء الآن'), findsOneWidget);
      expect(find.byKey(const Key('blocked-money')), findsOneWidget);
      await _tap(tester, find.byKey(const Key('blocked-cancel')));
      await _tap(tester, find.text('نعم، ألغوها'), 20);
      expect(find.text('اختاروا الباقة'), findsOneWidget);
      expect(find.text('أُلغيت الدورة ولم يُحسب شيء.'), findsWidgets);
      await _close(tester);
    });

    testWidgets('the academy enrols itself: its words and the way to message it', (tester) async {
      await _open(tester, '/family/start', more: (api) => api.on('GET', 'family/start/options', fxStartOptions(selfServe: false)));
      expect(find.text('تسجّل الأكاديمية الدورات بنفسها'), findsOneWidget);
      expect(find.textContaining('راسلوها لتختار معكم'), findsOneWidget);
      expect(find.byKey(const Key('off-help')), findsOneWidget);
      await _close(tester);
    });

    testWidgets('the invoice opened again while the gateway confirms, then failed: a calm wait, then the way to try again', (tester) async {
      await _open(tester, '/family/pay/i1', more: (api) => api.on('GET', 'family/invoices/i1', fxInvoiceView(checkout: 'failed')));
      expect(find.byKey(const Key('pay-retry')), findsOneWidget);
      expect(find.text('حاولوا الدفع مرة أخرى'), findsOneWidget);
      // Without a course started here, nothing to call off from this screen.
      expect(find.byKey(const Key('pay-cancel')), findsNothing);
      await _close(tester);
    });

    testWidgets('a coupon typed and refused: the API\'s words under the field', (tester) async {
      await _open(
        tester,
        '/family/pay/i1',
        more: (api) => api
          ..on('GET', 'family/invoices/i1', fxInvoiceView(offer: false))
          ..on('POST', 'family/invoices/i1/coupon', {
            'type': 'validation',
            'title': 'x',
            'status': 422,
            'errors': {
              'coupon': ['لا يوجد كوبون بالرمز NOPE.'],
            },
          }, status: 422),
      );
      await tester.enterText(find.byKey(const Key('pay-coupon-field')), 'NOPE');
      await _tap(tester, find.byKey(const Key('pay-coupon-apply')));
      expect(find.byKey(const Key('pay-coupon-problem')), findsOneWidget);
      expect(find.text('لا يوجد كوبون بالرمز NOPE.'), findsOneWidget);
      await _close(tester);
    });

    testWidgets("a child's own look never reaches the flow", (tester) async {
      tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
      final app = await TestApp.create(accounts: [fxStudent('teen')]);
      fxStudentRoutes(app.api, 'teen', fxJourney(stage: 'teen', style: 'calm'));
      startRoutes(app.api);
      await app.session.start();
      await tester.binding.setSurfaceSize(const Size(420, 1400));
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 15);
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/start');
      await frames(tester, 15);
      expect(_where(tester), startsWith('/teen'));
      expect(app.api.sent('GET', 'family/start/options'), isEmpty);
      await _close(tester);
    });
  });
}
