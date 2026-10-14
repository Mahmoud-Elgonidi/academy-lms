import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/roadmap/domain/learning.dart';
import 'package:academy_app/features/teaching/domain/teaching.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> material(String id, String type, String title, {bool teachers = false}) => {
  'id': id,
  'type': type,
  'title': title,
  'url': 'https://demo.lms.test/materials/$id?expires=1&signature=x',
  'teachers_only': teachers,
  'size': type == 'link' ? null : 1200,
};

Future<void> _open(WidgetTester tester, TestApp app) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 20);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  test('reads the lesson now\'s materials for the family and for the teacher', () {
    final course = Course.fromJson({
      'id': 'e1',
      'subject': 'قرآن',
      'teacher': 'أ. نور',
      'current_materials': [material('m1', 'pdf', 'صفحة الفاتحة'), material('m2', 'link', 'فيديو التلاوة')],
    });
    expect(course.materials.map((m) => m.type), ['pdf', 'link']);
    expect(course.materials.first.url, contains('signature='));

    final student = TeacherStudent.fromJson({
      'enrollment_id': 'e1',
      'student': 'يوسف',
      'subject': 'قرآن',
      'materials': [material('m3', 'pdf', 'دليل المدرس', teachers: true)],
    });
    expect(student.materials.single.teachersOnly, isTrue);
    expect(Course.fromJson({'id': 'e2'}).materials, isEmpty);
  });

  testWidgets('the family sees the lesson now\'s materials under the journey', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    app.api.on('GET', 'learning/students', {
      'data': [
        {
          'id': 'c1',
          'name': 'سلمى رامي',
          'enrollments': [
            {
              'id': 'e1',
              'subject': 'قرآن',
              'teacher': 'أ. نور الهدى',
              'program': 'جزء عم',
              'percent': 40,
              'current': {'id': 'n3', 'title': 'سورة الكوثر', 'path': <String>[]},
              'current_materials': [material('m1', 'pdf', 'صفحة الكوثر'), material('m2', 'audio', 'تلاوة الكوثر')],
              'eta_weeks': 4,
              'homework_open': 0,
            },
          ],
        },
      ],
    });
    await _open(tester, app);

    await tester.tap(find.text('الرحلة').last);
    await frames(tester, 12);
    await tester.ensureVisible(find.byKey(const Key('journey-courses')));
    await tester.tap(find.byKey(const Key('journey-courses')));
    await frames(tester, 12);
    expect(find.text('مواد الدروس والتقارير'), findsOneWidget);
    expect(find.text('مواد الدرس'), findsOneWidget);
    expect(find.text('صفحة الكوثر'), findsOneWidget);
    expect(find.text('تلاوة الكوثر'), findsOneWidget);
    expect(find.byIcon(Icons.picture_as_pdf_outlined), findsOneWidget);

    await _close(tester);
  });
}
