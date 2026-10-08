import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/roadmap/domain/report_answers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// Pages memorised (a number, required), tajweed (a choice), revision (yes or no), a note only the academy reads.
Map<String, Object?> template() => {
  'id': 't1',
  'version': 2,
  'fields': [
    {
      'key': 'f1',
      'label': 'الصفحات اللي اتحفظت',
      'type': 'number',
      'required': true,
      'family': true,
      'options': <Object>[],
      'min': 0,
      'max': 20,
      'unit': 'صفحة',
    },
    {
      'key': 'f2',
      'label': 'التجويد',
      'type': 'choice',
      'required': false,
      'family': true,
      'options': [
        {'key': 'o1', 'label': 'ممتاز'},
        {'key': 'o2', 'label': 'كويس'},
      ],
      'min': null,
      'max': null,
      'unit': null,
    },
    {
      'key': 'f4',
      'label': 'راجع اللي فات',
      'type': 'yes_no',
      'required': false,
      'family': true,
      'options': <Object>[],
      'min': null,
      'max': null,
      'unit': null,
    },
    {
      'key': 'f3',
      'label': 'ملاحظة للمشرف',
      'type': 'text',
      'required': false,
      'family': false,
      'options': <Object>[],
      'min': null,
      'max': null,
      'unit': null,
    },
  ],
};

void main() {
  test('reads the questions and the answers, and sends only what was answered', () {
    final t = ReportTemplate.fromJson(template())!;
    expect(t.questions.map((q) => q.type), ['number', 'choice', 'yes_no', 'text']);
    expect(t.questions.last.family, isFalse);
    expect(t.questions.first.unit, 'صفحة');
    expect(t.missing({'f1': ' '}).map((q) => q.key), ['f1']);
    expect(t.payload({'f1': '2.5', 'f2': 'o1', 'f4': false, 'f3': '', 'f9': 'x'}), {'f1': 2.5, 'f2': 'o1', 'f4': false});
    expect(ReportTemplate.fromJson(null), isNull);

    final answers = ReportAnswer.listOf([
      {'key': 'f5', 'label': 'التركيز', 'type': 'rating', 'value': 4, 'text': '4/5', 'family': true, 'unit': null, 'max': 5},
      {'key': 'f1', 'label': 'الصفحات', 'type': 'number', 'value': 2, 'text': '2 صفحة', 'family': true, 'unit': 'صفحة', 'max': null},
    ]);
    expect(answers.first.stars, 4);
    expect(answers.last.stars, isNull);
    expect(answers.last.text, '2 صفحة');
  });

  testWidgets('a teacher answers the academy\'s questions in the report', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/reports/r1', {
        'data': {
          ...report('r1'),
          'lesson_options': <Object>[],
          'homework': null,
          'roadmap': null,
          'template': template(),
          'answers': <Object>[],
        },
      })
      ..on('PUT', 'teacher/reports/r1', {'data': report('r1', status: 'submitted')});
    await app.session.start();
    await tester.binding.setSurfaceSize(const Size(420, 2000));
    await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
    await frames(tester, 20);

    await tester.tap(find.text('تقرير مستنيك'));
    await frames(tester, 10);
    await tester.tap(find.text('يوسف خالد'));
    await frames(tester, 10);
    expect(find.text('أسئلة الأكاديمية'), findsOneWidget);
    expect(find.text('للأكاديمية بس'), findsOneWidget);

    // Ratings, but the pages memorised are still missing.
    final stars = find.byWidgetPredicate((w) => w.runtimeType.toString() == 'StarPicker');
    await tester.tap(find.descendant(of: stars.at(0), matching: find.byType(InkResponse)).at(4));
    await tester.tap(find.descendant(of: stars.at(1), matching: find.byType(InkResponse)).at(3));
    await frames(tester, 2);
    await tester.scrollUntilVisible(find.text('ابعت التقرير'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('ابعت التقرير'));
    await frames(tester, 3);
    expect(find.text('جاوب على "الصفحات اللي اتحفظت".'), findsOneWidget);
    expect(app.api.sent('PUT', 'teacher/reports/r1'), isEmpty);

    await tester.enterText(find.widgetWithText(TextField, 'الصفحات اللي اتحفظت'), '3');
    await tester.tap(find.text('كويس'));
    await tester.tap(find.text('أيوه'));
    await tester.enterText(find.widgetWithText(TextField, 'ملاحظة للمشرف (اختياري)'), 'محتاج تشجيع');
    await frames(tester, 2);
    await tester.scrollUntilVisible(find.text('ابعت التقرير'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('ابعت التقرير'));
    await frames(tester, 12);

    final sent = app.api.sent('PUT', 'teacher/reports/r1').single.data as Map<String, dynamic>;
    expect(sent['answers'], {'f1': 3, 'f2': 'o2', 'f4': true, 'f3': 'محتاج تشجيع'});

    await tester.pumpWidget(const SizedBox());
    await frames(tester, 2);
    await tester.binding.setSurfaceSize(null);
  });
}
