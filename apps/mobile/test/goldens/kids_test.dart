@Tags(['golden'])
library;

import 'package:flutter_test/flutter_test.dart';

import '../fixtures/journey.dart';
import '../helpers.dart';
import 'golden_kit.dart';

/// The kids' look, as a child sees it: these pictures lock it while the
/// other looks are redesigned around it.
Future<TestApp> _kid() async {
  final app = await TestApp.create(accounts: [fxStudent('kids')]);
  fxStudentRoutes(app.api, 'kids', fxJourney());
  app.api
    ..on('GET', 'family/lessons', {
      'learner': {'id': 'u-kids', 'name': 'يوسف خالد'},
      'data': [lesson('l1'), lesson('l2', canCancel: false)],
      'notices': <String>[],
    })
    ..on('GET', 'learning/homework', {
      'data': [homeworkJson('h1'), homeworkJson('h2', status: 'submitted', body: 'قريت السورة 3 مرات')],
    })
    ..on('GET', 'messages/unread', {
      'data': {'count': 1},
    })
    ..on('GET', 'messages/conversations', {
      'data': [
        {
          'id': 'k1',
          'with': {'id': 't1', 'name': 'أ. نور الهدى'},
          'last_message': {
            'body': 'برافو يا يوسف، حفظك ممتاز',
            'at': time('2099-10-07T10:00:00Z', '2099-10-07T13:00:00+03:00', label: '13:00'),
          },
          'unread': 1,
          'can_write': true,
          'why_not': null,
        },
      ],
      'meta': {'learner': null, 'notice': null},
    });
  return app;
}

void main() {
  for (final (name, location) in [
    ('journey', '/kids/journey'),
    ('lessons', '/kids/lessons'),
    ('homework', '/kids/more/homework'),
    ('messages', '/kids/messages'),
    ('more', '/kids/more'),
  ]) {
    testWidgets('the kids\' $name stays as it is', (tester) async {
      final app = await _kid();
      await openAt(tester, app, location);
      await expectScreen('kids/$name.png');
      await closeApp(tester);
    });
  }
}
