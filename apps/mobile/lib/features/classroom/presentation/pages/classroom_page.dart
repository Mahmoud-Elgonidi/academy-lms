import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:livekit_client/livekit_client.dart' as lk;

import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../mushaf/data/mushaf_repository.dart';
import '../../../mushaf/domain/mushaf.dart';
import '../../../mushaf/presentation/widgets/mushaf_pane.dart';
import '../../../recordings/data/recordings_repository.dart';
import '../../../recordings/domain/recordings.dart';
import '../../data/classroom_repository.dart';
import '../../data/picture_in_picture.dart';
import '../../domain/join_ticket.dart';
import '../../domain/room_stage.dart';
import '../widgets/room/room_colors.dart';
import '../widgets/room/room_controls.dart';
import '../widgets/room/room_view.dart';

/// The live lesson in the app, on the academy's LiveKit room, laid out as a
/// video call ([RoomView]): a dark stage with the other person large and me
/// floating, everyone in a grid when more come, and a shared screen whole
/// (pinch to look closer) with its presenter's camera on it; a bar of round
/// buttons under it. Turned sideways, the picture takes the screen and the
/// bars step aside; on Android, leaving the app shrinks the room into a small
/// window ([PictureInPicture]). Attendance is the server's (LiveKit tells it
/// who is in the room). Everyone sees when the lesson is being recorded; its
/// teacher starts and stops it here, once everyone it records agreed. A
/// Quran lesson has its shared mushaf a tap away, the main picture small over
/// it: the teacher's turns and marks reach the room at once (data on the
/// `mushaf` topic, heard only from the teacher's seat), and the mushaf signal
/// follows for anyone beside the room.
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
  bool _leaving = false;

  /// Android's small window while the room is open.
  final _pip = PictureInPicture();

  /// The camera in use on a phone with two.
  lk.CameraPosition _facing = lk.CameraPosition.front;

  /// The lesson's shared mushaf, once the API says it has one; shown over the video while [_showMushaf].
  late final _place = MushafPlace(widget.lessonId);
  LoadCubit<LessonMushaf>? _mushaf;
  bool _showMushaf = false;
  late final lk.EventsListener<lk.RoomEvent> _events = _room.createListener();

  @override
  void initState() {
    super.initState();
    _room.addListener(_changed);
    _events.on<lk.DataReceivedEvent>((e) {
      if (isMushafSignal(topic: e.topic, metadata: e.participant?.metadata)) unawaited(_mushaf?.load());
    });
    // Whoever speaks gets the ring at once.
    _events.on<lk.ActiveSpeakersChangedEvent>((_) => _changed());
    // The room turns sideways with the phone (a shared screen reads better so); the app's own way comes back on leaving.
    unawaited(SystemChrome.setPreferredOrientations(DeviceOrientation.values));
    unawaited(_connect());
    unawaited(_findMushaf());
    _tick = Timer.periodic(const Duration(seconds: 1), (_) => mounted ? setState(() {}) : null);
  }

  @override
  void dispose() {
    _tick?.cancel();
    unawaited(_pip.setEnabled(false).whenComplete(_pip.dispose));
    // The app locks no orientation of its own (manifest, Info.plist, main.dart): the phone decides again.
    unawaited(SystemChrome.setPreferredOrientations(const []));
    _room.removeListener(_changed);
    unawaited(_events.dispose());
    unawaited(_mushaf?.close());
    unawaited(closeRoom(disconnect: _room.disconnect, dispose: _room.dispose));
    super.dispose();
  }

  Future<void> _findMushaf() async {
    final has = await context.read<MushafRepository>().has(widget.lessonId);
    if (has && mounted) setState(() => _mushaf = mushafCubit(context, _place)..load());
  }

  /// The teacher turned or marked: the room hears at once (the others read the mushaf again).
  Future<void> _tellRoom() async {
    try {
      await _room.localParticipant?.publishData(mushafSignal(), reliable: true, topic: mushafTopic);
    } on Object {
      // Not in the room (yet): the mushaf signal still reaches everyone a moment later.
    }
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
      // Left while it connected: the microphone and camera stay off.
      if (!mounted) return;
      _clock.start();
      unawaited(_pip.setEnabled(true));
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

  /// A phone's front and back cameras: the other one.
  bool get _canFlip => !kIsWeb && (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS);

  Future<void> _flipCamera() async {
    final track = _room.localParticipant?.videoTrackPublications.where((p) => p.source == lk.TrackSource.camera).firstOrNull?.track;
    if (track is! lk.LocalVideoTrack) return;
    final next = _facing == lk.CameraPosition.front ? lk.CameraPosition.back : lk.CameraPosition.front;
    try {
      await track.setCameraPosition(next);
      if (mounted) setState(() => _facing = next);
    } on Object {
      // One camera only after all: the picture stays as it was.
    }
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

  /// Out of the lesson at once, even when the network is gone; closing the page leaves the room ([dispose]).
  void _leave() {
    if (_leaving) return;
    _leaving = true;
    unawaited(_pip.setEnabled(false));
    context.canPop() ? context.pop() : context.go('/');
  }

  /// The people in the room as the stage needs them: me first, then the others as they came in.
  List<RoomPerson> _people() => [
    if (_room.localParticipant case final me?) _person(me, local: true),
    for (final p in _room.remoteParticipants.values) _person(p),
  ];

  RoomPerson _person(lk.Participant p, {bool local = false}) {
    bool shows(lk.TrackSource source) =>
        (local || !_audioOnly) && p.videoTrackPublications.any((pub) => _isSource(pub, source) && pub.subscribed && !pub.muted);
    return RoomPerson(
      identity: p.identity,
      name: p.name.isNotEmpty ? p.name : p.identity,
      isLocal: local,
      camera: shows(lk.TrackSource.camera),
      screen: shows(lk.TrackSource.screenShareVideo),
      micOn: p.isMicrophoneEnabled(),
      speaking: p.isSpeaking,
    );
  }

  /// A camera is anything that is not a shared screen (an older client says "unknown").
  static bool _isSource(lk.TrackPublication pub, lk.TrackSource source) => source == lk.TrackSource.screenShareVideo
      ? pub.source == lk.TrackSource.screenShareVideo
      : pub.source != lk.TrackSource.screenShareVideo;

  /// LiveKit's picture for a tile: a camera fills its box, a shared screen shows whole.
  Widget? _video(Tile tile) {
    if (!tile.hasVideo) return null;
    final lk.Participant? p = tile.person.isLocal ? _room.localParticipant : _room.remoteParticipants[tile.person.identity];
    final source = tile.source == FeedSource.screen ? lk.TrackSource.screenShareVideo : lk.TrackSource.camera;
    final track = p?.videoTrackPublications.where((pub) => _isSource(pub, source) && pub.subscribed && !pub.muted).firstOrNull?.track;
    if (track is! lk.VideoTrack) return null;
    return lk.VideoTrackRenderer(
      track,
      key: ValueKey('video-${track.sid}-${tile.fit.name}'),
      fit: tile.fit == TileFit.contain ? lk.VideoViewFit.contain : lk.VideoViewFit.cover,
    );
  }

  /// The worst line in the room: LiveKit's three steps as three bars.
  RoomQuality _quality() {
    final all = <lk.Participant>[?_room.localParticipant, ..._room.remoteParticipants.values].map((p) => p.connectionQuality);
    if (all.any((q) => q == lk.ConnectionQuality.poor || q == lk.ConnectionQuality.lost)) return RoomQuality.poor;
    if (all.any((q) => q == lk.ConnectionQuality.good)) return RoomQuality.fair;
    if (all.any((q) => q == lk.ConnectionQuality.excellent)) return RoomQuality.good;
    return RoomQuality.unknown;
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final others = _room.remoteParticipants.values.map((p) => p.name.isNotEmpty ? p.name : p.identity).toList();
    final me = _room.localParticipant;
    final quality = _quality();
    final ready = me != null;

    return Theme(
      data: Theme.of(context).copyWith(scaffoldBackgroundColor: RoomColors.stage),
      child: Scaffold(
        body: ValueListenableBuilder<bool>(
          valueListenable: _pip.active,
          builder: (context, pip, _) => RoomView(
            people: _people(),
            video: _video,
            pip: pip,
            title: others.isNotEmpty ? others.join('، ') : (_connecting ? l.joining : l.waitingOther),
            elapsed: _clock.elapsed,
            recording: _recordingOn,
            quality: quality,
            connecting: _connecting,
            notice: _problem != null || quality == RoomQuality.poor
                ? Note(_problem ?? l.weakNetwork, tone: _problem != null ? Tone.danger : Tone.reward, icon: Icons.wifi_rounded)
                : null,
            panel: _mushaf == null
                ? null
                : BlocProvider.value(
                    value: _mushaf!,
                    child: MushafPane(place: _place, onChanged: _tellRoom, size: 22),
                  ),
            controls: RoomControlsState(
              ready: ready,
              micOn: me?.isMicrophoneEnabled() ?? false,
              camOn: me?.isCameraEnabled() ?? false,
              canFlip: _canFlip,
              audioOnly: _audioOnly,
              hasPanel: _mushaf != null,
              panelOpen: _showMushaf,
              recordControls: _recording.controls,
              recordingOn: _recordingOn,
              recordBusy: _recordBusy,
              recordMissing: _recording.missing,
            ),
            actions: RoomActions(
              onMic: () => unawaited(_toggleMic()),
              onCam: () => unawaited(_toggleCam()),
              onFlip: () => unawaited(_flipCamera()),
              onAudioOnly: () => unawaited(_toggleAudioOnly()),
              onRecord: () => unawaited(_toggleRecording()),
              onPanel: () => setState(() => _showMushaf = !_showMushaf),
              onLeave: _leave,
            ),
          ),
        ),
      ),
    );
  }
}

/// Leaves a lesson's room and lets it go. A room that never connected, or
/// lost the network, answers the disconnect with a timeout after ten seconds:
/// it is let go all the same (its timers and listeners stop).
@visibleForTesting
Future<void> closeRoom({required Future<void> Function() disconnect, required Future<void> Function() dispose}) async {
  try {
    await disconnect();
  } on Object {
    // Nothing more to say to the server.
  }
  await dispose();
}
