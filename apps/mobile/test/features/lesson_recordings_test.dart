import 'package:academy_app/app/app.dart';
import 'package:academy_app/features/classroom/domain/join_ticket.dart';
import 'package:academy_app/features/recordings/domain/recordings.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

Map<String, Object?> _at(String label) => {
  'utc': '2099-10-06T15:00:00Z',
  'local': '2099-10-06T18:00:00+03:00',
  'timezone': 'Africa/Cairo',
  'label': label,
};

Map<String, Object?> _consent(String id, String name, {bool self = false, bool agreed = false, bool canChange = true, String? decides}) => {
  'person': {'id': id, 'name': name},
  'self': self,
  'state': agreed ? 'agreed' : 'none',
  'granted_at': agreed ? _at('5 أكتوبر') : null,
  'by': agreed ? 'وافقت يوم 5 أكتوبر 2099' : null,
  'can_change': canChange,
  'decides': decides,
  'text': {
    'version': 1,
    'title': 'الموافقة على تسجيل الحصص',
    'intro': 'الأكاديمية ممكن تسجل حصص $name.',
    'sections': [
      {'title': 'إيه اللي بيتسجل', 'body': 'الصوت والصورة.'},
    ],
    'agree': 'موافق على تسجيل الحصص',
  },
};

Map<String, Object?> _recording(String id, {String media = 'audio'}) => {
  'id': id,
  'at': _at('الثلاثاء 6 أكتوبر · 18:00'),
  'teacher': 'أ. نور الهدى',
  'student': 'سلمى رامي',
  'media': media,
  'duration_sec': 1500,
  'expires_at': _at('5 نوفمبر'),
};

Future<void> _open(WidgetTester tester, TestApp app, String location) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 1600));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 20);
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go(location);
  await frames(tester, 20);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

void main() {
  test('reads consents, recordings, and what a lesson screen shows about recording', () {
    final consents = RecordingConsents.fromJson({
      'recording': 'manual',
      'people': [_consent('c1', 'سلمى', agreed: true), _consent('c2', 'يوسف', canChange: false, decides: 'يوسف بيوافق بنفسه.')],
    });
    expect(consents.off, isFalse);
    expect(consents.people.first.agreed, isTrue);
    expect(consents.people.last.decides, 'يوسف بيوافق بنفسه.');

    final shelf = RecordingShelf.fromJson({
      'shared': true,
      'data': [_recording('r1'), _recording('r2', media: 'video')],
    });
    expect(shelf.items.map((r) => r.length), ['25:00', '25:00']);
    expect(shelf.items.first.audio, isTrue);
    expect(LessonRecording.fromJson({..._recording('x', media: 'video'), 'duration_sec': 3725}).length, '1:02:05');

    final ticket = JoinTicket.fromJson(
      {'provider': 'livekit', 'url': 'wss://lk', 'token': 't'},
      recording: {
        'mode': 'manual',
        'controls': true,
        'running': false,
        'missing': ['يوسف'],
      },
    );
    expect(ticket.recording.controls, isTrue);
    expect(ticket.recording.missing, ['يوسف']);
    expect(JoinTicket.fromJson({'provider': 'external', 'url': 'https://meet'}).recording.mode, 'off');
  });

  testWidgets('a guardian agrees to their child\'s lessons being recorded and opens a recording', (tester) async {
    final app = await TestApp.create(accounts: [guardian]);
    familyRoutes(app.api);
    var agreed = false;
    app.api
      ..onWith(
        'GET',
        'recordings/consents',
        (_) => (
          200,
          {
            'recording': 'manual',
            'people': [_consent('c1', 'سلمى رامي', agreed: agreed)],
          },
        ),
      )
      ..on('GET', 'family/recordings', {
        'shared': true,
        'data': [_recording('r1')],
      })
      ..onWith('POST', 'recordings/consents/c1', (_) {
        agreed = true;
        return (200, {'state': 'agreed'});
      })
      ..on('POST', 'recordings/r1/watch', {
        'url': 'https://demo.lms.test/recordings/r1/media?expires=1&signature=x',
        'media': 'audio',
        'expires_at': _at('بعد شوية'),
      });
    await _open(tester, app, '/family/me/recordings');

    expect(find.text('تسجيلات الحصص'), findsWidgets);
    expect(find.text('موافق على تسجيل الحصص'), findsOneWidget);
    final agree = find.widgetWithText(FilledButton, 'موافق');
    expect(tester.widget<FilledButton>(agree).onPressed, isNull);
    await tester.tap(find.byKey(const Key('agree-recording-c1')));
    await frames(tester, 4);
    await tester.tap(agree);
    await frames(tester, 12);
    expect(app.api.sent('POST', 'recordings/consents/c1').single.data, {'agree': true, 'version': 1});
    expect(find.text('وافقت يوم 5 أكتوبر 2099'), findsOneWidget);

    expect(find.text('الثلاثاء 6 أكتوبر · 18:00'), findsOneWidget);
    await tester.tap(find.byKey(const Key('watch-r1')));
    await frames(tester, 6);
    expect(app.api.sent('POST', 'recordings/r1/watch'), hasLength(1));

    await _close(tester);
  });

  testWidgets('a teacher sees their own consent and lessons\' recordings in More', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'recordings/consents', {
        'recording': 'auto',
        'people': [_consent('u-teacher', 'أ. نور الهدى', self: true, agreed: true)],
      })
      ..on('GET', 'teacher/recordings', {
        'shared': true,
        'data': [_recording('r1', media: 'video')],
      })
      ..on('POST', 'recordings/consents/u-teacher/withdraw', {'state': 'none'});
    await _open(tester, app, '/teach/more/recordings');

    expect(find.text('تسجيلات حصصي'), findsWidgets);
    expect(find.textContaining('سلمى رامي'), findsOneWidget);
    await tester.tap(find.text('اسحب الموافقة'));
    await frames(tester, 6);
    await tester.tap(find.widgetWithText(FilledButton, 'اسحب الموافقة'));
    await frames(tester, 10);
    expect(app.api.sent('POST', 'recordings/consents/u-teacher/withdraw'), hasLength(1));

    await _close(tester);
  });
}
