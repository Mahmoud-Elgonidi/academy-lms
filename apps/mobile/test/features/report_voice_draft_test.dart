import 'dart:convert';
import 'dart:typed_data';

import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/homework/data/homework_repository.dart';
import 'package:academy_app/features/homework/presentation/widgets/voice_recorder.dart';
import 'package:academy_app/features/teaching/domain/reports.dart';
import 'package:academy_app/features/teaching/domain/voice_draft.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'report_questions_test.dart' show template;
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> item(String id, {String? reason = 'next', String? outcome}) => {
  'node_id': id,
  'title': 'Lesson $id',
  'path': 'Level 1',
  'reason': reason,
  'objectives': null,
  'outcome': outcome,
  'score': null,
  'note': null,
};

ReportItem row(String id, {String? outcome, String? note}) =>
    ReportItem(nodeId: id, title: 'Lesson $id', path: 'Level 1', reason: 'next', outcome: outcome, note: note);

/// What the API answers: the open form with the draft in it, and what it filled.
Map<String, Object?> draftJson() => {
  'heard': 'يوسف قرأ الدرس الأول كويس جدًا، وحفظ صفحتين',
  'report': {
    'notes_internal': null,
    'engagement': 5,
    'overall': 4,
    'notes_for_student': 'برافو يا يوسف! قريت كويس جدًا.',
    'items': [
      {'node_id': 'n1', 'label': 'Level 1 › Lesson n1', 'outcome': 'mastered', 'score': 5, 'note': null},
      {'node_id': 'n3', 'label': 'Level 1 › Unit 2 › Long vowels', 'outcome': 'needs_review', 'score': null, 'note': 'يراجعه'},
    ],
    'answers': {'f1': 2, 'f4': true},
    'homework': {'title': 'سمّع السورة', 'instructions': null, 'accepts': 'audio', 'due_days': 4, 'practice': false},
  },
  'filled': [
    'engagement',
    'overall',
    'notes_for_student',
    'items.n1.outcome',
    'items.n1.score',
    'items.n3',
    'answers.f1',
    'answers.f4',
    'homework',
  ],
  'unsure': ['overall', 'items.n3', 'answers.f1'],
  'left': <String>[],
};

void main() {
  test('takes into the lessons only what the draft filled, and adds a lesson spoken of', () {
    final d = VoiceDraft.fromJson(draftJson());
    final items = d.items([row('n1', outcome: 'practiced', note: 'mine'), row('n2')]);
    expect(
      [for (final i in items) (i.nodeId, i.outcome, i.score, i.note, i.reason)],
      [('n1', 'mastered', 5, 'mine', 'next'), ('n2', null, null, null, 'next'), ('n3', 'needs_review', null, 'يراجعه', null)],
    );
    expect(items.last.title, 'Long vowels');
    expect(items.last.path, 'Level 1 › Unit 2');
  });

  test('marks what it filled, and what it was unsure of to check, a lesson by its row', () {
    final d = VoiceDraft.fromJson(draftJson());
    expect(d.marks['engagement'], DraftMark.suggested);
    expect(d.marks['overall'], DraftMark.check);
    expect(d.marks['items.n1'], DraftMark.suggested);
    expect(d.marks['items.n3'], DraftMark.check);
    expect(d.marks['notes_internal'], isNull);
    expect(d.answers, {'f1': 2, 'f4': true});
    expect(d.homework, (title: 'سمّع السورة', instructions: null, accepts: 'audio', dueDays: 4));
    expect(d.text('notes_internal'), isNull);
    expect(VoiceDraft.fromJson({...draftJson(), 'filled': <String>[]}).homework, isNull);
  });

  testWidgets('a teacher\'s voice note fills the empty fields, marked suggested, and the report goes out with them', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/reports/r1', {
        'data': {
          ...report('r1'),
          'items': [item('n1', outcome: 'practiced')],
          'lesson_options': [
            {'id': 'n1', 'label': 'Level 1 › Lesson n1'},
            {'id': 'n3', 'label': 'Level 1 › Unit 2 › Long vowels'},
          ],
          'homework': null,
          'roadmap': null,
          'template': template(),
          'answers': <Object>[],
          'assist': {'voice_draft': true},
        },
      })
      // As the API answers it: the teacher's own words for the family were there, so it filled the rest only.
      ..on('POST', 'teacher/reports/r1/draft', {
        'data': {
          ...draftJson(),
          'report': {...draftJson()['report']! as Map<String, Object?>, 'notes_for_student': 'كلامي أنا'},
          'filled': [
            for (final f in draftJson()['filled']! as List<String>)
              if (f != 'notes_for_student') f,
          ],
        },
      })
      ..on('PUT', 'teacher/reports/r1', {'data': report('r1', status: 'submitted')});
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 2400));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 20);

    await tester.tap(find.byKey(const Key('today-reports')));
    await frames(tester, 10);
    await tester.tap(find.text('يوسف خالد'));
    await frames(tester, 10);
    expect(find.text('مسودة من رسالة صوتية'), findsOneWidget);
    expect(find.text('سجّل رسالة'), findsOneWidget);

    // The teacher's own words stay; the note comes in as a recording.
    await tester.enterText(find.widgetWithText(TextField, 'ملاحظات للطالب وولي الأمر'), 'كلامي أنا');
    tester
        .widget<VoiceRecorder>(find.byType(VoiceRecorder))
        .onRecorded(HandInFile(bytes: Uint8List.fromList(List.filled(64, 1)), name: 'recording.wav', mime: 'audio/wav'));
    await frames(tester, 12);

    final asked = app.api.sent('POST', 'teacher/reports/r1/draft').single;
    final form = asked.data as FormData;
    expect(form.files.single.key, 'audio');
    final current = jsonDecode(form.fields.singleWhere((f) => f.key == 'current').value) as Map<String, dynamic>;
    expect(current['notes_for_student'], 'كلامي أنا');
    expect(current['items'], [
      {'node_id': 'n1', 'outcome': 'practiced', 'score': null, 'note': null},
    ]);
    expect(asked.receiveTimeout, const Duration(seconds: 60));

    expect(find.text('Long vowels'), findsWidgets);
    expect(find.text('مقترح'), findsWidgets);
    expect(find.text('راجعه'), findsWidgets);
    expect(find.text('راجع دول: المسودة مكانتش متأكدة'), findsOneWidget);
    expect(find.text('سجّل تاني'), findsOneWidget);
    expect(find.text('المسودة ملت 8 خانات. راجعهم قبل ما تبعت.'), findsOneWidget);

    await tester.scrollUntilVisible(find.text('ابعت التقرير'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('ابعت التقرير'));
    await frames(tester, 12);
    final sent = app.api.sent('PUT', 'teacher/reports/r1').single.data as Map<String, dynamic>;
    expect(sent['engagement'], 5);
    expect(sent['overall'], 4);
    expect(sent['notes_for_student'], 'كلامي أنا');
    expect(sent['answers'], {'f1': 2, 'f4': true});
    expect((sent['items'] as List).map((i) => (i as Map)['node_id']), ['n1', 'n3']);
    expect(sent['homework'], {'title': 'سمّع السورة', 'instructions': '', 'accepts': 'audio', 'due_days': 4});

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
    await tester.binding.setSurfaceSize(null);
  });

  testWidgets('no recorder when the academy has not turned drafts on', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api.on('GET', 'teacher/reports/r1', {
      'data': {...report('r1'), 'lesson_options': <Object>[], 'homework': null, 'roadmap': null, 'template': null, 'answers': <Object>[]},
    });
    await app.session.start();
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 20);
    await tester.tap(find.byKey(const Key('today-reports')));
    await frames(tester, 10);
    await tester.tap(find.text('يوسف خالد'));
    await frames(tester, 10);
    expect(find.text('ملاحظات للطالب وولي الأمر'), findsWidgets);
    expect(find.text('مسودة من رسالة صوتية'), findsNothing);
    expect(find.byType(VoiceRecorder), findsNothing);

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
  });
}
