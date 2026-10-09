import 'package:academy_app/app/app.dart';
import 'package:academy_app/app/router/areas.dart';
import 'package:academy_app/app/router/targets.dart';
import 'package:academy_app/features/privacy/domain/my_data.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _request(
  String id, {
  required String kind,
  required String status,
  String subject = 'g1',
  String? url,
  String? answer,
}) => {
  'id': id,
  'kind': {'key': kind, 'label': kind == 'export' ? 'نسخة من البيانات' : 'مسح الحساب'},
  'status': {'key': status, 'label': status},
  'subject': {'id': subject, 'name': subject == 'g1' ? 'رامي ناصر' : 'سلمى رامي', 'self': subject == 'g1'},
  'requester': 'رامي ناصر',
  'reason': null,
  'answer': answer,
  'created_at': time('2026-10-09T07:00:00Z', '2026-10-09T10:00:00+03:00', label: 'الجمعة 9 أكتوبر · 10:00'),
  'due_at': kind == 'erasure' ? time('2026-11-08T07:00:00Z', '2026-11-08T10:00:00+03:00', label: 'الأحد 8 نوفمبر') : null,
  'erase_after': null,
  'done_at': null,
  'expires_at': url == null ? null : time('2026-10-16T07:00:00Z', '2026-10-16T10:00:00+03:00', label: 'الجمعة 16 أكتوبر'),
  'size': url == null ? null : 5288,
  'download_url': url,
  'held_back': <String>[],
  'can_cancel': kind == 'erasure' && status == 'pending',
};

Map<String, Object?> _mine(List<Map<String, Object?>> requests, {bool canCopy = true}) => {
  'data': {
    'subjects': [
      {'id': 'g1', 'name': 'رامي ناصر', 'self': true, 'can_copy': canCopy},
      {'id': 'c1', 'name': 'سلمى رامي', 'self': false, 'can_copy': true},
    ],
    'requests': requests,
    'goes': ['الحساب'],
    'stays': ['الفواتير'],
    'stays_note': 'اللي بيفضل بيفضل من غير اسم.',
    'grace_hours': 24,
    'answer_days': 30,
    'links': {
      'privacy': 'https://demo.example/privacy',
      'terms': 'https://demo.example/terms',
      'deletion': 'https://demo.example/account-deletion',
    },
  },
};

void main() {
  test('reads whom one may ask about, the latest copy and the open erasure', () {
    final mine = MyData.fromJson(
      _mine([
        _request('r2', kind: 'erasure', status: 'pending'),
        _request('r1', kind: 'export', status: 'ready', url: 'https://demo.example/privacy/copies/r1?signature=x'),
      ]),
    );
    expect(mine.subjects.map((s) => s.name), ['رامي ناصر', 'سلمى رامي']);
    expect(mine.copyOf('g1')?.downloadUrl, isNotNull);
    expect(mine.erasureOf('g1')?.isOpenErasure, isTrue);
    expect(mine.erasureOf('c1'), isNull);
    expect(mine.links['deletion'], 'https://demo.example/account-deletion');
  });

  test('a message about one\'s data opens My data in each look', () {
    expect(targetOf('my_data', 'r1', Area.family), '/family/me/my-data');
    expect(targetOf('my_data', 'r1', Area.teacher), '/teach/more/my-data');
  });

  testWidgets('a guardian asks for a copy, asks to erase their child\'s account with a reason, and takes it back', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    final requests = <Map<String, Object?>>[];
    app.api
      ..onWith('GET', 'me/data-requests', (_) => (200, _mine(requests, canCopy: !requests.any((r) => r['id'] == 'x1'))))
      ..onWith('POST', 'me/data-requests', (o) {
        final body = o.data as Map<String, dynamic>;
        final made = body['kind'] == 'export'
            ? _request('x1', kind: 'export', status: 'pending', subject: body['subject_id'] as String)
            : _request('e1', kind: 'erasure', status: 'pending', subject: body['subject_id'] as String);
        requests.insert(0, made);
        return (201, {'data': made});
      })
      ..onWith('POST', 'me/data-requests/e1/cancel', (_) {
        requests.removeWhere((r) => r['id'] == 'e1');
        return (200, {'data': _request('e1', kind: 'erasure', status: 'cancelled', subject: 'c1')});
      });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1600));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/me');
    await frames(tester, 10);
    await tester.tap(find.byKey(const Key('open-my-data')));
    await frames(tester, 12);
    expect(find.text('بياناتي'), findsWidgets);
    expect(find.text('سلمى رامي'), findsOneWidget);

    // A copy of the guardian's own data: one request, marked to be sent once.
    await tester.tap(find.byKey(const Key('copy-g1')));
    await frames(tester, 12);
    final copy = app.api.sent('POST', 'me/data-requests').single;
    expect(copy.data, {'kind': 'export', 'subject_id': 'g1'});
    expect(copy.headers['Idempotency-Key'], isNotNull);
    expect(find.byKey(const Key('copy-g1')), findsNothing);

    // Erasure of the child's account, with a reason.
    await tester.ensureVisible(find.byKey(const Key('erase-c1')));
    await tester.tap(find.byKey(const Key('erase-c1')));
    await frames(tester, 8);
    expect(find.text('تطلب مسح حساب سلمى رامي؟'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('erase-reason')), 'خلّصت');
    await tester.tap(find.widgetWithText(FilledButton, 'امسح الحساب ده'));
    await frames(tester, 12);
    expect(app.api.sent('POST', 'me/data-requests').last.data, {'kind': 'erasure', 'subject_id': 'c1', 'reason': 'خلّصت'});
    expect(find.text('اتطلب: الأكاديمية هترد قبل الأحد 8 نوفمبر.'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('cancel-c1')));
    await tester.tap(find.byKey(const Key('cancel-c1')));
    await frames(tester, 12);
    expect(app.api.sent('POST', 'me/data-requests/e1/cancel'), hasLength(1));
    expect(find.byKey(const Key('erase-c1')), findsOneWidget);
    expect(find.byKey(const Key('page-deletion')), findsOneWidget);
    await tester.binding.setSurfaceSize(null);
  });
}
