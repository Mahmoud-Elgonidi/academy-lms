import 'dart:io';

import 'package:academy_app/app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../features/sign_in_flow_test.dart' show frames;
import '../helpers.dart';

/// The app's own fonts and Material's icons, so pictures show real letters
/// and icons rather than boxes.
Future<void> loadGoldenFonts() async {
  const faces = {
    'ReadexPro': ['400', '600', '700'],
    'IBMPlexSansArabic': ['400', '500', '600', '700'],
    'BalooBhaijaan2': ['500', '700', '800'],
    'Rubik': ['400', '500', '700', '800'],
  };
  for (final MapEntry(key: family, value: weights) in faces.entries) {
    final loader = FontLoader(family);
    for (final w in weights) {
      loader.addFont(rootBundle.load('assets/fonts/$family-$w.ttf'));
    }
    await loader.load();
  }
  final icons = FontLoader('MaterialIcons')..addFont(Future.value(ByteData.sublistView(File(_materialIcons()).readAsBytesSync())));
  await icons.load();
}

/// MaterialIcons from the Flutter SDK running the tests.
String _materialIcons() {
  final roots = [
    ?Platform.environment['FLUTTER_ROOT'],
    // flutter_tester lives in <sdk>/bin/cache/artifacts/engine/<platform>/.
    File(Platform.resolvedExecutable).parent.parent.parent.parent.parent.parent.path,
  ];
  for (final root in roots) {
    final file = File('$root/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf');
    if (file.existsSync()) return file.path;
  }
  throw StateError('MaterialIcons-Regular.otf not found under $roots');
}

/// A phone (390 × 844 at 2×) that asks for no animation, so loops stand
/// still and each picture is the same every run.
void phone(WidgetTester tester) {
  tester.view
    ..physicalSize = const Size(780, 1688)
    ..devicePixelRatio = 2;
  tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
  addTearDown(() {
    tester.view.reset();
    tester.platformDispatcher.clearAccessibilityFeaturesTestValue();
  });
}

/// Opens the app signed in as [app]'s account, then [location].
Future<void> openAt(WidgetTester tester, TestApp app, String location) async {
  phone(tester);
  await app.session.start();
  await tester.pumpWidget(AcademyApp(services: app.services, session: app.session));
  await frames(tester, 20);
  GoRouter.of(tester.element(find.byType(Scaffold).first)).go(location);
  await frames(tester, 20);
}

/// Leaves the app and lets its timers run out.
Future<void> closeApp(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(seconds: 10));
}

/// The whole screen as a picture.
Future<void> expectScreen(String name) => expectLater(find.byType(MaterialApp), matchesGoldenFile(name));
