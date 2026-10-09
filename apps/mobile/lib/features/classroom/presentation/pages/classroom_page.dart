import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:livekit_client/livekit_client.dart' as lk;

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../recordings/data/recordings_repository.dart';
import '../../../recordings/domain/recordings.dart';
import '../../data/classroom_repository.dart';
import '../../domain/join_ticket.dart';

/// The live lesson in the app, on the academy's LiveKit room: the other
/// person large, me small, and four big buttons (mic, camera, audio only,
/// leave). Attendance is the server's (LiveKit tells it who is in the room).
/// Everyone sees when the lesson is being recorded; its teacher starts and
/// stops it here, once everyone it records agreed.
class ClassroomPage extends StatefulWidget {
  const ClassroomPage({required this.lessonId, this.ticket, super.key});

  final String lessonId;

  /// The seat from the join button; asked for again when missing (a restored page).
  final Object? ticket;

  @override
  State<ClassroomPage> createState() => _ClassroomPageState();
}

class _ClassroomPageState extends State<ClassroomPage> {
  final _room = lk.Room(roomOptions: const lk.RoomOptions(adaptiveStream: true, dynacast: true));
  final _clock = Stopwatch();
  Timer? _tick;
  String? _problem;
  bool _connecting = true;
  bool _audioOnly = false;
  RecordingState _recording = const RecordingState();

  /// What the lesson's own start or stop said last; the room's word wins when it speaks.
  bool? _running;
  bool _recordBusy = false;

  @override
  void initState() {
    super.initState();
    _room.addListener(_changed);
    unawaited(_connect());
    _tick = Timer.periodic(const Duration(seconds: 1), (_) => mounted ? setState(() {}) : null);
  }

  @override
  void dispose() {
    _tick?.cancel();
    _room.removeListener(_changed);
    unawaited(_room.disconnect().then((_) => _room.dispose()));
    super.dispose();
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  Future<void> _connect() async {
    try {
      final ticket = widget.ticket is JoinTicket
          ? widget.ticket! as JoinTicket
          : await context.read<ClassroomRepository>().join(widget.lessonId);
      _recording = ticket.recording;
      await _room.connect(ticket.url, ticket.token!);
      _clock.start();
      if (ticket.canPublish) {
        await _room.localParticipant?.setMicrophoneEnabled(true);
        try {
          // The academy may have a student start with the camera off (under-18s, or everyone): the button turns it on.
          if (ticket.camera) await _room.localParticipant?.setCameraEnabled(true);
        } on Object {
          // No camera (or refused): the lesson goes on with sound.
        }
      }
      if (mounted) setState(() => _connecting = false);
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() {
          _connecting = false;
          _problem = context.problemText(p);
        });
      }
    } on Object {
      if (mounted) {
        setState(() {
          _connecting = false;
          _problem = context.l10n.offline;
        });
      }
    }
  }

  Future<void> _toggleMic() async {
    final me = _room.localParticipant;
    if (me != null) await me.setMicrophoneEnabled(!me.isMicrophoneEnabled());
  }

  Future<void> _toggleCam() async {
    final me = _room.localParticipant;
    if (me != null) await me.setCameraEnabled(!me.isCameraEnabled());
  }

  /// Saves the line: my camera off and the other side's video not fetched.
  Future<void> _toggleAudioOnly() async {
    final on = !_audioOnly;
    setState(() => _audioOnly = on);
    if (on) await _room.localParticipant?.setCameraEnabled(false);
    for (final p in _room.remoteParticipants.values) {
      for (final pub in p.videoTrackPublications) {
        on ? await pub.unsubscribe() : await pub.subscribe();
      }
    }
  }

  /// LiveKit says when the room is recorded, and so does the room's metadata (the server sets it).
  bool get _recordingOn {
    if (_room.isRecording) return true;
    final meta = _room.metadata;
    if (meta != null && meta.contains('"recording":true')) return true;
    if (meta != null && meta.contains('"recording":false')) return _running ?? false;
    return _running ?? _recording.running;
  }

  Future<void> _toggleRecording() async {
    final repo = context.read<RecordingsRepository>();
    final on = _recordingOn;
    setState(() => _recordBusy = true);
    try {
      if (on) {
        await repo.stop(widget.lessonId);
        _running = false;
      } else {
        _running = await repo.start(widget.lessonId);
      }
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _recordBusy = false);
    }
  }

  Future<void> _leave() async {
    await _room.disconnect();
    if (mounted) context.canPop() ? context.pop() : context.go('/');
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final other = _room.remoteParticipants.values.firstOrNull;
    final me = _room.localParticipant;
    final otherVideo = _audioOnly ? null : other?.videoTrackPublications.where((p) => p.subscribed && !p.muted).firstOrNull?.track;
    final myVideo = me?.videoTrackPublications.where((p) => !p.muted).firstOrNull?.track;
    final weak = me?.connectionQuality == lk.ConnectionQuality.poor || other?.connectionQuality == lk.ConnectionQuality.poor;
    final elapsed = _clock.elapsed;
    final mmss = '${elapsed.inMinutes.toString().padLeft(2, '0')}:${(elapsed.inSeconds % 60).toString().padLeft(2, '0')}';
    const ink = Color(0xFFFAFAF9);

    return Theme(
      data: Theme.of(context).copyWith(scaffoldBackgroundColor: const Color(0xFF0C0A09)),
      child: Scaffold(
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        other?.name.isNotEmpty ?? false ? other!.name : (_connecting ? l.joining : l.waitingOther),
                        style: const TextStyle(color: ink, fontSize: 16, fontWeight: FontWeight.w700),
                      ),
                    ),
                    if (_recordingOn) ...[
                      Semantics(
                        liveRegion: true,
                        child: Container(
                          key: const Key('recording-on'),
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(color: const Color(0xFFDC2626), borderRadius: BorderRadius.circular(999)),
                          child: Text(
                            l.recordingOn,
                            style: const TextStyle(color: ink, fontWeight: FontWeight.w700, fontSize: 12),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                    ],
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(999)),
                      child: Row(
                        children: [
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(color: Color(0xFF34D399), shape: BoxShape.circle),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            mmss,
                            textDirection: TextDirection.ltr,
                            style: const TextStyle(color: ink, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: Container(
                  margin: const EdgeInsets.symmetric(horizontal: 12),
                  clipBehavior: Clip.antiAlias,
                  decoration: BoxDecoration(color: const Color(0xFF1C1917), borderRadius: BorderRadius.circular(26)),
                  child: Stack(
                    children: [
                      Positioned.fill(
                        child: otherVideo != null
                            ? lk.VideoTrackRenderer(otherVideo, fit: lk.VideoViewFit.cover)
                            : Center(
                                child: other == null
                                    ? Icon(Icons.hourglass_empty_rounded, size: 56, color: ink.withValues(alpha: 0.5))
                                    : _Initial(name: other.name, size: 140, colour: c.brandDeep),
                              ),
                      ),
                      if (other != null) PositionedDirectional(bottom: 14, start: 14, child: _Label(other.name)),
                      PositionedDirectional(
                        top: 14,
                        end: 14,
                        child: Container(
                          width: 112,
                          height: 150,
                          clipBehavior: Clip.antiAlias,
                          decoration: BoxDecoration(
                            color: const Color(0xFF44403C),
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(color: Colors.white.withValues(alpha: 0.25), width: 2),
                          ),
                          child: Stack(
                            children: [
                              Positioned.fill(
                                child: myVideo != null
                                    ? lk.VideoTrackRenderer(myVideo, fit: lk.VideoViewFit.cover)
                                    : Center(
                                        child: _Initial(name: me?.name ?? '', size: 56, colour: c.attention),
                                      ),
                              ),
                              PositionedDirectional(bottom: 8, start: 8, child: _Label(l.you)),
                            ],
                          ),
                        ),
                      ),
                      if (weak || _problem != null)
                        PositionedDirectional(
                          top: 14,
                          start: 14,
                          end: 140,
                          child: Note(
                            _problem ?? l.weakNetwork,
                            tone: _problem != null ? Tone.danger : Tone.reward,
                            icon: Icons.wifi_rounded,
                          ),
                        ),
                      if (_connecting) const Center(child: CircularProgressIndicator(color: ink)),
                    ],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 18, 16, 20),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        _RoundButton(
                          tooltip: me?.isMicrophoneEnabled() ?? false ? l.micOff : l.micOn,
                          icon: me?.isMicrophoneEnabled() ?? false ? Icons.mic_rounded : Icons.mic_off_rounded,
                          pressed: !(me?.isMicrophoneEnabled() ?? true),
                          onPressed: me == null ? null : _toggleMic,
                        ),
                        const SizedBox(width: 14),
                        _RoundButton(
                          tooltip: me?.isCameraEnabled() ?? false ? l.camOff : l.camOn,
                          icon: me?.isCameraEnabled() ?? false ? Icons.videocam_rounded : Icons.videocam_off_rounded,
                          pressed: !(me?.isCameraEnabled() ?? true),
                          onPressed: me == null || _audioOnly ? null : _toggleCam,
                        ),
                        const SizedBox(width: 14),
                        _RoundButton(
                          tooltip: l.audioOnly,
                          icon: Icons.headphones_rounded,
                          pressed: _audioOnly,
                          onPressed: me == null ? null : _toggleAudioOnly,
                        ),
                        if (_recording.controls) ...[
                          const SizedBox(width: 14),
                          _RoundButton(
                            key: const Key('record'),
                            tooltip: _recordingOn ? l.recordStop : l.recordStart,
                            icon: _recordingOn ? Icons.stop_circle_outlined : Icons.fiber_manual_record_rounded,
                            pressed: _recordingOn,
                            onPressed: me == null || _recordBusy || (!_recordingOn && _recording.missing.isNotEmpty)
                                ? null
                                : _toggleRecording,
                          ),
                        ],
                        const SizedBox(width: 14),
                        Tooltip(
                          message: l.leave,
                          child: SizedBox(
                            width: 76,
                            height: 60,
                            child: FilledButton(
                              style: FilledButton.styleFrom(
                                backgroundColor: const Color(0xFFDC2626),
                                padding: EdgeInsets.zero,
                                shape: const StadiumBorder(),
                              ),
                              onPressed: _leave,
                              child: const Icon(Icons.call_end_rounded, size: 28),
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (_recording.controls && !_recordingOn && _recording.missing.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Text(
                        l.recordingMissing(_recording.missing.join('، ')),
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Color(0xFFFCA5A5), fontSize: 12),
                      ),
                    ],
                    const SizedBox(height: 12),
                    Text(
                      l.rejoinHint,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Color(0xFFD6D3D1), fontSize: 12),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({required this.tooltip, required this.icon, required this.pressed, required this.onPressed, super.key});

  final String tooltip;
  final IconData icon;
  final bool pressed;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => Semantics(
    toggled: pressed,
    child: IconButton(
      tooltip: tooltip,
      onPressed: onPressed,
      iconSize: 26,
      style: IconButton.styleFrom(
        fixedSize: const Size(60, 60),
        backgroundColor: pressed ? Colors.white : Colors.white.withValues(alpha: 0.14),
        foregroundColor: pressed ? const Color(0xFF1C1917) : Colors.white,
        disabledBackgroundColor: Colors.white.withValues(alpha: 0.06),
      ),
      icon: Icon(icon),
    ),
  );
}

class _Initial extends StatelessWidget {
  const _Initial({required this.name, required this.size, required this.colour});

  final String name;
  final double size;
  final Color colour;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    alignment: Alignment.center,
    decoration: BoxDecoration(color: colour, shape: BoxShape.circle),
    child: Text(
      name.trim().isEmpty ? '' : name.trim().characters.first,
      style: TextStyle(color: Colors.white, fontSize: size * 0.4, fontWeight: FontWeight.w700),
    ),
  );
}

class _Label extends StatelessWidget {
  const _Label(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
    decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.55), borderRadius: BorderRadius.circular(999)),
    child: Text(
      text,
      style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
    ),
  );
}
