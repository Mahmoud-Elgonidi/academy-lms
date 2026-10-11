import 'dart:async';

import 'package:audioplayers/audioplayers.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../shared/motion/juice.dart';

/// The islands' sounds (made in `tool/sounds.py`, as the web makes them):
/// short effects, and each island's music on its own scale over the sea.
/// The music note on the map mutes them all, on this phone only; the app's
/// own "sounds" choice in Me silences them too.
enum GameEffect { zoom, back, locked, knock, hatch, cheep, star, win }

class GameSound {
  GameSound._();

  static const _key = 'lms.games.muted';
  static final _effects = <AudioPlayer>[];
  static AudioPlayer? _music;
  static String? _playing;
  static bool _muted = false;
  static bool _read = false;

  static bool get muted => _muted || !Juice.sounds;

  /// Reads the choice kept on the phone (once).
  static Future<void> load() async {
    if (_read || !Juice.enabled) return;
    _read = true;
    try {
      _muted = await SharedPreferencesAsync().getBool(_key) ?? false;
    } on Object {
      _muted = false;
    }
  }

  static Future<void> setMuted(bool on) async {
    _muted = on;
    if (on) {
      await stopMusic();
    }
    if (!Juice.enabled) return;
    try {
      await SharedPreferencesAsync().setBool(_key, on);
    } on Object {
      // Kept for this run only.
    }
  }

  /// Plays an effect (and never waits for it).
  static void play(GameEffect effect) => unawaited(_play(effect));

  static Future<void> _play(GameEffect effect) async {
    if (muted || !Juice.enabled) return;
    final file = switch (effect) {
      GameEffect.zoom => 'island_zoom',
      GameEffect.back => 'island_back',
      GameEffect.locked => 'island_locked',
      GameEffect.knock => 'egg_knock',
      GameEffect.hatch => 'egg_hatch',
      GameEffect.cheep => 'creature_cheep',
      GameEffect.star => 'round_star',
      GameEffect.win => 'round_win',
    };
    try {
      final player = _effects.length < 4 ? AudioPlayer() : _effects.removeAt(0);
      if (!_effects.contains(player)) _effects.add(player);
      await player.stop();
      await player.play(AssetSource('sounds/$file.wav'), volume: 0.8);
    } on Object {
      // No audio here: silence.
    }
  }

  /// An island's music (its catalog `music`: major, pentatonic, dorian, lydian, phrygian; the map's otherwise), looping.
  static void music(String? scale) => unawaited(_loop(scale));

  static Future<void> _loop(String? scale) async {
    if (muted || !Juice.enabled) return;
    const scales = {'major', 'pentatonic', 'dorian', 'lydian', 'phrygian'};
    final name = scales.contains(scale) ? scale! : 'map';
    if (_playing == name) return;
    try {
      final player = _music ??= AudioPlayer();
      await player.stop();
      await player.setReleaseMode(ReleaseMode.loop);
      _playing = name;
      await player.play(AssetSource('sounds/music_$name.wav'), volume: 0.45);
    } on Object {
      _playing = null;
    }
  }

  static Future<void> stopMusic() async {
    _playing = null;
    try {
      await _music?.stop();
    } on Object {
      // Nothing playing.
    }
  }
}
