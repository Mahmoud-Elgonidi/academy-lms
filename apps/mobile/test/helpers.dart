import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:academy_app/app/di/services.dart';
import 'package:academy_app/core/auth/account.dart';
import 'package:academy_app/core/auth/account_store.dart';
import 'package:academy_app/core/auth/session_cubit.dart';
import 'package:academy_app/core/auth/session_holder.dart';
import 'package:academy_app/core/auth/session_repository.dart';
import 'package:academy_app/core/config.dart';
import 'package:academy_app/core/network/api_client.dart';
import 'package:academy_app/core/prefs/app_prefs.dart';
import 'package:academy_app/core/push/push.dart';
import 'package:academy_app/shared/motion/juice.dart';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared_preferences_platform_interface/in_memory_shared_preferences_async.dart';
import 'package:shared_preferences_platform_interface/shared_preferences_async_platform_interface.dart';

typedef Reply = (int, Object?);

/// The API for tests: answers by method and path ("GET family/lessons",
/// "*" matches one segment), records every request, and can drop the
/// connection.
class FakeApi implements HttpClientAdapter {
  final _routes = <(String, RegExp, Reply Function(RequestOptions))>[];
  final requests = <RequestOptions>[];

  void on(String method, String path, Object? json, {int status = 200}) => onWith(method, path, (_) => (status, json));

  void onWith(String method, String path, Reply Function(RequestOptions r) reply) {
    final pattern = RegExp('^${path.split('/').map((s) => s == '*' ? '[^/]+' : RegExp.escape(s)).join('/')}\$');
    _routes.insert(0, (method, pattern, reply));
  }

  List<RequestOptions> sent(String method, String path) => requests.where((r) => r.method == method && r.path == path).toList();

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
    for (final (method, pattern, reply) in _routes) {
      if (method == options.method && pattern.hasMatch(options.path)) {
        final (status, json) = reply(options);
        return ResponseBody.fromString(
          json == null ? '' : jsonEncode(json),
          status,
          headers: {
            Headers.contentTypeHeader: ['application/json'],
          },
        );
      }
    }
    return ResponseBody.fromString(
      jsonEncode({'type': 'not-found', 'title': 'غير موجود.', 'status': 404}),
      404,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

/// An adapter that loses the network for the first [failures] calls.
class Flaky implements HttpClientAdapter {
  Flaky(this.inner, {this.failures = 1});

  final HttpClientAdapter inner;
  int failures;
  final seen = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) {
    seen.add(options);
    if (failures-- > 0) throw const SocketLikeError();
    return inner.fetch(options, requestStream, cancelFuture);
  }

  @override
  void close({bool force = false}) {}
}

class SocketLikeError implements Exception {
  const SocketLikeError();
}

/// Push that is up, with a token the test can rotate.
class FakePush implements PushTokens {
  FakePush(this.token);

  @override
  String? token;

  @override
  String get platform => 'android';

  final _refreshed = StreamController<String>.broadcast();

  @override
  Stream<String> get refreshed => _refreshed.stream;

  void rotate(String next) => _refreshed.add(token = next);
}

/// A whole app wired to a [FakeApi], with secrets and preferences in memory.
class TestApp {
  TestApp._(this.api, this.secrets, this.holder, this.client, this.session, this.services);

  static Future<TestApp> create({List<Account> accounts = const [], String? current, String? appVersion, PushTokens? push}) async {
    SharedPreferencesAsyncPlatform.instance = InMemorySharedPreferencesAsync.empty();
    // No audio or haptics plugins in widget tests.
    Juice.enabled = false;
    final api = FakeApi();
    final dio = Dio()..httpClientAdapter = api;
    final secrets = MemorySecrets();
    final store = AccountStore(secrets);
    if (accounts.isNotEmpty) await store.save(accounts, current ?? accounts.first.id);
    final holder = SessionHolder();
    final client = ApiClient(
      config: const AppConfig(apiBaseUrl: 'http://api.test/api/v1'),
      context: holder,
      dio: dio,
    );
    final session = SessionCubit(
      holder: holder,
      store: store,
      prefs: AppPrefs(SharedPreferencesAsync()),
      repository: SessionRepository(client),
      deviceTimezone: 'Asia/Riyadh',
      deviceLocale: 'ar',
      appVersion: appVersion,
      push: push,
    );
    return TestApp._(api, secrets, holder, client, session, Services(client));
  }

  final FakeApi api;
  final MemorySecrets secrets;
  final SessionHolder holder;
  final ApiClient client;
  final SessionCubit session;
  final Services services;
}

Map<String, Object?> time(String utc, String local, {String zone = 'Asia/Riyadh', String label = ''}) => {
  'utc': utc,
  'local': local,
  'timezone': zone,
  'label': label,
};

const guardian = Account(
  academySlug: 'demo',
  academyNameAr: 'أكاديمية النور',
  academyNameEn: 'Noor Academy',
  userId: 'u-guardian',
  userName: 'رامي فؤاد',
  roles: ['guardian'],
  token: 'token-guardian',
);

Map<String, Object?> branding() => {
  'data': {
    'name': {'ar': 'أكاديمية النور', 'en': 'Noor Academy'},
    'tokens': {
      'primary': '#1D5FB8',
      'attention': '#B7791F',
      'danger': '#B42318',
      'info': '#1D5FB8',
      'neutral': 'stone',
      'heading_font': 'Readex Pro',
      'body_font': 'IBM Plex Sans Arabic',
      'radius': 'soft',
    },
    'logo_url': null,
  },
};

Map<String, Object?> me({bool mismatch = false}) => {
  'data': {
    'id': 'u-guardian',
    'name': 'رامي فؤاد',
    'timezone': 'Asia/Riyadh',
    'display_timezone': 'Asia/Riyadh',
    'timezone_mismatch': mismatch,
  },
};

Map<String, Object?> lesson(String id, {bool canCancel = true, String status = 'scheduled'}) => {
  'id': id,
  'starts_at': time('2099-10-11T16:00:00Z', '2099-10-11T19:00:00+03:00'),
  'ends_at': time('2099-10-11T16:45:00Z', '2099-10-11T19:45:00+03:00'),
  'minutes': 45,
  'subject': 'لغة عربية',
  'with': 'أ. منى إبراهيم',
  'status': status,
  'status_label': 'محجوزة',
  'type': 'regular',
  'type_label': null,
  'unfunded': false,
  'join': {'open': false, 'opens_at': null},
  'substitute_for': null,
  'can_cancel': canCancel,
  'can_refuse_substitute': false,
  'cancelled_because': null,
};

/// The family's home and tabs, answered with one child and one lesson.
void familyRoutes(FakeApi api) {
  api
    ..on('GET', 'branding', branding())
    ..on('GET', 'me', me())
    ..on('GET', 'family/learners', {
      'data': [
        {'id': 'c1', 'name': 'سلمى رامي', 'is_self': false, 'stage': 'classic'},
      ],
      'viewer_stage': 'classic',
    })
    ..on('GET', 'family/lessons', {
      'learner': {'id': 'c1', 'name': 'سلمى رامي'},
      'data': [lesson('l1')],
      'notices': <String>[],
    })
    ..on('GET', 'family/balance', {'data': <Object>[]})
    ..on('GET', 'family/invoices', {'owed': null, 'data': <Object>[]})
    ..on('GET', 'learning/students', {'data': <Object>[]})
    ..on('GET', 'learning/homework', {'data': <Object>[]})
    ..on('GET', 'me/sessions', {'data': <Object>[]});
}

const teacher = Account(
  academySlug: 'demo',
  academyNameAr: 'أكاديمية النور',
  academyNameEn: 'Noor Academy',
  userId: 'u-teacher',
  userName: 'أ. نور الهدى',
  roles: ['teacher'],
  token: 'token-teacher',
);

/// The teacher's today: no lessons left, one report to write, one homework to mark.
void teacherRoutes(FakeApi api) {
  api
    ..on('GET', 'branding', branding())
    ..on('GET', 'me', me())
    ..on('GET', 'me/sessions', {'data': <Object>[]})
    ..on('GET', 'teacher/lessons', {'from': '2099-10-07', 'to': '2099-10-07', 'count': 0, 'minutes': 0, 'data': <Object>[]})
    ..on('GET', 'teacher/reports', {
      'data': [report('r1', status: 'due')],
    })
    ..on('GET', 'teacher/homework', {
      'data': [homeworkJson('h1', status: 'submitted', body: 'قريت السورة 3 مرات')],
    });
}

Map<String, Object?> report(String id, {String status = 'due', String kind = 'lesson'}) => {
  'id': id,
  'session_id': 's-$id',
  'enrollment_id': 'e1',
  'kind': kind,
  'status': status,
  'status_label': status == 'due' ? 'مطلوب' : 'اتسلّم',
  'student': {'id': 'u-kid', 'name': 'يوسف خالد'},
  'teacher': {'id': 'u-teacher', 'name': 'أ. نور الهدى'},
  'lesson_at': time('2099-10-06T16:00:00Z', '2099-10-06T19:00:00+03:00', label: 'الثلاثاء 6 أكتوبر · 19:00'),
  'due_at': time('2099-10-07T04:00:00Z', '2099-10-07T07:00:00+03:00', label: 'الأربعاء 7 أكتوبر · 07:00'),
  'submitted_at': null,
  'late': false,
  'engagement': null,
  'overall': null,
  'notes_for_student': null,
  'absence_note': null,
  'items': <Object>[],
  'notes_internal': null,
  'review_note': null,
  'reviewed_at': null,
};

Map<String, Object?> homeworkJson(
  String id, {
  String status = 'assigned',
  String accepts = 'any',
  String? body,
  Map<String, Object?>? file,
}) => {
  'id': id,
  'report_id': 'r0',
  'enrollment_id': 'e1',
  'student': {'id': 'u-kid', 'name': 'يوسف خالد'},
  'teacher': {'id': 'u-teacher', 'name': 'أ. نور الهدى'},
  'title': 'سمّع سورة النصر',
  'instructions': null,
  'accepts': accepts,
  'status': status,
  'status_label': status == 'submitted' ? 'اتسلّم' : 'مطلوب',
  'due_at': time('2099-10-09T16:00:00Z', '2099-10-09T19:00:00+03:00'),
  'grade': null,
  'feedback': null,
  'reviewed_at': null,
  'submissions': [
    if (body != null || file != null)
      {'id': 'sub-$id', 'body': body, 'file': file, 'submitted_at': time('2099-10-08T10:00:00Z', '2099-10-08T13:00:00+03:00')},
  ],
};
