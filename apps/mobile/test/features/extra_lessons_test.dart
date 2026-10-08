import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/billing/domain/billing.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _balance({Map<String, Object?>? waiting}) => {
  'learner': {'id': 'c1', 'name': 'سلمى رامي'},
  'data': [
    {
      'enrollment_id': 'e1',
      'subject': 'قرآن',
      'minutes': 60,
      'session_minutes': 30,
      'sessions': 2,
      'extra': {'price': '50 ر.س.', 'price_minor': 5000, 'currency': 'SAR', 'minutes': 30, 'max': 10, 'waiting_invoice': waiting},
    },
  ],
};

Map<String, Object?> _invoices() => {
  'owed': null,
  'manual': {'note': null, 'takes_proofs': false, 'methods': <Object>[]},
  'data': [
    {
      'id': 'i1',
      'number': 'NR-000004',
      'for': 'سلمى · قرآن',
      'currency': 'SAR',
      'total': '360 ر.س.',
      'outstanding': '',
      'outstanding_minor': 0,
      'outstanding_decimal': '0.00',
      'status': 'paid',
      'status_label': 'اتدفعت',
      'pay_with': <Object>[],
      'proof': null,
      'can_send_proof': false,
      'lines': [
        {'kind': 'block', 'description': 'قرآن — 8 حصص (البلوك 1)', 'quantity': 1, 'amount_minor': 40000, 'amount': '400 ر.س.'},
        {'kind': 'discount', 'description': 'خصم الإخوات (10%)', 'quantity': 1, 'amount_minor': -4000, 'amount': '-40 ر.س.'},
      ],
    },
  ],
};

void main() {
  test('reads a course\'s extra lessons and an invoice\'s discount lines', () {
    final course = Balance.fromJson((_balance()['data'] as List).single as Map<String, dynamic>);
    expect(course.enrollmentId, 'e1');
    expect(course.extra?.price, '50 ر.س.');
    expect(course.extra?.max, 10);
    expect(course.extra?.waitingNumber, isNull);
    expect(Balance.fromJson({'subject': 'x', 'minutes': 0}).extra, isNull);
    final invoice = Invoices.fromJson(_invoices()).list.single;
    expect(invoice.lines.where((l) => l.isDiscount).single.description, 'خصم الإخوات (10%)');
  });

  testWidgets('the payer buys two extra lessons from Money, once, and sees the discount on an invoice', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    var waiting = false;
    app.api
      ..onWith('GET', 'family/balance', (_) => (200, _balance(waiting: waiting ? {'id': 'i9', 'number': 'NR-000009'} : null)))
      ..on('GET', 'family/invoices', _invoices())
      ..onWith('POST', 'family/enrollments/e1/extra-lessons', (_) {
        waiting = true;
        return (
          201,
          {
            'data': {'id': 'i9', 'number': 'NR-000009', 'currency': 'SAR', 'total_minor': 9000, 'total': '90 ر.س.', 'status': 'issued'},
          },
        );
      });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1100));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/money');
    await frames(tester, 12);
    expect(find.textContaining('خصم الإخوات (10%)'), findsOneWidget);

    await tester.tap(find.byKey(const Key('extra-e1')));
    await frames(tester, 12);
    expect(find.text('حصص زيادة في قرآن'), findsOneWidget);
    await tester.tap(find.byKey(const Key('extra-count-2')));
    await frames(tester, 3);
    expect(find.text('2 × 50 ر.س.'), findsOneWidget);
    await tester.tap(find.byKey(const Key('extra-submit')));
    await frames(tester, 15);

    final sent = app.api.sent('POST', 'family/enrollments/e1/extra-lessons').single;
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect((sent.data as Map)['lessons'], 2);
    expect(find.text('فاتورة الحصص الزيادة NR-000009 مستنية الدفع.'), findsOneWidget);
    await tester.binding.setSurfaceSize(null);
  });
}
