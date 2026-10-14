import 'package:academy_app/app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// The app's own faces, so lines measure as they do on a phone (tests otherwise draw every letter as a square).
Future<void> _loadFonts() async {
  const faces = {
    'ReadexPro': ['400', '600', '700'],
    'IBMPlexSansArabic': ['400', '500', '600', '700'],
    'BalooBhaijaan2': ['500', '700', '800'],
    'Rubik': ['400', '500', '700', '800'],
  };
  for (final MapEntry(key: family, value: weights) in faces.entries) {
    final loader = FontLoader(family);
    for (final w in weights) {
      loader.addFont(rootBundle.load('assets/fonts/$family-$w.ttf'));
    }
    await loader.load();
  }
}

/// A small Android phone ([width] dp wide) with the text made larger in the phone's settings.
Future<void> _phone(WidgetTester tester, double width, {double text = 1.3}) async {
  tester.view
    ..devicePixelRatio = 2
    ..physicalSize = Size(width * 2, 640 * 2);
  tester.platformDispatcher.textScaleFactorTestValue = text;
  addTearDown(() {
    tester.view.reset();
    tester.platformDispatcher.clearTextScaleFactorTestValue();
  });
}

Future<void> _go(WidgetTester tester, String location) async {
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go(location);
  await frames(tester, 12);
}

/// Scrolls the screen's list to its end, so every line of it is laid out.
Future<void> _scrollDown(WidgetTester tester) async {
  final lists = find.byType(Scrollable);
  if (lists.evaluate().isEmpty) return;
  for (var i = 0; i < 8; i++) {
    await tester.drag(lists.first, const Offset(0, -500), warnIfMissed: false);
    await frames(tester, 2);
  }
}

Map<String, Object?> _onNow(String id) => {
  ...lesson(id, canCancel: false, status: 'in_progress'),
  'status_label': 'شغالة',
  'subject': 'قرآن كريم وتجويد',
  'with': 'أ. منى إبراهيم عبد الرحمن',
  'join': {'open': true, 'opens_at': null},
};

Map<String, Object?> _everything(String id) => {
  ...lesson(id),
  'subject': 'لغة عربية للمرحلة الابتدائية',
  'with': 'أ. هبة سمير',
  'type': 'makeup',
  'type_label': 'تعويض',
  'unfunded': true,
  'substitute_for': 'أ. منى إبراهيم عبد الرحمن',
  'can_refuse_substitute': true,
  'join': {'open': false, 'opens_at': time('2099-10-11T15:50:00Z', '2099-10-11T18:50:00+03:00')},
};

Map<String, Object?> _disputable(String id) => {
  ...lesson(id, canCancel: false, status: 'teacher_absent'),
  'status_label': 'المدرس ما حضرش',
  'can_dispute': true,
  'dispute_until': time('2099-10-13T16:45:00Z', '2099-10-13T19:45:00+03:00'),
  'dispute_reasons': [
    {'key': 'teacher_came', 'label': 'المدرس حضر بس اتأخر'},
  ],
  'dispute': null,
};

void _familyData(FakeApi api) {
  familyRoutes(api);
  api
    ..on('GET', 'family/lessons', {
      'learner': {'id': 'c1', 'name': 'سلمى رامي'},
      'data': [_onNow('l0'), _everything('l1'), _disputable('l2')],
      'notices': ['الأكاديمية إجازة يوم الخميس 15 أكتوبر بمناسبة العيد، والحصص اللي فيه هتتعوّض.'],
    })
    ..on('GET', 'family/balance', {
      'data': [
        {
          'enrollment_id': 'e1',
          'subject': 'قرآن كريم وتجويد',
          'sessions': 1,
          'minutes': 45,
          'extra': {'price': '50 ر.س.', 'minutes': 45, 'max': 4, 'waiting_invoice': null},
        },
      ],
    })
    ..on('GET', 'family/invoices', {
      'owed': '1,250.50 ر.س.',
      'manual': {
        'note': 'حوّل على حساب الأكاديمية في بنك الراجحي رقم SA00 0000 0000 0000 0000',
        'takes_proofs': true,
        'methods': [
          {'value': 'bank_transfer', 'label': 'تحويل بنكي'},
          {'value': 'instapay', 'label': 'إنستاباي'},
        ],
      },
      'data': [
        {
          'id': 'i1',
          'number': 'NR-000123',
          'for': 'سلمى رامي ويوسف رامي · قرآن كريم وتجويد',
          'total': '1,250.50 ر.س.',
          'outstanding': '1,250.50 ر.س.',
          'outstanding_minor': 125050,
          'outstanding_decimal': '1250.50',
          'status': 'overdue',
          'status_label': 'متأخرة',
          'issued_at': time('2099-10-01T09:00:00Z', '2099-10-01T12:00:00+03:00'),
          'due_at': time('2099-10-08T09:00:00Z', '2099-10-08T12:00:00+03:00'),
          'family': true,
          'pay_with': [
            {'account_id': 'g1', 'label': 'Stripe'},
          ],
          'proof': {'status': 'rejected', 'text': 'إثبات الدفع اترفض: المبلغ اللي وصل أقل من المطلوب', 'reason': 'ناقص'},
          'can_send_proof': true,
          'lines': [
            {'kind': 'discount', 'description': 'خصم الإخوات (10%)', 'amount': '-125.00 ر.س.'},
          ],
        },
      ],
    });
}

void main() {
  setUpAll(_loadFonts);

  for (final width in [320.0, 360.0]) {
    testWidgets('sign-in fits a $width dp phone with large text', (tester) async {
      await _phone(tester, width);
      final app = await TestApp.create();
      app.api.on('GET', 'branding', branding());
      await app.session.start();
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester);
      expect(find.byKey(const Key('legal-privacy')), findsOneWidget);
      await tester.enterText(find.byKey(const Key('contact')), '0501234504');
      await frames(tester, 3);
      // The country code sits beside the number.
      expect(find.text('+966'), findsOneWidget);
      await tester.tap(find.text('عندك كلمة سر؟ ادخل بيها'));
      await frames(tester);
      await tester.pumpWidget(const SizedBox());
      await frames(tester, 2);
    });

    testWidgets('a family\'s home, lessons and money fit a $width dp phone with large text', (tester) async {
      await _phone(tester, width);
      final app = await TestApp.create(accounts: [guardian]);
      _familyData(app.api);
      await app.session.start();
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 15);
      expect(find.text('الحصة شغالة دلوقتي'), findsOneWidget);
      await _scrollDown(tester);

      await _go(tester, '/family/lessons');
      await _scrollDown(tester);
      // The fullest lesson's sheet (a cover, unpaid, a makeup, the room's time, cancelling), then the one to dispute.
      for (final id in ['l1', 'l2']) {
        await tester.ensureVisible(find.byKey(Key('lesson-$id')));
        await tester.tap(find.byKey(Key('lesson-$id')));
        await frames(tester, 8);
        final sheet = find.descendant(of: find.byType(BottomSheet), matching: find.byType(Scrollable));
        for (var i = 0; i < 4; i++) {
          await tester.drag(sheet.first, const Offset(0, -400), warnIfMissed: false);
          await frames(tester, 2);
        }
        if (id == 'l2') expect(find.text('اعترض'), findsOneWidget);
        Navigator.of(tester.element(find.byType(BottomSheet))).pop();
        await frames(tester, 8);
      }

      await _go(tester, '/family/money');
      await tester.tap(find.text('دفعت بالتحويل'));
      await frames(tester, 10);
      expect(find.text('ابعت إثبات الدفع'), findsOneWidget);
      await _scrollDown(tester);
      Navigator.of(tester.element(find.text('ابعت إثبات الدفع'))).pop();
      await frames(tester, 10);
      expect(find.text('ابعت إثبات الدفع'), findsNothing);
      await _scrollDown(tester);

      await tester.pumpWidget(const SizedBox());
      await frames(tester, 2);
    });

    testWidgets('a teacher\'s today, week, earnings and time off fit a $width dp phone with large text', (tester) async {
      await _phone(tester, width);
      final app = await TestApp.create(accounts: [teacher]);
      teacherRoutes(app.api);
      Map<String, Object?> money(String f) => {'formatted': f};
      app.api
        ..on('GET', 'teacher/lessons', {
          'from': '2099-10-11',
          'to': '2099-10-17',
          'count': 3,
          'minutes': 135,
          'data': [_onNow('l0'), _everything('l1'), _disputable('l2')],
        })
        ..on('GET', 'teacher/homework', {
          'data': [homeworkJson('h1', status: 'submitted', body: 'تم')],
        })
        ..on('GET', 'teacher/earnings', {
          'month': '2099-10',
          'label': 'أكتوبر 2099',
          'closed': false,
          'unpriced': 2,
          'totals': [
            {
              'lessons': 24,
              'hours': '18.5',
              'lesson_pay': money('12,345.50 ج.م.'),
              'bonuses': money('1,250.00 ج.م.'),
              'deductions': money('-350.00 ج.م.'),
              'total': money('13,245.50 ج.م.'),
            },
          ],
          'lessons': [
            {
              'when': 'الثلاثاء 6 أكتوبر · 19:00',
              'student': 'عبد الرحمن محمد إبراهيم',
              'subject': 'قرآن كريم وتجويد',
              'outcome': 'الطالب غاب',
              'share': '50%',
              'amount': '1,250.00 ج.م.',
              'negative': false,
              'corrected': true,
              'notes': ['اتصحّحت بعد مراجعة الأكاديمية'],
            },
          ],
          'adjustments': [
            {'kind': 'مكافأة الالتزام', 'note': 'ولا غياب الشهر ده', 'amount': '500.00 ج.م.', 'negative': false, 'at': '1 أكتوبر'},
          ],
        })
        ..on('GET', 'teacher/time-off', {
          'data': [
            {
              'id': 't1',
              'period': 'من الأحد 20 أكتوبر لحد الخميس 24 أكتوبر',
              'reason_label': 'إجازة سنوية',
              'note': 'سفر مع العيلة',
              'status': 'pending',
              'status_label': 'مستني الرد',
              'can_withdraw': true,
              'cover': {'total': 12, 'covered': 7},
            },
          ],
          'holidays': [
            {'name': 'عيد الأضحى', 'period': 'من 15 لحد 18 أكتوبر'},
          ],
        });
      await app.session.start();
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 15);
      await _scrollDown(tester);
      expect(find.text('تقرير مستنيك'), findsOneWidget);

      for (final place in ['/teach/week', '/teach/reports', '/teach/students', '/teach/more', '/teach/more/earnings']) {
        await _go(tester, place);
        await _scrollDown(tester);
      }
      await _go(tester, '/teach/more/time-off');
      await tester.tap(find.text('جزء من يوم'));
      await frames(tester, 4);
      await _scrollDown(tester);

      await tester.pumpWidget(const SizedBox());
      await frames(tester, 2);
    });
  }
}
