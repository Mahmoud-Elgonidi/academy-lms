import 'dart:async';

import 'package:flutter/material.dart';

import '../../../../../app/theme/app_theme.dart';
import '../../../../../core/l10n/l10n.dart';
import '../../../../../shared/motion/motion.dart';
import '../../../domain/room_stage.dart';
import 'room_colors.dart';
import 'room_controls.dart';
import 'room_tile.dart';
import 'room_top_bar.dart';
import 'stage_view.dart';

/// The lesson room on screen, from plain values: the people in it, how to
/// draw a picture, the bars' state and what the buttons do (the page
/// hands LiveKit's; tests hand their own). It keeps the layout chosen and
/// the pinned tile, lays the stage out with [pickStage], puts the bars
/// above and below it upright and over it in landscape (gone after four
/// quiet seconds, back with a tap), and in picture-in-picture shows the
/// main picture alone.
class RoomView extends StatefulWidget {
  const RoomView({
    required this.people,
    required this.video,
    required this.title,
    required this.elapsed,
    required this.controls,
    required this.actions,
    this.recording = false,
    this.quality = RoomQuality.unknown,
    this.connecting = false,
    this.notice,
    this.panel,
    this.pip = false,
    super.key,
  });

  final List<RoomPerson> people;
  final TileVideo video;
  final String title;
  final Duration elapsed;
  final RoomControlsState controls;
  final RoomActions actions;
  final bool recording;
  final RoomQuality quality;
  final bool connecting;

  /// A line over the stage (a weak line, a problem).
  final Widget? notice;

  /// The side panel (the lesson's mushaf), shown instead of the stage while [RoomControlsState.panelOpen].
  final Widget? panel;

  /// Android shrank the app into a small window: the main picture alone.
  final bool pip;

  /// Quiet this long in landscape, the bars step aside.
  static const barsLinger = Duration(seconds: 4);

  @override
  State<RoomView> createState() => _RoomViewState();
}

class _RoomViewState extends State<RoomView> {
  RoomLayout _layout = RoomLayout.auto;
  String? _pinned;
  bool _bars = true;
  bool? _landscape;

  // A Timer, not an animation's wait: hiding the bars answers four quiet
  // seconds (any touch starts them again), and the fade itself is an
  // implicit animation that stands still for those who asked for less motion.
  Timer? _hide;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final landscape = MediaQuery.orientationOf(context) == Orientation.landscape;
    if (landscape != _landscape) {
      _landscape = landscape;
      _wake(rebuild: false);
    }
  }

  @override
  void didUpdateWidget(RoomView old) {
    super.didUpdateWidget(old);
    // Back from the small window, or the panel closed: the bars show, and go again when quiet.
    if (old.pip != widget.pip || old.controls.panelOpen != widget.controls.panelOpen) _wake(rebuild: false);
  }

  @override
  void dispose() {
    _hide?.cancel();
    super.dispose();
  }

  bool get _mayHide => (_landscape ?? false) && !widget.pip && !(widget.controls.panelOpen && widget.panel != null);

  /// The bars back, and four seconds more before they go.
  void _wake({bool rebuild = true}) {
    _hide?.cancel();
    _hide = null;
    if (rebuild && !_bars) setState(() => _bars = true);
    _bars = true;
    if (_mayHide) {
      _hide = Timer(RoomView.barsLinger, () {
        if (mounted && _mayHide) setState(() => _bars = false);
      });
    }
  }

  void _pin(Tile tile) {
    setState(() => _pinned = _pinned == tile.key ? null : tile.key);
    _wake();
  }

  void _chooseLayout(RoomLayout layout) {
    setState(() {
      _layout = layout;
      _pinned = null;
    });
    _wake();
  }

  @override
  Widget build(BuildContext context) {
    final plan = pickStage(widget.people, layout: _layout, pinned: _pinned);
    // A pinned tile whose person left lets go.
    if (plan.pinned == null) _pinned = null;
    final landscape = _landscape ?? false;

    if (widget.pip) {
      final main = plan.main ?? plan.grid.firstOrNull;
      return ColoredBox(
        key: const Key('room-pip'),
        color: RoomColors.stage,
        child: main == null ? const SizedBox.expand() : RoomTile(tile: main, video: widget.video(main), radius: 0),
      );
    }

    final top = RoomTopBar(title: widget.title, elapsed: widget.elapsed, recording: widget.recording, quality: widget.quality);
    final bottom = RoomControls(state: widget.controls, actions: widget.actions, layout: _layout, onLayout: _chooseLayout);

    if (!landscape) {
      return ColoredBox(
        color: RoomColors.stage,
        child: SafeArea(
          child: Column(
            children: [
              top.appear(context, 0, rise: -0.3),
              Expanded(child: _stage(context, plan, landscape: false)),
              bottom.appear(context, 1, rise: 0.3),
            ],
          ),
        ),
      );
    }

    final shown = _bars || !_mayHide;
    final fade = context.calm ? Duration.zero : const Duration(milliseconds: 220);
    Widget bar(Widget child, {required bool atTop}) => IgnorePointer(
      ignoring: !shown,
      child: AnimatedOpacity(
        opacity: shown ? 1 : 0,
        duration: fade,
        child: AnimatedSlide(
          offset: shown ? Offset.zero : Offset(0, atTop ? -0.4 : 0.4),
          duration: fade,
          curve: Curves.easeOutCubic,
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: atTop ? Alignment.topCenter : Alignment.bottomCenter,
                end: atTop ? Alignment.bottomCenter : Alignment.topCenter,
                colors: const [RoomColors.scrim, Color(0x000F1114)],
              ),
            ),
            child: SafeArea(top: atTop, bottom: !atTop, child: child),
          ),
        ),
      ),
    );

    return ColoredBox(
      color: RoomColors.stage,
      child: Listener(
        onPointerDown: (_) => shown ? _wake() : null,
        child: Stack(
          children: [
            Positioned.fill(
              child: SafeArea(
                child: _stage(
                  context,
                  plan,
                  landscape: true,
                  selfInsets: shown ? const EdgeInsets.fromLTRB(12, 60, 12, 84) : const EdgeInsets.all(12),
                ),
              ),
            ),
            Positioned(top: 0, left: 0, right: 0, child: bar(top, atTop: true)),
            Positioned(bottom: 0, left: 0, right: 0, child: bar(bottom, atTop: false)),
            // Bars away: the first tap brings them back (and pins nothing).
            if (!shown)
              Positioned.fill(
                child: Semantics(
                  button: true,
                  label: context.l10n.roomShowBars,
                  child: GestureDetector(key: const Key('room-wake'), behavior: HitTestBehavior.opaque, onTap: _wake),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _stage(BuildContext context, StagePlan plan, {required bool landscape, EdgeInsets selfInsets = const EdgeInsets.all(12)}) {
    final panel = widget.panel;
    final Widget body;
    if (panel != null && widget.controls.panelOpen) {
      final main = plan.main ?? plan.grid.firstOrNull;
      body = Container(
        key: const Key('classroom-mushaf'),
        margin: EdgeInsets.all(landscape ? 6 : 8),
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(color: context.colors.page, borderRadius: BorderRadius.circular(22)),
        child: Stack(
          children: [
            Positioned.fill(child: panel),
            // The main picture small over the page; a tap goes back to it.
            if (main != null)
              PositionedDirectional(
                top: 10,
                end: 10,
                width: landscape ? 140 : 92,
                height: landscape ? 92 : 122,
                child: Tooltip(
                  message: context.l10n.mushafBackToVideo,
                  child: Semantics(
                    button: true,
                    label: context.l10n.mushafBackToVideo,
                    excludeSemantics: true,
                    child: GestureDetector(
                      key: const Key('mushaf-pip'),
                      onTap: widget.actions.onPanel,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: Colors.white, width: 2),
                          boxShadow: const [BoxShadow(color: Color(0x33000000), blurRadius: 12, offset: Offset(0, 4))],
                        ),
                        child: RoomTile(tile: main, video: widget.video(main), radius: 14),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      );
    } else {
      body = StageView(plan: plan, video: widget.video, onPin: _pin, landscape: landscape, selfInsets: selfInsets);
    }
    final notice = widget.notice;
    return Stack(
      children: [
        Positioned.fill(child: body),
        if (notice != null)
          PositionedDirectional(
            top: landscape && _bars ? 64 : 14,
            start: 14,
            // Clear of my own picture in its first corner.
            end: plan.self != null && !landscape ? 128 : 14,
            child: notice.appear(context, 0),
          ),
        if (widget.connecting) const Center(child: CircularProgressIndicator(color: RoomColors.ink)),
      ],
    );
  }
}
