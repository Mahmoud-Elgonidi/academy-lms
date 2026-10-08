import 'dart:typed_data';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/billing/data/billing_repository.dart';
import 'package:academy_app/features/billing/domain/billing.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _invoices({Map<String, Object?>? proof, bool canSend = true}) => {
  'owed': '400 ر.س.',
  'manual': {
    'note': 'إنستاباي: noor@instapay',
    'takes_proofs': true,
    'methods': [
      {'value': 'bank_transfer', 'label': 'تحويل بنكي'},
      {'value': 'instapay', 'label': 'إنستاباي'},
    ],
  },
  'data': [
    {
      'id': 'i1',
      'number': 'NR-000002',
      'for': 'سلمى · قرآن',
      'currency': 'SAR',
      'total': '400 ر.س.',
      'outstanding': '400 ر.س.',
      'outstanding_minor': 40000,
      'outstanding_decimal': '400.00',
      'status': 'issued',
      'status_label': 'مستحقة',
      'pay_with': <Object>[],
      'proof': proof,
      'can_send_proof': canSend,
    },
  ],
};

void main() {
  test('reads how to pay by hand and where each invoice\'s proof stands', () {
    final invoices = Invoices.fromJson(
      _invoices(proof: {'status': 'rejected', 'text': 'إثبات دفع 400 ر.س. اترفض: المبلغ ماوصلش', 'reason': 'المبلغ ماوصلش'}),
    );
    expect(invoices.manual.takesProofs, isTrue);
    expect(invoices.manual.methods.map((m) => m.value), ['bank_transfer', 'instapay']);
    final i = invoices.list.single;
    expect(i.outstandingDecimal, '400.00');
    expect(i.canSendProof, isTrue);
    expect(i.proof?.status, 'rejected');
    expect(i.proof?.reason, 'المبلغ ماوصلش');
    expect(Invoices.fromJson({'owed': null, 'data': <Object>[]}).manual.takesProofs, isFalse);
  });

  test('sends the receipt as multipart, once, with an idempotency key', () async {
    final app = await TestApp.create(accounts: [guardian]);
    await app.session.start();
    app.api.on('POST', 'family/invoices/i1/proofs', {
      'data': {'status': 'pending', 'text': 'إثبات دفع 400 ر.س. اتبعت يوم 5 أكتوبر، ومستني الأكاديمية'},
    }, status: 201);

    final state = await BillingRepository(app.services.api).sendProof(
      'i1',
      method: 'instapay',
      amount: '400.00',
      paidOn: '2026-10-05',
      reference: 'IP-1',
      receipt: Receipt(bytes: Uint8List.fromList([1, 2, 3]), name: 'receipt.jpg', mime: 'image/jpeg'),
    );

    expect(state.status, 'pending');
    final sent = app.api.sent('POST', 'family/invoices/i1/proofs').single;
    expect(sent.headers['Idempotency-Key'], isNotNull);
    final form = sent.data as FormData;
    expect(Map.fromEntries(form.fields), {'method': 'instapay', 'amount': '400.00', 'paid_on': '2026-10-05', 'reference': 'IP-1'});
    expect(form.files.single.key, 'file');
    expect(form.files.single.value.filename, 'receipt.jpg');
    expect(form.files.single.value.contentType.toString(), 'image/jpeg');
  });

  testWidgets('a guardian who paid by hand opens the form, reads how to pay, and cannot send without the receipt', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('GET', 'family/invoices', _invoices());
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1000));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/money');
    await frames(tester, 12);
    // No gateway takes this invoice: paying by hand is the way.
    expect(find.text('دفعت بالتحويل'), findsOneWidget);

    await tester.tap(find.text('دفعت بالتحويل'));
    await frames(tester, 12);
    expect(find.text('ابعت إثبات الدفع'), findsOneWidget);
    expect(find.text('إنستاباي: noor@instapay'), findsOneWidget);
    expect(find.widgetWithText(TextField, 'المبلغ اللي دفعته'), findsOneWidget);
    expect(find.text('400.00'), findsOneWidget);

    await tester.ensureVisible(find.text('ابعت الإثبات'));
    await tester.tap(find.text('ابعت الإثبات'));
    await frames(tester, 4);
    expect(find.text('اختار الإيصال الأول.'), findsOneWidget);
    expect(app.api.sent('POST', 'family/invoices/i1/proofs'), isEmpty);
    await tester.binding.setSurfaceSize(null);
  });

  testWidgets('a proof waiting for the academy shows where it stands, and no second one is offered', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on(
      'GET',
      'family/invoices',
      _invoices(canSend: false, proof: {'status': 'pending', 'text': 'إثبات دفع 400 ر.س. اتبعت يوم 5 أكتوبر، ومستني الأكاديمية'}),
    );
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1000));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/money');
    await frames(tester, 12);
    expect(find.text('إثبات دفع 400 ر.س. اتبعت يوم 5 أكتوبر، ومستني الأكاديمية'), findsWidgets);
    expect(find.text('دفعت بالتحويل'), findsNothing);
    await tester.binding.setSurfaceSize(null);
  });
}
