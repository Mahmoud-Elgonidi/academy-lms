import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../domain/homework.dart';

/// A file to hand in: a recording made in the app or one picked from the phone.
class HandInFile {
  const HandInFile({required this.bytes, required this.name, required this.mime});

  final Uint8List bytes;
  final String name;
  final String mime;
}

class HomeworkRepository {
  HomeworkRepository(this._api);

  final ApiClient _api;

  Future<List<Homework>> list({String? studentId}) async => [
    for (final h in (await _api.get('learning/homework', query: {'student_id': ?studentId}))['data'] as List<dynamic>)
      Homework.fromJson(h as Map<String, dynamic>),
  ];

  /// Hands in words, a file (sent as multipart), or both.
  Future<Homework> handIn(String homeworkId, {String? body, HandInFile? file}) async {
    final Object payload = file == null
        ? {'body': body}
        : FormData.fromMap({
            'body': ?body,
            'file': MultipartFile.fromBytes(file.bytes, filename: file.name, contentType: DioMediaType.parse(file.mime)),
          });
    return Homework.fromJson((await _api.post('learning/homework/$homeworkId/submissions', body: payload))['data'] as Map<String, dynamic>);
  }

  /// The file of a hand-in (a recording to play, a picture to show).
  Future<Uint8List> file(String submissionId) => _api.download('learning/submissions/$submissionId/file');
}
