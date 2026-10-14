import 'dart:convert';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:academy_app/features/games/domain/games.dart';
import 'package:academy_app/features/games/presentation/player/game_frame.dart';
import 'package:academy_app/features/games/presentation/player/game_session.dart';
import 'package:academy_app/features/journey/domain/journey.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/journey.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _started() => {
  'data': {
    'play_id': '0199c0de-0000-7000-8000-000000000001',
    'page_url': 'http://api.test/games/v1',
    'game': {'id': 'g-bridge', 'key': 'turtle-bridge', 'title': 'جسر السلاحف', 'how_to_play': 'رصّ السلاحف', 'island': 'turtles'},
    'focus': {'reason': 'station', 'title': 'قريش والفيل'},
    'round': {
      'items': [
        {'id': 'i1', 'kind': 'order', 'prompt': 'لإيلاف'},
        {'id': 'i2', 'kind': 'order', 'prompt': 'قريش'},
        {'id': 'i3', 'kind': 'order', 'prompt': 'إيلافهم'},
      ],
      'stage': 'kids',
      'locale': 'ar',
      'dir': 'rtl',
      'title': 'جسر السلاحف',
    },
    'max_seconds': 1200,
  },
};

Map<String, Object?> _finished({int stars = 3, int? seconds = 39, Map<String, Object?>? creature, Map<String, Object?>? homework}) => {
  'data': {
    'stars': stars,
    'correct': 3,
    'total': 3,
    'seconds': seconds,
    'creature': creature,
    'egg': {'have': 9, 'need': 12},
    'homework': homework,
  },
};

/// A stand-in for the game's page: "play" says ready, answers every item (one wrong first) and finishes.
final _sent = <String>[];

Widget _stand(GameFrame frame) => Center(
  child: ElevatedButton(
    onPressed: () {
      frame.session.attach(_sent.add);
      void say(Map<String, Object?> m) {
        final event = frame.session.handle(jsonEncode(m));
        if (event == GameEvent.ready) frame.session.sendStart();
        if (event != GameEvent.ignored) frame.onEvent(event);
      }

      say({'lms': 1, 'type': 'ready'});
      say({'lms': 1, 'type': 'answer', 'item': 'i1', 'correct': false});
      say({'lms': 1, 'type': 'answer', 'item': 'i1', 'correct': true});
      say({'lms': 1, 'type': 'answer', 'item': 'i2', 'correct': true});
      say({'lms': 1, 'type': 'answer', 'item': 'not-mine', 'correct': true});
      say({'lms': 1, 'type': 'answer', 'item': 'i3', 'correct': true});
      say({'lms': 1, 'type': 'finish'});
    },
    child: const Text('play the stand-in'),
  ),
);

Future<void> _open(WidgetTester tester, TestApp app, {Size size = const Size(420, 1000)}) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(size);
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 20);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  // The world's slow loops start late: let those waits run out.
  await tester.pump(const Duration(seconds: 10));
  await tester.binding.setSurfaceSize(null);
}

GoRouter _router(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).first));

void main() {
  setUp(() {
    GameFrame.stand = _stand;
    _sent.clear();
  });
  tearDown(() => GameFrame.stand = null);

  test('reads a journey: levels as islands, stations, missions, stars, the chest and the egg', () {
    final map = JourneyMap.fromJson(fxJourney(eggReady: true, chestReady: true)['data']! as Map<String, dynamic>);
    expect(map.courses.single.islands.map((i) => i.state), ['done', 'here', 'ahead']);
    expect(map.courses.single.here?.title, 'سور أطول');
    expect(map.courses.single.islands[1].here?.id, 's4');
    expect(map.missionsOn(map.courses.single.islands[1].stations[1]).map((m) => m.kind), ['homework', 'lesson']);
    expect(map.nextLesson?.lessonId, 'l1');
    expect(map.chest?.canOpen, isTrue);
    expect(map.games?.eggReady, isTrue);
    expect(map.lastLesson?.labels, ['في الميعاد', 'فضلت لآخر الحصة']);
    // The lesson's room opening, starting and ending, and the chest's week closing, reload the screen.
    expect(map.clockMoments.length, 4);
    // A practice homework plays the teacher's game, else the best one on its station.
    final practice = JourneyMap.fromJson(fxJourney(practiceHomework: true)['data']! as Map<String, dynamic>);
    expect(practice.gameFor(practice.mission('homework:h1')!), 'turtle-bridge');
    expect(map.gameFor(map.mission('homework:h1')!), 'g-bridge');
    // Plain: no stars, chest, egg or games.
    final plain = JourneyMap.fromJson(fxJourney(style: 'plain')['data']! as Map<String, dynamic>);
    expect(plain.rewards, isFalse);
    expect(plain.playable, isFalse);
  });

  test('a session counts only its round\'s items, and nothing after the finish', () {
    final round = StartedRound.fromJson(_started()['data']! as Map<String, dynamic>);
    final session = GameSession(round);
    expect(session.handle('{"lms":1,"type":"answer","item":"i1","correct":true}'), GameEvent.answer);
    expect(session.handle('{"lms":1,"type":"finish"}'), GameEvent.ignored, reason: 'not started yet');
    expect(session.handle('{"lms":1,"type":"ready"}'), GameEvent.ready);
    expect(session.handle('{"lms":1,"type":"answer","item":"zz","correct":true}'), GameEvent.ignored);
    expect(session.handle('not json'), GameEvent.ignored);
    expect(session.handle('{"lms":2,"type":"finish"}'), GameEvent.ignored);
    expect(session.handle('{"lms":1,"type":"finish"}'), GameEvent.finish);
    expect(session.handle('{"lms":1,"type":"answer","item":"i2","correct":true}'), GameEvent.ignored);
    expect(session.answers.map((a) => a.item), ['i1']);
    expect(jsonDecode(session.startMessage()), containsPair('type', 'start'));
  });

  test('games, challenges and the journey open each look\'s journey', () {
    for (final screen in ['games', 'challenges', 'journey']) {
      expect(targetOf(screen, null, Area.kids), '/kids/journey');
      expect(targetOf(screen, null, Area.middle), '/middle/journey');
      expect(targetOf(screen, null, Area.teen), '/teen/journey');
      expect(targetOf(screen, null, Area.family), '/family/journey');
    }
    expect(targetOf('homework', 'h1', Area.kids), '/kids/more/homework');
    expect(targetOf('report', 'r1', Area.family), '/family/journey/courses');
    expect(Area.middle.home, '/middle/journey');
    expect(Area.teen.home, '/teen/journey');
  });

  testWidgets('a child sees a cheer and their last lesson\'s stars once, opens the island they are on, and plays a station', (
    tester,
  ) async {
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(
      app.api,
      'kids',
      fxJourney(
        cheers: [
          {'id': 'ch1', 'kind': 'heart', 'at': time('2099-10-08T10:00:00Z', '2099-10-08T13:00:00+03:00')},
        ],
      ),
    );
    app.api
      ..on('POST', 'games/plays', _started(), status: 201)
      ..on('POST', 'games/plays/*/finish', _finished());
    await _open(tester, app);

    // The tabs: the journey, lessons, talking to the teacher, more. No islands or missions tab.
    final nav = find.byType(NavigationBar);
    for (final tab in ['رحلتي', 'حصصي', 'كلّم مدرسك', 'المزيد']) {
      expect(find.descendant(of: nav, matching: find.text(tab)), findsOneWidget);
    }
    expect(find.descendant(of: nav, matching: find.text('الجزر')), findsNothing);
    expect(find.descendant(of: nav, matching: find.text('مهماتي')), findsNothing);

    // A cheer from the family, once; then it is seen.
    expect(find.text('تشجيع من أهلك!'), findsOneWidget);
    await tester.tap(find.text('شكرًا!'));
    await frames(tester, 8);
    expect(app.api.sent('POST', 'learning/cheers/seen'), hasLength(1));

    expect(find.text('رحلة يوسف'), findsOneWidget);
    // The last lesson's stars, until "great!".
    expect(find.text('خدت 4 نجوم في آخر حصة!'), findsOneWidget);
    expect(find.text('في الميعاد · فضلت لآخر الحصة'), findsOneWidget);
    await tester.tap(find.text('تمام!'));
    await frames(tester, 6);
    expect(find.text('خدت 4 نجوم في آخر حصة!'), findsNothing);

    // The levels as islands: done with a cup, the one they are on with its share, the one ahead locked.
    expect(find.text('سور أطول · 33%'), findsOneWidget);
    await tester.tap(find.bySemanticsLabel('جزء تبارك، لسه مقفولة'));
    await frames(tester, 6);
    expect(find.text('بتتفتح لما تخلّص سور أطول'), findsOneWidget);

    // The island they are on zooms in: its lessons as a path, with the homework flag.
    await tester.tap(find.bySemanticsLabel(RegExp('^سور أطول، انت هنا')));
    await frames(tester, 24);
    expect(find.text('المستوى 2'), findsOneWidget);
    expect(find.text('1 من 3 دروس'), findsOneWidget);

    // A station ahead says so and plays nothing.
    await tester.tap(find.bySemanticsLabel(RegExp('^الهمزة والعصر')));
    await frames(tester, 10);
    expect(find.text('الدرس ده لسه جاي. هتتدرّب عليه لما توصله.'), findsOneWidget);
    expect(find.text('العب على الدرس ده'), findsNothing);
    await tester.tapAt(const Offset(200, 40));
    await frames(tester, 10);

    // The station they are on: its homework and lesson, its games; a game plays this lesson's own items.
    await tester.tap(find.bySemanticsLabel(RegExp('^قريش والفيل. انت هنا. واجب')));
    await frames(tester, 10);
    expect(find.text('واجب: سجّل سورة قريش'), findsOneWidget);
    expect(find.text('حصة · قرآن · حفظ'), findsOneWidget);
    expect(find.text('العب على الدرس ده'), findsOneWidget);
    await tester.tap(find.text('جسر السلاحف'));
    await frames(tester, 12);
    expect(app.api.sent('POST', 'games/plays').single.data, {'game': 'g-bridge', 'station': 's4'});

    await tester.tap(find.text('play the stand-in'));
    await frames(tester, 14);
    final finish = app.api.sent('POST', 'games/plays/0199c0de-0000-7000-8000-000000000001/finish').single;
    expect((finish.data as Map<String, dynamic>)['answers'], hasLength(4));
    expect(finish.headers['Idempotency-Key'], isNotNull);
    expect(find.text('رائع! كله صح تقريبًا'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('a child opens the week\'s chest once, and gets its stars and sticker', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(app.api, 'kids', fxJourney(chestReady: true));
    app.api.on('POST', 'learning/journey/chest', {
      'data': {
        'stars': 10,
        'sticker': {'key': 'sun', 'name': 'شمس'},
        'total_before': 151,
        'total': 161,
        'ranked_up': false,
        'rank': {'key': 'hero', 'label': 'بطل'},
      },
    }, status: 201);
    await _open(tester, app, size: const Size(420, 2600));
    await tester.tap(find.text('تمام!'));
    await frames(tester, 6);

    expect(find.text('الصندوق جاهز! دوس عليه'), findsOneWidget);
    await tester.tap(find.text('الصندوق جاهز! دوس عليه'));
    await frames(tester, 16);
    final open = app.api.sent('POST', 'learning/journey/chest').single;
    expect(open.data, {'period': '2099-10-03'});
    expect(open.headers['Idempotency-Key'], isNotNull);
    expect(find.text('برافو عليك!'), findsOneWidget);
    expect(find.text('+10 نجوم في رحلتك!'), findsOneWidget);
    expect(find.text('شمس'), findsOneWidget);
    await tester.tap(find.text('تمام!'));
    await frames(tester, 8);
    expect(app.api.sent('GET', 'learning/journey/map').length, greaterThanOrEqualTo(2));

    await _close(tester);
  });

  testWidgets('a child knocks the ready egg three times; it hatches, they name it and see it in the album', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(app.api, 'kids', fxJourney(eggReady: true));
    var named = false;
    app.api
      ..on('POST', 'games/hatch', {
        'data': {
          'creature': {
            'key': 'turtles.crab',
            'name': 'كابوريا',
            'emoji': '🦀',
            'rarity': 'common',
            'island': 'turtles',
            'look': fxTurtle,
            'story': 'بتمشي بالعرض',
            'names': ['كابوريا', 'بندق', 'كوكي'],
            'found': 3,
            'total': 20,
          },
          'egg': {'have': 0, 'need': 12},
        },
      }, status: 201)
      ..onWith('PUT', 'games/creatures/turtles.crab', (_) {
        named = true;
        return (200, {'data': fxCreature('turtles.crab', 'كابوريا', island: 'turtles', look: fxTurtle)});
      })
      ..onWith(
        'GET',
        'games/creatures',
        (_) => (
          200,
          {
            'data': [
              {...fxCreature('birds.risha', 'ريشة'), 'names': <String>[]},
              {
                ...fxCreature('turtles.crab', 'كابوريا', found: named, island: 'turtles', look: fxTurtle),
                if (named) 'nickname': 'بندق',
                'names': named ? ['كابوريا', 'بندق', 'كوكي'] : <String>[],
              },
            ],
          },
        ),
      );
    await _open(tester, app, size: const Size(420, 2600));
    await tester.tap(find.text('تمام!'));
    await frames(tester, 6);

    final egg = find.bySemanticsLabel(RegExp('^بيضتك جاهزة!'));
    expect(egg, findsOneWidget);
    for (var i = 0; i < 3; i++) {
      await tester.tap(egg);
      await frames(tester, 4);
    }
    await frames(tester, 16);
    final hatch = app.api.sent('POST', 'games/hatch').single;
    expect(hatch.data, {'island': 'turtles'});
    expect(hatch.headers['Idempotency-Key'], isNotNull);
    // Knocked on the map: it opens at once, and the child names it.
    expect(find.text('فقست!'), findsOneWidget);
    expect(find.text('كائن جديد في ألبومك · 3 من 20'), findsOneWidget);
    await tester.tap(find.text('بندق'));
    await tester.tap(find.text('حطّه في الألبوم'));
    await frames(tester, 20);
    expect(app.api.sent('PUT', 'games/creatures/turtles.crab').single.data, {'name': 'بندق'});
    expect(find.text('ألبوم كائناتي'), findsOneWidget);
    expect(_router(tester).state.matchedLocation, '/kids/journey/album');

    await _close(tester);
  });

  testWidgets('middle school sees its stars toward the next rank and its levels, and races itself on a lesson', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('middle')]);
    fxStudentRoutes(app.api, 'middle', fxJourney(stage: 'middle', stars: 74, lessonOpen: true));
    app.api
      ..on('POST', 'games/plays', _started(), status: 201)
      ..on('POST', 'games/plays/*/finish', _finished(seconds: 39));
    await _open(tester, app, size: const Size(420, 2400));

    final nav = find.byType(NavigationBar);
    for (final tab in ['رحلتي', 'حصصي', 'الرسايل', 'المزيد']) {
      expect(find.descendant(of: nav, matching: find.text(tab)), findsOneWidget);
    }
    expect(find.text('رحلة يوسف'), findsOneWidget);
    expect(find.text('نجومك'), findsOneWidget);
    expect(find.text('باقيلك 226 نجمة لـ أسطورة'), findsOneWidget);
    expect(find.text('7 حصص ورا بعض'), findsOneWidget);
    expect(find.text('مهماتك'), findsOneWidget);
    // The room is open: join from the mission.
    expect(find.widgetWithText(FilledButton, 'ادخل الحصة'), findsOneWidget);
    expect(find.text('المستويات'), findsOneWidget);
    expect(find.text('بتتفتح لما تخلّص سور أطول'), findsOneWidget);

    await tester.tap(find.bySemanticsLabel(RegExp('^قريش والفيل. انت هنا')));
    await frames(tester, 10);
    expect(find.text('سباق مع نفسك'), findsOneWidget);
    expect(find.textContaining('أحسن وقت: 42ث · آخر مرة: 48ث'), findsOneWidget);
    await tester.tap(find.text('جسر السلاحف'));
    await frames(tester, 12);
    expect(app.api.sent('POST', 'games/plays').single.data, {'game': 'g-bridge', 'station': 's4'});
    await tester.tap(find.text('play the stand-in'));
    await frames(tester, 14);
    expect(find.text('كسرت رقمك! 🔥'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('secondary school gets the calm journey: the level, the next lesson with the last one\'s stars, the levels', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('teen')]);
    fxStudentRoutes(app.api, 'teen', fxJourney(stage: 'teen', stars: 55, may: false));
    await _open(tester, app, size: const Size(420, 2400));

    final nav = find.byType(NavigationBar);
    for (final tab in ['رحلتي', 'حصصي', 'الواجبات', 'التقارير']) {
      expect(find.descendant(of: nav, matching: find.text(tab)), findsOneWidget);
    }
    expect(find.descendant(of: nav, matching: find.text('أهدافي')), findsNothing);
    expect(find.text('رحلتك يا يوسف'), findsOneWidget);
    expect(find.text('المستوى 2 من 3'), findsOneWidget);
    expect(find.text('الحصة الجاية'), findsOneWidget);
    expect(find.textContaining('في الميعاد · فضلت لآخر الحصة'), findsOneWidget);
    expect(find.text('المستوى 2 · سور أطول'), findsOneWidget);
    // Games are optional on a station; a closed day says why and starts nothing.
    await tester.tap(find.text('قريش والفيل').last);
    await frames(tester, 10);
    expect(find.text('خلصت دقايق اللعب النهارده. نكمل بكرة! 🌙'), findsOneWidget);
    await tester.tap(find.text('جسر السلاحف'));
    await frames(tester, 8);
    expect(app.api.sent('POST', 'games/plays'), isEmpty);

    await _close(tester);
  });

  testWidgets('a guardian sees their child\'s journey, cheers, sets the play limits and plays together', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..on(
        'GET',
        'learning/journey/map',
        fxJourney(
          stage: 'kids',
          viewer: 'guardian',
          chestReady: true,
          limits: {
            'enabled': true,
            'daily_minutes': null,
            'night_lock': false,
            'academy_minutes': 45,
            'choices': [15, 30, 45, 60],
          },
          glance: {
            'rounds': 4,
            'practised': {'title': 'قريش والفيل', 'rounds': 3, 'games': 2},
            'hardest': {'text': 'لإيلاف ↔ قريش'},
            'best': {'correct': 4, 'total': 4, 'game': 'جسر السلاحف'},
            'hatched': null,
          },
        ),
      )
      ..on('PUT', 'games/limits', {
        'data': {
          'enabled': true,
          'daily_minutes': 30,
          'night_lock': false,
          'academy_minutes': 45,
          'choices': [15, 30, 45, 60],
        },
      })
      ..on('POST', 'learning/cheers', null, status: 201)
      ..on('POST', 'games/plays', _started(), status: 201)
      ..on('POST', 'games/plays/*/finish', _finished());
    await _open(tester, app, size: const Size(420, 3200));

    // Home leads to the journey; the tab took Progress's place.
    expect(find.text('رحلة سلمى'), findsOneWidget);
    expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('التقدم')), findsNothing);
    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('الرحلة')));
    await frames(tester, 14);
    expect(find.text('رحلة سلمى'), findsWidgets);
    expect(find.byKey(const Key('journey-hero')), findsOneWidget);
    expect(
      find.descendant(of: find.byKey(const Key('journey-hero')), matching: find.text('نجوم سلمى · بطل · 7 حصص ورا بعض')),
      findsOneWidget,
    );
    expect(find.text('آخر حصة لـ سلمى · الخميس 8 أكتوبر · 17:00 · في الميعاد · فضلت لآخر الحصة'), findsOneWidget);
    expect(find.text('مهمات سلمى'), findsOneWidget);
    // Only the child opens the chest; no join or hand-in for a guardian.
    expect(find.text('الصندوق جاهز يتفتح من حساب سلمى'), findsOneWidget);
    expect(find.text('سلّم'), findsNothing);
    expect(find.text('12 من 30 دقيقة لعب النهارده. فاضل 18 دقيقة.'), findsOneWidget);
    expect(find.text('60 د'), findsNothing);

    await tester.tap(find.text('قلب'));
    await frames(tester, 8);
    expect(app.api.sent('POST', 'learning/cheers').single.data, {'learner_id': 'c1', 'kind': 'heart'});

    await tester.tap(find.text('30 د'));
    await frames(tester, 8);
    expect(app.api.sent('PUT', 'games/limits').last.data, {'learner_id': 'c1', 'enabled': true, 'daily_minutes': 30});

    // Playing together on the lesson the child is on.
    await tester.tap(find.widgetWithText(FilledButton, 'العب').first);
    await frames(tester, 10);
    await tester.tap(find.text('جسر السلاحف'));
    await frames(tester, 12);
    expect(app.api.sent('POST', 'games/plays').single.data, {'game': 'g-bridge', 'station': 's4', 'learner_id': 'c1'});

    await _close(tester);
  });

  testWidgets('a practice homework is played, never handed in by a form, and shows how it went', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(app.api, 'kids', fxJourney(practiceHomework: true));
    app.api
      ..on('GET', 'learning/homework', {
        'data': [
          {...homeworkJson('h1', accepts: 'practice'), 'practice': null},
          {
            ...homeworkJson('h2', accepts: 'practice', status: 'submitted'),
            'title': 'جولة على الفاتحة',
            'practice': {
              'done': true,
              'correct': 9,
              'total': 10,
              'stars': 3,
              'game': 'توأم العش',
              'label': 'التدريب: 9 من 10 صح · 3 ⭐ (توأم العش)',
            },
          },
        ],
      })
      ..on('POST', 'games/plays', _started(), status: 201)
      ..on('POST', 'games/plays/*/finish', _finished(homework: {'title': 'سمّع سورة النصر', 'handed_in': true}));
    await _open(tester, app, size: const Size(420, 1400));
    await tester.tap(find.text('تمام!'));
    await frames(tester, 4);

    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('المزيد')));
    await frames(tester, 10);
    await tester.tap(find.byKey(const Key('more-homework')));
    await frames(tester, 12);
    expect(find.text('الواجب ده بيتسلّم لما تلعب جولته.'), findsOneWidget);
    expect(find.text('التدريب: 9 من 10 صح · 3 ⭐ (توأم العش)'), findsOneWidget);
    // No empty form to hand in: play it.
    expect(find.text('سلّم'), findsNothing);
    await tester.tap(find.widgetWithText(FilledButton, 'العب'));
    await frames(tester, 14);
    expect(app.api.sent('POST', 'games/plays').single.data, {'game': 'turtle-bridge', 'homework_id': 'h1'});
    await tester.tap(find.text('play the stand-in'));
    await frames(tester, 14);
    expect(find.text('✅ الواجب «سمّع سورة النصر» اتسلّم بالتدريب ده.'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('the kids\' sticker album reads the stickers of their journey', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    final journey = fxJourney();
    (journey['data']! as Map<String, Object?>)['stickers'] = {
      'earned': 1,
      'items': [
        {'key': 'lion', 'name': 'أسد', 'earned': true},
        {'key': 'rocket', 'name': 'صاروخ', 'earned': false},
      ],
    };
    fxStudentRoutes(app.api, 'kids', journey);
    await _open(tester, app);
    _router(tester).go('/kids/more/stickers');
    await frames(tester, 12);
    expect(find.text('1 من 2'), findsOneWidget);
    expect(find.text('أسد'), findsOneWidget);
    expect(app.api.sent('GET', 'learning/challenges'), isEmpty);
    await _close(tester);
  });

  testWidgets('the old places lead to the journey', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(app.api, 'kids', fxJourney());
    app.api.on('GET', 'games/creatures', {'data': <Object>[]});
    await _open(tester, app);
    final router = _router(tester);
    for (final (from, to) in [
      ('/kids/islands', '/kids/journey'),
      ('/kids/missions', '/kids/journey'),
      ('/kids/islands/album', '/kids/journey/album'),
      ('/kids/homework', '/kids/more/homework'),
    ]) {
      router.go(from);
      await frames(tester, 8);
      expect(router.state.matchedLocation, to, reason: from);
    }
    await _close(tester);
  });

  testWidgets('the old places lead to the journey in the older looks and the family', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('teen'), guardian]);
    fxStudentRoutes(app.api, 'teen', fxJourney(stage: 'teen'));
    await _open(tester, app);
    var router = _router(tester);
    for (final (from, to) in [('/teen/plan', '/teen/journey'), ('/teen/plan/games', '/teen/journey'), ('/teen/goals', '/teen/journey')]) {
      router.go(from);
      await frames(tester, 8);
      expect(router.state.matchedLocation, to, reason: from);
    }

    await app.session.open(guardian.id);
    await frames(tester, 16);
    router = _router(tester);
    for (final (from, to) in [
      ('/family/home/games', '/family/journey'),
      ('/family/home/challenges', '/family/journey'),
      ('/family/progress', '/family/journey'),
    ]) {
      router.go(from);
      await frames(tester, 8);
      expect(router.state.matchedLocation, to, reason: from);
    }
    await _close(tester);
  });
}
