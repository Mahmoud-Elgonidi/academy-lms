import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Every call the app makes (`_api.get('family/lessons')`, …) must be an
/// endpoint of the committed OpenAPI document, with the same method. The
/// calls are read from the app's own code, so a renamed route or a typo
/// fails here before it fails on a phone.
void main() {
  final spec = jsonDecode(File('../../packages/openapi/openapi.json').readAsStringSync()) as Map<String, dynamic>;
  final paths = (spec['paths'] as Map<String, dynamic>).map(
    (path, ops) => MapEntry(path, (ops as Map<String, dynamic>).keys.map((m) => m.toUpperCase()).toSet()),
  );

  final call = RegExp(r"_api\.(get|post|put|patch|delete|download)\(\s*'([^']+)'");
  final calls = <(String, String, String)>[];
  for (final file in Directory('lib').listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'))) {
    for (final m in call.allMatches(file.readAsStringSync())) {
      // download() reads a file with GET.
      calls.add((m.group(1) == 'download' ? 'GET' : m.group(1)!.toUpperCase(), m.group(2)!, file.path));
    }
  }

  test('the app calls the API', () => expect(calls.length, greaterThan(25)));

  for (final (method, path, file) in calls) {
    test('$method $path', () {
      // "lessons/$lessonId/cancel" → /v1/lessons/{…}/cancel
      final segments = path.split('/').map((s) => s.contains(r'$') ? null : s).toList();
      final match = paths.entries.where((e) {
        final spec = e.key.split('/').skip(2).toList();
        if (spec.length != segments.length) return false;
        for (var i = 0; i < spec.length; i++) {
          final param = spec[i].startsWith('{');
          if (segments[i] == null ? !param : spec[i] != segments[i]) return false;
        }
        return true;
      }).toList();
      expect(match, isNotEmpty, reason: '$file calls $path, which the API does not have');
      expect(match.any((e) => e.value.contains(method)), isTrue, reason: '$file sends $method to $path, which the API does not take');
    });
  }
}
