import 'package:academy_app/app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

void main() {
  testWidgets('a guardian cancels a lesson after reading the policy, once, with an idempotency key', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..on('GET', 'lessons/l1/cancellation', {
        'outcome': 'الإلغاء قبل الحصة بأكتر من 12 ساعة: الحصة هترجع لرصيدك.',
        'reasons': [
          {'value': 'illness', 'label': 'تعبان'},
          {'value': 'travel', 'label': 'سفر'},
        ],
      })
      ..on('POST', 'lessons/l1/cancel', {'data': lesson('l1', canCancel: false, status: 'cancelled')});
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1000));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);
    expect(find.text('حصص سلمى'), findsOneWidget);

    // Cancelling is in the lesson's own sheet, in red, last.
    await tester.tap(find.byKey(const Key('lesson-l1')));
    await frames(tester, 8);
    await tester.tap(find.text('إلغاء الحصة'));
    await frames(tester, 10);
    expect(find.text('الإلغاء قبل الحصة بأكتر من 12 ساعة: الحصة هترجع لرصيدك.'), findsOneWidget);

    // No reason yet: nothing is sent.
    await tester.tap(find.text('أيوه، ألغيها'));
    await frames(tester, 3);
    expect(find.text('اختار سبب الأول.'), findsOneWidget);
    expect(app.api.sent('POST', 'lessons/l1/cancel'), isEmpty);

    await tester.tap(find.text('سفر'));
    await tester.enterText(find.byType(TextField).last, 'مسافرين العمرة');
    await tester.tap(find.text('أيوه، ألغيها'));
    await frames(tester, 10);

    final cancel = app.api.sent('POST', 'lessons/l1/cancel').single;
    expect(cancel.data, {'reason': 'travel', 'note': 'مسافرين العمرة'});
    expect(cancel.headers['Idempotency-Key'], isNotNull);
    expect(find.text('الحصة اتلغت.'), findsOneWidget);
    // The list reloads after the cancel.
    expect(app.api.sent('GET', 'family/lessons').length, greaterThanOrEqualTo(2));

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
    await tester.binding.setSurfaceSize(null);
  });
}
