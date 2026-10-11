import 'dart:convert';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:academy_app/features/games/domain/games.dart';
import 'package:academy_app/features/games/presentation/player/game_frame.dart';
import 'package:academy_app/features/games/presentation/player/game_session.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Account _student(String stage) => Account(
  academySlug: 'demo',
  academyNameAr: 'أكاديمية النور',
  academyNameEn: 'Noor Academy',
  userId: 'u-$stage',
  userName: 'يوسف خالد',
  roles: const ['student'],
  token: 'token-$stage',
  stage: stage,
);

const _bird = {'kind': 'bird', 'body': '#74C0FC', 'wing': '#4DABF7', 'belly': '#E7F5FF'};
const _turtle = {'kind': 'turtle', 'body': '#2F9E44', 'belly': '#C3FAE8'};

Map<String, Object?> _creature(
  String key,
  String name, {
  bool found = false,
  Map<String, Object?> look = _bird,
  String rarity = 'common',
  bool buddy = false,
}) => {
  'key': key,
  'name': name,
  'emoji': '🐦',
  'rarity': rarity,
  'look': look,
  'story': 'قصة $name',
  'found': found,
  'found_at': found ? time('2099-10-01T10:00:00Z', '2099-10-01T13:00:00+03:00') : null,
  'source': found ? 'stars' : null,
  'nickname': null,
  'buddy': buddy,
};

Map<String, Object?> _game(
  String id,
  String key,
  String island,
  String title, {
  bool playable = true,
  int plays = 0,
  int best = 0,
  Map<String, Object?>? quest,
}) => {
  'id': id,
  'key': key,
  'island': island,
  'title': title,
  'summary': 'ملخص $title',
  'how_to_play': 'إزاي تلعب $title',
  'kinds': ['choice'],
  'playable': playable,
  'plays': plays,
  'best': best,
  'quest': quest,
};

Map<String, Object?> _world(
  String stage, {
  bool guardian = false,
  bool may = true,
  int stars = 7,
  int? bestSeconds,
  Map<String, Object?>? glance,
}) {
  final quest = {'key': 'homework:h1', 'reason': 'homework', 'title': 'حروف المد', 'items': 4, 'done': false};
  final balloons = _game('g1', 'bird-balloons', 'birds', 'بالونات الطيور', plays: 2, best: 2, quest: quest);
  return {
    'data': {
      'learner_id': 'u-$stage',
      'stage': stage,
      'allowance': {
        'may': may,
        'used': 12,
        'limit': 30,
        'left': 18,
        'reason': may ? null : 'limit',
        'reason_label': may ? null : 'خلصت دقايق اللعب النهارده. نكمل بكرة! 🌙',
        'night_lock': false,
      },
      'stars': stars,
      'egg': {'have': 7, 'need': 12},
      'creatures_found': 1,
      'creatures_total': 3,
      'buddy': _creature('sky-chick', 'كتكوت السما', found: true, buddy: true),
      'quests': [
        {
          'key': 'homework:h1',
          'reason': 'homework',
          'reason_label': 'تدريب الواجب',
          'title': 'حروف المد',
          'homework_id': 'h1',
          'due_at': null,
          'done': false,
          'items': 4,
          'game': balloons,
          'rounds': bestSeconds == null ? 0 : 2,
          'best_seconds': bestSeconds,
          'last_seconds': bestSeconds == null ? null : bestSeconds + 6,
        },
      ],
      'streak': 3,
      'islands': [
        {
          'key': 'birds',
          'name': 'جزيرة الطيور',
          'story': 'هنا الطيور بتتعلم الحروف',
          'emoji': '🐦',
          'colors': {'sky': '#8ED8FF'},
          'music': 'major',
          'open': true,
          'fresh': false,
          'homework': true,
          'creatures': [_creature('sky-chick', 'كتكوت السما', found: true, buddy: true), _creature('sun-bird', 'عصفور الشمس')],
          'games': [balloons, _game('g2', 'bird-gates', 'birds', 'بوابات الريح', playable: false)],
          'next': {'key': 'sun-bird', 'look': _bird, 'rarity': 'common'},
        },
        {
          'key': 'turtles',
          'name': 'جزيرة السلاحف',
          'story': 'سلاحف بتحب الكلمات',
          'emoji': '🐢',
          'colors': {'sky': '#A5D8FF'},
          'music': 'pentatonic',
          'open': false,
          'fresh': false,
          'homework': false,
          'creatures': [_creature('sea-turtle', 'سلحفاة البحر', look: _turtle, rarity: 'rare')],
          'games': <Object>[],
          'next': null,
        },
      ],
      'recent': [
        {
          'game': 'بالونات الطيور',
          'stars': 2,
          'correct': 3,
          'total': 4,
          'at': time('2099-10-04T10:00:00Z', '2099-10-04T13:00:00+03:00', label: 'امبارح'),
          'focus': 'homework',
        },
      ],
      'history': [
        {'share': 0.75, 'stars': 2, 'at': time('2099-10-04T10:00:00Z', '2099-10-04T13:00:00+03:00')},
      ],
      'limits': guardian
          ? {
              'enabled': true,
              'daily_minutes': null,
              'night_lock': false,
              'academy_minutes': 45,
              'choices': [15, 30, 45, 60],
            }
          : null,
      'glance': guardian ? glance : null,
    },
  };
}

Map<String, Object?> _started() => {
  'data': {
    'play_id': '0199c0de-0000-7000-8000-000000000001',
    'page_url': 'http://api.test/games/v1',
    'game': {'id': 'g1', 'key': 'bird-balloons', 'title': 'بالونات الطيور', 'how_to_play': 'فرقع البالونة الصح', 'island': 'birds'},
    'focus': {'reason': 'homework', 'title': 'حروف المد'},
    'round': {
      'items': [
        {'id': 'i1', 'kind': 'choice', 'prompt': 'ما'},
        {'id': 'i2', 'kind': 'choice', 'prompt': 'مي'},
        {'id': 'i3', 'kind': 'choice', 'prompt': 'مو'},
      ],
      'stage': 'kids',
      'locale': 'ar',
      'dir': 'rtl',
      'title': 'بالونات الطيور',
    },
    'max_seconds': 1800,
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

void _studentRoutes(FakeApi api, String stage) {
  familyRoutes(api);
  api
    ..on('GET', 'family/learners', {
      'data': [
        {'id': 'u-$stage', 'name': 'يوسف خالد', 'is_self': true, 'stage': stage},
      ],
      'viewer_stage': stage,
    })
    ..on('GET', 'learning/journey', {
      'data': {
        'stars': 3,
        'streak': 2,
        'points': 800,
        'rank': {'key': 'star', 'label': 'نجم', 'points': 800, 'next': 1500, 'next_label': 'بطل', 'progress': 0},
        'badges': <Object>[],
        'courses': <Object>[],
      },
    })
    ..on('GET', 'learning/challenges', {
      'data': {
        'learner_id': 'u-$stage',
        'stage': stage,
        'can_claim': true,
        'week': {'starts_at': null, 'ends_at': null},
        'points': 0,
        'rank': null,
        'challenges': <Object>[],
        'stickers': null,
        'cheers': <Object>[],
        'cheers_left': 0,
      },
    })
    ..on('POST', 'learning/cheers/seen', null, status: 204);
}

Future<void> _open(WidgetTester tester, TestApp app) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1000));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 20);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  // The world's slow loops start late (a cloud after 8 seconds): let those waits run out.
  await tester.pump(const Duration(seconds: 10));
  await tester.binding.setSurfaceSize(null);
}

void main() {
  setUp(() {
    GameFrame.stand = _stand;
    _sent.clear();
  });
  tearDown(() => GameFrame.stand = null);

  testWidgets('a child opens an island, plays its homework game, and hatches and names a creature', (tester) async {
    final app = await TestApp.create(accounts: [_student('kids')]);
    _studentRoutes(app.api, 'kids');
    var named = false;
    app.api
      ..on('GET', 'games/world', _world('kids'))
      ..on('POST', 'games/plays', _started(), status: 201)
      ..on('POST', 'games/plays/*/finish', {
        'data': {
          'stars': 2,
          'correct': 2,
          'total': 3,
          'seconds': 41,
          'creature': {
            'key': 'sun-bird',
            'name': 'عصفور الشمس',
            'emoji': '🐦',
            'rarity': 'common',
            'island': 'birds',
            'look': _bird,
            'story': 'بيغني الصبح',
            'names': ['عصفور الشمس', 'بندق', 'كوكي'],
            'found': 2,
            'total': 3,
          },
          'egg': {'have': 0, 'need': 12},
          'homework': {'title': 'حروف المد', 'handed_in': true},
        },
      })
      ..onWith('PUT', 'games/creatures/sun-bird', (_) {
        named = true;
        return (200, {'data': _creature('sun-bird', 'عصفور الشمس', found: true)});
      })
      ..onWith(
        'GET',
        'games/creatures',
        (_) => (
          200,
          {
            'data': [
              {
                ..._creature('sky-chick', 'كتكوت السما', found: true, buddy: true),
                'island': 'birds',
                'island_name': 'جزيرة الطيور',
                'names': ['كتكوت السما', 'لولو', 'قمر'],
              },
              {
                ..._creature('sun-bird', 'عصفور الشمس', found: named),
                if (named) 'nickname': 'بندق',
                'island': 'birds',
                'island_name': 'جزيرة الطيور',
                'names': named ? ['عصفور الشمس', 'بندق', 'كوكي'] : <String>[],
              },
              {
                ..._creature('sea-turtle', 'سلحفاة البحر', look: _turtle, rarity: 'rare'),
                'island': 'turtles',
                'island_name': 'جزيرة السلاحف',
                'names': <String>[],
              },
            ],
          },
        ),
      );
    await _open(tester, app);

    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('الجزر')));
    await frames(tester, 14);
    expect(find.text('عالم يوسف'), findsOneWidget);
    // The current island holds the first quest, with how many of its creatures were found; the homework is pinned.
    expect(find.text('جزيرة الطيور · 1 من 2'), findsOneWidget);
    expect(find.text('واجب'), findsOneWidget);

    // A locked island wobbles and says why.
    await tester.tap(find.bySemanticsLabel('جزيرة السلاحف، لسه مقفولة'));
    await frames(tester, 6);
    expect(find.text('جزيرة السلاحف لسه بتتجهّز، وهتتفتح بألعاب جديدة قريب'), findsOneWidget);

    // The open island zooms in: its story, its games with their badges, the egg.
    await tester.tap(find.bySemanticsLabel('جزيرة الطيور، فيها الواجب'));
    await frames(tester, 24);
    expect(find.text('هنا الطيور بتتعلم الحروف'), findsOneWidget);
    expect(find.text('بالونات الطيور'), findsOneWidget);
    expect(find.text('قريب'), findsOneWidget);
    expect(find.text('البيضة الجاية: 7 من 12 ⭐'), findsOneWidget);

    // A game that needs more practice only says so.
    await tester.tap(find.text('بوابات الريح'));
    await frames(tester, 4);
    expect(app.api.sent('POST', 'games/plays'), isEmpty);
    expect(find.text('محتاجة تدريبات من النوع ده في دروسك.'), findsWidgets);

    await tester.tap(find.text('بالونات الطيور'));
    await frames(tester, 12);
    expect(app.api.sent('POST', 'games/plays').single.data, {'game': 'g1', 'quest': 'homework:h1'});
    expect(find.text('بتتمرن على: حروف المد'), findsOneWidget);

    // The page is handed its round, and only its own items' first tries count.
    await tester.tap(find.text('play the stand-in'));
    await frames(tester, 12);
    final start = jsonDecode(_sent.first) as Map<String, dynamic>;
    expect(start['type'], 'start');
    expect((start['round'] as Map<String, dynamic>)['items'], hasLength(3));
    final finish = app.api.sent('POST', 'games/plays/0199c0de-0000-7000-8000-000000000001/finish').single;
    expect((finish.data as Map<String, dynamic>)['answers'], [
      {'item': 'i1', 'correct': false},
      {'item': 'i1', 'correct': true},
      {'item': 'i2', 'correct': true},
      {'item': 'i3', 'correct': true},
    ]);
    expect(finish.headers['Idempotency-Key'], isNotNull);

    // An egg was full: it hatches after three knocks, and the child names it.
    expect(find.text('البيضة جاهزة!'), findsOneWidget);
    for (var i = 0; i < 3; i++) {
      await tester.tap(find.bySemanticsLabel(RegExp('البيضة، دوس عليها')));
      await frames(tester, 6);
    }
    await frames(tester, 10);
    expect(find.text('فقست!'), findsOneWidget);
    expect(find.text('كائن جديد في ألبومك · 2 من 3'), findsOneWidget);
    await tester.tap(find.text('بندق'));
    await tester.tap(find.text('حطّه في الألبوم'));
    await frames(tester, 20);
    expect(app.api.sent('PUT', 'games/creatures/sun-bird').single.data, {'name': 'بندق'});

    // It lands in the album, its sheet open.
    expect(find.text('ألبوم كائناتي'), findsOneWidget);
    expect(find.text('2 من 3'), findsOneWidget);
    expect(find.text('خليه صاحبك'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('a middle-school student races their best time and sees the record beaten', (tester) async {
    final app = await TestApp.create(accounts: [_student('middle')]);
    _studentRoutes(app.api, 'middle');
    app.api
      ..on('GET', 'games/world', _world('middle', bestSeconds: 48))
      ..on('POST', 'games/plays', _started(), status: 201)
      ..on('POST', 'games/plays/*/finish', {
        'data': {
          'stars': 3,
          'correct': 3,
          'total': 3,
          'seconds': 39,
          'creature': null,
          'egg': {'have': 10, 'need': 12},
          'homework': null,
        },
      });
    await _open(tester, app);

    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('الألعاب')));
    await frames(tester, 14);
    expect(find.text('سباق مع نفسك'), findsOneWidget);
    expect(find.text('أحسن وقت: 48ث'), findsOneWidget);

    await tester.tap(find.text('يلا'));
    await frames(tester, 12);
    await tester.tap(find.text('play the stand-in'));
    await frames(tester, 14);
    expect(find.text('كسرت رقمك! 🔥'), findsOneWidget);
    expect(find.text('3 من 3 صح في 39ث'), findsOneWidget);

    await tester.tap(find.text('اقفل اللعبة وارجع'));
    await frames(tester, 10);
    expect(find.text('سباق مع نفسك'), findsOneWidget);
    expect(app.api.sent('GET', 'games/world').length, greaterThanOrEqualTo(2));
    await _close(tester);
  });

  testWidgets('a teen reviews on the week\'s constellation; a closed day says why and starts nothing', (tester) async {
    final app = await TestApp.create(accounts: [_student('teen')]);
    _studentRoutes(app.api, 'teen');
    app.api
      ..on('GET', 'games/world', _world('teen', may: false))
      ..on('GET', 'learning/students', {'data': <Object>[]});
    await _open(tester, app);

    await tester.tap(find.text('الألعاب'));
    await frames(tester, 14);
    expect(find.text('كوكبة الأسبوع'), findsOneWidget);
    expect(find.text('خلصت دقايق اللعب النهارده. نكمل بكرة! 🌙'), findsOneWidget);
    await tester.tap(find.text('ابدأ المراجعة'));
    await frames(tester, 6);
    expect(app.api.sent('POST', 'games/plays'), isEmpty);
    await _close(tester);
  });

  testWidgets('a guardian sees the child\'s play, sets the minutes and the night lock, and cheers a new creature', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..on(
        'GET',
        'games/world',
        _world(
          'kids',
          guardian: true,
          glance: {
            'rounds': 4,
            'practised': {'title': 'حروف المد', 'rounds': 3, 'games': 2},
            'hardest': {'text': 'مو → واو'},
            'best': {'correct': 4, 'total': 4, 'game': 'بالونات الطيور'},
            'hatched': {'key': 'sun-bird', 'name': 'بندق', 'look': _bird, 'island': 'جزيرة الطيور', 'source': 'stars', 'found': 2},
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
      ..on('GET', 'learning/challenges', {
        'data': {
          'learner_id': 'c1',
          'stage': 'classic',
          'can_claim': false,
          'week': {'starts_at': null, 'ends_at': null},
          'points': 0,
          'rank': null,
          'challenges': <Object>[],
          'stickers': null,
          'cheers': <Object>[],
          'cheers_left': 5,
        },
      });
    await _open(tester, app);

    await tester.scrollUntilVisible(find.text('وقت اللعب، اللي اتراجع، والحدود'), 200);
    await tester.tap(find.text('وقت اللعب، اللي اتراجع، والحدود'));
    await frames(tester, 14);
    expect(find.text('12 من 30 دقيقة لعب النهارده. فاضل 18 دقيقة.'), findsOneWidget);
    // Never more than the academy's own minutes.
    expect(find.text('60 د'), findsNothing);
    expect(find.text('اتراجع «حروف المد» 3 مرات في لعبتين'), findsOneWidget);
    expect(find.text('أصعب سؤال: «مو → واو». ممكن تراجعوه سوا.'), findsOneWidget);

    await tester.tap(find.text('30 د'));
    await frames(tester, 8);
    expect(app.api.sent('PUT', 'games/limits').last.data, {'learner_id': 'u-kids', 'enabled': true, 'daily_minutes': 30});

    await tester.tap(find.text('قفل اللعب من 9 بالليل لـ 7 الصبح'));
    await frames(tester, 8);
    expect((app.api.sent('PUT', 'games/limits').last.data as Map<String, dynamic>)['night_lock'], true);

    await tester.scrollUntilVisible(find.text('هنّيه'), 200);
    await tester.tap(find.text('هنّيه'));
    await frames(tester, 8);
    expect(app.api.sent('POST', 'learning/cheers').single.data, {'learner_id': 'u-kids', 'kind': 'heart'});
    expect(find.text('وصلته تهنئتك 💛'), findsOneWidget);
    await _close(tester);
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

  test('a link to the games opens each look\'s own place', () {
    expect(targetOf('games', null, Area.kids), '/kids/islands');
    expect(targetOf('games', null, Area.middle), '/middle/games');
    expect(targetOf('games', null, Area.teen), '/teen/plan/games');
    expect(targetOf('games', null, Area.family), '/family/home/games');
  });
}
