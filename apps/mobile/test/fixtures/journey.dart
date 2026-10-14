// The learner's journey and the student's routes, shared by the journey
// tests and the kids' look goldens.
import 'package:academy_app/core/auth/account.dart';

import '../helpers.dart';

Account fxStudent(String stage) => Account(
  academySlug: 'demo',
  academyNameAr: 'أكاديمية النور',
  academyNameEn: 'Noor Academy',
  userId: 'u-$stage',
  userName: 'يوسف خالد',
  roles: const ['student'],
  token: 'token-$stage',
  stage: stage,
);

const fxBird = {'kind': 'bird', 'body': '#74C0FC', 'wing': '#4DABF7', 'belly': '#E7F5FF'};
const fxTurtle = {'kind': 'turtle', 'body': '#8CE99A', 'wing': '#2F9E44', 'belly': '#40C057'};

Map<String, Object?> fxCreature(
  String key,
  String name, {
  bool found = true,
  String island = 'birds',
  Map<String, Object?> look = fxBird,
}) => {
  'key': key,
  'name': name,
  'emoji': '🐦',
  'rarity': 'common',
  'look': look,
  'story': 'قصة $name',
  'found': found,
  'found_at': found ? time('2099-10-01T10:00:00Z', '2099-10-01T13:00:00+03:00') : null,
  'source': found ? 'stars' : null,
  'nickname': null,
  'buddy': key == 'birds.risha',
  'island': island,
  'island_name': island == 'birds' ? 'جزيرة الطيور' : 'جزيرة السلاحف',
};

Map<String, Object?> fxGame(String id, String key, String title, {bool chosen = false, String island = 'turtles'}) => {
  'id': id,
  'key': key,
  'island': island,
  'title': title,
  'summary': 'ملخص $title',
  'how_to_play': 'إزاي تلعب $title',
  'kinds': ['order'],
  'chosen': chosen,
  'items': 4,
};

Map<String, Object?> fxStation(
  String id,
  String title,
  String state, {
  bool playable = true,
  bool due = false,
  List<String> missions = const [],
  List<Object> games = const [],
  Map<String, Object?>? race,
}) => {
  'id': id,
  'title': title,
  'unit': null,
  'state': state,
  'due_review': due,
  'playable': playable,
  'practice': state == 'here' ? {'rounds': 3, 'answered': 12, 'correct': 10, 'percent': 83} : null,
  'missions': missions,
  'games': games,
  'race': race,
};

Map<String, Object?> fxArt(String key, String name) => {
  'key': key,
  'name': name,
  'emoji': key == 'birds' ? '🐦' : '🐢',
  'colors': {'sky': '#9be7ff', 'sea': '#1a8fb0', 'land': '#f7d07a'},
  'music': 'major',
};

/// A learner's journey: three levels (done, here, ahead), a homework and a lesson on the lesson they are on, a review (or two).
Map<String, Object?> fxJourney({
  String stage = 'kids',
  String viewer = 'self',
  String style = 'playful',
  int stars = 151,
  bool eggReady = false,
  bool chestReady = false,
  bool may = true,
  bool lessonOpen = false,
  bool practiceHomework = false,
  bool twoReviews = false,
  List<Object> cheers = const [],
  Map<String, Object?>? limits,
  Map<String, Object?>? glance,
}) => {
  'data': {
    'learner_id': viewer == 'guardian' ? 'c1' : 'u-$stage',
    'stage': stage,
    'style': style,
    'viewer': viewer,
    'stars': {
      'total': stars,
      'rank': {'key': 'hero', 'label': 'بطل', 'next': 300, 'next_label': 'أسطورة', 'progress': 0, 'stars': stars},
      'from': {'lessons': 28, 'practice': 74, 'homework': 4, 'mastered': 25, 'chest': 20},
      'per_egg': 12,
    },
    'streak': 7,
    'last_lesson': {
      'id': 'l0',
      'starts_at': time('2099-10-08T14:00:00Z', '2099-10-08T17:00:00+03:00', label: 'الخميس 8 أكتوبر · 17:00'),
      'stars': 4,
      'late_min': 1,
      'on_time': true,
      'stayed': true,
      'labels': ['في الميعاد', 'فضلت لآخر الحصة'],
    },
    'courses': [
      {
        'enrollment_id': 'e1',
        'subject': 'قرآن · حفظ',
        'teacher': 'أ. نور الهدى',
        'program': 'حفظ جزء عمّ',
        'has_curriculum': true,
        'percent': 55,
        'finished': false,
        'lessons': {'mastered': 3, 'total': 6},
        'eta_weeks': 2.4,
        'finish_on': time('2099-10-28T12:00:00Z', '2099-10-28T15:00:00+03:00', label: 'الأربعاء 28 أكتوبر'),
        'current': {
          'id': 's4',
          'title': 'قريش والفيل',
          'path': ['سور أطول'],
        },
        'islands': [
          {
            'id': 'i1',
            'index': 1,
            'title': 'قصار السور',
            'percent': 100,
            'state': 'done',
            'lessons': 2,
            'done': 2,
            'art': fxArt('birds', 'جزيرة الطيور'),
            'creatures': [fxCreature('birds.risha', 'ريشة')],
            'next_creature': null,
            'stations': [
              fxStation(
                's1',
                'الفاتحة',
                'review',
                due: true,
                missions: ['review:s1'],
                games: [fxGame('g-twins', 'bird-twins', 'توأم العش', island: 'birds')],
              ),
              fxStation('s2', 'الناس والفلق', 'done'),
            ],
          },
          {
            'id': 'i2',
            'index': 2,
            'title': 'سور أطول',
            'percent': 33,
            'state': 'here',
            'lessons': 3,
            'done': 1,
            'art': fxArt('turtles', 'جزيرة السلاحف'),
            'creatures': [
              fxCreature('turtles.shell', 'صدفة', island: 'turtles', look: fxTurtle),
              fxCreature('turtles.crab', 'كابوريا', found: false, island: 'turtles', look: fxTurtle),
            ],
            'next_creature': {'key': 'turtles.crab', 'look': fxTurtle, 'rarity': 'common'},
            'stations': [
              fxStation('s3', 'الكوثر والماعون', 'done'),
              fxStation(
                's4',
                'قريش والفيل',
                'here',
                missions: ['homework:h1', 'lesson:l1'],
                games: [fxGame('g-bridge', 'turtle-bridge', 'جسر السلاحف'), fxGame('g-balloons', 'bird-balloons', 'بالونات الحكمة')],
                race: {'rounds': 8, 'best_seconds': 42, 'last_seconds': 48, 'best_stars': 3},
              ),
              fxStation('s5', 'الهمزة والعصر', 'todo', playable: false),
            ],
          },
          {
            'id': 'i3',
            'index': 3,
            'title': 'جزء تبارك',
            'percent': 0,
            'state': 'ahead',
            'lessons': 1,
            'done': 0,
            'art': fxArt('monkeys', 'جزيرة القرود'),
            'creatures': <Object>[],
            'next_creature': null,
            'stations': [fxStation('s6', 'سورة الملك', 'todo', playable: false)],
          },
        ],
      },
    ],
    'missions': [
      {
        'key': 'homework:h1',
        'kind': 'homework',
        'kind_label': 'واجب',
        'state': 'open',
        'enrollment_id': 'e1',
        'island_id': 'i2',
        'station_id': 's4',
        'title': 'سجّل سورة قريش',
        'homework_id': 'h1',
        'due_at': time('2099-10-13T16:00:00Z', '2099-10-13T19:00:00+03:00', label: 'الثلاثاء 13 أكتوبر · 19:00'),
        'accepts': practiceHomework ? 'practice' : 'audio',
        'practice': practiceHomework,
        'game': practiceHomework ? 'turtle-bridge' : null,
        'result': null,
        'stars': 2,
      },
      {
        'key': 'lesson:l1',
        'kind': 'lesson',
        'kind_label': 'حصة',
        'state': 'open',
        'enrollment_id': 'e1',
        'island_id': 'i2',
        'station_id': 's4',
        'title': 'قرآن · حفظ',
        'lesson_id': 'l1',
        'starts_at': time('2099-10-15T14:00:00Z', '2099-10-15T17:00:00+03:00', label: 'الخميس 15 أكتوبر · 17:00'),
        'ends_at': time('2099-10-15T14:30:00Z', '2099-10-15T17:30:00+03:00', label: 'الخميس 15 أكتوبر · 17:30'),
        'join': {
          'open': lessonOpen,
          'opens_at': time('2099-10-15T13:50:00Z', '2099-10-15T16:50:00+03:00', label: 'الخميس 15 أكتوبر · 16:50'),
        },
        'stars': 4,
      },
      {
        'key': 'review:s1',
        'kind': 'review',
        'kind_label': 'مراجعة',
        'state': 'open',
        'enrollment_id': 'e1',
        'island_id': 'i1',
        'station_id': 's1',
        'title': 'الفاتحة',
      },
      if (twoReviews)
        {
          'key': 'review:s2',
          'kind': 'review',
          'kind_label': 'مراجعة',
          'state': 'open',
          'enrollment_id': 'e1',
          'island_id': 'i1',
          'station_id': 's2',
          'title': 'الناس والفلق',
        },
    ],
    'chest': {
      'period': '2099-10-03',
      'target': 3,
      'progress': chestReady ? 3 : 1,
      'state': chestReady ? 'done' : 'active',
      'stars': 10,
      'sticker': {'key': 'sun', 'name': 'شمس'},
      'ends_at': time('2099-10-09T21:00:00Z', '2099-10-10T00:00:00+03:00', label: 'السبت 10 أكتوبر · 00:00'),
      'can_open': chestReady && viewer == 'self',
    },
    'games': {
      'allowance': {
        'academy': true,
        'child': true,
        'limit': 30,
        'used': 12,
        'left': 18,
        'night_lock': false,
        'may': may,
        'reason': may ? null : 'limit',
        'reason_label': may ? null : 'خلصت دقايق اللعب النهارده. نكمل بكرة! 🌙',
      },
      'egg': {'have': eggReady ? 12 : 7, 'need': 12, 'ready': eggReady},
      'buddy': fxCreature('birds.risha', 'ريشة'),
      'creatures_found': 2,
      'creatures_total': 20,
      'recent': <Object>[],
      'limits': limits,
      'glance': glance,
    },
    'cheers': cheers,
    'cheers_left': viewer == 'guardian' ? 3 : 0,
  },
};

void fxStudentRoutes(FakeApi api, String stage, Map<String, Object?> journey) {
  familyRoutes(api);
  api
    ..on('GET', 'family/learners', {
      'data': [
        {'id': 'u-$stage', 'name': 'يوسف خالد', 'is_self': true, 'stage': stage},
      ],
      'viewer_stage': stage,
    })
    ..on('GET', 'learning/journey/map', journey)
    ..on('POST', 'learning/cheers/seen', null, status: 204);
}
