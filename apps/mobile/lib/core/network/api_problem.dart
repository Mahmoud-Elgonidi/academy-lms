import 'package:dio/dio.dart';

/// An error from the API as it sends them: RFC 7807 problem details
/// (`type`, `title`, `status`, `detail`, and `errors` per field for a 422),
/// already in the reader's language. A network failure has [isOffline].
class ApiProblem implements Exception {
  const ApiProblem({required this.type, required this.title, required this.status, this.detail, this.errors = const {}, this.retryAfter});

  factory ApiProblem.offline() => const ApiProblem(type: 'offline', title: '', status: 0);

  factory ApiProblem.fromDio(DioException e) {
    final response = e.response;
    if (response == null) {
      return ApiProblem.offline();
    }
    final body = response.data;
    final retry = int.tryParse(response.headers.value('retry-after') ?? '');
    if (body is Map<String, dynamic>) {
      final errors = <String, List<String>>{};
      final raw = body['errors'];
      if (raw is Map<String, dynamic>) {
        raw.forEach((field, messages) {
          if (messages is List) {
            errors[field] = messages.map((m) => '$m').toList();
          }
        });
      }
      return ApiProblem(
        type: '${body['type'] ?? 'http-error'}',
        title: '${body['title'] ?? ''}',
        status: response.statusCode ?? 0,
        detail: body['detail'] as String?,
        errors: errors,
        retryAfter: retry,
      );
    }
    return ApiProblem(type: 'http-error', title: '', status: response.statusCode ?? 0, retryAfter: retry);
  }

  final String type;
  final String title;
  final int status;
  final String? detail;
  final Map<String, List<String>> errors;

  /// Seconds to wait before trying again (429).
  final int? retryAfter;

  bool get isOffline => type == 'offline';
  bool get isValidation => status == 422;
  bool get isUnauthenticated => status == 401;
  bool get isNotFound => status == 404;

  /// The first message for a field, if the API named one.
  String? field(String name) => errors[name]?.first;

  /// The one line to show: the first field error, else the detail, else the title.
  String? get message {
    for (final messages in errors.values) {
      if (messages.isNotEmpty) return messages.first;
    }
    if (detail != null && detail!.isNotEmpty) return detail;
    return title.isEmpty ? null : title;
  }

  @override
  String toString() => 'ApiProblem($status $type: ${message ?? ''})';
}
