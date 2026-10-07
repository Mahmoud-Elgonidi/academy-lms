import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/account.dart';
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

Map<String, Object?> _journey() => {
  'data': {
    'stars': 3,
    'streak': 2,
    'points': 800,
    'rank': {'key': 'star', 'label': 'نجم', 'points': 800, 'next': 1500, 'next_label': 'بطل', 'progress': 0},
    'badges': <Object>[],
    'courses': <Object>[],
  },
};

Map<String, Object?> _challenge(
  String key,
  String period, {
  String group = 'week',
  String state = 'active',
  int target = 2,
  int progress = 1,
  int reward = 30,
  String unit = 'جوهرة',
  Map<String, Object?>? sticker,
  bool self = true,
}) => {
  'key': key,
  'period': period,
  'group': group,
  'title': 'تحدي $key',
  'hint': 'وصف $key',
  'icon': 'star',
  'target': target,
  'progress': progress,
  'state': state,
  'state_label': state == 'claimed' ? 'استلمته' : (state == 'done' ? 'جاهز تستلمه' : 'شغال'),
  'claimable': self && state == 'done',
  'reward': reward,
  'reward_label': '$reward $unit',
  'sticker': sticker,
  'world': null,
  'ends_at': time('2099-10-09T21:00:00Z', '2099-10-10T00:00:00+03:00'),
};

Map<String, Object?> _board(
  String learner,
  String stage,
  List<Map<String, Object?>> challenges, {
  bool self = true,
  List<Object> cheers = const [],
  int points = 800,
}) => {
  'data': {
    'learner_id': learner,
    'stage': stage,
    'can_claim': self,
    'week': {
      'starts_at': time('2099-10-02T21:00:00Z', '2099-10-03T00:00:00+03:00'),
      'ends_at': time('2099-10-09T21:00:00Z', '2099-10-10T00:00:00+03:00'),
    },
    'points': points,
    'rank': {'key': 'star', 'label': 'نجم', 'points': points, 'next': 1500, 'next_key': 'hero', 'next_label': 'بطل', 'progress': 10},
    'challenges': challenges,
    'stickers': stage != 'kids'
        ? null
        : {
            'earned': 2,
            'items': [
              {'key': 'lion', 'earned': true, 'name': 'أسد'},
              {'key': 'rocket', 'earned': true, 'name': 'صاروخ'},
              {'key': 'sun', 'earned': false, 'name': 'شمس'},
            ],
          },
    'cheers': cheers,
    'cheers_left': self ? 0 : 5,
  },
};

void _studentRoutes(FakeApi api, String stage) {
  familyRoutes(api);
  api
    ..on('GET', 'family/learners', {
      'data': [
        {'id': 'u-$stage', 'name': 'يوسف خالد', 'is_self': true, 'stage': stage},
      ],
      'viewer_stage': stage,
    })
    ..on('GET', 'learning/journey', _journey())
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
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  testWidgets('a child sees a cheer once, opens last week\'s chest, and gets the sticker inside', (tester) async {
    final app = await TestApp.create(accounts: [_student('kids')]);
    _studentRoutes(app.api, 'kids');
    var opened = false;
    app.api
      ..onWith(
        'GET',
        'learning/challenges',
        (_) => (
          200,
          _board(
            'u-kids',
            'kids',
            [
              _challenge(
                'chest',
                '2099-09-26',
                group: 'last_week',
                state: opened ? 'claimed' : 'done',
                target: 3,
                progress: 3,
                reward: 50,
                sticker: {'key': 'sun', 'name': 'شمس'},
              ),
              _challenge('attend', '2099-10-03'),
              _challenge('homework', '2099-10-03', state: 'done', target: 1),
              _challenge('chest', '2099-10-03', target: 2, reward: 50, sticker: {'key': 'whale', 'name': 'حوت'}),
            ],
            cheers: opened
                ? const []
                : [
                    {'id': 'c1', 'kind': 'heart', 'from': 'رامي', 'at': time('2099-10-04T10:00:00Z', '2099-10-04T13:00:00+03:00')},
                  ],
          ),
        ),
      )
      ..onWith('POST', 'learning/challenges/claim', (_) {
        opened = true;
        return (
          201,
          {
            'data': {
              'reward': 50,
              'reward_label': '50 جوهرة',
              'sticker': {'key': 'sun', 'name': 'شمس'},
              'points_before': 800,
              'points': 850,
              'ranked_up': false,
              'rank': {'key': 'star', 'label': 'نجم', 'points': 850, 'next': 1500, 'next_label': 'بطل', 'progress': 7},
            },
          },
        );
      });
    await _open(tester, app);

    // The guardian's cheer pops up on the home, once, and is marked seen.
    expect(find.text('تشجيع من رامي!'), findsOneWidget);
    await tester.tap(find.text('شكرًا!'));
    await frames(tester, 14);
    expect(app.api.sent('POST', 'learning/cheers/seen'), hasLength(1));
    expect(find.text('تشجيع من رامي!'), findsNothing);

    // Two rewards wait: the missions tab says so.
    expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('2')), findsOneWidget);

    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('مهماتي')));
    await frames(tester, 12);
    expect(find.text('مهمات الأسبوع'), findsOneWidget);
    expect(find.text('الصندوق جاهز! دوس عليه'), findsOneWidget);
    // The sticker inside is a surprise until it opens.
    expect(find.text('شمس'), findsNothing);

    await tester.tap(find.text('الصندوق جاهز! دوس عليه'));
    await frames(tester, 16);
    final claim = app.api.sent('POST', 'learning/challenges/claim').single;
    expect(claim.data, {'challenge': 'chest', 'period': '2099-09-26'});
    expect(claim.headers['Idempotency-Key'], isNotNull);
    expect(find.text('برافو عليك!'), findsOneWidget);
    expect(find.text('\u2066+50\u2069 جوهرة'), findsOneWidget);
    expect(find.text('ستيكر جديد في ألبومك!'), findsOneWidget);
    expect(find.text('شمس'), findsOneWidget);

    await tester.tap(find.text('تمام!'));
    await frames(tester, 14);
    expect(app.api.sent('GET', 'learning/challenges').length, greaterThanOrEqualTo(2));
    // Opened: the week's own chest is the big one now, still locked.
    expect(find.text('برافو عليك!'), findsNothing);
    expect(find.text('جواه ستيكر مفاجأة'), findsOneWidget);

    // The album: the stickers won in colour, the rest a question mark.
    await tester.tap(find.byTooltip('ألبوم الستيكرز'));
    await frames(tester, 12);
    expect(find.text('أسد'), findsOneWidget);
    expect(find.text('2 من 3'), findsOneWidget);

    await _close(tester);
  });

  testWidgets('a middle-school student collects a quest and ranks up', (tester) async {
    final app = await TestApp.create(accounts: [_student('middle')]);
    _studentRoutes(app.api, 'middle');
    app.api
      ..on(
        'GET',
        'learning/challenges',
        _board('u-middle', 'middle', [
          _challenge('master', '2099-10-03', state: 'done', target: 1, reward: 120, unit: 'XP'),
          _challenge('attend', '2099-10-03', reward: 100, unit: 'XP'),
        ], points: 1400),
      )
      ..on('POST', 'learning/challenges/claim', {
        'data': {
          'reward': 120,
          'reward_label': '120 XP',
          'sticker': null,
          'points_before': 1400,
          'points': 1520,
          'ranked_up': true,
          'rank': {'key': 'hero', 'label': 'بطل', 'points': 1520, 'next': 3000, 'next_label': 'أسطورة', 'progress': 1},
        },
      }, status: 201);
    await _open(tester, app);

    await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('تحدياتي')));
    await frames(tester, 12);
    expect(find.text('تحديات الأسبوع'), findsOneWidget);
    expect(find.text('+120 XP'), findsOneWidget);

    await tester.tap(find.text('استلم 120 XP'));
    await frames(tester, 16);
    expect(app.api.sent('POST', 'learning/challenges/claim').single.data, {'challenge': 'master', 'period': '2099-10-03'});
    expect(find.text('أحسنت!'), findsOneWidget);
    expect(find.text('رتبة جديدة: بطل'), findsOneWidget);

    await tester.tap(find.text('تمام!'));
    await frames(tester, 10);
    await _close(tester);
  });

  testWidgets('a guardian follows a child\'s challenges and cheers them on', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api
      ..on(
        'GET',
        'learning/challenges',
        _board('c1', 'classic', [
          _challenge('attend', '2099-10-03', reward: 80, unit: 'نقطة', self: false),
          _challenge('homework', '2099-10-03', state: 'done', target: 1, reward: 80, unit: 'نقطة', self: false),
        ], self: false),
      )
      ..on('POST', 'learning/cheers', {
        'data': {'id': 'x1', 'kind': 'heart'},
      }, status: 201);
    await _open(tester, app);

    expect(find.text('تحديات سلمى'), findsOneWidget);
    expect(app.api.sent('GET', 'learning/challenges').first.queryParameters['learner_id'], 'c1');
    // A guardian watches: nothing to collect, a "done" mark instead.
    expect(find.textContaining('استلم'), findsNothing);

    await tester.scrollUntilVisible(find.text('قلب'), 200, scrollable: find.byType(Scrollable).last);
    await tester.tap(find.text('قلب'));
    await frames(tester, 12);
    expect(app.api.sent('POST', 'learning/cheers').single.data, {'learner_id': 'c1', 'kind': 'heart'});
    expect(find.text('التشجيع وصل لـ سلمى!'), findsOneWidget);

    // The whole board, in the child's own words.
    await tester.tap(find.text('شوف الكل'));
    await frames(tester, 12);
    expect(find.text('أهداف الأسبوع'), findsWidgets);
    expect(find.text('تم!'), findsWidgets);

    await _close(tester);
  });
}
