import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/homework/domain/recitation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'report_voice_draft_test.dart' show item;
import 'sign_in_flow_test.dart' show frames;

/// A check as the teacher's homework answers give it (made-up words, never the Quran).
Map<String, Object?> checkJson() => {
  'status': 'ready',
  'status_label': 'اتراجع',
  'failure': null,
  'summary': {'words': 4, 'ok': 2, 'missing': 1, 'wrong': 1, 'extra': 1},
  'summary_label': 'صح 2 من 4 · ناقصة 1 · غلط 1 · زيادة 1',
  'verses': [
    {
      'surah': 1,
      'verse': 2,
      'words': [
        {'text': 'كَلِمَةٌ', 'state': 'ok', 'heard': null},
        {'text': 'أُولَى', 'state': 'missing', 'heard': null},
      ],
    },
    {
      'surah': 1,
      'verse': 3,
      'words': [
        {'text': 'جُمْلَةٌ', 'state': 'ok', 'heard': null},
        {'text': 'قَصِيرَةٌ', 'state': 'wrong', 'heard': 'طويله'},
      ],
    },
  ],
  'extra': [
    {'after': 1, 'text': 'كلمه'},
  ],
  'heard': 'كلمه كلمه جمله طويله',
  'note': 'مساعدة للتصحيح مش حكم: الكلام بيتعرف آليًا وممكن يغلط، فاسمع التسجيل بنفسك.',
};

Map<String, Object?> surahsJson() => {
  'data': [
    {'number': 1, 'name_ar': 'السورة الأولى', 'name_en': 'First', 'verses': 3},
    {'number': 2, 'name_ar': 'السورة التانية', 'name_en': 'Second', 'verses': 5},
  ],
  'attribution': 'Tanzil Quran Text',
};

void main() {
  test('reads a check in order: extra words after the one they followed, verse numbers at their end', () {
    final check = RecitationCheck.fromJson(checkJson())!;
    expect(check.ready, isTrue);
    expect(
      [
        for (final t in check.tokens)
          switch (t) {
            RecitedWord(:final text, :final state, :final heard) => '$text:${state.name}${heard == null ? '' : ':$heard'}',
            ExtraWord(:final text) => '+$text',
            VerseEnd(:final number) => '($number)',
          },
      ],
      ['كَلِمَةٌ:ok', '+كلمه', 'أُولَى:missing', '(2)', 'جُمْلَةٌ:ok', 'قَصِيرَةٌ:wrong:طويله', '(3)'],
    );
    expect(RecitationCheck.fromJson(null), isNull);
  });

  test('sends what to recite only when the form asks it', () {
    expect(expectedPayload(offered: false, on: true, accepts: 'audio', surah: 1, from: 2, to: 3).send, isFalse);
    expect(expectedPayload(offered: true, on: true, accepts: 'any', surah: 1, from: 2, to: 3).value, {'surah': 1, 'from': 2, 'to': 3});
    final off = expectedPayload(offered: true, on: false, accepts: 'audio', surah: 1, from: 2, to: 3);
    expect((off.send, off.value), (true, null));
    expect(expectedPayload(offered: true, on: true, accepts: 'text', surah: 1, from: 2, to: 3).value, isNull);
    expect(expectedPayload(offered: true, on: true, accepts: 'audio', surah: 1, from: 2).value, isNull);
  });

  testWidgets('a teacher asks for the check on homework by voice, from the lesson\'s own passage', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/reports/r1', {
        'data': {
          ...report('r1'),
          'engagement': 4,
          'overall': 4,
          'items': [item('n1', outcome: 'practiced')],
          'lesson_options': <Object>[],
          'homework': null,
          'roadmap': null,
          'template': null,
          'answers': <Object>[],
          'assist': {'voice_draft': false, 'recitation_check': true},
          'expected_suggestion': {'surah': 1, 'from': 2, 'to': 3, 'title': 'السورة الأولى ٢–٣'},
        },
      })
      ..on('GET', 'teacher/texts/quran', surahsJson())
      ..on('PUT', 'teacher/reports/r1', {'data': report('r1', status: 'submitted')});
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 2400));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 20);

    await tester.tap(find.byKey(const Key('today-reports')));
    await frames(tester, 10);
    await tester.tap(find.text('يوسف خالد'));
    await frames(tester, 10);
    await tester.tap(find.text('أعطِ واجبًا'));
    await frames(tester, 3);
    await tester.enterText(find.widgetWithText(TextField, 'الواجب'), 'سمّع المقطع');

    // Text homework does not offer it; by voice it does, the lesson's passage ready.
    await tester.tap(find.text('كتابة'));
    await frames(tester, 2);
    expect(find.byKey(const Key('recite')), findsNothing);
    await tester.tap(find.text('تسجيل صوت'));
    await frames(tester, 2);
    expect(find.text('تصحيح التسميع آليًا'), findsOneWidget);
    await tester.tap(find.byKey(const Key('recite')));
    await frames(tester, 3);
    expect(find.text('1. السورة الأولى · First'), findsOneWidget);
    expect(find.text('من نص الدرس: السورة الأولى ٢–٣'), findsOneWidget);

    await tester.scrollUntilVisible(find.text('أرسل التقرير'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('أرسل التقرير'));
    await frames(tester, 12);
    final sent = app.api.sent('PUT', 'teacher/reports/r1').single.data as Map<String, dynamic>;
    expect(sent['homework'], {
      'title': 'سمّع المقطع',
      'instructions': '',
      'accepts': 'audio',
      'due_days': 3,
      'expected': {'surah': 1, 'from': 2, 'to': 3},
    });

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
    await tester.binding.setSurfaceSize(null);
  });

  testWidgets('marking shows each recording\'s words in colour, with what was heard', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final homework = homeworkJson(
      'h1',
      status: 'submitted',
      accepts: 'audio',
      file: {'name': 'r.webm', 'mime': 'audio/webm', 'size': 64, 'is_audio': true},
    );
    final submission = (homework['submissions']! as List).single as Map<String, Object?>;
    app.api.on('GET', 'teacher/homework', {
      'data': [
        {
          ...homework,
          'expected': {'surah': 1, 'from': 2, 'to': 3, 'title': 'السورة الأولى ٢–٣'},
          'submissions': [
            {...submission, 'check': checkJson()},
          ],
        },
      ],
    });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 2000));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 20);

    await tester.tap(find.byKey(const Key('today-marking')));
    await frames(tester, 10);
    expect(find.text('التسميع: السورة الأولى ٢–٣'), findsOneWidget);
    expect(find.text('تصحيح التسميع'), findsOneWidget);
    expect(find.text('صح 2 من 4 · ناقصة 1 · غلط 1 · زيادة 1'), findsOneWidget);
    final words = tester.widget<Text>(find.byKey(const Key('recited-words'))).textSpan!.toPlainText(includeSemanticsLabels: false);
    expect(words, contains('قَصِيرَةٌ (طويله)'));
    expect(words, contains('كَلِمَةٌ كلمه أُولَى (2)'));
    expect(find.textContaining('مساعدة للتصحيح مش حكم'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
    await tester.binding.setSurfaceSize(null);
  });
}
