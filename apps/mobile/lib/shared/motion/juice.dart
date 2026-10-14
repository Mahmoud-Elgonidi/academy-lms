import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// The app's sounds, made by `tool/sounds.py`. The first five are the kids'
/// and the students' rewards; the rest are the everyday app's, quieter.
enum Sound {
  pop('pop', 0.35),
  coin('coin', 0.8),
  chest('chest', 0.8),
  levelup('levelup', 0.8),
  cheer('cheer', 0.8),
  tapSoft('tap_soft', 0.25),
  success('success', 0.6),
  swoosh('swoosh', 0.4),
  send('send', 0.45),
  coinSoft('coin_soft', 0.5);

  const Sound(this.file, this.volume);

  final String file;
  final double volume;
}

/// The little things that make a tap feel alive: a sound and a buzz, both
/// only while the person keeps sounds on (Me › Sounds). Never fails: no
/// audio on a device or in a test is just silence.
class Juice {
  Juice._();

  static bool sounds = true;

  /// Turned off in widget tests (no audio plugin there).
  static bool enabled = true;

  /// Plays in place of the audio plugin, for tests that count sounds.
  @visibleForTesting
  static Future<void> Function(Sound sound)? player;

  /// Whether a tap may make a sound or buzz now.
  static bool get on => enabled && sounds;

  static final _players = <AudioPlayer>[];

  static Future<void> play(Sound sound) async {
    if (!sounds) return;
    if (player != null) return player!(sound);
    if (!enabled) return;
    try {
      final next = _players.length < 4 ? AudioPlayer() : _players.removeAt(0);
      if (!_players.contains(next)) _players.add(next);
      await next.stop();
      await next.play(AssetSource('sounds/${sound.file}.wav'), volume: sound.volume);
    } on Object {
      // No audio here: silence.
    }
  }

  /// A tap in the kids' and the students' worlds: a pop.
  static void tap() {
    _haptic(HapticFeedback.selectionClick);
    play(Sound.pop);
  }

  /// A tap anywhere else: a soft tick.
  static void press() {
    _haptic(HapticFeedback.selectionClick);
    play(Sound.tapSoft);
  }

  static void reward() {
    _haptic(HapticFeedback.mediumImpact);
    play(Sound.coin);
  }

  static void big(Sound sound) {
    _haptic(HapticFeedback.heavyImpact);
    play(sound);
  }

  /// Something done: [sound] and a buzz as strong as the look wants.
  static void done({Sound sound = Sound.success, bool light = false}) {
    _haptic(light ? HapticFeedback.lightImpact : HapticFeedback.mediumImpact);
    play(sound);
  }

  /// Something not right (a wrong code, an empty field): a firm buzz, no sound.
  static void wrong() => _haptic(HapticFeedback.heavyImpact);

  static void _haptic(Future<void> Function() buzz) {
    if (!sounds || (!enabled && player == null)) return;
    buzz().catchError((Object _) {});
  }
}
