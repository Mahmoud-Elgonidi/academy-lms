import 'package:flutter/material.dart';

import '../../../../../app/theme/app_theme.dart';
import '../../../../../core/l10n/l10n.dart';
import '../../../domain/room_stage.dart';
import 'room_colors.dart';

/// Where the room's buttons stand: plain values, so the bar is drawn (and
/// tested) without a call.
class RoomControlsState {
  const RoomControlsState({
    this.ready = false,
    this.micOn = false,
    this.camOn = false,
    this.canFlip = false,
    this.audioOnly = false,
    this.hasPanel = false,
    this.panelOpen = false,
    this.recordControls = false,
    this.recordingOn = false,
    this.recordBusy = false,
    this.recordMissing = const [],
  });

  /// In the room with a seat that publishes (the buttons work).
  final bool ready;
  final bool micOn;
  final bool camOn;

  /// A phone's front and back cameras, the camera on.
  final bool canFlip;
  final bool audioOnly;

  /// The lesson has its shared mushaf, and it is open now.
  final bool hasPanel;
  final bool panelOpen;

  /// This seat starts and stops the recording (the lesson's teacher).
  final bool recordControls;
  final bool recordingOn;
  final bool recordBusy;

  /// Who has not agreed to recording yet (it cannot start).
  final List<String> recordMissing;

  bool get canRecord => ready && !recordBusy && (recordingOn || recordMissing.isEmpty);
}

/// What the room's buttons do.
class RoomActions {
  const RoomActions({
    required this.onMic,
    required this.onCam,
    required this.onFlip,
    required this.onAudioOnly,
    required this.onRecord,
    required this.onPanel,
    required this.onLeave,
  });

  final VoidCallback onMic;
  final VoidCallback onCam;
  final VoidCallback onFlip;
  final VoidCallback onAudioOnly;
  final VoidCallback onRecord;
  final VoidCallback onPanel;
  final VoidCallback onLeave;
}

class _Control {
  const _Control({required this.id, required this.icon, required this.label, this.onPressed, this.tone = _Tone.rest});

  final String id;
  final IconData icon;
  final String label;
  final VoidCallback? onPressed;
  final _Tone tone;
}

/// At rest, switched on (the academy's colour), switched off where that is
/// a warning (a microphone or camera off, recording: red), or open (white).
enum _Tone { rest, on, off, open }

/// The round buttons under the stage, as in a video call: microphone,
/// camera, the other camera, the mushaf, audio only, the layout, recording
/// (its teacher), and leaving in red. Where the bar has no room for them
/// all, audio only, the layout and recording go under "more options".
class RoomControls extends StatelessWidget {
  const RoomControls({required this.state, required this.actions, required this.layout, required this.onLayout, super.key});

  final RoomControlsState state;
  final RoomActions actions;
  final RoomLayout layout;
  final ValueChanged<RoomLayout> onLayout;

  static const _size = 52.0;
  static const _gap = 10.0;

  String _layoutName(AppLocalizations l, RoomLayout layout) => switch (layout) {
    RoomLayout.auto => l.roomLayoutAuto,
    RoomLayout.grid => l.roomLayoutGrid,
    RoomLayout.spotlight => l.roomLayoutSpotlight,
  };

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = state;
    final main = [
      _Control(
        id: 'room-mic',
        icon: s.micOn ? Icons.mic_rounded : Icons.mic_off_rounded,
        label: s.micOn ? l.micOff : l.micOn,
        tone: s.micOn || !s.ready ? _Tone.rest : _Tone.off,
        onPressed: s.ready ? actions.onMic : null,
      ),
      _Control(
        id: 'room-cam',
        icon: s.camOn ? Icons.videocam_rounded : Icons.videocam_off_rounded,
        label: s.camOn ? l.camOff : l.camOn,
        tone: s.camOn || !s.ready ? _Tone.rest : _Tone.off,
        onPressed: s.ready && !s.audioOnly ? actions.onCam : null,
      ),
      if (s.canFlip && s.camOn)
        _Control(id: 'room-flip', icon: Icons.flip_camera_android_rounded, label: l.roomFlipCamera, onPressed: actions.onFlip),
      if (s.hasPanel)
        _Control(
          id: 'mushaf-toggle',
          icon: s.panelOpen ? Icons.videocam_rounded : Icons.menu_book_rounded,
          label: s.panelOpen ? l.mushafShowVideo : l.mushafShow,
          tone: s.panelOpen ? _Tone.open : _Tone.rest,
          onPressed: actions.onPanel,
        ),
    ];
    final more = [
      _Control(
        id: 'room-audio-only',
        icon: Icons.headphones_rounded,
        label: l.audioOnly,
        tone: s.audioOnly ? _Tone.on : _Tone.rest,
        onPressed: s.ready ? actions.onAudioOnly : null,
      ),
      _Control(
        id: 'room-layout',
        icon: switch (layout) {
          RoomLayout.auto => Icons.auto_awesome_mosaic_rounded,
          RoomLayout.grid => Icons.grid_view_rounded,
          RoomLayout.spotlight => Icons.featured_video_rounded,
        },
        label: '${l.roomLayout}: ${_layoutName(l, layout)}',
        onPressed: () => onLayout(nextLayout(layout)),
      ),
      if (s.recordControls)
        _Control(
          id: 'record',
          icon: s.recordingOn ? Icons.stop_circle_outlined : Icons.fiber_manual_record_rounded,
          label: s.recordingOn ? l.recordStop : l.recordStart,
          tone: s.recordingOn ? _Tone.off : _Tone.rest,
          onPressed: s.canRecord ? actions.onRecord : null,
        ),
    ];
    final missing = s.recordControls && !s.recordingOn && s.recordMissing.isNotEmpty;

    return LayoutBuilder(
      builder: (context, box) {
        final all = main.length + more.length + 1;
        final fits = all * _size + (all - 1) * _gap + 16 <= box.maxWidth;
        final shown = [
          ...main,
          if (fits)
            ...more
          else
            _Control(
              id: 'room-more',
              icon: Icons.more_horiz_rounded,
              label: l.roomMoreOptions,
              onPressed: () => _openMore(context, more, missing),
            ),
        ];
        return Padding(
          padding: const EdgeInsets.fromLTRB(8, 10, 8, 14),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (missing && fits) ...[
                Text(
                  l.recordingMissing(s.recordMissing.join('، ')),
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Color(0xFFFCA5A5), fontSize: 12),
                ),
                const SizedBox(height: 8),
              ],
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (final c in shown) ...[_RoundButton(c), const SizedBox(width: _gap)],
                  _LeaveButton(label: l.leave, onPressed: actions.onLeave),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _openMore(BuildContext context, List<_Control> more, bool missing) {
    final l = context.l10n;
    return showModalBottomSheet<void>(
      context: context,
      backgroundColor: RoomColors.sheet,
      showDragHandle: true,
      // As tall as its options (it scrolls on a short screen held sideways).
      isScrollControlled: true,
      builder: (sheet) => SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(8, 0, 8, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final c in more.where((c) => c.id != 'room-layout'))
                ListTile(
                  key: Key('${c.id}-option'),
                  enabled: c.onPressed != null,
                  leading: Icon(c.icon, color: c.tone == _Tone.off ? RoomColors.red : RoomColors.ink),
                  title: Text(
                    c.label,
                    style: const TextStyle(color: RoomColors.ink, fontWeight: FontWeight.w600),
                  ),
                  trailing: c.id == 'room-audio-only' && state.audioOnly ? Icon(Icons.check_rounded, color: context.colors.brand) : null,
                  onTap: c.onPressed == null
                      ? null
                      : () {
                          Navigator.of(sheet).pop();
                          c.onPressed!();
                        },
                ),
              if (missing)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                  child: Text(
                    l.recordingMissing(state.recordMissing.join('، ')),
                    style: const TextStyle(color: Color(0xFFFCA5A5), fontSize: 12),
                  ),
                ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: Text(
                  l.roomLayout,
                  style: const TextStyle(color: RoomColors.inkSoft, fontSize: 13, fontWeight: FontWeight.w700),
                ),
              ),
              for (final option in RoomLayout.values)
                ListTile(
                  key: Key('room-layout-${option.name}'),
                  selected: option == layout,
                  leading: Icon(switch (option) {
                    RoomLayout.auto => Icons.auto_awesome_mosaic_rounded,
                    RoomLayout.grid => Icons.grid_view_rounded,
                    RoomLayout.spotlight => Icons.featured_video_rounded,
                  }, color: RoomColors.ink),
                  title: Text(_layoutName(l, option), style: const TextStyle(color: RoomColors.ink)),
                  trailing: option == layout ? Icon(Icons.check_rounded, color: context.colors.brand) : null,
                  onTap: () {
                    Navigator.of(sheet).pop();
                    onLayout(option);
                  },
                ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: Text(l.rejoinHint, style: const TextStyle(color: RoomColors.inkSoft, fontSize: 12)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RoundButton extends StatelessWidget {
  const _RoundButton(this.control);

  final _Control control;

  @override
  Widget build(BuildContext context) {
    final c = control;
    final (bg, fg) = switch (c.tone) {
      _Tone.rest => (RoomColors.control, RoomColors.ink),
      _Tone.on => (context.colors.brand, Colors.white),
      _Tone.off => (RoomColors.red, Colors.white),
      _Tone.open => (RoomColors.ink, RoomColors.stage),
    };
    return Semantics(
      toggled: c.tone == _Tone.rest ? null : true,
      child: IconButton(
        key: Key(c.id),
        tooltip: c.label,
        onPressed: c.onPressed,
        iconSize: 24,
        style: IconButton.styleFrom(
          fixedSize: const Size(RoomControls._size, RoomControls._size),
          minimumSize: const Size(RoomControls._size, RoomControls._size),
          backgroundColor: bg,
          foregroundColor: fg,
          disabledBackgroundColor: RoomColors.controlDisabled,
          disabledForegroundColor: RoomColors.inkSoft,
        ),
        icon: Icon(c.icon),
      ),
    );
  }
}

class _LeaveButton extends StatelessWidget {
  const _LeaveButton({required this.label, required this.onPressed});

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Tooltip(
    message: label,
    child: Semantics(
      button: true,
      label: label,
      excludeSemantics: true,
      child: SizedBox(
        width: 68,
        height: RoomControls._size,
        child: FilledButton(
          key: const Key('room-leave'),
          style: FilledButton.styleFrom(
            backgroundColor: RoomColors.red,
            foregroundColor: Colors.white,
            padding: EdgeInsets.zero,
            shape: const StadiumBorder(),
          ),
          onPressed: onPressed,
          child: const Icon(Icons.call_end_rounded, size: 26),
        ),
      ),
    ),
  );
}
