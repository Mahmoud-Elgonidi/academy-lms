import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:academy_app/features/billing/data/billing_repository.dart';
import 'package:academy_app/features/billing/data/cards_repository.dart';
import 'package:academy_app/features/billing/domain/billing.dart';
import 'package:academy_app/features/billing/domain/cards.dart';
import 'package:academy_app/features/billing/domain/course_balance.dart';
import 'package:academy_app/features/billing/presentation/pages/cards_page.dart';
import 'package:academy_app/features/start/data/start_repository.dart';
import 'package:academy_app/features/start/domain/start.dart';
import 'package:academy_app/features/start/presentation/pay/pay_cubit.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/balance.dart';
import '../fixtures/cards.dart';
import '../fixtures/start.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// The family's invoices, with [i1] taken by a gateway that keeps cards (and one that does not when [two]).
Map<String, Object?> _invoices({bool saves = true, bool two = false}) => {
  'owed': '310.50 ر.س',
  'data': [
    {
      'id': 'i1',
      'number': 'INV-2026-0042',
      'for': 'جنى · التجويد',
      'total': '310.50 ر.س',
      'outstanding': '310.50 ر.س',
      'outstanding_minor': 31050,
      'status': 'issued',
      'status_label': 'بانتظار الدفع',
      'pay_with': [
        {'account_id': 'g1', 'label': 'بطاقة (Moyasar)', 'saves_cards': saves},
        if (two) {'account_id': 'g2', 'label': 'Paymob', 'saves_cards': false},
      ],
      'can_send_proof': false,
      'lines': <Object>[],
    },
  ],
};

Future<TestApp> _open(WidgetTester tester, String at, {void Function(FakeApi api)? more}) async {
  tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
  latinDigitsInDates();
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  startRoutes(app.api);
  app.api.on('GET', 'family/cards', fxCards());
  more?.call(app.api);
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 2000));
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

String _where(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).last)).state.uri.toString();

Future<void> _tap(WidgetTester tester, Finder f, [int n = 12]) async {
  await tester.ensureVisible(f);
  await frames(tester, 2);
  await tester.tap(f);
  await frames(tester, n);
}

void main() {
  group('reading the API', () {
    test('cards: brand, last four and expiry; each account\'s switch; the charges coming; the mandate', () {
      final d = SavedCards.fromJson(fxCards(soon: true));
      expect(d.hasCard, isTrue);
      expect(d.cards.single.label, 'Visa ****4242');
      expect(d.cards.single.expiry, '12/34');
      expect(d.cards.single.expiresSoon, isTrue);
      expect(d.autoRenew, isTrue);
      expect(d.accounts.single.cardId, 'pm1');
      expect(d.perAccount, isFalse);
      expect(d.coming.single.status, ChargeStatus.scheduled);
      expect(d.coming.single.at?.label, 'الخميس 15 أكتوبر · 6:00 م');
      expect(d.stopsSomething, isTrue);
      expect(d.mandate?.version, '2027-07-20');
      final none = SavedCards.fromJson(fxCards(card: false));
      expect([none.hasCard, none.autoRenew], [false, false]);
    });

    test('a course balance carries automatic renewal (its payer, the classic look); the invoice its gateway and the offer', () {
      final b = CourseBalance.fromJson(fxCourseBalance(auto: fxCourseAuto()));
      expect(b.autoRenew?.on, isTrue);
      expect(b.autoRenew?.card?.label, 'Visa ****4242');
      expect(b.autoRenew?.next?.amount, '400 ر.س.');
      expect(CourseBalance.fromJson(fxCourseBalance(auto: fxCourseAuto(), payer: false)).autoRenew, isNull);

      final invoices = Invoices.fromJson(_invoices(two: true));
      expect(invoices.list.single.payWith.map((g) => g.savesCards), [true, false]);
      expect(invoices.list.single.savesCards, isTrue);

      final view = InvoiceView.fromJson(fxInvoiceView(paid: true, checkout: 'paid', saveCard: true, cardSaved: true));
      expect(view.saveCard?.offered, isTrue);
      expect(view.saveCard?.mandate.version, '2027-07-20');
      expect(view.checkout?.cardSaved, isTrue);
      expect(InvoiceView.fromJson(fxInvoiceView()).saveCard, isNull);
    });

    test('a notification about the cards opens the cards screen under Payments', () {
      expect(targetOf('cards', 'u1', Area.family), '/family/money/cards');
      expect(opensOverTabs('/family/money/cards'), isFalse);
      expect(targetOf('invoice', 'i9', Area.family), '/family/pay/i9');
    });
  });

  group('CardsCubit', () {
    Future<(TestApp, CardsCubit)> cubit({void Function(FakeApi api)? more}) async {
      final app = await TestApp.create(accounts: [guardian]);
      await app.session.start();
      app.api.on('GET', 'family/cards', fxCards());
      more?.call(app.api);
      return (app, CardsCubit(CardsRepository(app.services.api)));
    }

    test('turning it off: one PUT with an idempotency key, and the answer shown without reading again', () async {
      final (app, c) = await cubit(
        more: (api) => api.on('PUT', 'family/cards/auto-renew', {...fxCards(on: false), 'message': 'أوقفنا التجديد التلقائي.'}),
      );
      await c.load();
      expect(c.state.data?.autoRenew, isTrue);
      final done = await c.autoRenew(false);
      expect(done.message, 'أوقفنا التجديد التلقائي.');
      final sent = app.api.sent('PUT', 'family/cards/auto-renew').single;
      expect(sent.data, {'on': false});
      expect(sent.headers['Idempotency-Key'], isNotNull);
      expect(c.state.data?.autoRenew, isFalse);
      expect(app.api.sent('GET', 'family/cards'), hasLength(1));
      await c.close();
    });

    test('one account of several by its id; on without a card is refused in the API\'s words', () async {
      final (app, c) = await cubit(
        more: (api) => api.on('PUT', 'family/cards/auto-renew', {
          'type': 'validation',
          'title': 'x',
          'status': 422,
          'errors': {
            'on': ['لا توجد بطاقة محفوظة لهذه العملة.'],
          },
        }, status: 422),
      );
      await c.load();
      final done = await c.autoRenew(true, accountId: 'ba2');
      expect(done.problem?.message, 'لا توجد بطاقة محفوظة لهذه العملة.');
      expect(app.api.sent('PUT', 'family/cards/auto-renew').single.data, {'on': true, 'billing_account_id': 'ba2'});
      expect(c.state.data?.autoRenew, isTrue);
      await c.close();
    });

    test('removing a card: DELETE once, idempotent; with none left renewal is off', () async {
      final (app, c) = await cubit(
        more: (api) => api.on('DELETE', 'family/cards/pm1', {...fxCards(card: false), 'message': 'أُزيلت البطاقة.'}),
      );
      await c.load();
      final done = await c.remove('pm1');
      expect(done.message, 'أُزيلت البطاقة.');
      expect(app.api.sent('DELETE', 'family/cards/pm1').single.headers['Idempotency-Key'], isNotNull);
      expect(c.state.data?.hasCard, isFalse);
      expect(c.state.data?.autoRenew, isFalse);
      await c.close();
    });
  });

  group('PayCubit: saving the card', () {
    Future<(TestApp, PayCubit)> pay({bool two = false, bool saves = true}) async {
      final app = await TestApp.create(accounts: [guardian]);
      await app.session.start();
      startRoutes(app.api);
      app.api
        ..on('GET', 'family/invoices', _invoices(two: two, saves: saves))
        ..on('GET', 'family/invoices/i1', fxInvoiceView(saveCard: true))
        ..on('POST', 'family/invoices/i1/checkout', {'checkout_id': 'k1', 'url': 'https://gateway.test/pay/k1'}, status: 201);
      final cubit = PayCubit(StartRepository(app.services.api), BillingRepository(app.services.api), 'i1');
      await cubit.load();
      await Future<void>.delayed(Duration.zero);
      return (app, cubit);
    }

    test('offered, never ticked for them: not ticked, nothing about the card is sent', () async {
      final (app, cubit) = await pay();
      expect(cubit.state.canSaveCard, isTrue);
      expect(cubit.state.keepCard, isFalse);
      await cubit.checkout(accountId: 'g1');
      expect(app.api.sent('POST', 'family/invoices/i1/checkout').single.data, {'account_id': 'g1', 'return': 'app'});
      await cubit.close();
    });

    test('ticked: save_card with the version read, idempotent; back paid with the card kept', () async {
      final (app, cubit) = await pay();
      cubit.keepCard(true);
      await cubit.checkout(accountId: 'g1');
      final sent = app.api.sent('POST', 'family/invoices/i1/checkout').single;
      expect(sent.data, {'account_id': 'g1', 'return': 'app', 'save_card': true, 'mandate_version': '2027-07-20'});
      expect(sent.headers['Idempotency-Key'], isNotNull);
      expect(cubit.state.askedToSave, isTrue);

      app.api.on('GET', 'family/invoices/i1', fxInvoiceView(paid: true, checkout: 'paid', saveCard: true, cardSaved: true));
      await cubit.load();
      expect(cubit.state.phase, PayPhase.paid);
      expect(cubit.state.cardSaved, isTrue);
      expect(cubit.state.cardNotSaved, isFalse);
      await cubit.close();
    });

    test('ticked, but the gateway did not keep it: said once paid', () async {
      final (app, cubit) = await pay();
      cubit.keepCard(true);
      await cubit.checkout(accountId: 'g1');
      app.api.on('GET', 'family/invoices/i1', fxInvoiceView(paid: true, checkout: 'paid', saveCard: true));
      await cubit.load();
      expect(cubit.state.cardNotSaved, isTrue);
      await cubit.close();
    });

    test('ticked, then a gateway that keeps no cards is chosen: paid without saving', () async {
      final (app, cubit) = await pay(two: true);
      cubit.keepCard(true);
      await cubit.checkout(accountId: 'g2');
      expect(app.api.sent('POST', 'family/invoices/i1/checkout').single.data, {'account_id': 'g2', 'return': 'app'});
      expect(cubit.state.askedToSave, isFalse);
      await cubit.close();
    });

    test('no gateway keeps cards: not offered', () async {
      final (_, cubit) = await pay(saves: false);
      expect(cubit.state.canSaveCard, isFalse);
      await cubit.close();
    });
  });

  group('the screens', () {
    testWidgets('paying an invoice: the tick, the mandate a tap away, sent with it, and the card kept once paid', (tester) async {
      final launcher = FakeLauncher.install();
      final app = await _open(
        tester,
        '/family/pay/i1',
        more: (api) => api
          ..on('GET', 'family/invoices', _invoices())
          ..on('GET', 'family/invoices/i1', fxInvoiceView(saveCard: true))
          ..on('POST', 'family/invoices/i1/checkout', {'checkout_id': 'k1', 'url': 'https://gateway.test/pay/k1'}, status: 201),
      );
      expect(find.byKey(const Key('pay-save-card')), findsOneWidget);
      expect(find.text('احفظ بطاقتي وجدّد تلقائيًا'), findsOneWidget);
      expect(find.text(fxMandateText), findsNothing);
      await _tap(tester, find.byKey(const Key('pay-save-card-read')));
      expect(find.text(fxMandateText), findsOneWidget);
      await _tap(tester, find.byKey(const Key('pay-save-card-tick')));
      await _tap(tester, find.byKey(const Key('pay-now')));
      final sent = app.api.sent('POST', 'family/invoices/i1/checkout').single;
      expect((sent.data as Map)['save_card'], isTrue);
      expect((sent.data as Map)['mandate_version'], '2027-07-20');
      expect(launcher.opened.single.$1, 'https://gateway.test/pay/k1');

      // Back from the gateway: paid, and the card kept.
      app.api.on('GET', 'family/invoices/i1', fxInvoiceView(paid: true, checkout: 'paid', saveCard: true, cardSaved: true));
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await frames(tester, 20);
      expect(find.byKey(const Key('pay-settled')), findsOneWidget);
      expect(find.byKey(const Key('pay-card-saved')), findsOneWidget);
      expect(find.text('حُفظت بطاقتكم، وصار التجديد تلقائيًا'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('pay-open-cards')), 20);
      expect(_where(tester), '/family/money/cards');
      await _close(tester);
    });

    testWidgets('not offered in this look: no tick', (tester) async {
      await _open(
        tester,
        '/family/pay/i1',
        more: (api) => api
          ..on('GET', 'family/invoices', _invoices())
          ..on('GET', 'family/invoices/i1', fxInvoiceView()),
      );
      expect(find.byKey(const Key('pay-view')), findsOneWidget);
      expect(find.byKey(const Key('pay-save-card')), findsNothing);
      await _close(tester);
    });

    testWidgets('the cards screen: the card, its expiry warning, the charge coming, the words agreed to', (tester) async {
      await _open(tester, '/family/money/cards', more: (api) => api.on('GET', 'family/cards', fxCards(soon: true)));
      expect(find.text('البطاقات والتجديد التلقائي'), findsWidgets);
      expect(find.byKey(const Key('card-pm1')), findsOneWidget);
      expect(find.text('••••  ••••  ••••  4242'), findsOneWidget);
      expect(find.text('تنتهي في 12/34'), findsOneWidget);
      expect(find.byKey(const Key('card-pm1-soon')), findsOneWidget);
      expect(find.text('تُخصم منها التجديدات'), findsOneWidget);
      expect(find.byKey(const Key('coming-i9-at')), findsOneWidget);
      expect(find.text('الخميس 15 أكتوبر · 6:00 م'), findsOneWidget);
      expect(find.text('مجدول'), findsOneWidget);
      expect(find.text(fxMandateText), findsNothing);
      await _tap(tester, find.byKey(const Key('cards-mandate')));
      expect(find.text(fxMandateText), findsOneWidget);
      await _close(tester);
    });

    testWidgets('turning it off is asked first; kept on sends nothing, confirmed sends it once', (tester) async {
      final app = await _open(
        tester,
        '/family/money/cards',
        more: (api) => api.on('PUT', 'family/cards/auto-renew', {...fxCards(on: false), 'message': 'أوقفنا التجديد التلقائي.'}),
      );
      final toggle = find.byKey(const Key('cards-auto'));
      await _tap(tester, toggle);
      expect(find.text('إيقاف التجديد التلقائي؟'), findsOneWidget);
      await _tap(tester, find.text('أبقوه يعمل'));
      expect(app.api.sent('PUT', 'family/cards/auto-renew'), isEmpty);

      await _tap(tester, toggle);
      await _tap(tester, find.text('أوقفوه'), 20);
      expect(app.api.sent('PUT', 'family/cards/auto-renew').single.data, {'on': false});
      expect(find.byKey(const Key('cards-auto-text')), findsOneWidget);
      expect(find.text('متوقف: تصلكم فاتورة كل باقة تالية لتدفعوها بأنفسكم.'), findsOneWidget);
      expect(tester.widget<Switch>(find.descendant(of: toggle, matching: find.byType(Switch), matchRoot: true)).value, isFalse);

      // On again: no question (the card was saved with the words agreed to).
      app.api.on('PUT', 'family/cards/auto-renew', {...fxCards(), 'message': 'شغّلنا التجديد التلقائي.'});
      await frames(tester, 30);
      await _tap(tester, toggle, 20);
      expect(app.api.sent('PUT', 'family/cards/auto-renew').last.data, {'on': true});
      expect(tester.widget<Switch>(find.descendant(of: toggle, matching: find.byType(Switch), matchRoot: true)).value, isTrue);
      await _close(tester);
    });

    testWidgets('removing the only card: asked first, saying renewal stops; then the empty state', (tester) async {
      final app = await _open(
        tester,
        '/family/money/cards',
        more: (api) => api.on('DELETE', 'family/cards/pm1', {...fxCards(card: false), 'message': 'أُزيلت البطاقة.'}),
      );
      await _tap(tester, find.byKey(const Key('card-pm1-remove')));
      expect(find.text('إزالة Visa ****4242؟'), findsOneWidget);
      expect(find.text('إنها بطاقتكم الوحيدة، فيتوقف التجديد التلقائي وتُلغى الخصومات القادمة.'), findsOneWidget);
      await _tap(tester, find.text('أزيلوها'), 20);
      expect(app.api.sent('DELETE', 'family/cards/pm1'), hasLength(1));
      expect(find.byKey(const Key('cards-empty')), findsOneWidget);
      expect(find.text('لا توجد بطاقة محفوظة'), findsOneWidget);
      expect(find.byKey(const Key('cards-auto')), findsNothing);
      await _close(tester);
    });

    testWidgets('Payments leads to the cards screen, its line saying where renewal stands', (tester) async {
      await _open(tester, '/family/money');
      expect(find.text('التجديد التلقائي يعمل · Visa ****4242'), findsOneWidget);
      await _tap(tester, find.byKey(const Key('money-cards')), 20);
      expect(_where(tester), '/family/money/cards');
      await _close(tester);
    });

    testWidgets('a course\'s balance: automatic renewal on, the next charge as written, and the way to the cards', (tester) async {
      await _open(
        tester,
        '/family/money/courses/e1',
        more: (api) => api.on('GET', 'family/courses/e1/balance', fxCourseBalance(auto: fxCourseAuto(soon: true))),
      );
      expect(find.byKey(const Key('balance-auto')), findsOneWidget);
      expect(find.text('التجديد التلقائي يعمل: تُخصم الباقة التالية من Visa ****4242 بعد إبلاغكم بثلاثة أيام.'), findsOneWidget);
      expect(find.text('الخصم التالي 400 ر.س. · الخميس 15 أكتوبر · 6:00 م'), findsOneWidget);
      expect(find.byKey(const Key('balance-auto-expiring')), findsOneWidget);
      await _tap(tester, find.byKey(const Key('balance-auto-manage')), 20);
      expect(_where(tester), '/family/money/cards');
      await _close(tester);
    });

    testWidgets('a course\'s balance with renewal off: said, no charge, and still the way to turn it on', (tester) async {
      await _open(
        tester,
        '/family/money/courses/e1',
        more: (api) => api.on('GET', 'family/courses/e1/balance', fxCourseBalance(auto: fxCourseAuto(on: false))),
      );
      expect(find.text('متوقف'), findsOneWidget);
      expect(find.byKey(const Key('balance-auto-next')), findsNothing);
      expect(find.byKey(const Key('balance-auto-manage')), findsOneWidget);
      await _close(tester);
    });
  });
}
