import 'dart:async';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/consent/data/consent_repository.dart';
import 'package:academy_app/features/dashboard/data/family_repository.dart';
import 'package:academy_app/features/dashboard/presentation/bloc/add_child_cubit.dart';
import 'package:academy_app/features/billing/domain/course_balance.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/balance.dart';
import '../fixtures/journey.dart';
import '../fixtures/start.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Future<TestApp> _open(WidgetTester tester, {void Function(FakeApi api)? more, String at = '/family/home'}) async {
  tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
  latinDigitsInDates();
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  startRoutes(app.api);
  more?.call(app.api);
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 2400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  if (at != '/family/home') {
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go(at);
    await frames(tester, 15);
  }
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

Future<(TestApp, AddChildCubit)> _cubit({void Function(FakeApi api)? more}) async {
  final app = await TestApp.create(accounts: [guardian]);
  await app.session.start();
  app.api
    ..on('GET', 'family/consents', fxConsentsWithNewChild())
    ..on('POST', 'family/children', fxAddedChild(), status: 201);
  more?.call(app.api);
  return (app, AddChildCubit(FamilyRepository(app.services.api), ConsentRepository(app.services.api), clock: () => DateTime(2026, 11, 2)));
}

void main() {
  group('reading the API', () {
    test('the classic look: lessons on a package, the mark, the plan, the expiry and the next invoice', () {
      final b = CourseBalance.fromJson(fxCourseBalance());
      expect(b.classic, isTrue);
      expect(b.lessonsLeft, 3);
      expect(b.makeupLessons, 1);
      expect(b.plan?.perBlock, 8);
      expect(b.share, closeTo(3 / 8, 0.0001));
      expect(b.mark, closeTo(2 / 8, 0.0001));
      expect(b.expiry?.soon, isTrue);
      expect(b.next?.atLessonsLeft, 2);
      expect(b.next?.lessonsUntil, 1);
      expect(b.next?.now, isFalse);
      expect(b.next?.on?.localDate, '2099-10-15');
      expect(b.waiting, isNull);
      expect(b.low, isFalse);
    });

    test('a waiting invoice: its id only for the payer', () {
      expect(CourseBalance.fromJson(fxCourseBalance(waiting: true)).waiting?.id, 'i9');
      final other = CourseBalance.fromJson(fxCourseBalance(waiting: true, payer: false));
      expect(other.waiting?.id, isNull);
      expect(other.waiting?.outstanding, '400 ر.س.');
      expect(other.isPayer, isFalse);
    });

    test('the kids\' look reads the lessons left alone; a shape with no block has no bar', () {
      final kids = CourseBalance.fromJson(fxCourseBalance(stage: 'kids'));
      expect(kids.kids, isTrue);
      expect(kids.lessonsLeft, 3);
      expect([kids.plan, kids.expiry, kids.waiting, kids.next, kids.isPayer, kids.makeupLessons], everyElement(isNull));
      expect(kids.share, isNull);
      final teen = CourseBalance.fromJson(fxCourseBalance(stage: 'teen'));
      expect(teen.plan, isNotNull);
      expect([teen.waiting, teen.next, teen.isPayer], everyElement(isNull));
      // A mark at or past the package's size cannot be placed on its bar.
      final odd = CourseBalance.fromJson({
        'enrollment_id': 'e1',
        'viewer_stage': 'classic',
        'lessons_left': 0,
        'plan': {'name': 'x', 'sessions_per_block': 4, 'renews_at_lessons_left': 4},
      });
      expect(odd.mark, isNull);
      expect(odd.share, 0);
      expect(odd.low, isTrue);
    });
  });

  group('AddChildCubit', () {
    test('the name and the birth are asked for before the consent', () async {
      final (_, cubit) = await _cubit();
      await cubit.next();
      expect(cubit.state.step, AddChildStep.about);
      expect(cubit.state.errors.keys, containsAll([AddChildField.name, AddChildField.birth]));
      cubit
        ..setName('  يارا   سالم ')
        ..birthBy(BirthBy.year)
        ..setYear(2018);
      expect(cubit.state.errors, isEmpty);
      await cubit.next();
      expect(cubit.state.step, AddChildStep.consent);
      expect(cubit.state.wording?.version, 4);
      expect(cubit.state.wording?.named('يارا').agree, 'أنا ولي أمر يارا، وأوافق.');
    });

    test('adding: not before the tick; then once, idempotent, with the year alone and the wording\'s version', () async {
      final (app, cubit) = await _cubit();
      cubit
        ..setName('يارا سالم')
        ..birthBy(BirthBy.year)
        ..setYear(2018)
        ..setGender('female');
      await cubit.next();
      await cubit.submit();
      expect(cubit.state.agreeMissing, isTrue);
      expect(app.api.sent('POST', 'family/children'), isEmpty);

      cubit.agree(true);
      await cubit.submit();
      final sent = app.api.sent('POST', 'family/children').single;
      expect(sent.data, {'name': 'يارا سالم', 'birth_year': 2018, 'gender': 'female', 'agree': true, 'version': 4});
      expect(sent.headers['Idempotency-Key'], isNotNull);
      expect(cubit.state.added?.id, 'c9');
      expect(cubit.state.added?.message, contains('يارا سالم'));
    });

    test('a day of birth goes as the day; a gender picked twice is left unsaid', () async {
      final (app, cubit) = await _cubit();
      cubit
        ..setName('عمر')
        ..setDate(DateTime(2017, 3, 9))
        ..setGender('male')
        ..setGender('male');
      await cubit.next();
      cubit.agree(true);
      await cubit.submit();
      expect(app.api.sent('POST', 'family/children').single.data, {'name': 'عمر', 'birth_date': '2017-03-09', 'agree': true, 'version': 4});
    });

    test('the same child twice: back to the details with the API\'s words under the name', () async {
      const words = 'في حسابكم طفل بالاسم نفسه وسنة الميلاد نفسها (يارا سالم). اختاروه من قائمة أبنائكم بدل إضافته مرة أخرى.';
      final (_, cubit) = await _cubit(
        more: (api) => api.on('POST', 'family/children', {
          'type': 'validation',
          'title': 'x',
          'status': 422,
          'errors': {
            'name': [words],
          },
        }, status: 422),
      );
      cubit
        ..setName('يارا سالم')
        ..birthBy(BirthBy.year)
        ..setYear(2018);
      await cubit.next();
      cubit.agree(true);
      await cubit.submit();
      expect(cubit.state.step, AddChildStep.about);
      expect(cubit.state.errors[AddChildField.name], words);
      expect(cubit.state.added, isNull);
    });

    test('anything else stays on the consent with why', () async {
      final (_, cubit) = await _cubit(
        more: (api) => api.on('POST', 'family/children', {
          'type': 'forbidden',
          'title': 'إضافة طفل متاحة لحساب وليّ الأمر.',
          'status': 403,
        }, status: 403),
      );
      cubit
        ..setName('يارا')
        ..birthBy(BirthBy.year)
        ..setYear(2018);
      await cubit.next();
      cubit.agree(true);
      await cubit.submit();
      expect(cubit.state.step, AddChildStep.consent);
      expect(cubit.state.problem?.status, 403);
    });
  });

  group('the screens', () {
    testWidgets('home: each course\'s balance with its bar, plan, expiry warning and the next invoice', (tester) async {
      await _open(
        tester,
        more: (api) => api
          ..on('GET', 'family/balance', fxBalanceList())
          ..on('GET', 'family/courses/e1/balance', fxCourseBalance()),
      );
      final card = find.byKey(const Key('balance-e1'));
      await tester.ensureVisible(card);
      await frames(tester, 3);
      expect(find.descendant(of: card, matching: find.text('التجويد')), findsOneWidget);
      expect(find.descendant(of: card, matching: find.text('3 حصص')), findsOneWidget);
      expect(find.descendant(of: card, matching: find.text('متبقية من باقة 8 حصص')), findsOneWidget);
      expect(find.descendant(of: card, matching: find.text('وحصة تعويضية واحدة أيضًا')), findsOneWidget);
      expect(find.descendant(of: card, matching: find.byKey(const Key('balance-mark'))), findsOneWidget);
      expect(find.descendant(of: card, matching: find.text('حصتان في الأسبوع')), findsOneWidget);
      expect(find.descendant(of: card, matching: find.text('الحصة 45 دقيقة')), findsOneWidget);
      expect(find.descendant(of: card, matching: find.byKey(const Key('balance-expiry-soon'))), findsOneWidget);
      expect(find.descendant(of: card, matching: find.text('نحو 400 ر.س.')), findsOneWidget);
      expect(find.descendant(of: card, matching: find.textContaining('تصدر عندما تبقى حصتان، في')), findsOneWidget);
      expect(find.descendant(of: card, matching: find.text('بعد حصة واحدة')), findsOneWidget);

      // The card opens the course's own page under Payments, with how the balance works.
      await _tap(tester, card);
      expect(_where(tester), '/family/money/courses/e1');
      expect(find.byKey(const Key('course-balance-page')), findsOneWidget);
      expect(find.text('كيف يعمل الرصيد؟'), findsOneWidget);
      expect(find.textContaining('عندما تبقى حصتان تصدر فاتورة الباقة التالية'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('a waiting invoice says which and by when, and Pay opens it to be paid', (tester) async {
      await _open(
        tester,
        more: (api) => api
          ..on('GET', 'family/balance', fxBalanceList())
          ..on('GET', 'family/courses/e1/balance', fxCourseBalance(waiting: true, soon: false)),
      );
      final waiting = find.byKey(const Key('balance-waiting'));
      await tester.ensureVisible(waiting);
      expect(find.descendant(of: waiting, matching: find.text('فاتورة INV-2099-0009 · المتبقي 400 ر.س.')), findsOneWidget);
      expect(find.descendant(of: waiting, matching: find.textContaining('ادفعوا قبل')), findsOneWidget);
      // With an invoice waiting, no estimate of the next one; the expiry far off is a quiet line.
      expect(find.byKey(const Key('balance-next')), findsNothing);
      expect(find.byKey(const Key('balance-expiry')), findsOneWidget);
      expect(find.byKey(const Key('balance-expiry-soon')), findsNothing);

      await _tap(tester, find.byKey(const Key('balance-pay')));
      expect(_where(tester), '/family/pay/i9');
      await _close(tester);
    });

    testWidgets('another guardian pays: the invoice is named, with no button to pay it here', (tester) async {
      await _open(
        tester,
        more: (api) => api
          ..on('GET', 'family/balance', fxBalanceList())
          ..on('GET', 'family/courses/e1/balance', fxCourseBalance(waiting: true, payer: false)),
      );
      await tester.ensureVisible(find.byKey(const Key('balance-waiting')));
      expect(find.byKey(const Key('balance-pay')), findsNothing);
      expect(find.byKey(const Key('balance-not-payer')), findsOneWidget);
      await _close(tester);
    });

    testWidgets('a paused course: why no invoice comes, and the state on the card', (tester) async {
      await _open(
        tester,
        more: (api) => api
          ..on('GET', 'family/balance', fxBalanceList())
          ..on(
            'GET',
            'family/courses/e1/balance',
            fxCourseBalance(status: 'paused', renewalNote: 'الكورس موقوف حتى الخميس 15 أكتوبر، ولا تُحتسب حصص خلال الإيقاف.'),
          ),
      );
      await tester.ensureVisible(find.byKey(const Key('balance-renewal-note')));
      expect(find.textContaining('الكورس موقوف حتى'), findsOneWidget);
      expect(find.byKey(const Key('balance-next')), findsNothing);
      await _close(tester);
    });

    testWidgets('a notification about a course\'s balance opens its page; the balance reads again on payments', (tester) async {
      final app = await _open(tester, more: (api) => api.on('GET', 'family/courses/e1/balance', fxCourseBalance()));
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/open/course_balance/e1');
      await frames(tester, 15);
      expect(_where(tester), '/family/money/courses/e1');
      expect(find.byKey(const Key('balance-next')), findsOneWidget);
      final before = app.api.sent('GET', 'family/courses/e1/balance').length;
      app.services.live.changed('payments.changed');
      await frames(tester, 6);
      expect(app.api.sent('GET', 'family/courses/e1/balance').length, greaterThan(before));
      await _close(tester);
    });

    testWidgets('Payments: a course opens its balance, and an invoice opens to be paid', (tester) async {
      await _open(
        tester,
        at: '/family/money',
        more: (api) => api
          ..on('GET', 'family/balance', fxBalanceList())
          ..on('GET', 'family/courses/e1/balance', fxCourseBalance()),
      );
      await _tap(tester, find.byKey(const Key('balance-row-e1')));
      expect(_where(tester), '/family/money/courses/e1');
      await _close(tester);
    });

    testWidgets('kids: the lessons left as stars in their own words, no plan and no money', (tester) async {
      tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
      final app = await TestApp.create(accounts: [fxStudent('kids')]);
      fxStudentRoutes(app.api, 'kids', fxJourney(stage: 'kids'));
      app.api
        ..on('GET', 'family/balance', fxBalanceList())
        ..on('GET', 'family/courses/e1/balance', fxCourseBalance(stage: 'kids'));
      await app.session.start();
      await tester.binding.setSurfaceSize(const Size(420, 1600));
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 15);
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/kids/more');
      await frames(tester, 15);

      expect(find.byKey(const Key('balance-kids')), findsOneWidget);
      expect(find.text('بقيت لك 3 حصص'), findsOneWidget);
      expect(find.byIcon(Icons.star_rounded), findsNWidgets(3));
      for (final key in ['balance-plan', 'balance-waiting', 'balance-next', 'balance-expiry-soon', 'balance-mark']) {
        expect(find.byKey(Key(key)), findsNothing, reason: key);
      }
      expect(find.textContaining('ر.س'), findsNothing);
      await _close(tester);
    });

    testWidgets('«إضافة طفل» from More: the details, the consent with the name, then the start flow for the child', (tester) async {
      final app = await _open(
        tester,
        at: '/family/me',
        more: (api) => api
          ..on('GET', 'family/consents', fxConsentsWithNewChild())
          ..on('POST', 'family/children', fxAddedChild(), status: 201)
          ..on('GET', 'family/start/options', fxStartOptions(learner: 'c9')),
      );
      await _tap(tester, find.byKey(const Key('open-add-child')));
      expect(_where(tester), '/family/children/new');

      // Nothing typed: what is missing is said under each field.
      await _tap(tester, find.byKey(const Key('child-next')));
      expect(find.text('اكتبوا الاسم بحرفين على الأقل.'), findsOneWidget);
      expect(find.text('اختاروا تاريخ الميلاد أو سنته.'), findsOneWidget);

      await tester.enterText(find.byKey(const Key('child-name')), 'يارا سالم');
      await _tap(tester, find.text('السنة فقط'));
      expect(find.byKey(const Key('child-year-note')), findsOneWidget);
      await _tap(tester, find.byKey(const Key('child-birth')));
      await _tap(tester, find.byKey(const Key('year-2018')));
      expect(find.text('2018'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('child-gender-female')));
      await _tap(tester, find.byKey(const Key('child-next')));

      // The academy's wording with her name in it; adding waits for the tick.
      expect(find.text('موافقتك على حساب يارا سالم'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('child-submit')));
      expect(find.byKey(const Key('child-agree-needed')), findsOneWidget);
      expect(app.api.sent('POST', 'family/children'), isEmpty);
      await _tap(tester, find.byKey(const Key('child-agree')));
      await _tap(tester, find.byKey(const Key('child-submit')), 20);

      final sent = app.api.sent('POST', 'family/children').single;
      expect(sent.data, {'name': 'يارا سالم', 'birth_year': 2018, 'gender': 'female', 'agree': true, 'version': 4});
      expect(sent.headers['Idempotency-Key'], isNotNull);
      expect(_where(tester), '/family/start?learner=c9');
      expect(app.api.sent('GET', 'family/start/options').first.queryParameters['learner_id'], 'c9');
      expect(find.textContaining('أُضيف يارا سالم إلى حسابكم'), findsOneWidget);
      // The family's switcher read its learners again.
      expect(app.api.sent('GET', 'family/learners').length, greaterThan(1));
      await _close(tester);
    });

    testWidgets('the start flow\'s learner step adds a child and goes on with them chosen', (tester) async {
      final app = await _open(
        tester,
        more: (api) => api
          ..on('GET', 'family/consents', fxConsentsWithNewChild())
          ..on('POST', 'family/children', fxAddedChild(id: 'c2', name: 'عمر'), status: 201)
          ..on('GET', 'family/start/options', fxStartOptions(twoLearners: true)),
      );
      unawaited(GoRouter.of(tester.element(find.byType(Scaffold).first)).push('/family/start'));
      await frames(tester, 15);
      expect(find.byKey(const Key('learner-add')), findsOneWidget);
      await _tap(tester, find.byKey(const Key('learner-add')));
      expect(_where(tester), '/family/children/new?from=start');

      await tester.enterText(find.byKey(const Key('child-name')), 'عمر');
      await _tap(tester, find.text('السنة فقط'));
      await _tap(tester, find.byKey(const Key('child-birth')));
      await _tap(tester, find.byKey(const Key('year-2016')));
      await _tap(tester, find.byKey(const Key('child-next')));
      await _tap(tester, find.byKey(const Key('child-agree')));
      await _tap(tester, find.byKey(const Key('child-submit')), 20);

      expect(_where(tester), '/family/start');
      expect(app.api.sent('GET', 'family/start/options').last.queryParameters['learner_id'], 'c2');
      // On to the subject for him, with the API's sentence.
      expect(find.byKey(const Key('subject-sub1')), findsOneWidget);
      expect(find.textContaining('أُضيف عمر إلى حسابكم'), findsOneWidget);
      await _close(tester);
    });

    testWidgets('a paid invoice opened again says so plainly, with its copy and the lessons', (tester) async {
      await _open(tester, more: (api) => api.on('GET', 'family/invoices/i1', fxInvoiceView(paid: true)));
      unawaited(GoRouter.of(tester.element(find.byType(Scaffold).first)).push('/family/pay/i1'));
      await frames(tester, 15);
      expect(find.byKey(const Key('pay-settled')), findsOneWidget);
      expect(find.text('دُفعت هذه الفاتورة'), findsOneWidget);
      // Not a course's welcome: it was paid before.
      expect(find.text('تم الدفع، وبدأت الدورة!'), findsNothing);
      await _close(tester);
    });
  });
}
