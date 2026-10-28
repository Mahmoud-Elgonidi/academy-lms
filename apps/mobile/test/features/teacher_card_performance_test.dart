import 'dart:convert';
import 'dart:typed_data';

import 'package:academy_app/app/app.dart';
import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../helpers.dart';
import 'sign_in_flow_test.dart' show frames;

/// A picture picked on the phone, in memory.
final class _Picked extends PlatformFile {
  _Picked(this.name, this.bytes);

  @override
  final String name;
  final Uint8List bytes;

  @override
  Uri get uri => Uri.parse('file:///picked/$name');

  // Its type (XFile) is cross_file's, which the app does not use: the card page reads through readAsBytes.
  @override
  // ignore: always_declare_return_types
  get xFile => throw UnimplementedError('read through readAsBytes');

  @override
  int? lengthSync() => bytes.length;

  @override
  Future<int?> length() async => bytes.length;

  @override
  Future<Uint8List> readAsBytes() async => bytes;

  @override
  Stream<Uint8List> readAsByteStream() => Stream.value(bytes);
}

/// The phone's file picker: hands back [next], and remembers what it was asked for.
class _Picker extends FilePickerPlatform {
  PlatformFile? next;
  List<String>? asked;

  @override
  Future<PlatformFile?> pickFile({
    String? dialogTitle,
    String? initialDirectory,
    FileType type = FileType.any,
    List<String>? allowedExtensions,
    Function(FilePickerStatus)? onFileLoading,
    int compressionQuality = 0,
    AndroidOptions androidOptions = const AndroidOptions(),
    DarwinOptions darwinOptions = const DarwinOptions(),
    WindowsOptions windowsOptions = const WindowsOptions(),
    LinuxOptions linuxOptions = const LinuxOptions(),
    WebOptions webOptions = const WebOptions(),
  }) async {
    asked = allowedExtensions;
    return next;
  }
}

/// A one-pixel PNG.
final _png = base64Decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

Map<String, Object?> _card({
  String name = 'أ. سارة',
  String? bio = 'بحب أدرّس القرآن للصغيرين.',
  bool shown = false,
  String state = 'off',
  String label = 'كارتك مقفول ومابيظهرش في الصفحة.',
}) => {
  'data': {
    'display_name': name,
    'bio': bio,
    'photo_url': null,
    'shown': shown,
    'state': state,
    'state_label': label,
    'subjects': [
      {'name': 'قرآن', 'level_from': 'جزء عم', 'level_to': null},
      {'name': 'عربي', 'level_from': null, 'level_to': null},
    ],
  },
};

Map<String, Object?> _performance() => {
  'days': 90,
  'kpis': [
    {
      'key': 'attendance',
      'label': 'الحضور في الميعاد',
      'value': '96%',
      'target': 'الهدف 95% على الأقل',
      'n': 40,
      'state': 'good',
      'hint': 'من 40 حصة',
      'group': 'commitment',
    },
    {
      'key': 'report_on_time',
      'label': 'التقارير في وقتها',
      'value': '70%',
      'target': 'الهدف 90% على الأقل',
      'n': 30,
      'state': 'bad',
      'hint': null,
      'group': 'quality',
    },
  ],
  'events': [
    {
      'session': 's1',
      'kind': 'late_join',
      'text': 'دخلت متأخر 7 دقايق',
      'lesson': 'قرآن · السبت 10 أكتوبر · 5:00 م',
      'comment': null,
      'excluded': null,
      'note': null,
    },
    {
      'session': 's2',
      'kind': 'low_rating',
      'text': 'تقييم نجمتين',
      'lesson': 'عربي · الأحد 11 أكتوبر · 6:00 م',
      'comment': null,
      'excluded': 'مش محسوبة: مشكلة في المنصة (الإدارة)',
      'note': 'النت وقع عند الكل',
    },
  ],
  'ratings': {
    'avg': '4.5',
    'count': 2,
    'list': [
      {
        'stars': 5,
        'tags': ['شرح واضح'],
        'comment': 'حصة حلوة',
        'who': null,
        'lesson': 'قرآن · 10 أكتوبر',
      },
      {'stars': 4, 'tags': <String>[], 'comment': null, 'who': null, 'lesson': 'عربي · 11 أكتوبر'},
    ],
  },
};

Future<void> _open(WidgetTester tester, TestApp app, String row) async {
  await app.session.start();
  await tester.binding.setSurfaceSize(const Size(420, 2400));
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 15);
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/teach/more');
  await frames(tester, 12);
  await tester.ensureVisible(find.byKey(Key(row)));
  await tester.tap(find.byKey(Key(row)));
  await frames(tester, 12);
}

Future<void> _save(WidgetTester tester) async {
  await tester.ensureVisible(find.byKey(const Key('card-save')));
  await tester.tap(find.byKey(const Key('card-save')));
  await frames(tester, 10);
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await frames(tester, 2);
  await tester.binding.setSurfaceSize(null);
}

/// What a multipart request carried: its fields, and its files by field name.
({Map<String, String> fields, Map<String, MultipartFile> files}) _parts(RequestOptions r) {
  final form = r.data as FormData;
  return (fields: {for (final f in form.fields) f.key: f.value}, files: {for (final f in form.files) f.key: f.value});
}

void main() {
  late _Picker picker;

  setUp(() {
    picker = _Picker();
    FilePickerPlatform.instance = picker;
  });

  testWidgets('my card: where it stands, a preview with the subjects, and saving it with a photo sends the portal\'s form', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/card', _card())
      ..on(
        'POST',
        'teacher/card',
        _card(name: 'أ. سارة علي', bio: 'خبرة عشر سنين.', shown: true, state: 'on', label: 'كارتك ظاهر في صفحة الأكاديمية.'),
      );
    await _open(tester, app, 'open-card');

    expect(find.text('كارتي في صفحة الأكاديمية'), findsWidgets);
    expect(find.text('كارتك مقفول ومابيظهرش في الصفحة.'), findsOneWidget);
    // The preview: the name, each subject with its levels (the start or the end when open), and where they come from.
    final preview = find.byKey(const Key('card-preview'));
    expect(find.descendant(of: preview, matching: find.text('أ. سارة')), findsOneWidget);
    expect(find.descendant(of: preview, matching: find.text('جزء عم ← للآخر')), findsOneWidget);
    expect(find.descendant(of: preview, matching: find.text('عربي')), findsOneWidget);
    expect(find.text('المواد من ملفك في الأكاديمية'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('card-name')), ' أ. سارة علي ');
    await tester.enterText(find.byKey(const Key('card-bio')), 'خبرة عشر سنين.');
    await frames(tester, 2);
    expect(find.descendant(of: preview, matching: find.text('أ. سارة علي')), findsOneWidget);
    await tester.tap(find.byKey(const Key('card-shown')));
    await frames(tester, 2);

    picker.next = _Picked('me.webp', _png);
    await tester.ensureVisible(find.byKey(const Key('card-photo')));
    await tester.tap(find.byKey(const Key('card-photo')));
    await frames(tester, 4);
    expect(picker.asked, containsAll(['png', 'jpg', 'jpeg', 'webp']));
    expect(find.text('me.webp'), findsOneWidget);

    await _save(tester);
    final sent = _parts(app.api.sent('POST', 'teacher/card').single);
    expect(sent.fields, {'display_name': 'أ. سارة علي', 'bio': 'خبرة عشر سنين.', 'shown': '1'});
    expect(sent.files.keys, ['photo']);
    expect(sent.files['photo']!.filename, 'me.webp');
    expect(sent.files['photo']!.contentType.toString(), 'image/webp');
    expect(find.byKey(const Key('card-saved')), findsOneWidget);
    expect(find.text('كارتك ظاهر في صفحة الأكاديمية.'), findsOneWidget);
    await _close(tester);
  });

  testWidgets('my card: hidden by the academy is said without why, and the API\'s words come back on the field', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api
      ..on('GET', 'teacher/card', _card(shown: true, state: 'hidden', label: 'الأكاديمية شالت كارتك من الصفحة.'))
      ..on('POST', 'teacher/card', {
        'type': 'validation',
        'title': 'البيانات مش مظبوطة.',
        'status': 422,
        'errors': {
          'bio': ['الكلام فيه رقم أو لينك.'],
        },
      }, status: 422);
    await _open(tester, app, 'open-card');

    expect(find.text('الأكاديمية شالت كارتك من الصفحة.'), findsOneWidget);
    expect(find.byKey(const Key('card-remove-photo')), findsNothing); // no photo yet

    await tester.enterText(find.byKey(const Key('card-bio')), 'كلمني على 0100000000');
    await _save(tester);
    expect(_parts(app.api.sent('POST', 'teacher/card').single).fields['shown'], '1');
    expect(find.text('الكلام فيه رقم أو لينك.'), findsOneWidget);
    expect(find.byKey(const Key('card-saved')), findsNothing);

    // Something else wrong is said under the form.
    app.api.on('POST', 'teacher/card', {'type': 'server', 'title': 'حصلت مشكلة عندنا.', 'status': 500}, status: 500);
    await _save(tester);
    expect(find.byKey(const Key('card-error')), findsOneWidget);
    await _close(tester);
  });

  testWidgets('my card: a photo over 1 MB is turned down on the phone and nothing is sent with it', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api.on('GET', 'teacher/card', _card());
    await _open(tester, app, 'open-card');

    picker.next = _Picked('big.jpg', Uint8List(1024 * 1024 + 1));
    await tester.ensureVisible(find.byKey(const Key('card-photo')));
    await tester.tap(find.byKey(const Key('card-photo')));
    await frames(tester, 4);
    expect(find.text('الصورة أكبر من 1 ميجا.'), findsOneWidget);
    expect(find.text('big.jpg'), findsNothing);
    expect(app.api.sent('POST', 'teacher/card'), isEmpty);
    await _close(tester);
  });

  testWidgets('my performance: the numbers by group, what counted, and ratings without names', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api.on('GET', 'teacher/performance', _performance());
    await _open(tester, app, 'open-performance');

    expect(find.text('أدائي'), findsWidgets);
    expect(find.text('آخر 90 يوم · الحصص اللي سببها المنصة مابتتحسبش'), findsOneWidget);
    expect(find.text('الالتزام'), findsOneWidget);
    expect(find.text('الجودة'), findsOneWidget);
    expect(find.text('الاحترافية'), findsNothing); // a group with no numbers is left out
    expect(find.descendant(of: find.byKey(const Key('kpi-attendance')), matching: find.text('96%')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('kpi-attendance')), matching: find.text('تمام')), findsOneWidget);
    expect(
      find.descendant(of: find.byKey(const Key('kpi-attendance')), matching: find.text('الهدف 95% على الأقل · من 40 حصة')),
      findsOneWidget,
    );
    expect(find.descendant(of: find.byKey(const Key('kpi-report_on_time')), matching: find.text('محتاج تحسين')), findsOneWidget);

    expect(find.text('دخلت متأخر 7 دقايق'), findsOneWidget);
    expect(find.text('قرآن · السبت 10 أكتوبر · 5:00 م'), findsOneWidget); // as the API wrote it
    expect(find.text('مش محسوبة'), findsOneWidget);
    expect(find.text('النت وقع عند الكل'), findsOneWidget);

    expect(find.text('4.5 من 5 · تقييمين'), findsOneWidget);
    expect(find.text('شرح واضح'), findsOneWidget);
    expect(find.text('“حصة حلوة”'), findsOneWidget);
    final semantics = tester.ensureSemantics();
    await frames(tester, 2);
    expect(find.bySemanticsLabel(RegExp('^5 من 5')), findsOneWidget);
    expect(find.bySemanticsLabel(RegExp('^4 من 5')), findsOneWidget);
    semantics.dispose();
    await _close(tester);
  });

  testWidgets('my performance: nothing yet, and an API that refuses is said', (tester) async {
    final app = await TestApp.create(accounts: [teacher]);
    teacherRoutes(app.api);
    app.api.on('GET', 'teacher/performance', {
      'days': 90,
      'kpis': <Object>[],
      'events': <Object>[],
      'ratings': {'avg': null, 'count': 0, 'list': <Object>[]},
    });
    await _open(tester, app, 'open-performance');
    expect(find.text('مفيش حاجة اتحسبت عليك. استمر!'), findsOneWidget);
    expect(find.text('لسه مفيش تقييمات.'), findsOneWidget);
    await _close(tester);

    final refused = await TestApp.create(accounts: [teacher]);
    teacherRoutes(refused.api);
    refused.api.on('GET', 'teacher/performance', {'type': 'forbidden', 'title': 'مش مسموح.', 'status': 403}, status: 403);
    await _open(tester, refused, 'open-performance');
    expect(find.text('مش مسموح تشوف ده'), findsOneWidget);
    await _close(tester);
  });
}
