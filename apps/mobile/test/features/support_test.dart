import 'dart:typed_data';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/support/data/support_repository.dart';
import 'package:academy_app/features/support/domain/support.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _summary({String status = 'waiting'}) => {
  'id': 't1',
  'reference': '#7',
  'subject': 'الفصل مش بيفتح',
  'kind': {'key': 'technical', 'label': 'مشكلة تقنية'},
  'status': {'key': status, 'label': status == 'waiting' ? 'دورك' : 'مستنية الأكاديمية'},
  'learner': {'id': 's1', 'name': 'سلمى رامي'},
  'created_at': time('2026-10-07T09:00:00Z', '2026-10-07T12:00:00+03:00', label: 'الأربعاء 7 أكتوبر · 12:00'),
  'last_message_at': time('2026-10-07T10:00:00Z', '2026-10-07T13:00:00+03:00', label: 'الأربعاء 7 أكتوبر · 13:00'),
  'your_turn': status == 'waiting',
};

Map<String, Object?> _ticket({required List<Map<String, Object?>> messages, String status = 'waiting'}) => {
  ..._summary(status: status),
  'lesson': null,
  'can_reply': true,
  'can_reopen': false,
  'can_close': true,
  'reopen_until': null,
  'messages': messages,
};

Map<String, Object?> _line(String id, String side, String body) => {
  'id': id,
  'side': side,
  'kind': 'message',
  'author': side == 'staff' ? 'منى' : null,
  'body': body,
  'event': null,
  'file': null,
  'at': time('2026-10-07T10:00:00Z', '2026-10-07T13:00:00+03:00', label: '13:00'),
};

void main() {
  test('reads a ticket, its lines and what a new one can be about', () {
    final ticket = Ticket.fromJson(_ticket(messages: [_line('m1', 'requester', 'مش بيفتح'), _line('m2', 'staff', 'حاول مرة أخرى')]));
    expect(ticket.summary.reference, '#7');
    expect(ticket.summary.yourTurn, isTrue);
    expect(ticket.lines.first.mine, isTrue);
    expect(ticket.lines.last.author, 'منى');
    final options = TicketOptions.fromJson({
      'learner': null,
      'kinds': [
        {'key': 'technical', 'label': 'مشكلة تقنية'},
      ],
      'lessons': <Object>[],
    });
    expect(options.closed, isFalse);
    expect(TicketOptions.fromJson({'learner': null, 'kinds': <Object>[], 'lessons': <Object>[]}).closed, isTrue);
  });

  test('writes to the academy as multipart, once, with the learner and a picture', () async {
    final app = await TestApp.create(accounts: [guardian]);
    await app.session.start();
    app.api.on('POST', 'support/tickets', {
      'data': _ticket(messages: [_line('m1', 'requester', 'مش بيفتح')], status: 'open'),
    }, status: 201);

    final ticket = await SupportRepository(app.services.api).open(
      kind: 'technical',
      subject: 'الفصل مش بيفتح',
      body: 'مش بيفتح من الموبايل',
      learnerId: 's1',
      file: Attachment(bytes: Uint8List.fromList([1, 2, 3]), name: 'screen.png', mime: 'image/png'),
    );

    expect(ticket.summary.id, 't1');
    final sent = app.api.sent('POST', 'support/tickets').single;
    expect(sent.headers['Idempotency-Key'], isNotNull);
    final form = sent.data as FormData;
    expect(Map.fromEntries(form.fields), {
      'kind': 'technical',
      'subject': 'الفصل مش بيفتح',
      'body': 'مش بيفتح من الموبايل',
      'learner_id': 's1',
    });
    expect(form.files.single.value.filename, 'screen.png');
  });

  testWidgets('a guardian opens Help from Me, writes a ticket, follows the answer and answers back', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    final lines = [_line('m1', 'requester', 'بدوس على اللينك ومش بيفتح')];
    app.api
      ..onWith(
        'GET',
        'support/new',
        (_) => (
          200,
          {
            'data': {
              'learner': {'id': 's1', 'name': 'سلمى رامي'},
              'kinds': [
                {'key': 'complaint', 'label': 'شكوى'},
                {'key': 'technical', 'label': 'مشكلة تقنية'},
              ],
              'lessons': <Object>[],
            },
          },
        ),
      )
      ..onWith(
        'GET',
        'support/tickets',
        (_) => (
          200,
          {
            'data': lines.length > 1 ? [_summary()] : <Object>[],
          },
        ),
      )
      ..onWith('POST', 'support/tickets', (_) {
        lines.add(_line('m2', 'staff', 'جرّب من المتصفح لحد ما نصلحها'));
        return (201, {'data': _ticket(messages: lines, status: 'open')});
      })
      ..onWith('GET', 'support/tickets/t1', (_) => (200, {'data': _ticket(messages: lines)}))
      ..onWith('POST', 'support/tickets/t1/messages', (r) {
        lines.add(_line('m3', 'requester', 'تمام اشتغلت'));
        return (201, {'data': _ticket(messages: lines, status: 'open')});
      });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1200));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/me');
    await frames(tester, 12);
    await tester.tap(find.byKey(const Key('open-help')));
    await frames(tester, 12);
    expect(find.text('لا توجد تذاكر بعد. إن كان هناك أي شيء غير سليم، فاكتب إلينا.'), findsOneWidget);

    await tester.tap(find.byKey(const Key('new-ticket')));
    await frames(tester, 12);
    await tester.tap(find.byKey(const Key('kind-technical')));
    await tester.enterText(find.byKey(const Key('ticket-subject')), 'الفصل مش بيفتح');
    await tester.enterText(find.byKey(const Key('ticket-body')), 'بدوس على اللينك ومش بيفتح');
    await frames(tester, 3);
    await tester.tap(find.byKey(const Key('ticket-send')));
    await frames(tester, 15);
    final form = app.api.sent('POST', 'support/tickets').single.data as FormData;
    expect(Map.fromEntries(form.fields)['kind'], 'technical');
    expect(Map.fromEntries(form.fields)['learner_id'], 'c1'); // the learner the family tabs show

    // The ticket's page: the academy's answer, and an answer back.
    expect(find.text('جرّب من المتصفح لحد ما نصلحها'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('ticket-reply')), 'تمام اشتغلت');
    await tester.tap(find.byKey(const Key('ticket-reply-send')));
    await frames(tester, 15);
    expect(Map.fromEntries((app.api.sent('POST', 'support/tickets/t1/messages').single.data as FormData).fields), {'body': 'تمام اشتغلت'});
    expect(find.text('تمام اشتغلت'), findsOneWidget);
    await tester.binding.setSurfaceSize(null);
  });

  testWidgets('a teacher reaches Support from More', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'support/new', {
        'data': {
          'learner': null,
          'kinds': [
            {'key': 'technical', 'label': 'مشكلة تقنية'},
            {'key': 'billing', 'label': 'الدفع والفواتير'},
          ],
          'lessons': <Object>[],
        },
      })
      ..on('GET', 'support/tickets', {'data': <Object>[]});
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1000));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/teach/more');
    await frames(tester, 12);
    await tester.tap(find.byKey(const Key('open-support')));
    await frames(tester, 12);
    expect(find.text('المساعدة'), findsWidgets);
    await tester.tap(find.byKey(const Key('new-ticket')));
    await frames(tester, 12);
    // A teacher's "payments" is their pay.
    expect(find.text('راتبك أو كشف حسابك.'), findsOneWidget);
    await tester.binding.setSurfaceSize(null);
  });
}
