import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:record/record.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/audio_clip.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/homework_repository.dart';

/// Records a voice answer in the app: the microphone's sound, made into a
/// WAV file here (the same on phones and the web), listened to before it
/// is sent, recorded again if wanted. Stops by itself at ten minutes.
class VoiceRecorder extends StatefulWidget {
  const VoiceRecorder({required this.onRecorded, this.big = false, super.key});

  /// The recording, or null when it was cleared.
  final ValueChanged<HandInFile?> onRecorded;

  /// The kids' look: a bigger button.
  final bool big;

  @override
  State<VoiceRecorder> createState() => _VoiceRecorderState();
}

class _VoiceRecorderState extends State<VoiceRecorder> {
  static const _rate = 16000;
  static const _limit = Duration(minutes: 10);

  AudioRecorder? _recorder;
  StreamSubscription<Uint8List>? _sub;
  StreamSubscription<Amplitude>? _amp;
  Timer? _tick;
  final _pcm = BytesBuilder(copy: false);
  final _clock = Stopwatch();
  double _level = 0;
  HandInFile? _file;

  bool get _recording => _recorder != null;

  @override
  void dispose() {
    _tick?.cancel();
    unawaited(_sub?.cancel());
    unawaited(_amp?.cancel());
    unawaited(_recorder?.dispose());
    super.dispose();
  }

  Future<void> _start() async {
    final l = context.l10n;
    final recorder = AudioRecorder();
    try {
      if (!await recorder.hasPermission()) {
        unawaited(recorder.dispose());
        if (mounted) showSnack(context, l.micDenied);
        return;
      }
      final stream = await recorder.startStream(const RecordConfig(encoder: AudioEncoder.pcm16bits, sampleRate: _rate, numChannels: 1));
      Juice.tap();
      _pcm.clear();
      _sub = stream.listen(_pcm.add);
      try {
        _amp = recorder.onAmplitudeChanged(const Duration(milliseconds: 120)).listen((a) {
          if (mounted) setState(() => _level = ((a.current + 45) / 45).clamp(0.0, 1.0));
        });
      } on Object {
        // No level on this platform: the button just pulses.
      }
      _clock
        ..reset()
        ..start();
      _tick = Timer.periodic(const Duration(milliseconds: 250), (_) {
        if (!mounted) return;
        if (_clock.elapsed >= _limit) {
          unawaited(_stop());
          showSnack(context, l.recordingLimit(_limit.inMinutes));
        } else {
          setState(() {});
        }
      });
      setState(() => _recorder = recorder);
    } on Object {
      unawaited(recorder.dispose());
      if (mounted) showSnack(context, l.micDenied);
    }
  }

  Future<void> _stop() async {
    final recorder = _recorder;
    if (recorder == null) return;
    _tick?.cancel();
    _clock.stop();
    await recorder.stop();
    await _sub?.cancel();
    await _amp?.cancel();
    unawaited(recorder.dispose());
    final file = HandInFile(
      bytes: wav(_pcm.takeBytes(), sampleRate: _rate),
      name: 'recording.wav',
      mime: 'audio/wav',
    );
    Juice.tap();
    setState(() {
      _recorder = null;
      _file = file;
      _level = 0;
    });
    widget.onRecorded(file);
  }

  void _again() {
    setState(() => _file = null);
    widget.onRecorded(null);
  }

  /// 16-bit mono PCM with a WAV header.
  static Uint8List wav(Uint8List pcm, {required int sampleRate}) {
    final header = ByteData(44);
    void ascii(int at, String s) {
      for (var i = 0; i < s.length; i++) {
        header.setUint8(at + i, s.codeUnitAt(i));
      }
    }

    ascii(0, 'RIFF');
    header.setUint32(4, 36 + pcm.length, Endian.little);
    ascii(8, 'WAVE');
    ascii(12, 'fmt ');
    header
      ..setUint32(16, 16, Endian.little)
      ..setUint16(20, 1, Endian.little)
      ..setUint16(22, 1, Endian.little)
      ..setUint32(24, sampleRate, Endian.little)
      ..setUint32(28, sampleRate * 2, Endian.little)
      ..setUint16(32, 2, Endian.little)
      ..setUint16(34, 16, Endian.little);
    ascii(36, 'data');
    header.setUint32(40, pcm.length, Endian.little);
    return (BytesBuilder(copy: false)
          ..add(header.buffer.asUint8List())
          ..add(pcm))
        .takeBytes();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final file = _file;
    if (file != null) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AudioClip(load: () async => file.bytes, mime: file.mime),
          TextButton.icon(onPressed: _again, icon: const Icon(Icons.refresh_rounded), label: Text(l.recordAgain)),
        ],
      );
    }
    final size = widget.big ? 92.0 : 68.0;
    final elapsed = _clock.elapsed;
    final time = '${elapsed.inMinutes}:${(elapsed.inSeconds % 60).toString().padLeft(2, '0')}';
    Widget button = Semantics(
      button: true,
      label: _recording ? l.stopRecording : (widget.big ? l.recordKids : l.recordVoice),
      child: GestureDetector(
        onTap: _recording ? _stop : _start,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 120),
          width: size,
          height: size,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: _recording ? c.danger : c.brand,
            boxShadow: [
              BoxShadow(
                color: (_recording ? c.danger : c.brand).withValues(alpha: 0.35),
                blurRadius: 8 + 22 * _level,
                spreadRadius: 2 + 10 * _level,
              ),
            ],
          ),
          child: Icon(_recording ? Icons.stop_rounded : Icons.mic_rounded, color: Colors.white, size: size * 0.48),
        ),
      ),
    );
    if (!_recording) button = button.bob(context, by: widget.big ? 4 : 2);
    return Center(
      child: Column(
        children: [
          button,
          const SizedBox(height: 8),
          Text(
            _recording ? l.recordingNow(time) : (widget.big ? l.recordKids : l.recordVoice),
            style: context.text.titleSmall?.copyWith(color: _recording ? c.dangerInk : null),
          ),
          if (_recording) TextButton(onPressed: _stop, child: Text(l.stopRecording)),
        ],
      ),
    );
  }
}
