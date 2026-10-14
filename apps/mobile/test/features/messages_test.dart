import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:academy_app/features/messages/data/messages_repository.dart';
import 'package:academy_app/features/messages/domain/messages.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

const _notice = 'إنت موجود في كل محادثة بين مدرس وابنك، والأكاديمية بتراجعها.';

Map<String, Object?> _message(String id, String side, String body, {bool mine = false, String? hidden}) => {
  'id': id,
  'side': side,
  'side_label': side == 'teacher' ? 'المدرس' : 'ولي الأمر',
  'author': side == 'teacher' ? 'أ. نور الهدى' : 'خالد عمر',
  'mine': mine,
  'body': body,
  'hidden': hidden,
  'at': time('2026-10-07T10:00:00Z', '2026-10-07T13:00:00+03:00', label: '13:00'),
};

Map<String, Object?> _conversation(List<Map<String, Object?>> messages, {bool canWrite = true}) => {
  'id': 'k1',
  'with': {'id': 't1', 'name': 'أ. نور الهدى'},
  'members': [
    {'id': 't1', 'name': 'أ. نور الهدى', 'side': 'teacher', 'label': 'المدرس', 'me': false},
    {'id': 'c1', 'name': 'سلمى رامي', 'side': 'student', 'label': 'الطالب', 'me': false},
    {'id': 'g1', 'name': 'خالد عمر', 'side': 'guardian', 'label': 'ولي الأمر', 'me': true},
  ],
  'messages': messages,
  'can_write': canWrite,
  'why_not': canWrite ? null : 'الأكاديمية وقّفت المحادثة دي مؤقتًا.',
  'notice': _notice,
};

void main() {
  test('reads a conversation: who else is in it, what was hidden, and why it is read only', () {
    final talk = Conversation.fromJson(
      _conversation([_message('m1', 'teacher', 'كلمني على [•••]', hidden: 'اتخفى رقم تليفون.')], canWrite: false),
    );
    expect(talk.withName, 'أ. نور الهدى');
    expect(talk.others.map((m) => m.name), ['سلمى رامي']); // not the teacher it is with, not the reader
    expect(talk.messages.single.hidden, 'اتخفى رقم تليفون.');
    expect(talk.canWrite, isFalse);
    expect(talk.whyNot, isNotNull);
  });

  test('a message is sent once (an idempotency key), and a new conversation names the learner', () async {
    final app = await TestApp.create(accounts: [guardian]);
    await app.session.start();
    app.api
      ..on('POST', 'messages/conversations', {'data': _conversation([])}, status: 201)
      ..on('POST', 'messages/conversations/k1/messages', {'data': _message('m2', 'guardian', 'متشكرين', mine: true)}, status: 201);
    final repo = MessagesRepository(app.services.api);

    await repo.open('t1', learnerId: 'c1');
    expect(app.api.sent('POST', 'messages/conversations').single.data, {'with_id': 't1', 'learner_id': 'c1'});
    final sent = await repo.send('k1', 'متشكرين');
    expect(sent.mine, isTrue);
    expect(app.api.sent('POST', 'messages/conversations/k1/messages').single.headers['Idempotency-Key'], isNotNull);
  });

  test('a notification opens the conversation in every look', () {
    expect(targetOf('conversation', 'k1', Area.teacher), '/teach/more/messages/k1');
    expect(targetOf('conversation', 'k1', Area.family), '/family/me/messages/k1');
    // A child's and middle school's own tab.
    expect(targetOf('conversation', 'k1', Area.kids), '/kids/messages/k1');
    expect(targetOf('conversation', null, Area.middle), '/middle/messages');
    // Secondary school's under their More.
    expect(targetOf('conversation', null, Area.teen), '/teen/more/messages');
    expect(targetOf('conversation', 'k1', Area.teen), '/teen/more/messages/k1');
  });

  testWidgets('a guardian opens Messages from Me, starts talking with the teacher and hears what was hidden', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    final messages = <Map<String, Object?>>[];
    var opened = false;
    app.api
      ..onWith(
        'GET',
        'messages/conversations',
        (_) => (
          200,
          {
            'data': [
              {
                'id': opened ? 'k1' : null,
                'with': {'id': 't1', 'name': 'أ. نور الهدى'},
                'last_message': null,
                'unread': 0,
                'can_write': true,
                'why_not': null,
              },
            ],
            'meta': {
              'learner': {'id': 'c1', 'name': 'سلمى رامي'},
              'notice': _notice,
            },
          },
        ),
      )
      ..onWith('POST', 'messages/conversations', (_) {
        opened = true;
        return (201, {'data': _conversation(messages)});
      })
      ..onWith('GET', 'messages/conversations/k1', (_) => (200, {'data': _conversation(messages)}))
      ..onWith('POST', 'messages/conversations/k1/messages', (_) {
        messages.add(_message('m1', 'guardian', 'ابعتيلي على [•••]', mine: true, hidden: 'اتخفى رقم تليفون: الكلام بيفضل هنا على المنصة.'));
        return (201, {'data': messages.last});
      });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1000));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/me');
    await frames(tester, 12);
    await tester.tap(find.byKey(const Key('open-messages')));
    await frames(tester, 12);
    expect(find.text(_notice), findsOneWidget);
    await tester.tap(find.byKey(const Key('conversation-t1')));
    await frames(tester, 15);
    expect(app.api.sent('POST', 'messages/conversations').single.data, {
      'with_id': 't1',
      'learner_id': 'c1',
    }); // the learner the family tabs show
    expect(find.text('معاكم: سلمى رامي (الطالب)'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('message-body')), 'ابعتيلي على 01001234567');
    await tester.tap(find.byKey(const Key('message-send')));
    await frames(tester, 15);
    expect(find.text('ابعتيلي على [•••]'), findsOneWidget);
    expect(find.text('اتخفى رقم تليفون: الكلام بيفضل هنا على المنصة.'), findsWidgets); // under the bubble, and said once
    await tester.binding.setSurfaceSize(null);
  });
}
