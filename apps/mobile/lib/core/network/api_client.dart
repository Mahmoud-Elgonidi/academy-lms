import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

import '../config.dart';
import 'api_problem.dart';

/// What every request carries, read at the moment it is sent: the signed-in
/// account's token and academy, the language and the device's time zone.
abstract interface class ApiContext {
  String? get token;
  String? get academy;
  String get locale;
  String? get timezone;

  /// The token was refused (expired, or signed out from another device).
  void onUnauthorized();
}

/// The API over HTTP (dio). Every call returns the decoded JSON body or
/// throws an [ApiProblem]. Writes marked [idempotent] carry an
/// `Idempotency-Key`, kept across the retries of the same call, so a
/// cancel or a payment never happens twice on a bad network.
class ApiClient {
  ApiClient({required AppConfig config, required this._context, Dio? dio}) : _dio = dio ?? Dio() {
    _dio.options
      // Paths are relative ("family/lessons"): the base must end with a slash or dio joins "v1family".
      ..baseUrl = config.apiBaseUrl.endsWith('/') ? config.apiBaseUrl : '${config.apiBaseUrl}/'
      ..connectTimeout = const Duration(seconds: 15)
      ..receiveTimeout = const Duration(seconds: 30)
      ..responseType = ResponseType.json;
    _dio.interceptors.add(InterceptorsWrapper(onRequest: _decorate));
  }

  static const _idempotent = 'idempotent';
  static const _academy = 'academy';
  static const _anonymous = 'anonymous';
  static const _token = 'token';
  static const _attempts = 3;

  final ApiContext _context;
  final Dio _dio;
  final _uuid = const Uuid();

  Dio get dio => _dio;

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query, String? academy, bool anonymous = false}) =>
      _send('GET', path, query: query, academy: academy, anonymous: anonymous, retry: true);

  /// [academy] and [token] act as another account on this device (signing
  /// it out while a different one is open).
  Future<Map<String, dynamic>> post(
    String path, {
    Object? body,
    String? academy,
    String? token,
    bool anonymous = false,
    bool idempotent = false,
  }) => _send('POST', path, body: body, academy: academy, token: token, anonymous: anonymous, idempotent: idempotent, retry: idempotent);

  Future<Map<String, dynamic>> put(String path, {Object? body}) => _send('PUT', path, body: body, retry: true);

  /// A file the API streams (a homework recording), as bytes.
  Future<Uint8List> download(String path) async {
    for (var attempt = 1; ; attempt++) {
      try {
        final response = await _dio.get<List<int>>(path, options: Options(responseType: ResponseType.bytes));
        return Uint8List.fromList(response.data ?? const []);
      } on DioException catch (e) {
        final problem = ApiProblem.fromDio(e);
        if (problem.isOffline && attempt < _attempts) {
          await Future<void>.delayed(Duration(milliseconds: 400 * attempt * attempt));
          continue;
        }
        if (problem.isUnauthenticated && _context.token != null) _context.onUnauthorized();
        throw problem;
      }
    }
  }

  Future<Map<String, dynamic>> patch(String path, {Object? body}) => _send('PATCH', path, body: body);

  /// [academy] and [token] act as another account on this device, as for [post].
  Future<Map<String, dynamic>> delete(String path, {Object? body, String? academy, String? token}) =>
      _send('DELETE', path, body: body, academy: academy, token: token, retry: true);

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? query,
    Object? body,
    String? academy,
    String? token,
    bool anonymous = false,
    bool idempotent = false,
    bool retry = false,
  }) async {
    final options = Options(
      method: method,
      extra: {_academy: ?academy, _token: ?token, _anonymous: anonymous, if (idempotent) _idempotent: _uuid.v4()},
    );
    for (var attempt = 1; ; attempt++) {
      try {
        // A form with files is spent once sent: a retry sends a copy.
        final data = body is FormData && attempt > 1 ? body.clone() : body;
        final response = await _dio.request<Object?>(path, data: data, queryParameters: query, options: options);
        final json = response.data;
        return json is Map<String, dynamic> ? json : <String, dynamic>{};
      } on DioException catch (e) {
        final problem = ApiProblem.fromDio(e);
        if (problem.isOffline && retry && attempt < _attempts) {
          await Future<void>.delayed(Duration(milliseconds: 400 * attempt * attempt));
          continue;
        }
        if (problem.isUnauthenticated && !anonymous && token == null && _context.token != null) {
          _context.onUnauthorized();
        }
        throw problem;
      }
    }
  }

  void _decorate(RequestOptions options, RequestInterceptorHandler handler) {
    final anonymous = options.extra[_anonymous] == true;
    final academy = options.extra[_academy] as String? ?? (anonymous ? null : _context.academy);
    final token = anonymous ? null : options.extra[_token] as String? ?? _context.token;
    final key = options.extra[_idempotent] as String?;
    options.headers.addAll({
      'Accept': 'application/json',
      'Accept-Language': _context.locale,
      if (_context.timezone != null) 'X-Timezone': _context.timezone,
      'X-Academy': ?academy,
      if (token != null) 'Authorization': 'Bearer $token',
      'Idempotency-Key': ?key,
    });
    handler.next(options);
  }
}
