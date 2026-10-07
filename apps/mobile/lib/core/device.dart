import 'package:flutter/foundation.dart';

/// How this device appears in "My devices" (`device_name` at sign-in).
String deviceName() {
  if (kIsWeb) return 'Web app';
  return switch (defaultTargetPlatform) {
    TargetPlatform.iOS => 'iPhone app',
    TargetPlatform.android => 'Android app',
    TargetPlatform.macOS => 'Mac app',
    TargetPlatform.windows => 'Windows app',
    _ => 'App',
  };
}
