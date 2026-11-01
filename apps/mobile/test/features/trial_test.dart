import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/prefs/app_prefs.dart';
import 'package:academy_app/features/auth/presentation/widgets/code_boxes.dart';
import 'package:academy_app/features/trial/presentation/widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../fixtures/trial.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// The app's own faces, so lines measure as on a phone (tests otherwise draw every letter as a square).
Future<void> _loadFonts() async {
  const faces = {
    'ReadexPro': ['400', '600', '700'],
    'IBMPlexSansArabic': ['400', '500', '600', '700'],
  };
  for (final MapEntry(key: family, value: weights) in faces.entries) {
    final loader = FontLoader(family);
    for (final w in weights) {
      loader.addFont(rootBundle.load('assets/fonts/$family-$w.ttf'));
    }
    await loader.load();
  }
}

Future<TestApp> _open(WidgetTester tester, {String booking = 'family', Object? times, String? helpSubject}) async {
  final app = await TestApp.create();
  app.api
    ..on('GET', 'branding', branding())
    ..on('GET', 'site', sitePage(booking: booking, helpChooseSubjectId: helpSubject))
    ..on('GET', 'site/trial-slots', times ?? slots());
  await app.session.start();
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester);
  return app;
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
}

Future<void> _tap(WidgetTester tester, Finder f, [int n = 6]) async {
  await tester.ensureVisible(f);
  // A field that keeps its caret on screen may still be scrolling: let it settle.
  await frames(tester, 6);
  await tester.tap(f);
  await frames(tester, n);
}

/// From the welcome screen to who is learning, through the academy's code.
Future<void> _toWho(WidgetTester tester) async {
  await _tap(tester, find.byKey(const Key('book-trial')));
  await tester.enterText(find.byKey(const Key('trial-academy')), 'Demo ');
  await _tap(tester, find.text('التالي'));
}

/// A parent for an eight-year-old: up to the subjects.
Future<void> _toSubject(WidgetTester tester) async {
  await _toWho(tester);
  await _tap(tester, find.byKey(const Key('trial-who-child')));
  await tester.enterText(find.byKey(const Key('trial-child-name')), 'سلمى');
  await _tap(tester, find.byKey(const Key('trial-age-8')), 2);
  await tester.enterText(find.byKey(const Key('trial-name')), 'رامي فؤاد');
  await _tap(tester, find.text('التالي'));
}

/// The Quran, no wishes: up to the times.
Future<void> _toTimes(WidgetTester tester) async {
  await _toSubject(tester);
  await _tap(tester, find.byKey(const Key('trial-subject-sub-quran')));
  await _tap(tester, find.text('التالي'));
}

/// Saturday 6 pm, then the number: up to the code.
Future<void> _toCode(WidgetTester tester) async {
  await _toTimes(tester);
  await _tap(tester, find.byKey(const Key('trial-slot-2099-11-07T18:00:00+03:00')), 2);
  await _tap(tester, find.text('احجز هذا الموعد'));
  await tester.enterText(find.byKey(const Key('trial-phone')), '0501234504');
  await _tap(tester, find.byKey(const Key('trial-send')));
}

void main() {
  testWidgets('the welcome screen offers a free trial; a wrong academy code says so, the right one opens its page', (tester) async {
    final app = await _open(tester);
    expect(find.text('جديد معنا؟ احجز حصة تجريبية مجانية'), findsOneWidget);
    await _tap(tester, find.byKey(const Key('book-trial')));
    expect(find.text('في أي أكاديمية تريد الحصة؟'), findsOneWidget);

    // Empty: said here, nothing asked.
    await _tap(tester, find.text('التالي'), 3);
    expect(find.text('اكتب رمز الأكاديمية.'), findsOneWidget);

    app.api.on('GET', 'site', {'type': 'not-found', 'title': 'غير موجود.', 'status': 404}, status: 404);
    await tester.enterText(find.byKey(const Key('trial-academy')), 'nowhere');
    await _tap(tester, find.text('التالي'));
    expect(find.textContaining('لم نجد أكاديمية تستقبل طلبات الحصص التجريبية بهذا الرمز'), findsOneWidget);
    expect(app.api.sent('GET', 'site').single.headers['X-Academy'], 'nowhere');

    app.api.on('GET', 'site', sitePage());
    await tester.enterText(find.byKey(const Key('trial-academy')), 'Demo ');
    await _tap(tester, find.text('التالي'));
    expect(find.text('لمن الحصة؟'), findsOneWidget);
    expect(find.byKey(const Key('trial-academy-name')), findsOneWidget);
    expect(find.bySemanticsLabel('الخطوة 1 من 6'), findsOneWidget);
    expect(app.api.sent('GET', 'site').last.headers['X-Academy'], 'demo');
    // Remembered for next time.
    expect(await AppPrefs(SharedPreferencesAsync()).trialAcademy(), 'demo');
    await _close(tester);
  });

  testWidgets('the academy asked for last opens by itself next time', (tester) async {
    final app = await _open(tester);
    await AppPrefs(SharedPreferencesAsync()).setTrialAcademy('demo');
    await _tap(tester, find.byKey(const Key('book-trial')));
    expect(find.text('لمن الحصة؟'), findsOneWidget);
    expect(app.api.sent('GET', 'site').single.headers['X-Academy'], 'demo');

    // Another academy: back to its code, with the one remembered typed in.
    await _tap(tester, find.byKey(const Key('trial-other-academy')));
    expect(find.text('في أي أكاديمية تريد الحصة؟'), findsOneWidget);
    expect(tester.widget<TextField>(find.byKey(const Key('trial-academy'))).controller!.text, 'demo');
    await _close(tester);
  });

  testWidgets('who is learning says what is missing, and keeps what was typed going back and forth', (tester) async {
    await _open(tester);
    await _toWho(tester);
    await _tap(tester, find.text('التالي'), 3);
    expect(find.text('اختر لمن الحصة.'), findsOneWidget);

    await _tap(tester, find.byKey(const Key('trial-who-child')));
    await _tap(tester, find.text('التالي'), 3);
    expect(find.text('اكتب الاسم.'), findsNWidgets(2));
    expect(find.text('اختر عمر طفلك.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('trial-child-name')), 'سلمى');
    await _tap(tester, find.byKey(const Key('trial-age-8')), 2);
    await tester.enterText(find.byKey(const Key('trial-name')), 'ر');
    await _tap(tester, find.text('التالي'), 3);
    expect(find.text('اكتب الاسم كاملًا.'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('trial-name')), 'رامي فؤاد');
    await _tap(tester, find.text('التالي'));
    expect(find.text('اختر المادة'), findsOneWidget);
    expect(find.text('ساعدوني في الاختيار'), findsOneWidget);

    await _tap(tester, find.byKey(const Key('trial-back')));
    expect(find.text('لمن الحصة؟'), findsOneWidget);
    expect(find.text('سلمى'), findsOneWidget);
    expect(find.text('رامي فؤاد'), findsOneWidget);
    expect(tester.widget<TrialPill>(find.byKey(const Key('trial-age-8'))).selected, isTrue);
    // Each age is read out as an age.
    await tester.ensureVisible(find.byKey(const Key('trial-age-8')));
    await frames(tester, 2);
    expect(find.bySemanticsLabel('8 سنوات'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('the times come by day, soonest first; a time is read out whole and chosen in the API\'s words', (tester) async {
    final app = await _open(tester);
    await _toTimes(tester);
    expect(find.text('اختر موعد الحصة'), findsOneWidget);
    final asked = app.api.sent('GET', 'site/trial-slots').single.queryParameters;
    expect(asked['subject_id'], 'sub-quran');
    expect(asked['birth_year'], DateTime.now().year - 8);
    expect(asked['timezone'], 'Asia/Riyadh');
    expect(asked.containsKey('teacher_gender'), isFalse);

    expect(find.byKey(const Key('trial-day-2099-11-07')), findsOneWidget);
    expect(find.byKey(const Key('trial-day-2099-11-08')), findsOneWidget);
    // The first day's times; the button waits for one.
    expect(find.byKey(const Key('trial-slot-2099-11-07T09:00:00+03:00')), findsOneWidget);
    expect(find.byKey(const Key('trial-slot-2099-11-08T19:30:00+03:00')), findsNothing);
    expect(find.text('اختر موعدًا من القائمة.'), findsOneWidget);
    expect(find.bySemanticsLabel('السبت 7 نوفمبر · 6:00 م'), findsOneWidget);

    await _tap(tester, find.byKey(const Key('trial-day-2099-11-08')), 4);
    await _tap(tester, find.byKey(const Key('trial-slot-2099-11-08T19:30:00+03:00')), 2);
    expect(find.text('اخترت: الأحد 8 نوفمبر · 7:30 م'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('the days and hours they gave narrow the times, unless nothing fits', (tester) async {
    await _open(tester);
    await _toSubject(tester);
    await _tap(tester, find.byKey(const Key('trial-subject-sub-quran')));
    // Evenings on Sunday.
    await _tap(tester, find.byKey(const Key('trial-day-7')), 2);
    await _tap(tester, find.byKey(const Key('trial-part-evening')), 2);
    await _tap(tester, find.text('التالي'));
    expect(find.text('ما يناسبكم فقط'), findsOneWidget);
    expect(find.byKey(const Key('trial-day-2099-11-07')), findsNothing);
    expect(find.byKey(const Key('trial-slot-2099-11-08T19:30:00+03:00')), findsOneWidget);

    await _tap(tester, find.text('ما يناسبكم فقط'), 4);
    expect(find.byKey(const Key('trial-day-2099-11-07')), findsOneWidget);

    // Mornings on Friday: nothing fits, so all of them, said plainly.
    await _tap(tester, find.byKey(const Key('trial-back')));
    await _tap(tester, find.byKey(const Key('trial-day-7')), 2);
    await _tap(tester, find.byKey(const Key('trial-day-5')), 2);
    await _tap(tester, find.byKey(const Key('trial-part-morning')), 2);
    await _tap(tester, find.text('التالي'));
    expect(find.text('لا مواعيد في الأيام والساعات التي اخترتها، فهذه كل المواعيد المتاحة.'), findsOneWidget);
    expect(find.byKey(const Key('trial-day-2099-11-07')), findsOneWidget);
    await _close(tester);
  });

  testWidgets('no time at all: another teacher, or the request goes instead and the academy answers it', (tester) async {
    final app = await _open(tester, times: slots([]));
    app.api.on('POST', 'site/requests', {
      'data': {'received': true},
    }, status: 201);
    await _toSubject(tester);
    await _tap(tester, find.byKey(const Key('trial-subject-sub-quran')));
    await _tap(tester, find.byKey(const Key('trial-teacher-female')), 2);
    await _tap(tester, find.text('التالي'));
    expect(find.text('لا توجد مواعيد متاحة الآن'), findsOneWidget);
    expect(app.api.sent('GET', 'site/trial-slots').single.queryParameters['teacher_gender'], 'female');

    await _tap(tester, find.text('أظهر مواعيد كل المعلّمين'));
    expect(app.api.sent('GET', 'site/trial-slots').last.queryParameters.containsKey('teacher_gender'), isFalse);
    expect(find.textContaining('لا يوجد معلّم متاح لهذه المادة خلال الأسبوعين القادمين'), findsOneWidget);
    expect(find.textContaining('4 ساعات'), findsOneWidget);

    await _tap(tester, find.text('أرسل الطلب دون موعد'));
    expect(find.text('كيف نتواصل معك؟'), findsOneWidget);
    expect(find.text('نتفق على الموعد معك'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('trial-phone')), '0501234504');
    await _tap(tester, find.text('أرسل الطلب'), 60);
    final sent = app.api.sent('POST', 'site/requests').single;
    expect(sent.data, {
      'name': 'رامي فؤاد',
      'phone': '+966501234504',
      'timezone': 'Asia/Riyadh',
      'subject_id': 'sub-quran',
      'for_child': true,
      'child_name': 'سلمى',
      'child_birth_year': DateTime.now().year - 8,
      'kind': 'trial',
      'form_token': 'form-token-1',
    });
    expect(sent.headers['X-Academy'], 'demo');
    expect(find.text('وصل طلبك'), findsOneWidget);
    expect(find.text('نتواصل معك على واتساب خلال 4 ساعات لنتفق على موعد الحصة.'), findsOneWidget);
    // While families book, the message carries a link to pick a time.
    expect(find.textContaining('رابط تختار منه الموعد بنفسك'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('the code books the time; the success says when, big, and what happens next', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'site/trials/code', codeSent(), status: 202)
      ..on('POST', 'site/trials', booked(), status: 201);
    await _toCode(tester);
    expect(app.api.sent('POST', 'site/trials/code').single.data, {'phone': '+966501234504'});
    expect(find.text('اكتب الرمز'), findsOneWidget);
    expect(find.textContaining('نحجز الموعد فور كتابة الرمز.'), findsOneWidget);
    expect(find.textContaining('أرسل رمزًا جديدًا بعد'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 12);
    final sent = app.api.sent('POST', 'site/trials').single;
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(sent.data, containsPair('challenge_id', 'ch-trial'));
    expect(sent.data, containsPair('code', '123456'));
    expect(sent.data, containsPair('starts_at', '2099-11-07T15:00:00.000Z'));
    expect(sent.data, containsPair('child_name', 'سلمى'));

    expect(find.text('حُجزت الحصة التجريبية'), findsOneWidget);
    expect(find.text('القرآن الكريم مع أكاديمية النور'), findsOneWidget);
    expect(find.bySemanticsLabel(RegExp('السبت 7 نوفمبر · 6:00 م')), findsOneWidget);
    expect(find.text('ما الذي يحدث الآن'), findsOneWidget);
    expect(find.text('احجز لطفل آخر'), findsOneWidget);

    // Another child: the parent and the number stay, the rest starts again.
    await _tap(tester, find.byKey(const Key('trial-another')));
    expect(find.text('لمن الحصة؟'), findsOneWidget);
    expect(find.text('رامي فؤاد'), findsOneWidget);
    expect(find.text('سلمى'), findsNothing);
    await _close(tester);
  });

  testWidgets('signing in after booking starts with the same number', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'site/trials/code', codeSent(), status: 202)
      ..on('POST', 'site/trials', booked(), status: 201);
    await _toCode(tester);
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 12);
    await _tap(tester, find.byKey(const Key('trial-sign-in')), 12);
    expect(find.text('أهلًا بك'), findsOneWidget);
    expect(tester.widget<TextField>(find.byKey(const Key('contact'))).controller!.text, '+966501234504');
    await _close(tester);
  });

  testWidgets('a wrong code says how many tries are left; the fifth locks it and only a new code helps', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'site/trials/code', codeSent(resendAfter: 0), status: 202)
      ..on('POST', 'site/trials', refused('code', 'الرمز غير صحيح. بقيت 4 محاولات.'), status: 422);
    await _toCode(tester);
    await tester.enterText(find.byKey(const Key('code')), '000000');
    await frames(tester, 4);
    expect(find.text('الرمز غير صحيح. بقيت 4 محاولات.'), findsOneWidget);
    expect(tester.widget<CodeBoxes>(find.byType(CodeBoxes)).look, CodeLook.wrong);
    expect(tester.widget<CodeBoxes>(find.byType(CodeBoxes)).enabled, isTrue);

    for (var i = 0; i < 3; i++) {
      await tester.enterText(find.byKey(const Key('code')), '00000$i');
      await frames(tester, 4);
    }
    app.api.on('POST', 'site/trials', refused('code', 'كتبت رمزًا خاطئًا مرات كثيرة. اطلب رمزًا جديدًا.'), status: 422);
    await tester.enterText(find.byKey(const Key('code')), '000009');
    await frames(tester, 4);
    expect(find.text('الرمز مقفل'), findsOneWidget);
    expect(find.text('كتبت رمزًا خاطئًا مرات كثيرة. اطلب رمزًا جديدًا.'), findsOneWidget);
    expect(tester.widget<CodeBoxes>(find.byType(CodeBoxes)).enabled, isFalse);

    await _tap(tester, find.byKey(const Key('trial-new-code')));
    expect(app.api.sent('POST', 'site/trials/code'), hasLength(2));
    expect(find.text('أرسلنا إليك رمزًا جديدًا.'), findsOneWidget);
    expect(tester.widget<CodeBoxes>(find.byType(CodeBoxes)).enabled, isTrue);
    await _close(tester);
  });

  testWidgets('an ended code says so, distinct from a wrong one', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'site/trials/code', codeSent(expires: '2000-01-01T00:00:00Z'), status: 202)
      ..on('POST', 'site/trials', refused('code', 'انتهى هذا الرمز. اطلب رمزًا جديدًا.'), status: 422);
    await _toCode(tester);
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 4);
    expect(find.text('انتهى الرمز'), findsOneWidget);
    expect(find.text('انتهى هذا الرمز. اطلب رمزًا جديدًا.'), findsOneWidget);
    expect(find.text('الرمز مقفل'), findsNothing);
    await _close(tester);
  });

  testWidgets('a time taken meanwhile: the API\'s words over the fresh times, and nothing typed is lost', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'site/trials/code', codeSent(resendAfter: 0), status: 202)
      ..on(
        'POST',
        'site/trials',
        refused('starts_at', 'سبقكم أحد إلى هذا الموعد قبل لحظات. حدّثنا قائمة المواعيد، فاختاروا موعدًا آخر منها.'),
        status: 422,
      );
    await _toCode(tester);
    // Saturday 6 pm went.
    app.api.on(
      'GET',
      'site/trial-slots',
      slots([slot('2099-11-07', '09:00'), slot('2099-11-08', '19:30', label: 'الأحد 8 نوفمبر · 7:30 م')]),
    );
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 10);
    expect(find.text('اختر موعد الحصة'), findsOneWidget);
    expect(find.text('سبقكم أحد إلى هذا الموعد قبل لحظات. حدّثنا قائمة المواعيد، فاختاروا موعدًا آخر منها.'), findsOneWidget);
    expect(find.byKey(const Key('trial-slot-2099-11-07T18:00:00+03:00')), findsNothing);

    app.api.on('POST', 'site/trials', booked(), status: 201);
    await _tap(tester, find.byKey(const Key('trial-day-2099-11-08')), 4);
    await _tap(tester, find.byKey(const Key('trial-slot-2099-11-08T19:30:00+03:00')), 2);
    await _tap(tester, find.text('احجز هذا الموعد'));
    // The number is still there; the spent code is replaced by a new one.
    expect(tester.widget<TextField>(find.byKey(const Key('trial-phone'))).controller!.text, '+966501234504');
    expect(find.text('الحصة لـسلمى'), findsOneWidget);
    await _tap(tester, find.byKey(const Key('trial-send')));
    expect(app.api.sent('POST', 'site/trials/code'), hasLength(2));
    await tester.enterText(find.byKey(const Key('code')), '654321');
    await frames(tester, 12);
    expect(find.text('حُجزت الحصة التجريبية'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a trial the academy cannot book from here: its words, and the ways on', (tester) async {
    final app = await _open(tester);
    const words =
        'لهذا الطالب حصة تجريبية محجوزة في هذه المادة بالفعل، وموعدها في رسالتنا على واتساب. يمكنكم تغيير موعدها من الرابط الذي فيها.';
    app.api
      ..on('POST', 'site/trials/code', codeSent(), status: 202)
      ..on('POST', 'site/trials', refused('starts_at', words), status: 422);
    await _toCode(tester);
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 10);
    expect(find.text('لا يمكن حجز هذه الحصة من هنا'), findsOneWidget);
    expect(find.text(words), findsOneWidget);
    expect(find.text('راسل الأكاديمية على واتساب'), findsOneWidget);
    expect(find.text('ادخل بهذا الرقم'), findsOneWidget);

    await _tap(tester, find.byKey(const Key('trial-refused-send')));
    expect(find.text('كيف نتواصل معك؟'), findsOneWidget);
    expect(find.text('أفضّل أن أختار الموعد بنفسي'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a field the API refuses brings its step back with its words', (tester) async {
    final app = await _open(tester);
    app.api
      ..on('POST', 'site/trials/code', codeSent(), status: 202)
      ..on('POST', 'site/trials', refused('child_name', 'اسم الطفل طويل جدًا.'), status: 422);
    await _toCode(tester);
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 10);
    expect(find.text('لمن الحصة؟'), findsOneWidget);
    expect(find.text('اسم الطفل طويل جدًا.'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('too many codes: the wait runs down on the number\'s step', (tester) async {
    final app = await _open(tester);
    app.api.on(
      'POST',
      'site/trials/code',
      {'type': 'too-many-requests', 'title': 'محاولات كثيرة.', 'status': 429, 'detail': 'انتظر 40 ثانية قبل أن تطلب رمزًا آخر.'},
      status: 429,
      headers: {'retry-after': '40'},
    );
    await _toCode(tester);
    expect(find.text('رقم هاتفك'), findsOneWidget);
    expect(find.byKey(const Key('code')), findsNothing);
    expect(find.textContaining('طُلبت رموز كثيرة من هذا الجهاز أو لهذا الرقم'), findsOneWidget);
    final button = tester.widget<FilledButton>(
      find.descendant(of: find.byKey(const Key('trial-send')), matching: find.byType(FilledButton)),
    );
    expect(button.onPressed, isNull);
    await _close(tester);
  });

  testWidgets('an academy whose staff book the trials: no times, the request, and when they will hear', (tester) async {
    final app = await _open(tester, booking: 'staff');
    app.api.on('POST', 'site/requests', {
      'data': {'received': true},
    }, status: 201);
    await _toSubject(tester);
    expect(find.bySemanticsLabel('الخطوة 2 من 4'), findsOneWidget);
    await _tap(tester, find.byKey(const Key('trial-subject-sub-arabic')));
    await _tap(tester, find.text('التالي'));
    expect(find.text('كيف نتواصل معك؟'), findsOneWidget);
    expect(app.api.sent('GET', 'site/trial-slots'), isEmpty);
    expect(find.text('أفضّل أن أختار الموعد بنفسي'), findsNothing);
    await tester.enterText(find.byKey(const Key('trial-phone')), '0501234504');
    await tester.enterText(find.byKey(const Key('trial-email')), 'rami@mail.test');
    await _tap(tester, find.text('أرسل الطلب'), 60);
    expect(app.api.sent('POST', 'site/requests').single.data, containsPair('email', 'rami@mail.test'));
    expect(find.text('وصل طلبك'), findsOneWidget);
    expect(find.text('تصلك الآن رسالة تأكيد على واتساب.'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('help me choose: no subject, no times; the academy chooses with the family', (tester) async {
    final app = await _open(tester);
    app.api.on('POST', 'site/requests', {
      'data': {'received': true},
    }, status: 201);
    await _toSubject(tester);
    await _tap(tester, find.byKey(const Key('trial-help-choose')));
    await _tap(tester, find.text('التالي'));
    expect(find.text('نختار المادة معًا'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('trial-phone')), '+201001234567');
    await _tap(tester, find.text('أرسل الطلب'), 60);
    final data = app.api.sent('POST', 'site/requests').single.data as Map;
    expect(data['help_choose'], isTrue);
    expect(data.containsKey('subject_id'), isFalse);
    expect(data['phone'], '+201001234567');
    expect(find.textContaining('لنختار معًا المادة والموعد'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('help me choose where the academy starts with its own subject: its times, booked as help choosing', (tester) async {
    final app = await _open(tester, helpSubject: 'sub-quran');
    app.api
      ..on('POST', 'site/trials/code', codeSent(), status: 202)
      ..on('POST', 'site/trials', booked(teacher: 'أ. منى', minutes: 30), status: 201);
    await _toSubject(tester);
    expect(find.text('نبدأ بحصة في القرآن الكريم، ونختار معًا ما يناسب بعدها.'), findsOneWidget);
    await _tap(tester, find.byKey(const Key('trial-help-choose')));
    await _tap(tester, find.text('التالي'));
    expect(find.text('اختر موعد الحصة'), findsOneWidget);
    expect(app.api.sent('GET', 'site/trial-slots').single.queryParameters['subject_id'], 'sub-quran');
    await _tap(tester, find.byKey(const Key('trial-slot-2099-11-07T18:00:00+03:00')), 2);
    await _tap(tester, find.text('احجز هذا الموعد'));
    expect(find.text('نختار المادة معًا'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('trial-phone')), '0501234504');
    await _tap(tester, find.byKey(const Key('trial-send')));
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 12);
    final data = app.api.sent('POST', 'site/trials').single.data as Map;
    expect(data['help_choose'], isTrue);
    expect(data.containsKey('subject_id'), isFalse);
    // The teacher and the length, when the API says them.
    expect(find.text('مع أ. منى · 30 دقيقة'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a small phone with large text reaches every button of each step', (tester) async {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = const Size(320 * 2, 600 * 2);
    tester.platformDispatcher.textScaleFactorTestValue = 1.3;
    addTearDown(() {
      tester.view.reset();
      tester.platformDispatcher.clearTextScaleFactorTestValue();
    });
    await _loadFonts();
    final app = await _open(tester);
    app.api
      ..on('POST', 'site/trials/code', codeSent(), status: 202)
      ..on('POST', 'site/trials', booked(), status: 201);
    await _toCode(tester);
    expect(find.text('اكتب الرمز'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('code')), '123456');
    await frames(tester, 12);
    expect(find.text('حُجزت الحصة التجريبية'), findsOneWidget);
    await tester.ensureVisible(find.byKey(const Key('trial-later')));
    await frames(tester, 4);
    expect(tester.takeException(), isNull);
    await _close(tester);
  });
}
