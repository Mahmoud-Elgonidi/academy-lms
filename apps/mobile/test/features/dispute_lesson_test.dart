import 'package:academy_app/app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// A lesson recorded as held that the family may still dispute (or, once disputed, waiting with the academy's answer).
Map<String, Object?> heldLesson({Map<String, Object?>? dispute}) => {
  ...lesson('l1', canCancel: false, status: dispute == null ? 'completed' : 'needs_review'),
  'status_label': dispute == null ? 'اتعملت' : 'تحت المراجعة',
  'can_dispute': dispute == null,
  'dispute_until': dispute == null ? time('2099-10-13T16:45:00Z', '2099-10-13T19:45:00+03:00') : null,
  'dispute_reasons': dispute == null
      ? [
          {'key': 'teacher_absent', 'label': 'المدرس ما حضرش'},
          {'key': 'cut_short', 'label': 'الحصة خلصت بدري'},
        ]
      : <Object>[],
  'dispute': dispute,
};

void main() {
  testWidgets('a guardian disputes a held lesson with a reason and their words, once, and sees it under review', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    final waiting = {'status': 'open', 'status_label': 'مستني قرار', 'reason': 'الحصة خلصت بدري', 'answer': null};
    app.api
      ..on('GET', 'family/lessons', {
        'learner': {'id': 'c1', 'name': 'سلمى رامي'},
        'notices': <Object>[],
        'data': [heldLesson()],
      })
      ..on('POST', 'lessons/l1/dispute', {'data': heldLesson(dispute: waiting)});
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1000));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);
    await tester.tap(find.byKey(const Key('lesson-l1')));
    await frames(tester, 8);
    await tester.tap(find.text('اعترض'));
    await frames(tester, 10);
    expect(find.text('اعتراض على نتيجة الحصة'), findsOneWidget);

    // Nothing goes without a reason and words.
    await tester.tap(find.text('ابعت الاعتراض'));
    await frames(tester, 3);
    expect(find.text('اختار السبب واكتب اللي حصل.'), findsOneWidget);
    expect(app.api.sent('POST', 'lessons/l1/dispute'), isEmpty);

    await tester.tap(find.text('الحصة خلصت بدري'));
    await tester.enterText(find.byType(TextField).last, 'المدرس قفل بعد ربع ساعة');
    // The lesson comes back under review once the dispute is in.
    app.api.on('GET', 'family/lessons', {
      'learner': {'id': 'c1', 'name': 'سلمى رامي'},
      'notices': <Object>[],
      'data': [heldLesson(dispute: waiting)],
    });
    await tester.tap(find.text('ابعت الاعتراض'));
    await frames(tester, 10);

    final sent = app.api.sent('POST', 'lessons/l1/dispute').single;
    expect(sent.data, {'reason': 'cut_short', 'note': 'المدرس قفل بعد ربع ساعة'});
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(find.text('الاعتراض: مستني قرار'), findsOneWidget);
    expect(find.text('اعترض'), findsNothing);

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
    await tester.binding.setSurfaceSize(null);
  });
}
