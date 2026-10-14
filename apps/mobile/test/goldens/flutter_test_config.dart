import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';

import 'golden_kit.dart';

/// Pictures here pass when at most this share of pixels differs: text is
/// drawn by the same Flutter on every Linux machine, but anti-aliasing may
/// move a few pixels between a laptop and CI's runner.
const _tolerance = 0.005;

Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  final local = goldenFileComparator;
  if (local is LocalFileComparator) {
    goldenFileComparator = _Tolerant(local.basedir.resolve('kids_test.dart'));
  }
  TestWidgetsFlutterBinding.ensureInitialized();
  await loadGoldenFonts();
  await testMain();
}

class _Tolerant extends LocalFileComparator {
  _Tolerant(super.testFile);

  @override
  Future<bool> compare(Uint8List imageBytes, Uri golden) async {
    final result = await GoldenFileComparator.compareLists(imageBytes, await getGoldenBytes(golden));
    if (result.passed || result.diffPercent <= _tolerance) {
      result.dispose();
      return true;
    }
    final error = await generateFailureOutput(result, golden, basedir);
    result.dispose();
    throw FlutterError(error);
  }
}
