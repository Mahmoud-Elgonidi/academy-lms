import 'package:academy_app/core/auth/session_holder.dart';
import 'package:academy_app/core/config.dart';
import 'package:academy_app/core/network/api_client.dart';
import 'package:academy_app/core/network/api_problem.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

void main() {
  late FakeApi api;
  late SessionHolder holder;
  late Dio dio;
  late ApiClient client;

  setUp(() {
    api = FakeApi();
    holder = SessionHolder()
      ..account = guardian
      ..locale = 'ar'
      ..timezone = 'Asia/Riyadh';
    dio = Dio()..httpClientAdapter = api;
    client = ApiClient(
      config: const AppConfig(apiBaseUrl: 'http://api.test/api/v1'),
      context: holder,
      dio: dio,
    );
  });

  test('sends the account, language and clock with every request', () async {
    api.on('GET', 'family/lessons', {'data': <Object>[]});
    await client.get('family/lessons', query: {'learner_id': 'c1'});
    final r = api.requests.single;
    expect(r.uri.toString(), 'http://api.test/api/v1/family/lessons?learner_id=c1');
    expect(r.headers['Authorization'], 'Bearer token-guardian');
    expect(r.headers['X-Academy'], 'demo');
    expect(r.headers['Accept-Language'], 'ar');
    expect(r.headers['X-Timezone'], 'Asia/Riyadh');
  });

  test('anonymous calls name their academy and carry no token', () async {
    api.on('POST', 'auth/login', {'token': 't'});
    await client.post('auth/login', anonymous: true, academy: 'rawda', body: {'login': 'a'});
    final r = api.requests.single;
    expect(r.headers['Authorization'], isNull);
    expect(r.headers['X-Academy'], 'rawda');
  });

  test('an idempotent write keeps its key across retries after a dropped connection', () async {
    api.on('POST', 'lessons/l1/cancel', {'data': <String, Object>{}});
    final flaky = Flaky(api, failures: 2);
    dio.httpClientAdapter = flaky;
    await client.post('lessons/l1/cancel', idempotent: true, body: {'reason': 'travel'});
    expect(flaky.seen, hasLength(3));
    final keys = flaky.seen.map((r) => r.headers['Idempotency-Key']).toSet();
    expect(keys, hasLength(1));
    expect(keys.single, isNotNull);
  });

  test('a form with a file is sent again whole after a dropped connection', () async {
    api.on('POST', 'family/invoices/i1/proofs', {'data': <String, Object>{}});
    final flaky = Flaky(api, failures: 1);
    dio.httpClientAdapter = flaky;
    final form = FormData.fromMap({
      'amount': '400',
      'file': MultipartFile.fromBytes([1, 2, 3], filename: 'receipt.jpg'),
    });
    await client.post('family/invoices/i1/proofs', idempotent: true, body: form);
    expect(flaky.seen, hasLength(2));
    final again = flaky.seen.last.data as FormData;
    expect(again, isNot(same(form)));
    expect(again.fields.single.value, '400');
    expect(again.files.single.value.filename, 'receipt.jpg');
  });

  test('a plain write is not retried, and each call gets a new key', () async {
    final flaky = Flaky(api);
    dio.httpClientAdapter = flaky;
    await expectLater(client.post('lessons/l1/cancel', body: {}), throwsA(isA<ApiProblem>().having((p) => p.isOffline, 'offline', isTrue)));
    expect(flaky.seen, hasLength(1));
    expect(flaky.seen.single.headers['Idempotency-Key'], isNull);
  });

  test('a refused token tells the session once', () async {
    var told = 0;
    holder.expired = () => told++;
    api.on('GET', 'me', {'type': 'unauthenticated', 'title': 'سجّل دخول.', 'status': 401}, status: 401);
    await expectLater(client.get('me'), throwsA(isA<ApiProblem>()));
    expect(told, 1);
  });
}
