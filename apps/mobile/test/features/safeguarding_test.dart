import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/safeguarding/data/safeguarding_repository.dart';
import 'package:academy_app/features/safeguarding/domain/safeguarding.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _options({String look = 'classic'}) => {
  'look': look,
  'learner': look == 'teacher' ? null : {'id': 'c1', 'name': 'سلمى رامي'},
  'categories': [
    {'key': 'adult_behaviour', 'label': 'المدرس أو حد كبير قال أو عمل حاجة مش مظبوطة'},
    {'key': 'outside_contact', 'label': 'حد طلب يتكلم برّه المنصة'},
  ],
  'text_required': look != 'kids',
  'urgent_allowed': look != 'kids',
  'lessons': <Object>[],
  'students': look == 'teacher'
      ? [
          {'id': 'u-kid', 'name': 'يوسف خالد'},
        ]
      : <Object>[],
  'danger': 'لو حد في خطر دلوقتي، كلّم الطوارئ الأول.',
};

Map<String, Object?> _concern() => {
  'id': 'k1',
  'number': '#1',
  'category': {'key': 'outside_contact', 'label': 'حد طلب يتكلم برّه المنصة'},
  'status': {'key': 'new', 'label': 'وصلنا'},
  'urgent': true,
  'text': 'طلب رقم الواتساب',
  'reply': null,
  'created_at': time('2026-10-07T09:00:00Z', '2026-10-07T12:00:00+03:00', label: 'الأربعاء 7 أكتوبر · 12:00'),
  'closed_at': null,
};

void main() {
  test('reads what a concern can be about in the reporter\'s own words, and a teacher\'s documents', () {
    final kids = ConcernOptions.fromJson(_options(look: 'kids'));
    expect(kids.kids, isTrue);
    expect(kids.urgentAllowed, isFalse);
    expect(kids.textRequired, isFalse);
    final docs = MyDocuments.fromJson({
      'required': true,
      'state': 'verified',
      'state_label': 'وفيشك متراجع، وصالح لحد 20 سبتمبر 2027.',
      'valid_until': '2027-09-20',
      'kinds': [
        {'key': 'criminal_record', 'label': 'فيش جنائي'},
      ],
      'checks': [
        {
          'id': 'd1',
          'kind': {'key': 'criminal_record', 'label': 'فيش جنائي'},
          'reference': 'EG-1',
          'issued_on': '2026-09-20',
          'expires_on': '2027-09-20',
          'status': {'key': 'verified', 'label': 'متراجع'},
          'reason': null,
          'sent_at': '2026-09-21T09:00:00Z',
          'file': {'name': 'fish.pdf', 'url': 'https://x/api/v1/teacher/checks/d1/file'},
        },
      ],
    });
    expect(docs.checks.single.fileName, 'fish.pdf');
    expect(docs.state, 'verified');
  });

  test('raises a concern once, with the learner, and gives back the thanks', () async {
    final app = await TestApp.create(accounts: [guardian]);
    await app.session.start();
    app.api.on('POST', 'safeguarding/concerns', {'data': _concern(), 'message': 'شكرًا إنك قولتلنا.'}, status: 201);

    final thanks = await SafeguardingRepository(app.services.api)
        .raise(category: 'outside_contact', text: ' طلب رقم الواتساب ', urgent: true, learnerId: 'c1');

    expect(thanks, 'شكرًا إنك قولتلنا.');
    final sent = app.api.sent('POST', 'safeguarding/concerns').single;
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(sent.data, {'category': 'outside_contact', 'text': 'طلب رقم الواتساب', 'urgent': true, 'learner_id': 'c1'});
  });

  testWidgets('a guardian tells the academy from Me and sees it waiting', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    final mine = <Map<String, Object?>>[];
    app.api
      ..onWith('GET', 'safeguarding/new', (_) => (200, {'data': _options()}))
      ..onWith('GET', 'safeguarding/concerns', (_) => (200, {'data': mine}))
      ..onWith('POST', 'safeguarding/concerns', (_) {
        mine.add(_concern());
        return (201, {'data': _concern(), 'message': 'شكرًا إنك قولتلنا. مسؤول الحماية في الأكاديمية هيبص عليه.'});
      });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1400));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/family/me');
    await frames(tester, 12);
    await tester.tap(find.byKey(const Key('open-tell-us')));
    await frames(tester, 12);
    expect(find.text('لو حد في خطر دلوقتي، كلّم الطوارئ الأول.'), findsOneWidget);

    // Nothing chosen: it asks what it is about first.
    await tester.tap(find.byKey(const Key('concern-send')));
    await frames(tester, 6);
    expect(app.api.sent('POST', 'safeguarding/concerns'), isEmpty);

    await tester.tap(find.byKey(const Key('category-outside_contact')));
    await tester.enterText(find.byKey(const Key('concern-text')), 'طلب رقم الواتساب');
    await tester.tap(find.byKey(const Key('concern-urgent')));
    await frames(tester, 3);
    await tester.tap(find.byKey(const Key('concern-send')));
    await frames(tester, 15);

    final body = app.api.sent('POST', 'safeguarding/concerns').single.data as Map<String, Object?>;
    expect(body['learner_id'], 'c1'); // the learner the family tabs show
    expect(body['urgent'], isTrue);
    expect(find.byKey(const Key('concern-thanks')), findsOneWidget);
    await tester.binding.setSurfaceSize(null);
  });

  testWidgets('a teacher reaches Raise a concern and My documents from More', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'safeguarding/new', {'data': _options(look: 'teacher')})
      ..on('GET', 'safeguarding/concerns', {'data': <Object>[]})
      ..on('GET', 'teacher/checks', {
        'data': {
          'required': true,
          'state': 'missing',
          'state_label': 'الأكاديمية بتطلب فيش جنائي قبل ما تدرّس للي تحت 18: ابعته هنا.',
          'valid_until': null,
          'kinds': [
            {'key': 'criminal_record', 'label': 'فيش جنائي'},
          ],
          'checks': <Object>[],
        },
      });
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 1400));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 15);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/teach/more');
    await frames(tester, 12);
    await tester.tap(find.byKey(const Key('open-concern')));
    await frames(tester, 12);
    expect(find.text('يوسف خالد'), findsNothing); // the student list is in the dropdown, closed
    expect(find.byKey(const Key('concern-urgent')), findsOneWidget);

    GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/teach/more');
    await frames(tester, 12);
    await tester.tap(find.byKey(const Key('open-documents')));
    await frames(tester, 12);
    expect(find.text('الأكاديمية بتطلب فيش جنائي قبل ما تدرّس للي تحت 18: ابعته هنا.'), findsOneWidget);
    expect(find.byKey(const Key('send-document')), findsOneWidget);
    await tester.binding.setSurfaceSize(null);
  });
}
