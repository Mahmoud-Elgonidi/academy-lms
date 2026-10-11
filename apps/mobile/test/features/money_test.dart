import 'dart:typed_data';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:academy_app/features/billing/presentation/pages/money_page.dart';
import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher_platform_interface/url_launcher_platform_interface.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// A file picked on the phone, in memory.
final class _Picked extends PlatformFile {
  _Picked(this.name, this.bytes, {this.size});

  @override
  final String name;
  final Uint8List bytes;

  /// What the phone says the file weighs (null: unknown until read).
  final int? size;

  @override
  Uri get uri => Uri.parse('file:///picked/$name');

  // Its type (XFile) is cross_file's, which the app does not use: the money page reads through readAsBytes.
  @override
  // ignore: always_declare_return_types
  get xFile => throw UnimplementedError('read through readAsBytes');

  @override
  int? lengthSync() => size;

  @override
  Future<int?> length() async => size ?? bytes.length;

  @override
  Future<Uint8List> readAsBytes() async => bytes;

  @override
  Stream<Uint8List> readAsByteStream() => Stream.value(bytes);
}

/// The phone's file picker: hands back [next], and remembers what it was asked for.
class _Picker extends FilePickerPlatform {
  PlatformFile? next;
  List<String>? asked;

  @override
  Future<PlatformFile?> pickFile({
    String? dialogTitle,
    String? initialDirectory,
    FileType type = FileType.any,
    List<String>? allowedExtensions,
    Function(FilePickerStatus)? onFileLoading,
    int compressionQuality = 0,
    AndroidOptions androidOptions = const AndroidOptions(),
    DarwinOptions darwinOptions = const DarwinOptions(),
    WindowsOptions windowsOptions = const WindowsOptions(),
    LinuxOptions linuxOptions = const LinuxOptions(),
    WebOptions webOptions = const WebOptions(),
  }) async {
    asked = allowedExtensions;
    return next;
  }
}

Map<String, Object?> _invoice(
  String id, {
  String status = 'issued',
  String label = 'مستحقة',
  int outstanding = 40000,
  List<Map<String, Object?>> payWith = const [],
  Map<String, Object?>? proof,
  bool canSend = false,
  String? document,
  List<Map<String, Object?>> lines = const [],
}) => {
  'id': id,
  'number': 'NR-$id',
  'for': 'سلمى · قرآن',
  'currency': 'SAR',
  'total': '400 ر.س.',
  'outstanding': outstanding == 0 ? '0 ر.س.' : '${outstanding ~/ 100} ر.س.',
  'outstanding_minor': outstanding,
  'outstanding_decimal': '${outstanding ~/ 100}.00',
  'status': status,
  'status_label': label,
  'issued_at': time('2099-10-01T09:00:00Z', '2099-10-01T12:00:00+03:00'),
  'due_at': time('2099-10-08T09:00:00Z', '2099-10-08T12:00:00+03:00'),
  'document_url': document,
  'pay_with': payWith,
  'proof': proof,
  'can_send_proof': canSend,
  'lines': lines,
};

Map<String, Object?> _invoices(List<Map<String, Object?>> list, {bool proofs = true}) => {
  'owed': list.any((i) => (i['outstanding_minor']! as int) > 0) ? '400 ر.س.' : null,
  'manual': proofs
      ? {
          'note': 'حوّل على حساب الأكاديمية في بنك الراجحي',
          'takes_proofs': true,
          'methods': [
            {'value': 'bank_transfer', 'label': 'تحويل بنكي'},
          ],
        }
      : null,
  'data': list,
};

Future<TestApp> _money(WidgetTester tester, {Account account = guardian}) async {
  final app = await TestApp.create(accounts: [account]);
  familyRoutes(app.api);
  return app;
}

Future<void> _open(WidgetTester tester, TestApp app) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1200));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/money');
  await frames(tester, 12);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  late _Picker picker;
  late FakeLauncher launcher;

  setUp(() {
    picker = _Picker();
    FilePickerPlatform.instance = picker;
    launcher = FakeLauncher.install();
  });

  testWidgets('the lessons left, every invoice with what came off it, and an invoice\'s document', (tester) async {
    final app = await _money(tester);
    app.api
      ..on('GET', 'family/balance', {
        'data': [
          {'enrollment_id': 'e1', 'subject': 'قرآن', 'sessions': 1, 'minutes': 45},
          {'enrollment_id': 'e2', 'subject': 'عربي وإنجليزي', 'sessions': 8, 'minutes': 360, 'shared': true},
        ],
      })
      ..on(
        'GET',
        'family/invoices',
        _invoices([
          _invoice(
            'i2',
            status: 'paid',
            label: 'مدفوعة',
            outstanding: 0,
            document: 'https://demo.example/billing/i2?signature=x',
            lines: [
              {'kind': 'discount', 'description': 'خصم الإخوات', 'amount': '-40 ر.س.'},
            ],
          ),
        ]),
      );
    await _open(tester, app);

    expect(find.text('الحصص الفاضلة'), findsOneWidget);
    expect(find.text('قرآن'), findsOneWidget);
    expect(find.text('حصة واحدة'), findsOneWidget);
    expect(find.text('عربي وإنجليزي · باقة مشتركة'), findsOneWidget);
    // Nothing owed: no card asking to pay.
    expect(find.text('عليك دلوقتي'), findsNothing);
    expect(find.text('مدفوعة'), findsOneWidget);
    expect(find.text('خصم الإخوات  -40 ر.س.'), findsOneWidget);

    // The row opens the invoice's printable document (with the tax QR).
    await tester.tap(find.text('مدفوعة'));
    await frames(tester, 3);
    expect(launcher.urls, contains('https://demo.example/billing/i2?signature=x'));
    await _close(tester);
  });

  testWidgets('paying online: the gateway picked, one checkout with an idempotency key, the gateway\'s page, and back', (tester) async {
    final app = await _money(tester);
    var paid = false;
    app.api
      ..onWith(
        'GET',
        'family/invoices',
        (_) => (
          200,
          _invoices([
            paid
                ? _invoice('i1', status: 'paid', label: 'مدفوعة', outstanding: 0)
                : _invoice(
                    'i1',
                    payWith: [
                      {'account_id': 'g1', 'label': 'Stripe'},
                      {'account_id': 'g2', 'label': 'مدى (Moyasar)'},
                    ],
                  ),
          ], proofs: false),
        ),
      )
      ..on('POST', 'family/invoices/i1/checkout', {'url': 'https://pay.example/checkout/abc'});
    await _open(tester, app);

    expect(find.text('عليك دلوقتي'), findsOneWidget);
    // This academy takes no proofs: paying online is the way.
    expect(find.text('دفعت بالتحويل'), findsNothing);
    await tester.tap(find.text('ادفع 400 ر.س.'));
    await frames(tester, 6);
    expect(find.text('تدفع بإيه؟'), findsOneWidget);
    await tester.tap(find.text('مدى (Moyasar)'));
    await frames(tester, 8);

    final checkout = app.api.sent('POST', 'family/invoices/i1/checkout').single;
    expect(checkout.data, {'account_id': 'g2'});
    expect(checkout.headers['Idempotency-Key'], isNotNull);
    expect(launcher.opened.single, ('https://pay.example/checkout/abc', PreferredLaunchMode.externalApplication));

    // Back from the gateway's page, the money reloads and shows the gateway's answer.
    paid = true;
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    await frames(tester, 2);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await frames(tester, 10);
    expect(find.text('عليك دلوقتي'), findsNothing);
    expect(find.text('مدفوعة'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a checkout the gateway refuses shows its words and the button comes back', (tester) async {
    final app = await _money(tester);
    app.api
      ..on(
        'GET',
        'family/invoices',
        _invoices([
          _invoice(
            'i1',
            payWith: [
              {'account_id': 'g1', 'label': 'Stripe'},
            ],
          ),
        ], proofs: false),
      )
      ..on('POST', 'family/invoices/i1/checkout', {
        'type': 'gateway-unavailable',
        'title': 'بوابة الدفع مش متاحة دلوقتي. جرّب كمان شوية.',
        'status': 503,
      }, status: 503);
    await _open(tester, app);

    await tester.tap(find.text('ادفع 400 ر.س.'));
    await frames(tester, 8);
    expect(find.text('بوابة الدفع مش متاحة دلوقتي. جرّب كمان شوية.'), findsOneWidget);
    expect(launcher.opened, isEmpty);
    final pay = find.ancestor(of: find.text('ادفع 400 ر.س.'), matching: find.byWidgetPredicate((w) => w is ButtonStyleButton));
    expect(tester.widget<ButtonStyleButton>(pay.first).onPressed, isNotNull);
    await _close(tester);
  });

  testWidgets('sending a transfer receipt: the file, the amount in Arabic digits, once, then it waits with no button to press', (
    tester,
  ) async {
    final app = await _money(tester);
    var sent = false;
    app.api
      ..onWith(
        'GET',
        'family/invoices',
        (_) => (
          200,
          _invoices([
            _invoice(
              'i1',
              canSend: !sent,
              proof: sent ? {'status': 'pending', 'text': 'إثبات دفع 400 ر.س. اتبعت، ومستني الأكاديمية'} : null,
            ),
          ]),
        ),
      )
      ..onWith('POST', 'family/invoices/i1/proofs', (_) {
        sent = true;
        return (
          201,
          {
            'data': {'status': 'pending', 'text': 'إثبات دفع 400 ر.س. اتبعت، ومستني الأكاديمية'},
          },
        );
      });
    picker.next = _Picked('receipt.jpg', Uint8List.fromList([0xFF, 0xD8, 0xFF, 1, 2, 3]));
    await _open(tester, app);

    await tester.tap(find.text('دفعت بالتحويل'));
    await frames(tester, 10);
    expect(find.text('حوّل على حساب الأكاديمية في بنك الراجحي'), findsOneWidget);
    await tester.tap(find.text('اختار صورة أو PDF'));
    await frames(tester, 4);
    expect(picker.asked, containsAll(['jpg', 'png', 'pdf']));
    expect(find.text('receipt.jpg'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextField, 'المبلغ اللي دفعته'), '٤٠٠٫٥٠');
    await tester.enterText(find.widgetWithText(TextField, 'رقم التحويل (اختياري)'), 'TR-1');
    await tester.ensureVisible(find.text('ابعت الإثبات'));
    await tester.tap(find.text('ابعت الإثبات'));
    await frames(tester, 10);

    final post = app.api.sent('POST', 'family/invoices/i1/proofs').single;
    expect(post.headers['Idempotency-Key'], isNotNull);
    final form = post.data as FormData;
    final fields = Map.fromEntries(form.fields);
    expect(fields['amount'], '400.50');
    expect(fields['method'], 'bank_transfer');
    expect(fields['reference'], 'TR-1');
    expect(fields['paid_on'], DateTime.now().toIso8601String().substring(0, 10));
    expect(form.files.single.value.filename, 'receipt.jpg');
    expect(form.files.single.value.contentType.toString(), 'image/jpeg');
    expect(find.text('الإثبات اتبعت. هنقولك أول ما الأكاديمية تأكّده.'), findsOneWidget);

    // Waiting for the academy: where it stands, and nothing to press (no gateway, no second proof).
    expect(find.text('إثبات دفع 400 ر.س. اتبعت، ومستني الأكاديمية'), findsWidgets);
    expect(find.text('دفعت بالتحويل'), findsNothing);
    expect(find.text('ادفع 400 ر.س.'), findsNothing);
    await _close(tester);
  });

  testWidgets('a receipt the academy turned down: its reason, and sending another', (tester) async {
    final app = await _money(tester);
    app.api
      ..on(
        'GET',
        'family/invoices',
        _invoices([
          _invoice(
            'i1',
            canSend: true,
            proof: {'status': 'rejected', 'text': 'إثبات الدفع اترفض: المبلغ ماوصلش الحساب', 'reason': 'المبلغ ماوصلش الحساب'},
          ),
        ]),
      )
      ..on('POST', 'family/invoices/i1/proofs', {
        'data': {'status': 'pending', 'text': 'اتبعت'},
      }, status: 201);
    picker.next = _Picked('bank.pdf', Uint8List.fromList(List.filled(64, 1)));
    await _open(tester, app);

    expect(find.text('إثبات الدفع اترفض: المبلغ ماوصلش الحساب'), findsWidgets);
    await tester.tap(find.text('دفعت بالتحويل'));
    await frames(tester, 10);
    await tester.tap(find.text('اختار صورة أو PDF'));
    await frames(tester, 4);
    await tester.ensureVisible(find.text('ابعت الإثبات'));
    await tester.tap(find.text('ابعت الإثبات'));
    await frames(tester, 10);
    final form = app.api.sent('POST', 'family/invoices/i1/proofs').single.data as FormData;
    expect(form.files.single.value.contentType.toString(), 'application/pdf');
    expect(Map.fromEntries(form.fields)['amount'], '400.00');
    await _close(tester);
  });

  testWidgets('a receipt over 10 MB is turned down on the phone, before it is read or sent', (tester) async {
    final app = await _money(tester);
    app.api.on('GET', 'family/invoices', _invoices([_invoice('i1', canSend: true)]));
    picker.next = _Picked('video.png', Uint8List(0), size: 80 * 1024 * 1024);
    await _open(tester, app);

    await tester.tap(find.text('دفعت بالتحويل'));
    await frames(tester, 10);
    await tester.tap(find.text('اختار صورة أو PDF'));
    await frames(tester, 4);
    expect(find.text('الملف أكبر من 10 ميجا.'), findsOneWidget);
    await tester.ensureVisible(find.text('ابعت الإثبات'));
    await tester.tap(find.text('ابعت الإثبات'));
    await frames(tester, 4);
    expect(find.text('اختار الإيصال الأول.'), findsOneWidget);
    expect(app.api.sent('POST', 'family/invoices/i1/proofs'), isEmpty);
    await _close(tester);
  });

  testWidgets('a student in their own look has no money tab, and its address leads home', (tester) async {
    const teen = Account(
      academySlug: 'demo',
      academyNameAr: 'أكاديمية النور',
      academyNameEn: 'Noor Academy',
      userId: 'u-teen',
      userName: 'مريم',
      roles: ['student'],
      token: 'token-teen',
      stage: 'teen',
    );
    final app = await _money(tester, account: teen);
    app.api
      ..on('GET', 'family/learners', {
        'data': [
          {'id': 'u-teen', 'name': 'مريم', 'is_self': true, 'stage': 'teen'},
        ],
        'viewer_stage': 'teen',
      })
      ..on('GET', 'learning/journey/map', quietJourney(viewer: 'student', stage: 'teen'))
      ..on('GET', 'family/invoices', _invoices([_invoice('i1', canSend: true)]));
    await _open(tester, app);

    expect(find.byType(MoneyPage), findsNothing);
    expect(app.api.sent('GET', 'family/invoices'), isEmpty);
    await _close(tester);
  });
}
