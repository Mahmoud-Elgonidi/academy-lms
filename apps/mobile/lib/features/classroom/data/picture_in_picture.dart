import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Android's picture-in-picture for the lesson room, over the `lms/pip`
/// channel to `MainActivity`: while the room is open ([setEnabled] true),
/// leaving the app (home, another app) shrinks it to a small floating window
/// instead of hiding it, and [active] says when the window is that small (the
/// room then shows only the main picture). Elsewhere (iOS, the web) nothing
/// happens and [active] stays false; a phone without the channel (an old
/// build, tests) is the same.
class PictureInPicture {
  PictureInPicture({this.channel = const MethodChannel('lms/pip'), bool? supported})
    : supported = supported ?? (!kIsWeb && defaultTargetPlatform == TargetPlatform.android);

  final MethodChannel channel;

  /// Only Android has it here.
  final bool supported;

  /// The app is the small floating window now.
  final ValueNotifier<bool> active = ValueNotifier(false);

  bool _listening = false;

  /// On while a lesson's room is open, off when it is left.
  Future<void> setEnabled(bool on) async {
    if (!supported) return;
    if (on && !_listening) {
      _listening = true;
      channel.setMethodCallHandler(_heard);
    }
    try {
      await channel.invokeMethod<Object?>('setEnabled', on);
    } on MissingPluginException {
      // No channel on this build: the room stays as it is.
    } on PlatformException {
      // The phone refused (PiP turned off for the app in its settings).
    }
    if (!on) {
      active.value = false;
      if (_listening) {
        _listening = false;
        channel.setMethodCallHandler(null);
      }
    }
  }

  Future<Object?> _heard(MethodCall call) async {
    if (call.method == 'changed') active.value = call.arguments == true;
    return null;
  }

  void dispose() {
    if (_listening) channel.setMethodCallHandler(null);
    active.dispose();
  }
}
