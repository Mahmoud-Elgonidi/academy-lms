import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/classroom/domain/room_stage.dart';
import 'package:academy_app/features/journey/domain/journey.dart';
import 'package:academy_app/features/schedule/domain/lesson.dart';
import 'package:academy_app/features/teaching/domain/reports.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../fixtures/journey.dart';
import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// A group lesson as a family sees it: two classmates by first name (null
/// and a count in the kids' look), three of five seats taken.
Map<String, Object?> _groupLesson(String id, {bool kids = false, bool canCancel = true, String? seat, int? seatNumber}) => {
  ...lesson(id, canCancel: canCancel),
  'subject': 'القرآن الكريم',
  'with': 'أ. خالد يوسف',
  'format': 'group',
  'format_label': 'جماعية',
  'group': {
    'capacity': 5,
    'seats_taken': 3,
    'seat_number': seatNumber,
    'classmates': kids ? null : ['سارة', 'علي'],
    'classmates_count': 2,
    'classmates_label': 'مع زميلين',
    'seat': seat,
    'seat_label': seat == null ? null : 'معتذر',
    'students': null,
  },
};

Map<String, Object?> _private(String id) => {...lesson(id), 'format': 'private', 'format_label': 'فردية', 'group': null};

Map<String, Object?> _list(List<Map<String, Object?>> lessons) => {
  'learner': {'id': 'c1', 'name': 'سلمى رامي'},
  'data': lessons,
  'notices': <String>[],
};

Future<void> _open(WidgetTester tester, TestApp app, {String? location}) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1200));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  if (location != null) {
    GoRouter.of(tester.element(find.byType(Scaffold).first)).go(location);
    await frames(tester, 15);
  }
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  test('a lesson reads its format and group: a family its classmates and seat, a teacher everyone seated', () {
    final family = Lesson.fromJson(_groupLesson('g1', seat: 'excused'));
    expect(family.isGroup, isTrue);
    expect(family.group!.capacity, 5);
    expect(family.group!.seatsTaken, 3);
    expect(family.group!.classmates, ['سارة', 'علي']);
    expect(family.group!.forTeacher, isFalse);
    expect(family.group!.excused, isTrue);

    final teacher = Lesson.fromJson({
      ...lesson('g2'),
      'format': 'group',
      'group': {
        'capacity': '4', // the document types it loosely: a number either way
        'seats_taken': 2,
        'classmates': null,
        'classmates_count': null,
        'classmates_label': null,
        'seat': null,
        'seat_label': null,
        'students': [
          {'id': 's1', 'name': 'سارة أحمد'},
          {'id': 's2', 'name': 'علي حسن'},
        ],
      },
    });
    expect(teacher.group!.capacity, 4);
    expect(teacher.group!.forTeacher, isTrue);
    expect(teacher.group!.students!.map((s) => s.name), ['سارة أحمد', 'علي حسن']);

    // An older answer without the format is a one-to-one lesson.
    final old = Lesson.fromJson(lesson('l1'));
    expect(old.isGroup, isFalse);
    expect(old.group, isNull);
  });

  test('a group report reads every student\'s row; a one-to-one report has none', () {
    final form = ReportForm.fromJson({
      ...report('r1'),
      'group': {
        'students': [
          {'report_id': 'r1', 'student_id': 's1', 'name': 'سارة أحمد', 'kind': 'lesson', 'status': 'due', 'engagement': 4},
          {'report_id': 'r2', 'student_id': 's2', 'name': 'علي حسن', 'kind': 'absence', 'status': 'due'},
        ],
      },
    });
    expect(form.isGroup, isTrue);
    expect(form.group!.first.engagement, 4);
    expect(form.group!.last.absent, isTrue);
    expect(ReportForm.fromJson(report('r1')).isGroup, isFalse);
  });

  test('a group lesson\'s room calls everyone by their first name, a title kept with it', () {
    expect(firstNameOf('سارة أحمد'), 'سارة');
    expect(firstNameOf('أ. خالد يوسف'), 'أ. خالد');
    expect(firstNameOf('Dr. Sara Ali'), 'Dr. Sara');
    expect(firstNameOf('  منى  '), 'منى');

    const me = RoomPerson(identity: 'me', name: 'سلمى رامي', isLocal: true, camera: true);
    const teacher = RoomPerson(identity: 't', name: 'أ. خالد يوسف', camera: true);
    const sara = RoomPerson(identity: 's', name: 'سارة أحمد', camera: true);
    // One to one: the names as the room gave them.
    expect(namedForRoom([me, teacher]).map((p) => p.name), ['سلمى رامي', 'أ. خالد يوسف']);
    // A group: first names, and everyone on the grid.
    final group = namedForRoom([me, teacher, sara]);
    expect(group.map((p) => p.name), ['سلمى', 'أ. خالد', 'سارة']);
    expect(pickStage(group).kind, StageKind.grid);
    expect(pickStage(group).grid, hasLength(3));
  });

  testWidgets('a guardian sees a group lesson by its seats and classmates\' first names, and «فردية» beside it', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('GET', 'family/lessons', _list([_groupLesson('g1'), _private('l2')]));
    await _open(tester, app);
    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);

    expect(find.text('القرآن الكريم مع أ. خالد يوسف'), findsOneWidget);
    expect(find.text('مع سارة وعلي'), findsOneWidget);
    expect(find.byKey(const Key('group-pill')), findsOneWidget);
    expect(find.text('3/5'), findsOneWidget);
    // Both kinds in one list: the one-to-one lesson says so.
    expect(find.byKey(const Key('private-pill')), findsOneWidget);

    // The sheet names the classmates, and its last action is "I won't attend", not cancelling.
    await tester.tap(find.byKey(const Key('lesson-g1')));
    await frames(tester, 8);
    expect(find.byKey(const Key('sheet-group')), findsOneWidget);
    expect(find.text('الزملاء'), findsOneWidget);
    expect(find.text('لن أحضر هذه الحصة'), findsOneWidget);
    expect(find.text('إلغاء الحصة'), findsNothing);
    await _close(tester);
  });

  testWidgets('only one-to-one lessons: nothing says «فردية»', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('GET', 'family/lessons', _list([_private('l1'), _private('l2')]));
    await _open(tester, app);
    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);
    expect(find.byKey(const Key('private-pill')), findsNothing);
    expect(find.byKey(const Key('group-pill')), findsNothing);
    await _close(tester);
  });

  testWidgets('a child in the kids\' look sees how many classmates, never their names, and still cannot cancel', (tester) async {
    final app = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(app.api, 'kids', fxJourney());
    app.api.on('GET', 'family/lessons', {
      'learner': {'id': 'u-kids', 'name': 'يوسف خالد'},
      'data': [_groupLesson('g1', kids: true, canCancel: false)],
      'notices': <String>[],
    });
    await _open(tester, app, location: '/kids/lessons');

    expect(find.byKey(const Key('group-pill')), findsOneWidget);
    expect(find.text('مع زميلين'), findsOneWidget);
    expect(find.textContaining('سارة'), findsNothing);
    expect(find.text('لن أحضر هذه الحصة'), findsNothing);
    expect(find.text('إلغاء الحصة'), findsNothing);
    await _close(tester);
  });

  testWidgets('"I won\'t attend" on a group lesson says the lesson goes on, then excuses this learner\'s seat once', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    var excused = false;
    app.api
      ..onWith(
        'GET',
        'family/lessons',
        (_) => (200, _list([excused ? _groupLesson('g1', canCancel: false, seat: 'excused') : _groupLesson('g1')])),
      )
      ..on('GET', 'lessons/g1/cancellation', {
        'outcome': 'تستمر الحصة للمجموعة، ويُعفى مقعدك وتعود الحصة إلى رصيدك (قبل موعدها بـ 12 ساعة على الأقل).',
        'excuse_only': true,
        'reasons': [
          {'value': 'illness', 'label': 'مرض'},
          {'value': 'travel', 'label': 'سفر'},
        ],
      })
      ..onWith('POST', 'lessons/g1/cancel', (_) {
        excused = true;
        return (200, {'data': _groupLesson('g1', canCancel: false, seat: 'excused')});
      });
    await _open(tester, app);
    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);

    await tester.tap(find.byKey(const Key('lesson-g1')));
    await frames(tester, 8);
    await tester.tap(find.text('لن أحضر هذه الحصة'));
    await frames(tester, 10);

    // The sheet asks for this learner's seat, and says what happens: the group goes on, the policy decides the credit.
    expect(app.api.sent('GET', 'lessons/g1/cancellation').single.queryParameters['learner_id'], 'c1');
    expect(find.byKey(const Key('cancel-group')), findsOneWidget);
    expect(find.text('الحصة جماعية فلا تُلغى: تستمر لبقية المجموعة، ويُعفى مقعدك وحدك.'), findsOneWidget);
    expect(find.textContaining('وتعود الحصة إلى رصيدك'), findsOneWidget);
    expect(find.text('نعم، ألغِها'), findsNothing);

    await tester.tap(find.text('مرض'));
    await tester.tap(find.text('نعم، لن أحضر'));
    await frames(tester, 10);

    final sent = app.api.sent('POST', 'lessons/g1/cancel').single;
    expect(sent.data, {'reason': 'illness', 'learner_id': 'c1'});
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(find.text('سجّلنا الاعتذار، وتستمر الحصة للمجموعة.'), findsOneWidget);
    await frames(tester, 10);
    // Reloaded: the seat reads excused, and there is nothing more to cancel.
    expect(find.text('معتذر'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a teacher\'s group lesson shows its students and seats; its report is the shared part once and a row each', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final row = {'status': 'due', 'engagement': null, 'overall': null, 'notes_for_student': null, 'absence_note': null};
    app.api
      ..on('GET', 'teacher/reports/r1', {
        'data': {
          ...report('r1'),
          'lesson_options': <Object>[],
          'homework': null,
          'roadmap': null,
          'template': null,
          'answers': <Object>[],
          'group': {
            'students': [
              {...row, 'report_id': 'r1', 'student_id': 's1', 'name': 'سارة أحمد', 'kind': 'lesson'},
              {...row, 'report_id': 'r2', 'student_id': 's2', 'name': 'علي حسن', 'kind': 'absence'},
              {...row, 'report_id': 'r3', 'student_id': 's3', 'name': 'منى سعيد', 'kind': 'lesson'},
            ],
          },
        },
      })
      ..on('PUT', 'teacher/reports/r1', {'data': report('r1', status: 'submitted')});
    await _open(tester, app);

    await tester.tap(find.byKey(const Key('today-reports')));
    await frames(tester, 10);
    await tester.tap(find.text('يوسف خالد'));
    await frames(tester, 10);

    expect(find.text('تقرير الحصة الجماعية'), findsOneWidget);
    expect(find.byKey(const Key('group-shared')), findsOneWidget);
    // No one-to-one scores: each student's are on their row.
    expect(find.text('كيف كانت الحصة'), findsNothing);
    for (final id in ['s1', 's2', 's3']) {
      await tester.scrollUntilVisible(find.byKey(Key('group-row-$id')), 300, scrollable: find.byType(Scrollable).first);
      expect(find.byKey(Key('group-row-$id')), findsOneWidget);
    }
    expect(find.byKey(const Key('row-absence-s2')), findsOneWidget);
    expect(find.byKey(const Key('row-engagement-s2')), findsNothing);

    // Sara is rated, Mona not yet: nothing is sent, and Mona's row says why.
    Future<void> star(String key, int n) async {
      await tester.scrollUntilVisible(find.byKey(Key(key)), 200, scrollable: find.byType(Scrollable).first);
      await tester.tap(find.descendant(of: find.byKey(Key(key)), matching: find.byType(InkResponse)).at(n - 1));
      await frames(tester, 2);
    }

    await star('row-engagement-s1', 5);
    await star('row-overall-s1', 4);
    await tester.tap(find.text('أرسل التقرير'));
    await frames(tester, 3);
    expect(app.api.sent('PUT', 'teacher/reports/r1'), isEmpty);
    expect(find.byKey(const Key('row-error-s3')), findsOneWidget);
    expect(find.byKey(const Key('row-error-s1')), findsNothing);
    expect(find.text('قيّم منى سعيد: التركيز والمستوى العام.'), findsOneWidget);

    await star('row-engagement-s3', 3);
    await star('row-overall-s3', 3);
    expect(find.byKey(const Key('row-error-s3')), findsNothing);
    await tester.scrollUntilVisible(find.byKey(const Key('row-absence-s2')), 200, scrollable: find.byType(Scrollable).first);
    await tester.enterText(find.byKey(const Key('row-absence-s2')), 'كان مريضًا');
    await tester.tap(find.text('أرسل التقرير'));
    await frames(tester, 12);

    final sent = app.api.sent('PUT', 'teacher/reports/r1').single.data as Map<String, dynamic>;
    expect(sent['items'], isEmpty);
    expect(sent.containsKey('engagement'), isFalse);
    expect(sent['students'], [
      {'student_id': 's1', 'engagement': 5, 'overall': 4, 'notes_for_student': ''},
      {'student_id': 's2', 'absence_note': 'كان مريضًا'},
      {'student_id': 's3', 'engagement': 3, 'overall': 3, 'notes_for_student': ''},
    ]);
    await _close(tester);
  });

  testWidgets('a refusal on one student\'s row comes back on that row', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final row = {'status': 'due', 'engagement': 4, 'overall': 4, 'notes_for_student': null, 'absence_note': null, 'kind': 'lesson'};
    app.api
      ..on('GET', 'teacher/reports/r1', {
        'data': {
          ...report('r1'),
          'lesson_options': <Object>[],
          'homework': null,
          'template': null,
          'answers': <Object>[],
          'group': {
            'students': [
              {...row, 'report_id': 'r1', 'student_id': 's1', 'name': 'سارة أحمد'},
              {...row, 'report_id': 'r3', 'student_id': 's3', 'name': 'منى سعيد'},
            ],
          },
        },
      })
      ..on('PUT', 'teacher/reports/r1', {
        'type': 'https://httpstatuses.io/422',
        'title': 'تحقق من البيانات',
        'status': 422,
        'detail': 'الملاحظة طويلة.',
        'errors': {
          'students.1.notes_for_student': ['الملاحظة طويلة.'],
        },
      }, status: 422);
    await _open(tester, app);
    await tester.tap(find.byKey(const Key('today-reports')));
    await frames(tester, 10);
    await tester.tap(find.text('يوسف خالد'));
    await frames(tester, 10);

    await tester.tap(find.text('أرسل التقرير'));
    await frames(tester, 10);
    expect(app.api.sent('PUT', 'teacher/reports/r1'), hasLength(1));
    await tester.scrollUntilVisible(find.byKey(const Key('row-error-s3')), 200, scrollable: find.byType(Scrollable).first);
    expect(find.byKey(const Key('row-error-s3')), findsOneWidget);
    expect(find.byKey(const Key('row-error-s1')), findsNothing);
    await _close(tester);
  });

  testWidgets('a teacher\'s day lists a group lesson by its subject, students and seats', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final group = {
      ...lesson('g1'),
      'subject': 'القرآن الكريم',
      'with': 'جماعية · 2 طلاب',
      'format': 'group',
      'group': {
        'capacity': 4,
        'seats_taken': 2,
        'classmates': null,
        'classmates_count': null,
        'classmates_label': null,
        'seat': null,
        'seat_label': null,
        'students': [
          {'id': 's1', 'name': 'سارة أحمد'},
          {'id': 's2', 'name': 'علي حسن'},
        ],
      },
    };
    app.api.on('GET', 'teacher/lessons', {
      'from': '2099-10-11',
      'to': '2099-10-17',
      'count': 2,
      'minutes': 90,
      'data': [group, _private('l2')],
    });
    await _open(tester, app, location: '/teach/week');

    expect(find.byKey(const Key('group-line-g1')), findsOneWidget);
    expect(find.text('سارة أحمد وعلي حسن'), findsOneWidget);
    expect(find.text('2/4'), findsOneWidget);
    expect(find.text('القرآن الكريم'), findsWidgets);
    expect(find.textContaining('مع جماعية'), findsNothing);
    await _close(tester);
  });

  testWidgets('a family\'s lesson says its own seat before the classmates; the kids\' look leaves the seat out', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('GET', 'family/lessons', _list([_groupLesson('g1', seatNumber: 4)]));
    await _open(tester, app);
    await tester.tap(find.text('الحصص').last);
    await frames(tester, 10);
    expect(find.text('مقعدك 4 من 5 · مع سارة وعلي'), findsOneWidget);
    expect(Lesson.fromJson(_groupLesson('g1', seatNumber: 4)).group!.seatNumber, 4);
    await _close(tester);

    final kid = await TestApp.create(accounts: [fxStudent('kids')]);
    fxStudentRoutes(kid.api, 'kids', fxJourney());
    kid.api.on('GET', 'family/lessons', {
      'learner': {'id': 'u-kids', 'name': 'يوسف خالد'},
      'data': [_groupLesson('g1', kids: true, canCancel: false, seatNumber: 4)],
      'notices': <String>[],
    });
    await _open(tester, kid, location: '/kids/lessons');
    expect(find.text('مع زميلين'), findsOneWidget);
    expect(find.textContaining('مقعدك'), findsNothing);
    await _close(tester);
  });

  testWidgets('a group course on the journey reads «مجموعة», its seats and the learner\'s own seat; a one-to-one course none', (
    tester,
  ) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    final journey = fxJourney(stage: 'kids', viewer: 'guardian');
    final course = ((journey['data']! as Map)['courses']! as List).first as Map;
    course['format'] = 'group';
    course['group'] = {'id': 'grp1', 'capacity': 5, 'seats_taken': 4, 'seat': 4, 'status': 'running'};
    app.api.on('GET', 'learning/journey/map', journey);
    await _open(tester, app, location: '/family/journey');

    expect(find.byKey(const Key('course-group')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('course-group')), matching: find.text('مجموعة')), findsOneWidget);
    expect(find.text('4/5'), findsOneWidget);
    expect(find.text('مقعدك 4 من 5'), findsOneWidget);
    expect(find.textContaining('قيد التكوين'), findsNothing);
    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(seconds: 10));
    await tester.binding.setSurfaceSize(null);

    // A group still forming says so; a one-to-one course has no line at all.
    final forming = JourneyCourse.fromJson({
      ...course.cast<String, dynamic>(),
      'group': {'id': 'grp2', 'capacity': 6, 'seats_taken': 2, 'seat': 2, 'status': 'forming'},
    });
    expect(forming.group!.forming, isTrue);
    expect(JourneyCourse.fromJson({...course.cast<String, dynamic>(), 'format': 'private', 'group': null}).group, isNull);
  });

  testWidgets('the money screen names a group plan «باقة جماعية» with its seats and the learner\'s seat', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('GET', 'family/balance', {
      'data': [
        {
          'enrollment_id': 'e1',
          'subject': 'القرآن الكريم',
          'format': 'group',
          'group': {'id': 'grp1', 'capacity': 5, 'seats_taken': 3, 'seat': 3},
          'sessions': 6,
          'minutes': 270,
        },
        {'enrollment_id': 'e2', 'subject': 'لغة عربية', 'format': 'private', 'group': null, 'sessions': 4, 'minutes': 180},
      ],
    });
    await _open(tester, app, location: '/family/money');

    expect(find.byKey(const Key('course-group')), findsOneWidget);
    expect(find.text('باقة جماعية'), findsOneWidget);
    expect(find.text('3/5'), findsOneWidget);
    expect(find.text('مقعدك 3 من 5'), findsOneWidget);
    expect(find.text('لغة عربية'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a teacher\'s reports list a group lesson once, opening the group report', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    Map<String, Object?> seat(String id, String name, {String status = 'due'}) => {
      ...report(id, status: status),
      'session_id': 'g1',
      'format': 'group',
      'group_id': 'grp1',
      'student': {'id': 's-$id', 'name': name},
    };
    app.api
      ..on('GET', 'teacher/reports', {
        'data': [
          seat('r1', 'سارة أحمد'),
          {...report('r4'), 'format': 'private', 'group_id': null},
          seat('r2', 'علي حسن', status: 'overdue'),
          seat('r3', 'منى سعيد'),
        ],
      })
      ..on('GET', 'teacher/reports/r2', {
        'data': {
          ...seat('r2', 'علي حسن', status: 'overdue'),
          'lesson_options': <Object>[],
          'homework': null,
          'template': null,
          'answers': <Object>[],
          'group': {'students': <Object>[]},
        },
      });
    await _open(tester, app);
    // The count of what waits takes the group lesson as one report to write.
    expect(find.descendant(of: find.byKey(const Key('today-reports')), matching: find.text('2')), findsOneWidget);

    await tester.tap(find.byKey(const Key('today-reports')));
    await frames(tester, 10);
    expect(find.text('حصة جماعية · 3 طلاب'), findsOneWidget);
    expect(find.text('سارة أحمد'), findsNothing);
    expect(find.text('يوسف خالد'), findsOneWidget);

    // It opens the group's report through its most pressing one.
    await tester.tap(find.text('حصة جماعية · 3 طلاب'));
    await frames(tester, 10);
    expect(app.api.sent('GET', 'teacher/reports/r2'), hasLength(1));
    expect(find.text('تقرير الحصة الجماعية'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('a teacher disputes a group lesson about one student\'s seat, picked from its students', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    final group = {
      ...lesson('g1', canCancel: false, status: 'completed'),
      'subject': 'القرآن الكريم',
      'status_label': 'تمت',
      'format': 'group',
      'can_dispute': true,
      'dispute_until': time('2099-10-13T16:45:00Z', '2099-10-13T19:45:00+03:00'),
      'dispute_reasons': [
        {'key': 'student_attended', 'label': 'كان الطالب حاضرًا'},
      ],
      'group': {
        'capacity': 4,
        'seats_taken': 2,
        'seat_number': null,
        'classmates': null,
        'classmates_count': null,
        'classmates_label': null,
        'seat': null,
        'seat_label': null,
        'students': [
          {'id': 's1', 'name': 'سارة أحمد'},
          {'id': 's2', 'name': 'علي حسن'},
        ],
      },
    };
    app.api
      ..on('GET', 'teacher/lessons', {
        'from': '2099-10-11',
        'to': '2099-10-17',
        'count': 1,
        'minutes': 45,
        'data': [group],
      })
      ..on('POST', 'teacher/lessons/g1/dispute', {'data': group});
    await _open(tester, app, location: '/teach/week');

    await tester.tap(find.byKey(const Key('lesson-g1')));
    await frames(tester, 8);
    await tester.tap(find.text('اعترض'));
    await frames(tester, 8);

    // First whose seat: the lesson's students.
    expect(find.text('عن أي طالب الاعتراض؟'), findsOneWidget);
    expect(find.byKey(const Key('dispute-student-s1')), findsOneWidget);
    expect(find.byKey(const Key('dispute-student-s2')), findsOneWidget);
    await tester.tap(find.byKey(const Key('dispute-student-s2')));
    await frames(tester, 8);

    expect(find.text('اعتراض على نتيجة الحصة'), findsOneWidget);
    expect(find.textContaining('عن علي حسن'), findsOneWidget);
    await tester.tap(find.text('كان الطالب حاضرًا'));
    await tester.enterText(find.byType(TextField).last, 'كان علي في الحصة');
    await tester.tap(find.text('أرسل الاعتراض'));
    await frames(tester, 10);

    final sent = app.api.sent('POST', 'teacher/lessons/g1/dispute').single;
    expect(sent.data, {'reason': 'student_attended', 'note': 'كان علي في الحصة', 'student_id': 's2'});
    expect(sent.headers['Idempotency-Key'], isNotNull);
    await _close(tester);
  });
}
