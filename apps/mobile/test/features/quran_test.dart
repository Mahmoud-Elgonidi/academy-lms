import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/theme/app_theme.dart';
import 'package:academy_app/features/hifz/domain/hifz.dart';
import 'package:academy_app/features/quran/data/quran_repository.dart';
import 'package:academy_app/features/quran/domain/quran.dart';
import 'package:academy_app/features/quran/presentation/widgets/quran_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/hifz.dart';
import '../fixtures/journey.dart';
import '../fixtures/quran.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Future<void> _open(WidgetTester tester, TestApp app, {Size size = const Size(420, 2400)}) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(size);
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 20);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(seconds: 10));
  await tester.binding.setSurfaceSize(null);
}

String _location(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).last)).state.matchedLocation;

/// A hifz map with part 1 started (pages 1–3 memorized) and the whole of part 30 memorized.
Map<String, Object?> _boardWithPart30() {
  final board = fxHifzBoard();
  final data = board['data']! as Map<String, Object?>;
  data['juzs'] = fxHifzJuzs({1: 'strong', 2: 'fading', 3: 'weak', 4: 'learning', for (var p = 581; p <= 604; p++) p: 'strong'});
  return board;
}

void main() {
  test('reads a plan, its portion, a lesson\'s results, exams and certificates', () {
    final plans = QuranPlans.fromJson(fxQuranPlans()['data']! as Map<String, dynamic>);
    final plan = plans.courses.single.plan;
    expect((plan.programName, plan.trackLabel, plan.position, plan.percent, plan.isGroup), ('حفظ جزء تبارك', 'حفظ', 'الملك ١', 42, false));
    expect((plan.newAmount, plan.nearPortions, plan.farPages, plan.unitLabel), (10, 3, 2, 'آيات'));
    final portion = plan.portion!;
    expect(portion.parts.map((p) => p.key), ['new', 'near', 'far']);
    expect(portion.part('near')!.pageSpan, '564–566');
    expect(portion.part('new')!.pageSpan, '562');
    expect(portion.part('far')!.titles, 'النبأ ١–٤٠');
    expect(portion.stops, ['نهاية سورة الملك']);
    // A part the lesson does not have is left out.
    expect(Portion.fromJson(fxPortion(near: false, far: false)).parts.map((p) => p.key), ['new']);

    final result = plans.courses.single.recent.single;
    expect((result.advanced, result.recordedAt?.label, result.parts.single.gradeLabel), (true, 'الثلاثاء 6 أكتوبر', 'جيد جدًّا'));
    expect(result.parts.single.mistakesLine, 'خطأ 2');
    expect(result.parts.single.note, isNull, reason: 'families never read the teacher\'s note');

    final exam = QuranExam.fromJson(fxExam(status: 'failed'));
    expect(
      (exam.failed, exam.result!.retakeOn, exam.result!.note, exam.startsAt?.label),
      (true, '2099-10-20', 'راجع الآيات ١١–٢٠', 'الاثنين 12 أكتوبر · 7:00 م'),
    );
    expect(QuranExam.fromJson(fxExam(status: 'suggested')).startsAt, isNull);

    // The certificates come as a list, or keyed by id.
    final listed = QuranCertificates.fromJson({
      'shown': true,
      'certificates': [fxCertificate(), fxCertificate(revoked: true)],
    });
    expect(listed.list.map((c) => c.opens), [true, false]);
    final keyed = QuranCertificates.fromJson({
      'shown': true,
      'certificates': {'cert-1': fxCertificate()},
    });
    expect(keyed.list.single.code, 'ABCD-EFGH-JKLM-NPQR');

    expect([for (final g in gradeOrder) gradeStars(g)], [5, 4, 3, 2, 1]);
    expect(gradeStars(null), 0);
  });

  test('a part\'s record sends counts only when counted, and a note only with the teacher\'s own grade', () {
    final untouched = PartScore();
    expect(untouched.toJson(), isEmpty, reason: 'the words marked in the mushaf count');
    final counted = PartScore()
      ..add('wrong', 1)
      ..add('wrong', 1)
      ..add('tajweed', 1)
      ..add('tajweed', -1)
      ..note = 'لا يُرسل';
    expect(counted.toJson(), {
      'mistakes': [
        {'kind': 'wrong', 'count': 2},
        {'kind': 'tajweed', 'count': 0},
      ],
    });
    final graded = PartScore()
      ..grade = 'excellent'
      ..note = ' أحسن ';
    expect(graded.toJson(), {'grade': 'excellent', 'note': 'أحسن'});
  });

  test('the ways in stay hidden while the Quran is not in the academy\'s plan, and ask once in a while', () async {
    final app = await TestApp.create(accounts: [guardian]);
    final repo = QuranRepository(app.client);
    app.api
      ..on('GET', 'quran/plan', fxNotInPlan, status: 403)
      ..on('GET', 'quran/lessons/l1/portion', fxNotInPlan, status: 403)
      ..on('GET', 'quran/examiner/exams', fxNotInPlan, status: 403);
    await app.session.start();
    expect(await repo.follows(learnerId: 'c1'), isFalse);
    expect(await repo.follows(learnerId: 'c1'), isFalse);
    expect(app.api.sent('GET', 'quran/plan'), hasLength(1));
    expect(await repo.hasPortion('l1'), isFalse);
    expect(await repo.examines(), isFalse);

    // A lesson with no Quran plan has no portion either.
    app.api.on('GET', 'quran/lessons/l2/portion', fxLessonPortion(plan: false));
    expect(await repo.hasPortion('l2'), isFalse);
    app.api.on('GET', 'quran/lessons/l3/portion', fxLessonPortion());
    expect(await repo.hasPortion('l3'), isTrue);
  });

  test('results are sent once with one key across retries; a group names its student; the pace goes back with nulls', () async {
    final app = await TestApp.create(accounts: [teacher]);
    final repo = QuranRepository(app.client);
    app.api
      ..on('POST', 'quran/lessons/l1/result', {
        'data': {
          'results': [fxResult()],
          'plan': fxPlan(),
        },
      }, status: 201)
      ..on('POST', 'quran/exams/x1/result', {'data': fxExam(family: false)}, status: 201)
      ..on('PATCH', 'quran/students/e1/plan', {
        'data': {'enrollment_id': 'e1', 'plan': fxPlan()},
      });
    await app.session.start();

    final record = await repo.record(
      'l1',
      parts: {
        'new': {'grade': 'good', 'note': 'x'},
      },
      studentId: 'u-kid',
    );
    expect(record.advanced, isTrue);
    final sent = app.api.sent('POST', 'quran/lessons/l1/result').single;
    expect(sent.data, {
      'student_id': 'u-kid',
      'parts': {
        'new': {'grade': 'good', 'note': 'x'},
      },
    });
    expect(sent.headers['Idempotency-Key'], isNotNull);

    await repo.record('l1', parts: {'near': <String, Object?>{}}, studentId: 'u-two', group: true);
    expect(app.api.sent('POST', 'quran/lessons/l1/result').last.data, {
      'students': [
        {
          'student_id': 'u-two',
          'parts': {'near': <String, Object?>{}},
        },
      ],
    });

    final exam = await repo.recordExam('x1', mistakes: {'wrong': 1}, grade: 'excellent');
    expect(exam.passed, isTrue);
    final examSent = app.api.sent('POST', 'quran/exams/x1/result').single;
    expect(examSent.data, {
      'mistakes': [
        {'kind': 'wrong', 'count': 1},
      ],
      'grade': 'excellent',
    });
    expect(examSent.headers['Idempotency-Key'], isNotNull);

    await repo.setPace('e1');
    expect(app.api.sent('PATCH', 'quran/students/e1/plan').single.data, {'new_amount': null, 'near_portions': null, 'far_pages': null});
  });

  testWidgets('a guardian opens the Quran from the journey: the thirty parts, the review, the plan, exams and certificates', (
    tester,
  ) async {
    final semantics = tester.ensureSemantics();
    final launcher = FakeLauncher.install();
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    fxQuranRoutes(app.api);
    app.api
      ..on('GET', 'learning/journey/map', fxJourney(stage: 'classic', viewer: 'guardian'))
      ..on('GET', 'learning/hifz', _boardWithPart30());
    await _open(tester, app);

    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('الرحلة')));
    await frames(tester, 14);
    expect(find.text('الخطة والورد والاختبارات والشهادات'), findsOneWidget);
    expect(app.api.sent('GET', 'quran/plan').first.queryParameters, {'learner_id': 'c1'});
    await tester.tap(find.byKey(const Key('quran-card')));
    await frames(tester, 14);
    expect(_location(tester), '/family/journey/quran');
    expect(find.text('القرآن مع سلمى'), findsOneWidget);
    for (final path in ['quran/exams', 'quran/certificates', 'learning/hifz']) {
      expect(app.api.sent('GET', path).last.queryParameters, {'learner_id': 'c1'}, reason: path);
    }

    // The thirty parts: part 30 whole, part 1 begun, the rest not yet.
    expect(find.text('الأجزاء الثلاثون'), findsOneWidget);
    expect(find.text('3 صفحات محفوظة من 604'), findsOneWidget);
    expect(find.bySemanticsLabel('الجزء 30: محفوظ'), findsOneWidget);
    expect(find.bySemanticsLabel('الجزء 1: بدأ حفظه'), findsOneWidget);
    expect(find.bySemanticsLabel('الجزء 2: لم يبدأ'), findsOneWidget);

    // Today's review, and the way to the map and the notebook.
    expect(find.text('بقيت صفحتان اليوم'), findsOneWidget);
    expect(find.byKey(const Key('quran-open-hifz')), findsOneWidget);

    // The plan, this week's portion and the last grades with the mistakes by kind.
    expect(find.text('حفظ جزء تبارك'), findsWidgets);
    expect(find.text('الموضع الآن: الملك ١'), findsOneWidget);
    expect(find.text('أُنجز 42% من الخطة'), findsOneWidget);
    expect(find.text('ورد هذا الأسبوع'), findsOneWidget);
    expect(find.text('الملك ١–١٠'), findsOneWidget);
    expect(find.text('الصفحات 564–566'), findsOneWidget);
    expect(find.text('يصل به إلى اختبار: نهاية سورة الملك'), findsOneWidget);
    expect(find.text('خطأ 2'), findsOneWidget);
    expect(find.text('جيد جدًّا'), findsOneWidget);
    expect(find.text('انتقل إلى الورد التالي'), findsOneWidget);

    // The exams: booked with its time and examiner, failed with the retake day, passed.
    await tester.scrollUntilVisible(find.byKey(const Key('quran-certificate-cert-1')), 300, scrollable: find.byType(Scrollable).last);
    expect(find.text('الاثنين 12 أكتوبر · 7:00 م · مع أ. هدى سالم'), findsOneWidget);
    expect(find.text('راجع الآيات ١١–٢٠'), findsOneWidget);
    expect(find.textContaining('الإعادة يوم '), findsOneWidget);
    expect(find.text('ناجح'), findsOneWidget);

    // A certificate opens its page; a revoked one says so and opens nothing.
    expect(find.text('رمز التحقق: ABCD-EFGH-JKLM-NPQR'), findsOneWidget);
    expect(find.text('أُلغيت'), findsOneWidget);
    await tester.tap(find.byKey(const Key('quran-certificate-cert-2')));
    await frames(tester, 4);
    expect(launcher.urls, isEmpty);
    await tester.tap(find.byKey(const Key('quran-certificate-cert-1')));
    await frames(tester, 4);
    expect(launcher.urls, ['https://demo.test/quran/certificates/cert-1?signature=abc']);

    // To the hifz map beside it.
    await tester.scrollUntilVisible(find.byKey(const Key('quran-open-hifz')), -300, scrollable: find.byType(Scrollable).last);
    await tester.tap(find.byKey(const Key('quran-open-hifz')));
    await frames(tester, 14);
    expect(_location(tester), '/family/journey/hifz');

    semantics.dispose();
    await _close(tester);
  });

  testWidgets('a child reads their Quran in stars and simple words, without counts', (tester) async {
    final semantics = tester.ensureSemantics();
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(app.api, 'kids', fxJourney());
    fxQuranRoutes(app.api, counts: false);
    app.api.on('GET', 'learning/hifz', _boardWithPart30());
    await _open(tester, app, size: const Size(420, 2600));
    await tester.tap(find.text('رائع!'));
    await frames(tester, 6);

    expect(find.text('رحلتي مع القرآن ⭐'), findsOneWidget);
    expect(app.api.sent('GET', 'quran/plan').first.queryParameters, isEmpty, reason: 'the student is their own learner');
    await tester.tap(find.byKey(const Key('quran-card')));
    await frames(tester, 16);
    expect(_location(tester), '/kids/journey/quran');
    expect(find.text('قرآني'), findsOneWidget);

    expect(find.text('كل جزء تحفظه يضيء هنا ⭐'), findsOneWidget);
    final colours = Theme.of(tester.element(find.byKey(const Key('juz-bar')))).extension<LmsColors>()!;
    final part30 = tester.widget<Icon>(find.descendant(of: find.byKey(const Key('juz-30')), matching: find.byType(Icon)));
    expect((part30.icon, part30.color), (Icons.star_rounded, colours.reward));
    expect(
      tester.widget<Icon>(find.descendant(of: find.byKey(const Key('juz-2')), matching: find.byType(Icon))).icon,
      Icons.star_outline_rounded,
    );
    expect(find.text('وردك ينتظرك اليوم 📖'), findsOneWidget);
    expect(find.text('وصلت إلى الملك ١ ⭐'), findsOneWidget);
    expect(find.text('ما سأقرؤه هذا الأسبوع'), findsOneWidget);
    // No numbers: no percent, no pages counted, no mistakes; grades are stars.
    expect(find.textContaining('%'), findsNothing);
    expect(find.textContaining('صفحة محفوظة'), findsNothing);
    expect(find.textContaining('خطأ'), findsNothing);
    expect(find.text('جيد جدًّا'), findsNothing);
    expect(find.bySemanticsLabel('جيد جدًّا'), findsOneWidget);
    expect(find.text('اختباراتي'), findsOneWidget);

    semantics.dispose();
    await _close(tester);
  });

  testWidgets('without the Quran in the academy\'s plan the journey shows no way to it, and no error', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('teen')]);
    fxStudentRoutes(app.api, 'teen', fxJourney(stage: 'teen', style: 'calm'));
    app.api.on('GET', 'quran/plan', fxNotInPlan, status: 403);
    await _open(tester, app);

    expect(app.api.sent('GET', 'quran/plan'), isNotEmpty);
    expect(find.byKey(const Key('quran-card')), findsNothing);
    expect(find.text('هذا غير متاح في باقة الأكاديمية.'), findsNothing);
    await _close(tester);
  });

  testWidgets('a teacher opens the lesson\'s portion, counts mistakes, grades a part with a note and saves it once', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    var saved = false;
    app.api
      ..on('GET', 'teacher/lessons', {
        'from': '2099-10-11',
        'to': '2099-10-11',
        'count': 1,
        'minutes': 45,
        'data': [lesson('l1')],
      })
      ..onWith('GET', 'quran/lessons/l1/portion', (_) => (200, fxLessonPortion(results: saved ? [fxResult()] : const [])))
      ..onWith('POST', 'quran/lessons/l1/result', (_) {
        saved = true;
        return (
          201,
          {
            'data': {
              'results': [fxResult()],
              'plan': fxPlan(),
            },
          },
        );
      })
      ..on('GET', 'quran/students/e1/plan', {
        'data': {'enrollment_id': 'e1', 'plan': fxPlan(), 'recent': <Object>[], 'grades': fxGrades, 'mistake_types': fxMistakeTypes},
      })
      ..on('PATCH', 'quran/students/e1/plan', {
        'data': {'enrollment_id': 'e1', 'plan': fxPlan()},
      });
    await _open(tester, app);

    // From the next lesson's card.
    expect(find.text('ورد الحصة'), findsOneWidget);
    await tester.tap(find.text('ورد الحصة'));
    await frames(tester, 14);
    expect(_location(tester), '/teach/lessons/l1/quran');
    expect(find.text('حصة القرآن'), findsOneWidget);

    // The plan and the portion's three parts.
    expect(find.text('الموضع الآن: الملك ١'), findsOneWidget);
    expect(find.text('الجديد 10 آيات · المراجعة القريبة 3 · البعيدة 2'), findsOneWidget);
    for (final key in ['new', 'near', 'far']) {
      expect(find.byKey(Key('part-$key')), findsOneWidget, reason: key);
    }
    expect(find.text('النبأ ١–٤٠'), findsOneWidget);

    // The far review was not recited; two mistakes in the new part; the near part graded by the teacher.
    await tester.ensureVisible(find.byKey(const Key('recited-far')));
    await tester.tap(find.byKey(const Key('recited-far')));
    await frames(tester, 4);
    await tester.ensureVisible(find.byKey(const Key('more-new-wrong')));
    await tester.tap(find.byKey(const Key('more-new-wrong')));
    await tester.tap(find.byKey(const Key('more-new-wrong')));
    await frames(tester, 4);
    expect(tester.widget<Text>(find.byKey(const Key('count-new-wrong'))).data, '2');
    await tester.ensureVisible(find.byKey(const Key('grade-near-excellent')));
    await tester.tap(find.byKey(const Key('grade-near-excellent')));
    await frames(tester, 4);

    // The teacher's own grade needs a note.
    await tester.ensureVisible(find.byKey(const Key('quran-submit')));
    await tester.tap(find.byKey(const Key('quran-submit')));
    await frames(tester, 6);
    expect(find.text('اكتب ملاحظة تشرح التقدير الذي اخترته.'), findsOneWidget);
    expect(app.api.sent('POST', 'quran/lessons/l1/result'), isEmpty);
    await tester.ensureVisible(find.byKey(const Key('note-near')));
    await tester.enterText(find.byKey(const Key('note-near')), 'مراجعة متقنة');
    await tester.ensureVisible(find.byKey(const Key('quran-submit')));
    await tester.tap(find.byKey(const Key('quran-submit')));
    await frames(tester, 12);

    final sent = app.api.sent('POST', 'quran/lessons/l1/result').single;
    expect(sent.data, {
      'student_id': 'u-kid',
      'parts': {
        'new': {
          'mistakes': [
            {'kind': 'wrong', 'count': 2},
          ],
        },
        'near': {'grade': 'excellent', 'note': 'مراجعة متقنة'},
      },
    });
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(find.text('سُجّلت النتيجة، وينتقل الطالب إلى الورد التالي.'), findsOneWidget);
    await frames(tester, 6);
    // Saved once: the result now stands in place of the form.
    expect(find.text('سُجّلت نتيجة هذا الطالب في هذه الحصة.'), findsOneWidget);
    expect(find.byKey(const Key('quran-submit')), findsNothing);
    expect(find.text('تحسّن واضح'), findsOneWidget);

    // The student's own pace.
    await tester.scrollUntilVisible(find.byKey(const Key('quran-pace-open')), -300, scrollable: find.byType(Scrollable).last);
    await tester.tap(find.byKey(const Key('quran-pace-open')));
    await frames(tester, 10);
    await tester.enterText(find.byKey(const Key('pace-new')), '15');
    await tester.tap(find.byKey(const Key('pace-save')));
    await frames(tester, 10);
    expect(app.api.sent('PATCH', 'quran/students/e1/plan').single.data, {'new_amount': 15, 'near_portions': 3, 'far_pages': 2});
    expect(find.text('حُفظت الوتيرة.'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('a lesson with no Quran plan, or an academy without the Quran, gives the teacher no way to a portion', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/lessons', {
        'from': '2099-10-11',
        'to': '2099-10-11',
        'count': 2,
        'minutes': 90,
        'data': [lesson('l1'), lesson('l2')],
      })
      ..on('GET', 'quran/lessons/l1/portion', fxLessonPortion(plan: false))
      ..on('GET', 'quran/lessons/l2/portion', fxNotInPlan, status: 403)
      ..on('GET', 'quran/examiner/exams', fxNotInPlan, status: 403);
    await _open(tester, app);

    // The next lesson (no Quran plan) and the one later today (no Quran in the plan), on the card and in its sheet.
    expect(find.text('ورد الحصة'), findsNothing);
    await tester.tap(find.byKey(const Key('lesson-l2')));
    await frames(tester, 12);
    expect(find.text('ورد الحصة'), findsNothing);
    expect(app.api.sent('GET', 'quran/lessons/l2/portion'), isNotEmpty);
    await tester.tapAt(const Offset(10, 10));
    await frames(tester, 10);
    await tester.tap(find.text('المزيد').last);
    await frames(tester, 12);
    expect(find.byKey(const Key('open-quran-exams')), findsNothing);
    await _close(tester);
  });

  testWidgets('an examiner opens their exams from More and records a result', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final booked = fxExam(status: 'booked', family: false)..['id'] = 'x1';
    final board = {
      'data': {
        'booked': [booked],
        'recent': [fxExam(family: false)],
        'mistake_types': fxMistakeTypes,
        'grades': fxGrades,
      },
    };
    app.api
      ..on('GET', 'quran/examiner/exams', board)
      ..on('POST', 'quran/exams/x1/result', {'data': fxExam(family: false)}, status: 201);
    await _open(tester, app);
    await tester.tap(find.text('المزيد').last);
    await frames(tester, 12);
    await tester.tap(find.byKey(const Key('open-quran-exams')));
    await frames(tester, 14);
    expect(_location(tester), '/teach/more/quran-exams');
    expect(find.text('المحجوزة'), findsOneWidget);
    expect(find.text('آخر النتائج'), findsOneWidget);
    expect(find.text('يوسف خالد'), findsNWidgets(2));

    await tester.tap(find.byKey(const Key('exam-x1')));
    await frames(tester, 10);
    expect(find.text('سجّل نتيجة الاختبار'), findsWidgets);
    await tester.tap(find.byKey(const Key('more-hesitation')));
    await frames(tester, 2);
    await tester.tap(find.byKey(const Key('grade-excellent')));
    await frames(tester, 2);
    await tester.ensureVisible(find.byKey(const Key('exam-save')));
    await tester.tap(find.byKey(const Key('exam-save')));
    await frames(tester, 12);
    final sent = app.api.sent('POST', 'quran/exams/x1/result').single;
    expect(sent.data, {
      'mistakes': [
        {'kind': 'hesitation', 'count': 1},
      ],
      'grade': 'excellent',
    });
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(find.text('سُجّلت النتيجة: ناجح'), findsOneWidget);
    await _close(tester);
  });

  test('the juz bar counts a part as memorized by its strong, fading and weak pages', () {
    final map = HifzMap.fromJson(_boardWithPart30()['data']! as Map<String, dynamic>);
    expect(JuzBar.share(map.juzs.last), 1);
    expect(JuzBar.share(map.juzs.first), 3 / 20);
    expect(JuzBar.share(map.juzs[1]), 0);
  });
}
