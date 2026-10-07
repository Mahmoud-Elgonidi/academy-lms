import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/services.dart';

enum Sound { pop, coin, chest, levelup, cheer }

/// The little things that make a tap feel alive: a sound (if the person
/// keeps sounds on) and a buzz. Never fails: no audio on a device or in a
/// test is just silence.
class Juice {
  Juice._();

  static bool sounds = true;

  /// Turned off in widget tests (no audio plugin there).
  static bool enabled = true;

  static final _players = <AudioPlayer>[];

  static Future<void> play(Sound sound) async {
    if (!sounds || !enabled) return;
    try {
      final player = _players.length < 4 ? AudioPlayer() : _players.removeAt(0);
      if (!_players.contains(player)) _players.add(player);
      await player.stop();
      await player.play(AssetSource('sounds/${sound.name}.wav'), volume: sound == Sound.pop ? 0.35 : 0.8);
    } on Object {
      // No audio here: silence.
    }
  }

  static void tap() {
    _haptic(HapticFeedback.selectionClick);
    play(Sound.pop);
  }

  static void reward() {
    _haptic(HapticFeedback.mediumImpact);
    play(Sound.coin);
  }

  static void big(Sound sound) {
    _haptic(HapticFeedback.heavyImpact);
    play(sound);
  }

  static void _haptic(Future<void> Function() buzz) {
    if (!enabled) return;
    buzz().catchError((Object _) {});
  }
}
