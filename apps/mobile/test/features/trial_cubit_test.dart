import 'package:academy_app/core/network/api_problem.dart';
import 'package:academy_app/features/trial/data/trial_repository.dart';
import 'package:academy_app/features/trial/domain/trial.dart';
import 'package:academy_app/features/trial/presentation/trial_cubit.dart';
import 'package:flutter_test/flutter_test.dart';

import '../fixtures/trial.dart';
import '../helpers.dart';

/// A cubit over the fake API, on a clock the test moves, remembering academies in [remembered].
Future<(TrialCubit, FakeApi, List<Duration>)> _cubit({
  String booking = 'family',
  DateTime Function()? clock,
  String? recalled,
  List<String>? remembered,
}) async {
  final app = await TestApp.create();
  app.api
    ..on('GET', 'site', sitePage(booking: booking))
    ..on('GET', 'branding', branding())
    ..on('GET', 'site/trial-slots', slots());
  final waits = <Duration>[];
  final cubit = TrialCubit(
    TrialRepository(app.client),
    timezone: 'Asia/Riyadh',
    recall: () async => recalled,
    remember: (slug) async => remembered?.add(slug),
    clock: clock,
    wait: (d) async => waits.add(d),
  );
  addTearDown(cubit.close);
  return (cubit, app.api, waits);
}

/// A parent for an eight-year-old in the Quran: up to the wishes.
Future<void> _toWishes(TrialCubit c) async {
  await c.openAcademy('demo');
  c
    ..chooseLearner(Learner.child)
    ..saveWho(name: 'رامي فؤاد', childName: 'سلمى', childAge: 8)
    ..chooseSubject('sub-quran');
}

/// Up to the code, with Saturday 6 pm chosen.
Future<void> _toCode(TrialCubit c, FakeApi api) async {
  await _toWishes(c);
  await c.saveWishes();
  c.chooseSlot(c.state.slots![1]);
  c.confirmTime();
  api.on('POST', 'site/trials/code', codeSent(), status: 202);
  await c.submitContact(phone: '+966501234504');
}

void main() {
  test('starts with the academy remembered on this phone, and remembers the one opened', () async {
    final remembered = <String>[];
    final (c, api, _) = await _cubit(recalled: 'demo', remembered: remembered);
    await c.start();
    expect(c.state.step, TrialStep.who);
    expect(c.state.page!.name, 'أكاديمية النور');
    expect(c.state.branding, isNotNull);
    expect(remembered, ['demo']);
    expect(api.sent('GET', 'site').single.headers['X-Academy'], 'demo');
  });

  test('an academy that is not found keeps the academy step with the problem', () async {
    final (c, api, _) = await _cubit();
    api.on('GET', 'site', {'type': 'not-found', 'title': 'غير موجود.', 'status': 404}, status: 404);
    expect(await c.openAcademy(' Nowhere '), isFalse);
    expect(c.state.step, TrialStep.academy);
    expect(c.state.academyCode, 'nowhere');
    expect(c.state.problem!.isNotFound, isTrue);
  });

  test('the steps follow how the academy takes trials and what the family chose', () async {
    final (c, _, _) = await _cubit();
    await _toWishes(c);
    expect(c.state.steps, [TrialStep.who, TrialStep.subject, TrialStep.wishes, TrialStep.time, TrialStep.contact, TrialStep.code]);
    c.helpMeChoose();
    expect(c.state.selfBooks, isFalse);
    expect(c.state.steps, [TrialStep.who, TrialStep.subject, TrialStep.wishes, TrialStep.contact]);

    final (staff, _, _) = await _cubit(booking: 'staff');
    await _toWishes(staff);
    await staff.saveWishes();
    expect(staff.state.step, TrialStep.contact);
    expect(staff.state.slots, isNull);
  });

  test('the times are asked once for a subject, age and teacher, and again when those change', () async {
    final (c, api, _) = await _cubit();
    await _toWishes(c);
    await c.saveWishes();
    expect(c.state.step, TrialStep.time);
    expect(c.state.slots, hasLength(3));
    expect(c.state.days.map((d) => d.day), ['2099-11-07', '2099-11-08']);

    expect(c.back(), isTrue);
    await c.saveWishes();
    expect(api.sent('GET', 'site/trial-slots'), hasLength(1));

    c.back();
    c.keepWishes(teacherGender: TeacherGender.female);
    await c.saveWishes();
    expect(api.sent('GET', 'site/trial-slots'), hasLength(2));
    expect(api.sent('GET', 'site/trial-slots').last.queryParameters['teacher_gender'], 'female');
  });

  test('wishes narrow the times shown, unless none fits', () async {
    final (c, _, _) = await _cubit();
    await _toWishes(c);
    c.keepWishes(days: {7}, dayPart: DayPart.evening);
    await c.saveWishes();
    expect(c.state.shownSlots.map((s) => s.startsAt.local), ['2099-11-08T19:30:00+03:00']);
    c.showAllTimes(true);
    expect(c.state.shownSlots, hasLength(3));

    c.keepWishes(days: {5}, dayPart: DayPart.morning);
    c.showAllTimes(false);
    expect(c.state.anySuits, isFalse);
    expect(c.state.shownSlots, hasLength(3));
  });

  test('wrong codes count up to a locked one; an old code is ended', () async {
    var now = DateTime.utc(2099);
    final (c, api, _) = await _cubit(clock: () => now);
    await _toCode(c, api);
    expect(c.state.step, TrialStep.code);
    api.on('POST', 'site/trials', refused('code', 'الرمز غير صحيح.'), status: 422);
    for (var i = 1; i < 5; i++) {
      expect(await c.book('00000$i'), isFalse);
      expect(c.state.trouble, CodeTrouble.wrong);
    }
    await c.book('000005');
    expect(c.state.trouble, CodeTrouble.locked);

    await c.sendCode();
    expect(c.state.tries, 0);
    expect(c.state.trouble, isNull);
    now = DateTime.utc(2099, 1, 1, 0, 11);
    await c.book('123456');
    expect(c.state.trouble, CodeTrouble.expired);
  });

  test('a time taken meanwhile goes back to the fresh times with the API\'s words; the code is spent', () async {
    final (c, api, _) = await _cubit();
    await _toCode(c, api);
    api
      ..on('POST', 'site/trials', refused('starts_at', 'سبقكم أحد إلى هذا الموعد.'), status: 422)
      ..on('GET', 'site/trial-slots', slots([slot('2099-11-07', '09:00')]));
    expect(await c.book('123456'), isFalse);
    expect(c.state.step, TrialStep.time);
    expect(c.state.timeNote, 'سبقكم أحد إلى هذا الموعد.');
    expect(c.state.chosen, isNull);
    expect(c.state.code, isNull);
    expect(c.state.slots, hasLength(1));
    expect(c.state.draft.phone, '+966501234504');
    expect(c.state.draft.childName, 'سلمى');
  });

  test('a refusal about the learner or the number stays on the code step with the words', () async {
    final (c, api, _) = await _cubit();
    await _toCode(c, api);
    api.on('POST', 'site/trials', refused('starts_at', 'لهذا الرقم 3 حصص تجريبية محجوزة.'), status: 422);
    await c.book('123456');
    expect(c.state.step, TrialStep.code);
    expect(c.state.refusal, 'لهذا الرقم 3 حصص تجريبية محجوزة.');
    expect(c.state.codeSpent, isTrue);

    c.sendInstead();
    expect(c.state.viaRequest, isTrue);
    expect(c.state.step, TrialStep.contact);
    expect(c.state.refusal, isNull);
  });

  test('a refused field brings its own step back', () async {
    expect(TrialCubit.stepFor(_problem('child_birth_year')), TrialStep.who);
    expect(TrialCubit.stepFor(_problem('subject_id')), TrialStep.subject);
    expect(TrialCubit.stepFor(_problem('preferred_to')), TrialStep.wishes);
    expect(TrialCubit.stepFor(_problem('email')), TrialStep.contact);
  });

  test('booked: the time and subject as the API wrote them, sent once whatever the network does', () async {
    final (c, api, _) = await _cubit();
    await _toCode(c, api);
    api.on('POST', 'site/trials', booked(), status: 201);
    expect(await c.book('123456'), isTrue);
    expect(c.state.step, TrialStep.done);
    expect(c.state.booked!.startsAt!.label, 'السبت 7 نوفمبر · 6:00 م');
    expect(api.sent('POST', 'site/trials').single.headers['Idempotency-Key'], isNotNull);
  });

  test('a request waits until the form is a few seconds old, and reads a stale page again first', () async {
    var now = DateTime.utc(2099);
    final (c, api, waits) = await _cubit(booking: 'staff', clock: () => now);
    api.on('POST', 'site/requests', {
      'data': {'received': true},
    }, status: 201);
    await _toWishes(c);
    await c.saveWishes();
    now = now.add(const Duration(seconds: 1));
    expect(await c.submitContact(phone: '+966501234504'), isTrue);
    expect(waits, [const Duration(seconds: 3)]);
    expect(c.state.requested, isTrue);

    c.anotherChild();
    now = now.add(const Duration(hours: 6));
    c.saveWho(name: 'رامي فؤاد', childName: 'يوسف', childAge: 10);
    c.chooseSubject('sub-arabic');
    await c.saveWishes();
    await c.submitContact(phone: '+966501234504');
    expect(api.sent('GET', 'site'), hasLength(2));
    expect(waits, hasLength(1));
  });

  test('another child keeps the parent and the number, and nothing else', () async {
    final (c, api, _) = await _cubit();
    await _toCode(c, api);
    api.on('POST', 'site/trials', booked(), status: 201);
    await c.book('123456');
    c.anotherChild();
    expect(c.state.step, TrialStep.who);
    expect(c.state.draft.name, 'رامي فؤاد');
    expect(c.state.draft.phone, '+966501234504');
    expect(c.state.draft.childName, '');
    expect(c.state.draft.subjectId, isNull);
    expect(c.state.booked, isNull);
    expect(c.state.page, isNotNull);
  });

  test('back goes one step at a time; from choosing to send the request it goes back to the times', () async {
    final (c, _, _) = await _cubit();
    await _toWishes(c);
    await c.saveWishes();
    c.sendInstead();
    expect(c.state.step, TrialStep.contact);
    expect(c.back(), isTrue);
    expect(c.state.step, TrialStep.time);
    expect(c.state.viaRequest, isFalse);
    c.back();
    c.back();
    expect(c.state.step, TrialStep.subject);
    c.back();
    expect(c.state.step, TrialStep.who);
    expect(c.back(), isFalse);
  });

  test('the request carries the API\'s names for each part', () {
    const draft = TrialDraft(
      learner: Learner.child,
      name: ' رامي ',
      childName: 'سلمى',
      childAge: 8,
      subjectId: 'sub-quran',
      days: {7, 6},
      dayPart: DayPart.evening,
      teacherGender: TeacherGender.female,
      phone: '+966501234504',
    );
    expect(draft.toJson(thisYear: 2099, timezone: 'Asia/Riyadh'), {
      'name': 'رامي',
      'phone': '+966501234504',
      'timezone': 'Asia/Riyadh',
      'subject_id': 'sub-quran',
      'for_child': true,
      'child_name': 'سلمى',
      'child_birth_year': 2091,
      'preferred_days': [6, 7],
      'preferred_from': 17,
      'preferred_to': 23,
      'teacher_gender': 'female',
    });
    expect(const TrialDraft(learner: Learner.me, name: 'منى', helpChoose: true, phone: '+20100').toJson(thisYear: 2099), {
      'name': 'منى',
      'phone': '+20100',
      'help_choose': true,
      'for_child': false,
    });
  });
}

ApiProblem _problem(String field) => ApiProblem(
  type: 'validation',
  title: '',
  status: 422,
  errors: {
    field: ['…'],
  },
);
