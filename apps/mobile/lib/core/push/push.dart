import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

/// Push notifications through Firebase Cloud Messaging, which the API's
/// push channel sends to. The project's settings come with the build
/// (`--dart-define=FCM_API_KEY=… FCM_APP_ID=… FCM_SENDER_ID=…
/// FCM_PROJECT_ID=…`, and `FCM_VAPID_KEY` for the web); a build without
/// them simply has no push, and the app works the same otherwise.
abstract interface class PushTokens {
  /// This phone's token, once push is up (null without push).
  String? get token;

  /// ios, android or web.
  String get platform;

  /// A new token (Firebase rotates them).
  Stream<String> get refreshed;
}

class Push implements PushTokens {
  Push._();

  static final instance = Push._();

  static const _apiKey = String.fromEnvironment('FCM_API_KEY');
  static const _appId = String.fromEnvironment('FCM_APP_ID');
  static const _senderId = String.fromEnvironment('FCM_SENDER_ID');
  static const _projectId = String.fromEnvironment('FCM_PROJECT_ID');
  static const _vapidKey = String.fromEnvironment('FCM_VAPID_KEY');

  static bool get configured => _apiKey != '' && _appId != '' && _senderId != '' && _projectId != '';

  final _refreshed = StreamController<String>.broadcast();
  final _opened = StreamController<Map<String, String>>.broadcast();
  final _received = StreamController<({String title, String body, Map<String, String> data})>.broadcast();

  @override
  String? token;

  /// A notification tapped while the app was closed: opened once the router is up.
  Map<String, String>? pending;

  @override
  Stream<String> get refreshed => _refreshed.stream;

  /// The data of a notification the person tapped (`screen`, `id`, `notification_id`).
  Stream<Map<String, String>> get opened => _opened.stream;

  /// A notification that arrived while the app was open.
  Stream<({String title, String body, Map<String, String> data})> get received => _received.stream;

  @override
  String get platform => kIsWeb ? 'web' : (defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android');

  static Map<String, String> _data(RemoteMessage m) => {for (final e in m.data.entries) e.key: '${e.value}'};

  /// Starts push if this build carries the settings; never throws.
  Future<void> start() async {
    if (!configured) return;
    try {
      await Firebase.initializeApp(
        options: const FirebaseOptions(apiKey: _apiKey, appId: _appId, messagingSenderId: _senderId, projectId: _projectId),
      );
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();
      token = await messaging.getToken(vapidKey: kIsWeb && _vapidKey != '' ? _vapidKey : null);
      messaging.onTokenRefresh.listen((t) {
        token = t;
        _refreshed.add(t);
      });
      FirebaseMessaging.onMessageOpenedApp.listen((m) => _opened.add(_data(m)));
      FirebaseMessaging.onMessage.listen(
        (m) => _received.add((title: m.notification?.title ?? '', body: m.notification?.body ?? '', data: _data(m))),
      );
      final first = await messaging.getInitialMessage();
      if (first != null) pending = _data(first);
    } on Object catch (e) {
      token = null;
      debugPrint('Push is off: $e');
    }
  }
}
