import 'dart:typed_data';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/theme/app_theme.dart';
import 'package:academy_app/core/realtime/live.dart';
import 'package:academy_app/core/state/load_cubit.dart';
import 'package:academy_app/features/hifz/domain/hifz.dart';
import 'package:academy_app/features/homework/data/homework_repository.dart';
import 'package:academy_app/features/homework/domain/recitation.dart';
import 'package:academy_app/features/homework/presentation/widgets/voice_recorder.dart';
import 'package:academy_app/features/journey/domain/journey.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../core/realtime_test.dart' show FakeLink, FakeSource, settle;
import '../fixtures/hifz.dart';
import '../fixtures/journey.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Future<void> _open(WidgetTester tester, TestApp app, {Size size = const Size(420, 1600)}) async {
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

/// The app goes to the background and comes back: the live service catches every screen up.
Future<void> _awayAndBack(WidgetTester tester) async {
  for (final state in [
    AppLifecycleState.inactive,
    AppLifecycleState.hidden,
    AppLifecycleState.paused,
    AppLifecycleState.hidden,
    AppLifecycleState.inactive,
    AppLifecycleState.resumed,
  ]) {
    tester.binding.handleAppLifecycleStateChanged(state);
    await tester.pump();
  }
}

Color _squareColour(WidgetTester tester, int page) =>
    ((tester.widget<Container>(find.descendant(of: find.byKey(Key('page-$page')), matching: find.byType(Container))).decoration!
            as BoxDecoration)
        .color)!;

void main() {
  test('reads a learner\'s map: parts and their pages, the counts, today\'s review and the last recitation', () {
    final board = HifzBoard.fromJson(
      fxHifzBoard(
            mode: 'check',
            latest: fxRecitation(status: 'ready'),
          )['data']!
          as Map<String, dynamic>,
    );
    expect(board.checks, isTrue);
    expect(board.map.available, isTrue);
    expect(board.map.juzs, hasLength(30));
    expect(board.map.juzs.last.pages, hasLength(24));
    expect(board.map.juzs.first.pages.take(5).map((p) => p.strength), [
      Strength.strong,
      Strength.fading,
      Strength.weak,
      Strength.learning,
      Strength.fresh,
    ]);
    expect(board.map.juzs.first.tally[Strength.fresh], 16);
    expect(board.map.label(Strength.fading), 'يحتاج إلى مراجعة قريبًا');
    // The new pages are the ones neither memorized nor learning.
    expect(board.map.counts.of(Strength.fresh, board.map.pagesTotal), 600);
    expect(board.wird.pages.map((p) => (p.page, p.strength, p.overdueDays)), [(2, Strength.fading, 0), (3, Strength.weak, 2)]);
    expect(board.wird.finished, isFalse);
    expect(Wird.fromJson({'daily': 3, 'done_today': 3, 'pages': <Object>[]}).finished, isTrue);
    expect(Wird.fromJson({'daily': 3, 'done_today': 0, 'pages': <Object>[]}).finished, isFalse);

    final latest = board.latest!;
    expect((latest.id, latest.grade, latest.gradeLabel, latest.pending), ('rc1', 2, 'بصعوبة', false));
    expect(latest.check.tokens.whereType<RecitedWord>().map((w) => w.state), [
      WordState.ok,
      WordState.missing,
      WordState.ok,
      WordState.wrong,
    ]);
    expect(HifzRecitation.fromJson(fxRecitation()).pending, isTrue);
    expect(Strength.of('something else'), Strength.fresh);

    // Without the platform's Quran text there is nothing to show.
    expect(HifzMap.fromJson({'pages_total': 0, 'juzs': <Object>[]}).available, isFalse);

    final page = QuranPage.fromJson(fxQuranPage(3)['data']! as Map<String, dynamic>);
    expect((page.verses.length, page.strength, page.dueAt?.label, page.surah(1)?.nameEn), (2, Strength.weak, 'الجمعة 16 أكتوبر', 'First'));
  });

  test('a teacher\'s choice of parts and surahs covers each page once', () {
    final hifz = StudentHifz.fromJson(fxStudentHifz()['data']! as Map<String, dynamic>);
    expect(hifz.studentName, 'يوسف خالد');
    expect(hifz.marks.keys, ['strong', 'good', 'weak']);
    expect(hifz.recent.single.sourceLabel, 'تقرير الحصة');
    // Part 1 is pages 1–20, the second surah pages 2–49: 49 pages in all.
    expect(hifz.chosen(juzs: {1}, surahs: {2}).length, 49);
    expect(hifz.chosen(juzs: {30}).length, 24);
    expect(hifz.chosen(), isEmpty);
  });

  test('the journey says what is left of today\'s review, and nothing until there is a map', () {
    final none = JourneyMap.fromJson(fxJourney()['data']! as Map<String, dynamic>);
    expect(none.hifz, isNull);
    final some = JourneyMap.fromJson(fxJourney(hifz: fxHifzToday)['data']! as Map<String, dynamic>);
    expect((some.hifz?.due, some.hifz?.doneToday, some.hifz?.daily), (2, 0, 3));
  });

  test('the hifz screen reloads on the hifz signal, and not on others', () async {
    final links = <FakeLink>[];
    final live = Live(
      source: FakeSource(),
      apiBaseUrl: 'http://api.test/api/v1',
      connect: (uri) {
        final link = FakeLink(uri);
        links.add(link);
        return link;
      },
      debounce: const Duration(milliseconds: 20),
    );
    var fetched = 0;
    final cubit = LoadCubit<int>(() async => ++fetched, refreshOn: live.on({Live.hifz}));
    await cubit.load();
    await live.follow('demo:7');
    await settle();
    links.single.hello();
    await settle();

    links.single.signal('private-user.7', Live.practice);
    await Future<void>.delayed(const Duration(milliseconds: 60));
    expect(fetched, 1);
    links.single.signal('private-user.7', 'hifz.changed');
    await Future<void>.delayed(const Duration(milliseconds: 60));
    expect(fetched, 2);

    await cubit.close();
    await live.close();
  });

  testWidgets('the map shows each page in its strength\'s colour, with the key and the counts, from the journey', (tester) async {
    final semantics = tester.ensureSemantics();
    final app = await TestApp.create(accounts: [fxStudent('teen')]);
    fxStudentRoutes(app.api, 'teen', fxJourney(stage: 'teen', style: 'calm', hifz: fxHifzToday));
    app.api.on('GET', 'learning/hifz', fxHifzBoard());
    await _open(tester, app);

    // The way in is on the journey: what is left of today's review.
    expect(find.text('ورد الحفظ'), findsOneWidget);
    expect(find.text('بقيت صفحتان اليوم'), findsOneWidget);
    await tester.tap(find.byKey(const Key('hifz-card')));
    await frames(tester, 12);
    expect(GoRouter.of(tester.element(find.byType(Scaffold).last)).state.matchedLocation, '/teen/journey/hifz');

    expect(find.text('خريطة الحفظ'), findsOneWidget);
    expect(find.text('3 صفحات محفوظة من 604'), findsOneWidget);
    final colours = Theme.of(tester.element(find.byKey(const Key('hifz-map')))).extension<LmsColors>()!;
    expect(_squareColour(tester, 1), colours.brand);
    expect(_squareColour(tester, 2), colours.attention);
    expect(_squareColour(tester, 3), colours.danger);
    expect(_squareColour(tester, 4), colours.info);
    expect(_squareColour(tester, 5), colours.line);
    expect(_squareColour(tester, 604), colours.line);
    // The key, with how many pages each strength has.
    for (final line in ['ثابت · 1', 'يحتاج إلى مراجعة قريبًا · 1', 'ضعيف · 1', 'قيد الحفظ · 1', 'لم يبدأ · 600']) {
      expect(find.text(line), findsOneWidget, reason: line);
    }
    // A screen reader hears each part as one line.
    expect(find.bySemanticsLabel('الجزء 1: ثابت 1، يحتاج إلى مراجعة قريبًا 1، ضعيف 1، قيد الحفظ 1، لم يبدأ 16'), findsOneWidget);
    expect(find.bySemanticsLabel('الجزء 30: لم يبدأ 24'), findsOneWidget);

    // Today's review: two pages, the late one saying so.
    expect(find.text('ورد اليوم'), findsOneWidget);
    expect(find.text('أُنجز 0 من 3 صفحات'), findsOneWidget);
    expect(find.byKey(const Key('wird-2')), findsOneWidget);
    expect(find.text('متأخرة يومين'), findsOneWidget);
    await expectLater(tester, meetsGuideline(androidTapTargetGuideline));
    await expectLater(tester, meetsGuideline(labeledTapTargetGuideline));

    semantics.dispose();
    await _close(tester);
  });

  testWidgets('a guardian shows the page, says how it went, and the day\'s review ends with a celebration', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..on('GET', 'learning/journey/map', fxJourney(stage: 'kids', viewer: 'guardian', hifz: fxHifzToday))
      ..onWith('GET', 'learning/hifz', (r) {
        final graded =
            app.api.sent('POST', 'learning/hifz/pages/2/grade').length + app.api.sent('POST', 'learning/hifz/pages/3/grade').length;
        return (200, fxHifzBoard(wird: [2, 3].skip(graded).toList(), done: graded));
      })
      ..onWith('GET', 'learning/hifz/pages/*', (r) => (200, fxQuranPage(int.parse(r.path.split('/').last))))
      ..onWith('POST', 'learning/hifz/pages/*/grade', (r) => (200, fxQuranPage(int.parse(r.path.split('/')[3]))));
    await _open(tester, app, size: const Size(420, 2400));

    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('الرحلة')));
    await frames(tester, 14);
    await tester.tap(find.byKey(const Key('hifz-card')));
    await frames(tester, 12);
    expect(find.text('خريطة حفظ سلمى'), findsOneWidget);
    expect(app.api.sent('GET', 'learning/hifz').last.queryParameters, {'learner_id': 'c1'});

    // The page hidden first: recite from memory, then show it.
    await tester.tap(find.byKey(const Key('wird-2')));
    await frames(tester, 10);
    expect(find.text('صفحة 2 · السورة الأولى'), findsWidgets);
    expect(find.text('صفحة 2 · الجزء 1'), findsOneWidget);
    expect(find.byKey(const Key('hifz-page-text')), findsNothing);
    expect(find.byKey(const Key('grade-3')), findsNothing);
    await tester.tap(find.text('اكشف الصفحة'));
    await frames(tester, 8);
    expect(find.byKey(const Key('hifz-page-text')), findsOneWidget);
    expect(find.textContaining('كَلِمَةٌ أُولَى'), findsOneWidget);
    expect(find.text('Tanzil Quran Text'), findsOneWidget);

    await tester.tap(find.text('جيد'));
    await frames(tester, 12);
    final graded = app.api.sent('POST', 'learning/hifz/pages/2/grade').single;
    expect(graded.data, {'grade': 3, 'learner_id': 'c1'});
    expect(graded.headers['Idempotency-Key'], isNotNull);
    // One page left: it says when this one comes back.
    expect(find.textContaining('سُجّلت. ستعود إليك هذه الصفحة '), findsOneWidget);
    expect(find.byKey(const Key('wird-2')), findsNothing);

    // The last page of the day: the look's own celebration.
    await tester.tap(find.byKey(const Key('wird-3')));
    await frames(tester, 10);
    await tester.tap(find.text('اكشف الصفحة'));
    await frames(tester, 6);
    await tester.tap(find.text('سهلة'));
    await frames(tester, 6);
    expect(app.api.sent('POST', 'learning/hifz/pages/3/grade').single.data, {'grade': 4, 'learner_id': 'c1'});
    expect(find.text('انتهى ورد اليوم!'), findsOneWidget);
    await frames(tester, 20);
    expect(find.text('انتهى ورد اليوم. أحسنت!'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('a child recites a page; the check comes back by itself and shows the words in colour', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(app.api, 'kids', fxJourney(hifz: {'due': 1, 'done_today': 0, 'daily': 3}));
    var heard = false;
    app.api
      ..onWith(
        'GET',
        'learning/hifz',
        (_) => (
          200,
          fxHifzBoard(
            mode: 'check',
            wird: heard ? const [] : const [3],
            done: heard ? 1 : 0,
            latest: heard ? fxRecitation(status: 'ready') : null,
          ),
        ),
      )
      ..on('GET', 'learning/hifz/pages/3', fxQuranPage(3))
      ..on('POST', 'learning/hifz/pages/3/recitations', {'data': fxRecitation()}, status: 202)
      ..onWith('GET', 'learning/hifz/recitations/rc1', (_) => (200, {'data': fxRecitation(status: heard ? 'ready' : 'pending')}));
    await _open(tester, app, size: const Size(420, 2600));
    await tester.tap(find.text('رائع!'));
    await frames(tester, 6);

    // The child's own words, on the sea.
    expect(find.text('بقيت لك صفحة واحدة تسمّعها'), findsOneWidget);
    await tester.tap(find.byKey(const Key('hifz-card')));
    await frames(tester, 14);
    expect(find.text('خريطة حفظي'), findsOneWidget);
    expect(find.text('حفظت 3 صفحات! ⭐'), findsOneWidget);

    await tester.tap(find.byKey(const Key('wird-3')));
    await frames(tester, 10);
    // Recorded (five minutes at most), then sent.
    final recorder = tester.widget<VoiceRecorder>(find.byType(VoiceRecorder));
    expect(recorder.limit, const Duration(minutes: 5));
    expect(recorder.big, isTrue);
    expect(find.byKey(const Key('grade-3')), findsNothing);
    recorder.onRecorded(HandInFile(bytes: Uint8List.fromList(List.filled(64, 1)), name: 'recording.wav', mime: 'audio/wav'));
    await frames(tester, 2);
    await tester.tap(find.text('أرسل التسميع'));
    await frames(tester, 10);
    final form = app.api.sent('POST', 'learning/hifz/pages/3/recitations').single.data as FormData;
    expect(form.files.single.key, 'audio');
    expect(form.files.single.value.filename, 'recording.wav');
    expect(form.fields, isEmpty, reason: 'the student is their own learner');
    expect(find.text('نستمع إلى التسميع…'), findsOneWidget);
    final asked = app.api.sent('GET', 'learning/hifz/recitations/rc1').length;

    // Heard: the signal (here, the app coming back) reloads the check, never a timer of its own.
    await frames(tester, 30);
    expect(app.api.sent('GET', 'learning/hifz/recitations/rc1').length, asked);
    heard = true;
    await _awayAndBack(tester);
    await frames(tester, 12);
    expect(find.text('نستمع إلى التسميع…'), findsNothing);
    final sheet = find.byType(BottomSheet);
    expect(find.descendant(of: sheet, matching: find.text('صح 2 من 4 · ناقصة 1 · غلط 1 · زيادة 1')), findsOneWidget);
    expect(find.descendant(of: sheet, matching: find.text('بصعوبة')), findsOneWidget);
    final words = tester.widget<Text>(find.byKey(const Key('recited-words'))).textSpan!.toPlainText(includeSemanticsLabels: false);
    expect(words, contains('قَصِيرَةٌ (طويله)'));
    expect(words, contains('كَلِمَةٌ كلمه أُولَى (1)'));
    expect(find.textContaining('التصحيح آلي'), findsOneWidget);
    // The map came back too, with the day's review done.
    expect(find.text('أنهيت وردك! 🎉'), findsOneWidget);

    await tester.tap(find.text('رائع!'));
    await frames(tester, 20);
    expect(find.text('انتهى ورد اليوم. أحسنت!'), findsOneWidget);
    expect(find.byKey(const Key('hifz-latest')), findsOneWidget);

    await _close(tester);
  });

  testWidgets('when the check cannot hear the page, the child says how it went instead', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('middle')]);
    fxStudentRoutes(app.api, 'middle', fxJourney(stage: 'middle', hifz: fxHifzToday));
    app.api
      ..on(
        'GET',
        'learning/hifz',
        fxHifzBoard(
          mode: 'check',
          latest: fxRecitation(status: 'failed'),
        ),
      )
      ..on('GET', 'learning/hifz/pages/3', fxQuranPage(3))
      ..on('GET', 'learning/hifz/recitations/rc1', {'data': fxRecitation(status: 'failed')})
      ..on('POST', 'learning/hifz/pages/3/grade', fxQuranPage(3));
    await _open(tester, app, size: const Size(420, 2400));
    await tester.tap(find.byKey(const Key('hifz-card')));
    await frames(tester, 14);

    await tester.tap(find.byKey(const Key('hifz-latest')));
    await frames(tester, 10);
    expect(find.text('التسجيل مش واضح.'), findsWidgets);
    await tester.tap(find.text('قيّم الصفحة بنفسك'));
    await frames(tester, 6);
    await tester.tap(find.text('اكشف الصفحة'));
    await frames(tester, 6);
    await tester.tap(find.byKey(const Key('grade-2')));
    await frames(tester, 10);
    expect(app.api.sent('POST', 'learning/hifz/pages/3/grade').single.data, {'grade': 2});

    await _close(tester);
  });

  testWidgets('a teacher opens a student\'s map, says what they know by parts and surahs, and takes pages off', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/students', {
        'data': [
          {'enrollment_id': 'e1', 'student': 'يوسف خالد', 'subject': 'قرآن', 'paused': false, 'percent': 40, 'to_mark': 0},
          {'enrollment_id': 'e2', 'student': 'سلمى رامي', 'subject': 'قرآن', 'paused': false, 'percent': 10, 'to_mark': 0},
          {'enrollment_id': 'e3', 'student': 'آدم مالك', 'subject': 'English', 'paused': false, 'percent': 30, 'to_mark': 0},
        ],
      })
      ..on('GET', 'teacher/students/e1/hifz', fxStudentHifz())
      ..on('GET', 'teacher/students/e2/hifz', fxStudentHifz(total: 0))
      ..on('GET', 'teacher/students/e3/hifz', fxStudentHifz(follows: false))
      ..onWith('POST', 'teacher/students/e1/hifz/memorized', (_) {
        final map = fxStudentHifz()['data']! as Map<String, Object?>;
        return (
          200,
          {
            'data': {'marked': 72, ...map},
          },
        );
      })
      ..on('POST', 'teacher/students/e1/hifz/clear', {
        'data': {'cleared': 20},
      });
    await _open(tester, app);
    await tester.tap(find.text('الطلاب').last);
    await frames(tester, 12);

    // No map to show (no Quran text yet): no way to it.
    await tester.tap(find.byKey(const Key('student-e2')));
    await frames(tester, 10);
    expect(find.byKey(const Key('student-hifz')), findsNothing);
    await tester.tapAt(const Offset(10, 10));
    await frames(tester, 10);

    // Another subject's course (no Quran in its curriculum, no pages): no map either.
    await tester.tap(find.byKey(const Key('student-e3')));
    await frames(tester, 10);
    expect(find.byKey(const Key('student-hifz')), findsNothing);
    await tester.tapAt(const Offset(10, 10));
    await frames(tester, 10);

    await tester.tap(find.byKey(const Key('student-e1')));
    await frames(tester, 10);
    await tester.tap(find.byKey(const Key('student-hifz')));
    await frames(tester, 14);
    expect(GoRouter.of(tester.element(find.byType(Scaffold).last)).state.matchedLocation, '/teach/students/e1/hifz');
    expect(find.text('خريطة الحفظ'), findsOneWidget);
    expect(find.text('3 صفحات محفوظة من 604'), findsOneWidget);
    expect(_squareColour(tester, 1), Theme.of(tester.element(find.byKey(const Key('hifz-map')))).extension<LmsColors>()!.brand);
    expect(find.text('تقرير الحصة · الثلاثاء 6 أكتوبر'), findsOneWidget);
    expect(find.text('جيد'), findsOneWidget);

    // Part 30 and the second surah, found by name, known well.
    await tester.tap(find.text('سجّل ما يحفظه'));
    await frames(tester, 10);
    expect(find.text('لم تختر صفحات بعد'), findsOneWidget);
    await tester.tap(find.byKey(const Key('juz-30')));
    await tester.enterText(find.byKey(const Key('surah-search')), 'التانية');
    await frames(tester, 4);
    await tester.tap(find.byKey(const Key('surah-2')));
    await tester.tap(find.byKey(const Key('mark-strong')));
    await frames(tester, 4);
    expect(find.text('2. السورة التانية · Second'), findsWidgets);
    expect(find.text('72 صفحة'), findsOneWidget);
    await tester.tap(find.byKey(const Key('hifz-choose')));
    await frames(tester, 5);
    expect(app.api.sent('POST', 'teacher/students/e1/hifz/memorized').single.data, {
      'juz': [30],
      'surahs': [2],
      'strength': 'strong',
    });
    expect(find.text('سُجّلت 72 صفحة'), findsOneWidget);
    expect(app.api.sent('GET', 'teacher/students/e1/hifz').length, greaterThanOrEqualTo(3));
    await frames(tester, 20);

    // Part 1 marked by mistake: taken off, after asking.
    await tester.tap(find.text('أزل صفحات'));
    await frames(tester, 10);
    await tester.tap(find.byKey(const Key('juz-1')));
    await frames(tester, 2);
    await tester.tap(find.byKey(const Key('hifz-choose')));
    await frames(tester, 8);
    expect(find.text('أتزيل هذه الصفحات من الخريطة؟'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'أزل صفحات').last);
    await frames(tester, 10);
    expect(app.api.sent('POST', 'teacher/students/e1/hifz/clear').single.data, {
      'juz': [1],
    });
    expect(find.text('أُزيلت 20 صفحة'), findsOneWidget);

    await _close(tester);
  });
}
