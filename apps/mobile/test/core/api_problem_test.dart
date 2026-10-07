import 'package:academy_app/core/network/api_problem.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

DioException _with(int status, Object? body, {Map<String, List<String>> headers = const {}}) {
  final request = RequestOptions(path: 'x');
  return DioException(
    requestOptions: request,
    response: Response<Object?>(requestOptions: request, statusCode: status, data: body, headers: Headers.fromMap(headers)),
  );
}

void main() {
  test('reads problem details with field errors, in the API\'s words', () {
    final p = ApiProblem.fromDio(
      _with(422, {
        'type': 'validation',
        'title': 'البيانات المرسلة غير صحيحة.',
        'status': 422,
        'errors': {
          'code': ['الكود غلط.'],
        },
      }),
    );
    expect(p.isValidation, isTrue);
    expect(p.field('code'), 'الكود غلط.');
    expect(p.message, 'الكود غلط.');
  });

  test('falls back to the detail, then the title', () {
    expect(ApiProblem.fromDio(_with(403, {'type': 'forbidden', 'title': 'ممنوع.', 'detail': 'مش دلوقتي.'})).message, 'مش دلوقتي.');
    expect(ApiProblem.fromDio(_with(404, {'type': 'not-found', 'title': 'غير موجود.'})).message, 'غير موجود.');
  });

  test('no response is offline; Retry-After is read on 429', () {
    final offline = ApiProblem.fromDio(DioException(requestOptions: RequestOptions(path: 'x')));
    expect(offline.isOffline, isTrue);
    final busy = ApiProblem.fromDio(
      _with(
        429,
        {'type': 'too-many-requests', 'title': 'استنى شوية.'},
        headers: {
          'retry-after': ['42'],
        },
      ),
    );
    expect(busy.retryAfter, 42);
  });

  test('a body that is not problem details keeps the status', () {
    final p = ApiProblem.fromDio(_with(502, 'Bad gateway'));
    expect(p.status, 502);
    expect(p.message, isNull);
  });
}
