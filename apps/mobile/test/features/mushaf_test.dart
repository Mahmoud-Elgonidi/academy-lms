import 'dart:async';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/theme/app_theme.dart';
import 'package:academy_app/core/realtime/live.dart';
import 'package:academy_app/core/state/load_cubit.dart';
import 'package:academy_app/features/mushaf/data/mushaf_repository.dart';
import 'package:academy_app/features/mushaf/domain/mushaf.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../core/realtime_test.dart' show FakeLink, FakeSource, settle;
import '../fixtures/hifz.dart';
import '../fixtures/journey.dart';
import '../fixtures/mushaf.dart';
import '../helpers.dart';
import 'recitation_check_test.dart' show surahsJson;
import 'report_voice_draft_test.dart' show item;
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

/// The span a word of the Quran's text is drawn with.
TextSpan _word(WidgetTester tester, String word) {
  for (final text in tester.widgetList<RichText>(find.byType(RichText))) {
    TextSpan? found;
    text.text.visitChildren((span) {
      if (span is TextSpan && span.text == word) {
        found = span;
        return false;
      }
      return true;
    });
    if (found != null) return found!;
  }
  throw StateError('no span for $word');
}

/// A verse read aloud says this of one of its words (a verse's words are read as one line).
Finder _said(String words) => find.bySemanticsLabel(RegExp(RegExp.escape(words)));

LmsColors _colours(WidgetTester tester) => Theme.of(tester.element(find.byKey(const Key('mushaf-page')))).extension<LmsColors>()!;

void main() {
  test('reads a lesson\'s page: verses word by word, this lesson\'s marks, the earlier ones, and whose page it is', () {
    final m = LessonMushaf.fromJson(
      fxMushaf(
            teacherPage: 2,
            marks: [fxMark(2, 1, 2, 'wrong'), fxMark(2, 2, 1, 'something new')],
            earlier: [
              {'surah': 2, 'verse': 2, 'word': 1, 'kind': 'hesitation', 'times': 3},
            ],
          )['data']!
          as Map<String, dynamic>,
    );
    expect((m.teaches, m.canMark, m.page, m.pagesTotal, m.juz), (true, true, 2, 604, 1));
    expect(m.verses.map((v) => (v.surah, v.verse, v.words.length)), [(1, 3, 2), (2, 1, 3), (2, 2, 2)]);
    // A kind this app does not know is let be.
    expect(m.marks, {(surah: 2, verse: 1, word: 2): MistakeKind.wrong});
    expect(m.earlier[(surah: 2, verse: 2, word: 1)], (kind: MistakeKind.hesitation, times: 3));
    expect(m.label(MistakeKind.tajweed), 'تجويد');
    expect(m.bySurah.map((g) => g.first.surah), [1, 2]);
    // The basmala heads the second surah only: never al-Fatiha's or at-Tawba's verse 1.
    expect(m.verses.map(m.basmalaBefore), [false, true, false]);
    expect(m.basmalaBefore(const MushafVerse(surah: 9, verse: 1, words: [])), isFalse);
    expect(m.basmalaBefore(const MushafVerse(surah: 1, verse: 1, words: [])), isFalse);
    expect(m.surah(2)?.nameEn, 'Second');
    expect((m.hasPrevious, m.hasNext), (true, true));

    // Whose page: the teacher's once turned, else where the lesson starts.
    Map<String, dynamic> data(Map<String, Object?> json) => json['data']! as Map<String, dynamic>;
    expect(LessonMushaf.fromJson(data(fxMushaf(page: 3, teacherPage: 2))).onTeachersPage, isFalse);
    expect(LessonMushaf.fromJson(data(fxMushaf(page: 3, teacherPage: 3))).onTeachersPage, isTrue);
    expect(LessonMushaf.fromJson(data(fxMushaf(page: 2))).teachersView, 2);
    expect(LessonMushaf.fromJson(data(fxMushaf(page: 3))).basmala, isNull);
    expect(arabicDigits(12), '١٢');
    expect(MistakeKind.of('tajweed'), MistakeKind.tajweed);
    expect(MistakeKind.of('typo'), isNull);
  });

  test('reads the notebook by surah and verse, each word with how many times, what it was and when last', () {
    final book = MistakesBook.fromJson(fxMistakesBook()['data']! as Map<String, dynamic>);
    expect(
      (book.words, book.isEmpty, book.surahs.single.name('en'), book.surahs.single.name('ar')),
      (3, false, 'Second', 'السورة التانية'),
    );
    final first = book.surahs.single.verses.first;
    expect(first.marks.keys, [2, 3]);
    final twice = first.marks[2]!;
    expect((twice.times, twice.lastAt?.label), (2, 'الثلاثاء 6 أكتوبر'));
    expect(twice.kinds, [MistakeKind.hesitation, MistakeKind.wrong]);
    // Drawn in the worst of what it was.
    expect(twice.worst, MistakeKind.wrong);
    expect(first.marks[3]!.worst, MistakeKind.tajweed);
    expect(MistakesBook.fromJson(fxMistakesBook(empty: true)['data']! as Map<String, dynamic>).isEmpty, isTrue);
  });

  test('the room\'s data is heard only on the mushaf topic and only from the teacher\'s seat', () {
    const teacher = '{"role":"teacher","user_id":"u-teacher"}';
    expect(isMushafSignal(topic: 'mushaf', metadata: teacher), isTrue);
    expect(isMushafSignal(topic: 'mushaf', metadata: '{"role":"student","user_id":"u-kid"}'), isFalse);
    expect(isMushafSignal(topic: 'mushaf', metadata: '{"role":"guardian"}'), isFalse);
    expect(isMushafSignal(topic: 'chat', metadata: teacher), isFalse);
    expect(isMushafSignal(topic: null, metadata: teacher), isFalse);
    expect(isMushafSignal(topic: 'mushaf', metadata: null), isFalse, reason: 'sent from the server API, or no seat');
    expect(isMushafSignal(topic: 'mushaf', metadata: 'teacher'), isFalse);
    expect(isMushafSignal(topic: 'mushaf', metadata: '["teacher"]'), isFalse);
    expect(String.fromCharCodes(mushafSignal()), '{"v":1}');
  });

  test('a lesson card asks whether its lesson has a mushaf once in a while, and again after the network was down', () async {
    final app = await TestApp.create();
    var now = DateTime(2099, 10, 6, 19);
    final repo = MushafRepository(app.client, clock: () => now);
    var status = 404;
    app.api.onWith('GET', 'learning/lessons/*/mushaf', (_) => (status, status == 200 ? fxMushaf() : {'status': status}));

    expect(await repo.has('l1'), isFalse);
    status = 200;
    expect(await repo.has('l1'), isFalse, reason: 'known for a while');
    expect(app.api.sent('GET', 'learning/lessons/l1/mushaf'), hasLength(1));
    now = now.add(const Duration(minutes: 11));
    expect(await repo.has('l1'), isTrue);
    expect(await repo.has('l2'), isTrue);

    status = 503;
    expect(await repo.has('l3'), isFalse);
    status = 200;
    expect(await repo.has('l3'), isTrue, reason: 'a failure is not remembered');
  });

  test('a lesson\'s mushaf reloads on the mushaf signal, and not on others', () async {
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
    final cubit = LoadCubit<int>(() async => ++fetched, refreshOn: live.on({Live.mushaf}));
    await cubit.load();
    await live.follow('demo:7');
    await settle();
    links.single.hello();
    await settle();

    links.single.signal('private-user.7', Live.hifz);
    await Future<void>.delayed(const Duration(milliseconds: 60));
    expect(fetched, 1);
    links.single.signal('private-user.7', 'mushaf.changed');
    await Future<void>.delayed(const Duration(milliseconds: 60));
    expect(fetched, 2);

    await cubit.close();
    await live.close();
  });

  testWidgets('a teacher opens the lesson\'s mushaf from the next lesson, marks a word, takes it off and turns the page', (tester) async {
    final semantics = tester.ensureSemantics();
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final earlier = [
      {'surah': 2, 'verse': 2, 'word': 1, 'kind': 'wrong', 'times': 2},
    ];
    app.api
      ..on('GET', 'teacher/lessons', {
        'from': '2099-10-11',
        'to': '2099-10-11',
        'count': 1,
        'minutes': 45,
        'data': [lesson('l1')],
      })
      ..on('GET', 'learning/lessons/l1/mushaf', fxMushaf(earlier: earlier))
      ..on('POST', 'learning/lessons/l1/mistakes', fxMushaf(marks: [fxMark(2, 1, 2, 'wrong')], earlier: earlier))
      ..on('DELETE', 'learning/lessons/l1/mistakes', fxMushaf(earlier: earlier))
      ..onWith('PUT', 'learning/lessons/l1/mushaf', (r) {
        final page = (r.data as Map<String, dynamic>)['page'] as int;
        return (200, fxMushaf(page: page, teacherPage: page));
      });
    await _open(tester, app, size: const Size(420, 1800));

    await tester.tap(find.text('افتح المصحف'));
    await frames(tester, 14);
    expect(GoRouter.of(tester.element(find.byType(Scaffold).last)).state.matchedLocation, '/lessons/l1/mushaf');
    expect(find.text('صفحة 2 · السورة الأولى، السورة التانية'), findsOneWidget);
    expect(find.text('الجزء 1'), findsOneWidget); // the page is in the title already
    expect(find.text('انقر على أي كلمة لتعلّمها، ويرى الطالب الصفحة التي تفتحها.'), findsOneWidget);
    // The second surah begins here: its name, then its basmala from the platform's copy; the verses numbered at their end.
    expect(find.byKey(const Key('mushaf-surah-2')), findsOneWidget);
    expect(find.byKey(const Key('mushaf-surah-1')), findsNothing);
    expect(find.text('بِاسْمِ الْبِدَايَةِ'), findsOneWidget);
    final text = tester.widget<Text>(find.byKey(const Key('mushaf-text-2'))).textSpan!.toPlainText(includeSemanticsLabels: false);
    expect(text, contains('جُمْلَةٌ قَصِيرَةٌ جِدًّا ﴿١﴾ سَطْرٌ'));
    expect(_word(tester, 'جُمْلَةٌ').style?.fontFamily, 'AmiriQuran');
    expect(find.text('Tanzil Quran Text'), findsOneWidget);
    // A word marked in an earlier lesson: underlined faintly, and said so.
    expect(_word(tester, 'سَطْرٌ').style?.decorationStyle, TextDecorationStyle.dotted);
    expect(find.semantics.byLabel('سَطْرٌ: خطأ، عُلّمت في حصص سابقة مرتين'), findsOne);

    // A word: what it was.
    await tester.tapOnText(find.textRange.ofSubstring('قَصِيرَةٌ'));
    await frames(tester, 8);
    expect(find.text('علّم الكلمة'), findsOneWidget);
    expect(find.byKey(const Key('kind-clear')), findsNothing);
    await tester.tap(find.byKey(const Key('kind-wrong')));
    await frames(tester, 8);
    expect(app.api.sent('POST', 'learning/lessons/l1/mistakes').single.data, {
      'surah': 2,
      'verse': 1,
      'word': 2,
      'kind': 'wrong',
      'page': 2,
    });
    expect(_word(tester, 'قَصِيرَةٌ').style?.backgroundColor, _colours(tester).dangerSoft);
    expect(find.semantics.byLabel('قَصِيرَةٌ: خطأ'), findsOne);

    // Marked by mistake: the mark comes off.
    await tester.tapOnText(find.textRange.ofSubstring('قَصِيرَةٌ'));
    await frames(tester, 8);
    await tester.tap(find.byKey(const Key('kind-clear')));
    await frames(tester, 8);
    expect(app.api.sent('DELETE', 'learning/lessons/l1/mistakes').single.data, {'surah': 2, 'verse': 1, 'word': 2, 'page': 2});
    expect(_word(tester, 'قَصِيرَةٌ').style?.backgroundColor, isNull);

    // The teacher's turn moves the student's screen: by word, and by number.
    await tester.tap(find.text('الصفحة التالية'));
    await frames(tester, 8);
    expect(app.api.sent('PUT', 'learning/lessons/l1/mushaf').single.data, {'page': 3});
    expect(find.text('صفحة 3 · السورة التانية'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('mushaf-number')), '١٠');
    await tester.testTextInput.receiveAction(TextInputAction.go);
    await frames(tester, 8);
    expect(app.api.sent('PUT', 'learning/lessons/l1/mushaf').last.data, {'page': 10});
    expect(find.text('صفحة 10 · السورة التانية'), findsOneWidget);
    await expectLater(tester, meetsGuideline(androidTapTargetGuideline));

    semantics.dispose();
    await _close(tester);
  });

  testWidgets('a student follows the teacher\'s page, looks elsewhere, and goes back to it', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('teen')]);
    fxStudentRoutes(app.api, 'teen', fxJourney(stage: 'teen', style: 'calm'));
    var teacherPage = 2;
    app.api.onWith('GET', 'learning/lessons/l1/mushaf', (r) {
      final asked = int.tryParse('${r.queryParameters['page'] ?? ''}');
      return (200, fxMushaf(role: 'student', canMark: false, page: asked ?? teacherPage, teacherPage: teacherPage));
    });
    await _open(tester, app);

    // The next lesson on My lessons has its mushaf.
    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('حصصي')));
    await frames(tester, 14);
    await tester.tap(find.text('افتح المصحف'));
    await frames(tester, 14);
    expect(find.text('تتقلّب الصفحة مع المعلّم.'), findsOneWidget);
    expect(app.api.sent('GET', 'learning/lessons/l1/mushaf').last.queryParameters, isEmpty);

    // Words are only read here.
    await tester.tapOnText(find.textRange.ofSubstring('قَصِيرَةٌ'));
    await frames(tester, 6);
    expect(find.byKey(const Key('kind-wrong')), findsNothing);

    // A look at the next page: the teacher's stays a tap away.
    await tester.tap(find.text('الصفحة التالية'));
    await frames(tester, 8);
    expect(app.api.sent('GET', 'learning/lessons/l1/mushaf').last.queryParameters, {'page': 3});
    expect(find.text('صفحة 3 · السورة التانية'), findsOneWidget);
    expect(find.text('المعلّم على صفحة 2'), findsOneWidget);
    expect(app.api.requests.where((r) => r.method != 'GET'), isEmpty, reason: 'a student never turns the lesson\'s page');

    // The teacher turns meanwhile: the student stays where they look, told where the teacher is.
    teacherPage = 4;
    await _awayAndBack(tester);
    await frames(tester, 10);
    expect(find.text('صفحة 3 · السورة التانية'), findsOneWidget);
    expect(find.text('المعلّم على صفحة 4'), findsOneWidget);

    await tester.tap(find.text('ارجع إلى صفحة المعلّم'));
    await frames(tester, 8);
    expect(app.api.sent('GET', 'learning/lessons/l1/mushaf').last.queryParameters, isEmpty);
    expect(find.text('صفحة 4 · السورة التانية'), findsOneWidget);
    expect(find.text('تتقلّب الصفحة مع المعلّم.'), findsOneWidget);

    // Following again: the next turn moves this screen too.
    teacherPage = 5;
    await _awayAndBack(tester);
    await frames(tester, 10);
    expect(find.text('صفحة 5 · السورة التانية'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('a lesson without a mushaf shows no way to it, and its address says so', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    await _open(tester, app);
    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('الحصص')));
    await frames(tester, 14);
    await tester.tap(find.byKey(const Key('lesson-l1')));
    await frames(tester, 10);
    expect(app.api.sent('GET', 'learning/lessons/l1/mushaf'), isNotEmpty);
    expect(find.text('افتح المصحف'), findsNothing);
    await tester.tapAt(const Offset(10, 10));
    await frames(tester, 10);

    unawaited(GoRouter.of(tester.element(find.byType(Scaffold).first)).push('/lessons/l1/mushaf'));
    await frames(tester, 14);
    expect(find.text('لا يوجد مصحف في هذه الحصة.'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('a guardian opens a lesson\'s mushaf from its sheet', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('GET', 'learning/lessons/l1/mushaf', fxMushaf(role: 'guardian', canMark: false, teacherPage: 2));
    await _open(tester, app);
    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('الحصص')));
    await frames(tester, 14);
    await tester.tap(find.byKey(const Key('lesson-l1')));
    await frames(tester, 10);
    await tester.tap(find.text('افتح المصحف'));
    await frames(tester, 14);
    expect(find.byType(BottomSheet), findsNothing);
    expect(find.text('صفحة 2 · السورة الأولى، السورة التانية'), findsOneWidget);
    expect(find.text('تتقلّب الصفحة مع المعلّم.'), findsOneWidget);
    expect(find.byKey(const Key('mushaf-hint')), findsNothing);

    await _close(tester);
  });

  testWidgets('the learner\'s hifz map ends with the mistakes notebook, each word saying what it was', (tester) async {
    final semantics = tester.ensureSemantics();
    final app = await TestApp.create(accounts: [fxStudent('teen')]);
    fxStudentRoutes(app.api, 'teen', fxJourney(stage: 'teen', style: 'calm', hifz: fxHifzToday));
    app.api
      ..on('GET', 'learning/hifz', fxHifzBoard())
      ..on('GET', 'learning/mistakes', fxMistakesBook());
    await _open(tester, app, size: const Size(420, 2800));
    await tester.tap(find.byKey(const Key('hifz-card')));
    await frames(tester, 14);

    expect(find.text('دفتر الأخطاء'), findsOneWidget);
    expect(find.text('الكلمات التي عُلّمت في الحصص خلال الأشهر الستة الأخيرة، لتراجعها.'), findsOneWidget);
    expect(find.text('3 كلمات'), findsWidgets);
    expect(find.text('سورة السورة التانية'), findsOneWidget);
    expect(app.api.sent('GET', 'learning/mistakes').single.queryParameters, isEmpty, reason: 'the student is their own learner');
    expect(_said('قَصِيرَةٌ: تردد، خطأ، مرتين، آخر مرة الثلاثاء 6 أكتوبر'), findsOneWidget);
    expect(_said('جِدًّا: تجويد، مرة، آخر مرة الثلاثاء 6 أكتوبر'), findsOneWidget);
    final colours = Theme.of(tester.element(find.byKey(const Key('mistakes-book')))).extension<LmsColors>()!;
    expect(_word(tester, 'قَصِيرَةٌ').style?.backgroundColor, colours.dangerSoft);
    expect(_word(tester, 'جِدًّا').style?.backgroundColor, colours.infoSoft);
    expect(_word(tester, 'جُمْلَةٌ').style?.backgroundColor, isNull);

    semantics.dispose();
    await _close(tester);
  });

  testWidgets('a child\'s notebook is the words we are practising, in gentle colours, with no counts or dates', (tester) async {
    final semantics = tester.ensureSemantics();
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(app.api, 'kids', fxJourney(hifz: fxHifzToday));
    app.api
      ..on('GET', 'learning/hifz', fxHifzBoard())
      ..on('GET', 'learning/mistakes', fxMistakesBook());
    await _open(tester, app, size: const Size(420, 2800));
    await tester.tap(find.text('رائع!'));
    await frames(tester, 6);
    await tester.tap(find.byKey(const Key('hifz-card')));
    await frames(tester, 14);

    expect(find.text('كلمات نراجعها'), findsOneWidget);
    expect(find.text('هذه كلمات سنقولها معًا مرة أخرى حتى تصير سهلة.'), findsOneWidget);
    expect(find.text('دفتر الأخطاء'), findsNothing);
    expect(_said('قَصِيرَةٌ: كلمة نراجعها'), findsOneWidget);
    // No counts or dates in the kids' look.
    expect(find.text('3 كلمات'), findsNothing);
    expect(_said('مرتين'), findsNothing);
    expect(_said('أكتوبر'), findsNothing);
    final colours = Theme.of(tester.element(find.byKey(const Key('mistakes-book')))).extension<LmsColors>()!;
    expect(_word(tester, 'قَصِيرَةٌ').style?.backgroundColor, colours.rewardSoft);
    expect(_word(tester, 'سَطْرٌ').style?.backgroundColor, colours.rewardSoft);

    semantics.dispose();
    await _close(tester);
  });

  testWidgets('a teacher reads a student\'s notebook beside their map, empty until a word is marked', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/students/e1/hifz', fxStudentHifz())
      ..on('GET', 'teacher/students/e1/mistakes', fxMistakesBook(empty: true));
    await _open(tester, app, size: const Size(420, 2600));
    unawaited(GoRouter.of(tester.element(find.byType(Scaffold).first)).push('/teach/students/e1/hifz'));
    await frames(tester, 14);

    expect(find.text('دفتر الأخطاء'), findsOneWidget);
    expect(find.text('لا توجد كلمات معلَّمة في الأشهر الستة الأخيرة.'), findsOneWidget);
    expect(app.api.sent('GET', 'teacher/students/e1/mistakes'), hasLength(1));

    await _close(tester);
  });

  testWidgets('a report offers to recite again the verses with the lesson\'s marked words', (tester) async {
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
          'mistakes_suggestion': {'surah': 2, 'from': 1, 'to': 2, 'title': 'السورة التانية ١–٢'},
        },
      })
      ..on('GET', 'teacher/texts/quran', surahsJson())
      ..on('PUT', 'teacher/reports/r1', {'data': report('r1', status: 'submitted')});
    await _open(tester, app, size: const Size(420, 2400));

    await tester.tap(find.byKey(const Key('today-reports')));
    await frames(tester, 10);
    await tester.tap(find.text('يوسف خالد'));
    await frames(tester, 10);
    await tester.tap(find.text('أعطِ واجبًا'));
    await frames(tester, 3);
    await tester.enterText(find.widgetWithText(TextField, 'الواجب'), 'سمّع الآيات تاني');
    await tester.tap(find.text('تسجيل صوت'));
    await frames(tester, 2);

    // The check is off; the chip turns it on with the marked verses.
    expect(tester.widget<SwitchListTile>(find.byKey(const Key('recite'))).value, isFalse);
    await tester.tap(find.text('من أخطاء الحصة: السورة التانية ١–٢'));
    await frames(tester, 3);
    expect(tester.widget<SwitchListTile>(find.byKey(const Key('recite'))).value, isTrue);
    expect(find.text('2. السورة التانية · Second'), findsOneWidget);
    expect(tester.widget<TextField>(find.byKey(const Key('recite-from'))).controller!.text, '1');
    expect(tester.widget<TextField>(find.byKey(const Key('recite-to'))).controller!.text, '2');

    await tester.scrollUntilVisible(find.text('أرسل التقرير'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('أرسل التقرير'));
    await frames(tester, 12);
    final sent = app.api.sent('PUT', 'teacher/reports/r1').single.data as Map<String, dynamic>;
    expect((sent['homework'] as Map<String, dynamic>)['expected'], {'surah': 2, 'from': 1, 'to': 2});

    await _close(tester);
  });
}
