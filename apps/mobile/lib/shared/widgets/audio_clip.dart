import 'dart:async';
import 'dart:io';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';

import '../../app/theme/app_theme.dart';
import '../../core/l10n/l10n.dart';
import '../../core/network/api_problem.dart';
import 'lms.dart';

/// A recording to listen to: play and pause, how far along, how long. The
/// sound is fetched on the first tap ([load]), so a list of them costs nothing.
class AudioClip extends StatefulWidget {
  const AudioClip({required this.load, this.mime = 'audio/wav', super.key});

  final Future<Uint8List> Function() load;
  final String mime;

  @override
  State<AudioClip> createState() => _AudioClipState();
}

class _AudioClipState extends State<AudioClip> {
  AudioPlayer? _player;
  final _subs = <StreamSubscription<Object?>>[];
  bool _loading = false;
  bool _playing = false;
  Duration _at = Duration.zero;
  Duration _length = Duration.zero;

  @override
  void dispose() {
    for (final s in _subs) {
      unawaited(s.cancel());
    }
    unawaited(_player?.dispose());
    super.dispose();
  }

  Future<void> _toggle() async {
    if (_loading) return;
    final player = _player;
    if (player != null) {
      _playing ? await player.pause() : await player.resume();
      return;
    }
    setState(() => _loading = true);
    try {
      final bytes = await widget.load();
      final p = AudioPlayer();
      _subs
        ..add(p.onPositionChanged.listen((d) => mounted ? setState(() => _at = d) : null))
        ..add(p.onDurationChanged.listen((d) => mounted ? setState(() => _length = d) : null))
        ..add(p.onPlayerStateChanged.listen((s) => mounted ? setState(() => _playing = s == PlayerState.playing) : null))
        ..add(p.onPlayerComplete.listen((_) => mounted ? setState(() => _at = Duration.zero) : null));
      _player = p;
      await p.play(await _source(bytes));
    } on ApiProblem catch (e) {
      if (mounted) showSnack(context, context.problemText(e));
    } on Object {
      if (mounted) showSnack(context, context.l10n.cannotPlay);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// The web plays the bytes; phones play a file in the cache.
  Future<Source> _source(Uint8List bytes) async {
    if (kIsWeb) return BytesSource(bytes, mimeType: widget.mime);
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/clip-${identityHashCode(this)}.${_extension(widget.mime)}');
    await file.writeAsBytes(bytes, flush: true);
    return DeviceFileSource(file.path, mimeType: widget.mime);
  }

  static String _extension(String mime) => switch (mime) {
    'audio/mpeg' => 'mp3',
    'audio/mp4' || 'audio/x-m4a' || 'audio/aac' || 'video/mp4' => 'm4a',
    'audio/ogg' => 'ogg',
    'audio/webm' || 'video/webm' => 'webm',
    _ => 'wav',
  };

  static String _clock(Duration d) => '${d.inMinutes}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    final share = _length.inMilliseconds == 0 ? 0.0 : (_at.inMilliseconds / _length.inMilliseconds).clamp(0.0, 1.0);
    return Container(
      padding: const EdgeInsets.fromLTRB(6, 6, 14, 6),
      decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(999)),
      child: Row(
        children: [
          IconButton.filled(
            tooltip: _playing ? l.pause : l.playRecording,
            onPressed: _toggle,
            icon: _loading
                ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
                : Icon(_playing ? Icons.pause_rounded : Icons.play_arrow_rounded),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(value: share, minHeight: 6, color: c.brand, backgroundColor: c.card),
            ),
          ),
          const SizedBox(width: 10),
          Text(
            _length == Duration.zero ? '' : '${_clock(_at)} / ${_clock(_length)}',
            textDirection: TextDirection.ltr,
            style: context.text.bodySmall?.copyWith(color: c.brandDeep),
          ),
        ],
      ),
    );
  }
}
