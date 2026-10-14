// The answers each look's screens ask the API for, from the tests' fixtures:
// `flutter test tool/screens/data_test.dart` opens every tab of every look
// with the fake API, then writes what each screen fetched to
// build/screens/data/{look}.json for shoot.mjs to answer from. It is not
// part of the suite (it lives outside test/).
import 'dart:convert';
import 'dart:io';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../../test/features/sign_in_flow_test.dart' show frames;
import '../../test/fixtures/journey.dart';
import '../../test/helpers.dart';

/// The looks: who signs in, the fixtures, and the places to picture.
final looks = <String, (Account, void Function(FakeApi), List<String>)>{
  'guardian': (guardian, familyRoutes, ['/family/home', '/family/lessons', '/family/journey', '/family/money', '/family/me']),
  'teacher': (teacher, teacherRoutes, ['/teach/today', '/teach/week', '/teach/reports', '/teach/students', '/teach/more']),
  'kids': (
    fxStudent('kids'),
    (api) => fxStudentRoutes(api, 'kids', fxJourney()),
    ['/kids/journey', '/kids/lessons', '/kids/messages', '/kids/more'],
  ),
  'middle': (
    fxStudent('middle'),
    (api) => fxStudentRoutes(api, 'middle', fxJourney(stage: 'middle', twoReviews: true, lessonOpen: true)),
    ['/middle/journey', '/middle/lessons', '/middle/messages', '/middle/more', '/middle/more/homework'],
  ),
  'teen': (
    fxStudent('teen'),
    (api) => fxStudentRoutes(api, 'teen', fxJourney(stage: 'teen', style: 'calm', twoReviews: true, lessonOpen: true)),
    ['/teen/journey', '/teen/lessons', '/teen/homework', '/teen/reports', '/teen/more'],
  ),
};

void main() {
  for (final MapEntry(key: name, value: (account, routes, places)) in looks.entries) {
    testWidgets('the answers $name\'s screens need', (tester) async {
      final app = await TestApp.create(accounts: [account]);
      // What the shared fixtures leave to each test: a few students, no conversations yet.
      app.api
        ..on('GET', 'messages/unread', {
          'data': {'count': 1},
        })
        ..on('GET', 'messages/conversations', {'data': <Object>[]})
        ..on('GET', 'teacher/students', {
          'data': [
            {
              'enrollment_id': 'e1',
              'student_id': 's1',
              'student': 'يوسف خالد',
              'subject': 'قرآن',
              'paused': false,
              'percent': 40,
              'here': 'سورة النصر',
              'to_mark': 1,
              'last_report_at': time('2099-10-06T16:00:00Z', '2099-10-06T19:00:00+03:00', label: 'الثلاثاء 6 أكتوبر'),
              'materials': <Object>[],
            },
            {
              'enrollment_id': 'e2',
              'student_id': 's2',
              'student': 'مريم خالد',
              'subject': 'تجويد',
              'paused': false,
              'percent': 20,
              'here': 'أحكام النون الساكنة',
              'to_mark': 0,
            },
            {
              'enrollment_id': 'e3',
              'student_id': 's3',
              'student': 'سلمى رامي',
              'subject': 'لغة عربية',
              'paused': true,
              'percent': null,
              'to_mark': 0,
            },
          ],
        });
      routes(app.api);
      await app.session.start();
      await tester.binding.setSurfaceSize(const Size(390, 844));
      await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
      await frames(tester, 20);
      for (final at in places) {
        GoRouter.of(tester.element(find.byType(Scaffold).first)).go(at);
        await frames(tester, 20);
      }
      final answers = <String, Object?>{};
      for (final r in [...app.api.requests]) {
        if (r.method != 'GET') continue;
        final query = r.queryParameters.isEmpty ? '' : '?${Uri(queryParameters: r.queryParameters.map((k, v) => MapEntry(k, '$v'))).query}';
        final reply = await app.api.fetch(r, null, null);
        if (reply.statusCode != 200) continue;
        final body = await utf8.decodeStream(reply.stream);
        answers['${r.path}$query'] = body.isEmpty ? null : jsonDecode(body);
      }
      final out = File('build/screens/data/$name.json')..createSync(recursive: true);
      out.writeAsStringSync(
        const JsonEncoder.withIndent(' ').convert({
          'user': {'id': account.userId, 'name': account.userName, 'roles': account.roles},
          'academy': {'slug': account.academySlug, 'name_ar': account.academyNameAr, 'name_en': account.academyNameEn},
          'places': places,
          'answers': answers,
        }),
      );
      await tester.pumpWidget(const SizedBox());
      await frames(tester, 2);
      await tester.binding.setSurfaceSize(null);
    });
  }
}
