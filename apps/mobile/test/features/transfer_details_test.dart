import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/billing/domain/billing.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

// Made-up accounts: never a real number in a test.
const _gulf = 'بنك: بنك المثال\nصاحب الحساب: أكاديمية النور\nIBAN: SA00 0000 0000 0000 0000 0000';

Map<String, Object?> _invoices({String? transfer = _gulf, bool paid = false}) => {
  'owed': paid ? null : '400 ر.س.',
  'manual': {
    'note': transfer,
    'takes_proofs': transfer != null,
    'methods': [
      {'value': 'bank_transfer', 'label': 'تحويل بنكي'},
    ],
  },
  'data': [
    {
      'id': 'i1',
      'number': 'NR-000002',
      'for': 'سلمى · قرآن',
      'currency': 'SAR',
      'total': '400 ر.س.',
      'outstanding': paid ? null : '400 ر.س.',
      'outstanding_minor': paid ? 0 : 40000,
      'outstanding_decimal': paid ? '0.00' : '400.00',
      'status': paid ? 'paid' : 'issued',
      'status_label': paid ? 'مدفوعة' : 'مستحقة',
      'pay_with': <Object>[],
      'proof': null,
      'can_send_proof': !paid && transfer != null,
      'transfer_details': paid ? null : transfer,
    },
  ],
};

/// The phone's clipboard: [copied] gets what the app puts on it.
void _clipboard(WidgetTester tester, void Function(String?) copied) {
  tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
    if (call.method == 'Clipboard.setData') copied((call.arguments as Map)['text'] as String?);
    return null;
  });
  addTearDown(() => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null));
}

Future<void> _openMoney(WidgetTester tester, Map<String, Object?> invoices) async {
  final app = await TestApp.create(accounts: [guardian]);
  familyRoutes(app.api);
  app.api.on('GET', 'family/invoices', invoices);
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1200));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/money');
  await frames(tester, 12);
}

void main() {
  test('reads where to transfer for each invoice, line breaks kept, and none once nothing is left to pay', () {
    expect(Invoices.fromJson(_invoices()).list.single.transferText, _gulf);
    expect(Invoices.fromJson(_invoices(transfer: '  \n ')).list.single.transferText, isNull);
    expect(Invoices.fromJson(_invoices(paid: true)).list.single.transferText, isNull);
    // Nothing left to pay: none, even if a server still sent the text.
    final row = {...(_invoices()['data']! as List).single as Map<String, Object?>, 'outstanding_minor': 0, 'status': 'paid'};
    final paid = Invoices.fromJson({
      ..._invoices(),
      'data': [row],
    }).list.single;
    expect(paid.transferDetails, _gulf);
    expect(paid.transferText, isNull);
  });

  testWidgets('the money page says where to transfer, as selectable text, and copies it as written', (tester) async {
    String? copied;
    _clipboard(tester, (text) => copied = text);
    await _openMoney(tester, _invoices());

    expect(find.text('بيانات التحويل'), findsOneWidget);
    expect(find.widgetWithText(SelectableText, _gulf), findsOneWidget);

    await tester.tap(find.byKey(const Key('transfer-copy')));
    await frames(tester, 6);
    expect(copied, _gulf);
    expect(find.text('نُسخت بيانات التحويل.'), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('transfer-details')), matching: find.byIcon(Icons.check_rounded)), findsOneWidget);
  });

  testWidgets('the proof sheet says it again, for the invoice being paid, with its own copy button', (tester) async {
    String? copied;
    _clipboard(tester, (text) => copied = text);
    await _openMoney(tester, _invoices());

    await tester.tap(find.text('دفعت بالتحويل'));
    await frames(tester, 12);
    final sheet = find.byType(BottomSheet);
    expect(find.descendant(of: sheet, matching: find.text('أرسل إثبات الدفع')), findsOneWidget);
    expect(find.descendant(of: sheet, matching: find.widgetWithText(SelectableText, _gulf)), findsOneWidget);

    await tester.tap(find.descendant(of: sheet, matching: find.byKey(const Key('transfer-copy'))));
    await frames(tester, 6);
    expect(copied, _gulf);
  });

  testWidgets('a paid invoice, or an academy that wrote none, shows no transfer details', (tester) async {
    await _openMoney(tester, _invoices(paid: true));
    expect(find.byKey(const Key('transfer-details')), findsNothing);
    expect(find.text('بيانات التحويل'), findsNothing);
  });

  testWidgets('without transfer details there is no proof to send', (tester) async {
    await _openMoney(tester, _invoices(transfer: null));
    expect(find.byKey(const Key('transfer-details')), findsNothing);
    expect(find.text('دفعت بالتحويل'), findsNothing);
  });
}
