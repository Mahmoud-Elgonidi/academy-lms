import 'package:flutter/material.dart';

import '../../../../../core/l10n/l10n.dart';
import '../../../../../shared/motion/motion.dart';
import '../../../domain/room_stage.dart';
import 'room_tile.dart';
import 'self_view.dart';

/// The stage as [pickStage] laid it out: one tile large with a strip of
/// small ones (under it upright, beside it in landscape), everyone in a
/// grid, or the other person large with me floating. A shared screen shows
/// whole and can be pinched larger (a double tap puts it back).
class StageView extends StatelessWidget {
  const StageView({
    required this.plan,
    required this.video,
    required this.onPin,
    this.landscape = false,
    this.selfInsets = const EdgeInsets.all(12),
    super.key,
  });

  final StagePlan plan;
  final TileVideo video;

  /// A tile tapped: pinned large, or let go when it was the pinned one.
  final ValueChanged<Tile> onPin;
  final bool landscape;

  /// Kept clear for the floating self view (the bars in landscape).
  final EdgeInsets selfInsets;

  /// Edge to edge in landscape; a little room around it upright.
  double get _gap => landscape ? 0 : 8;
  double get _radius => landscape ? 0 : 20;

  @override
  Widget build(BuildContext context) => switch (plan.kind) {
    StageKind.grid => _grid(context),
    StageKind.spotlight => _spotlight(context),
    StageKind.alone || StageKind.pair => _withSelf(context, _large(context, plan.main)),
  };

  Widget _tile(BuildContext context, Tile tile, {double radius = 14}) => RoomTile(
    key: ValueKey('tile-${tile.key}'),
    tile: tile,
    video: video(tile),
    radius: radius,
    pinned: plan.pinned == tile.key,
    onTap: () => onPin(tile),
  );

  /// The large tile (nothing yet while the room connects).
  Widget _large(BuildContext context, Tile? tile) {
    if (tile == null) return const SizedBox.expand();
    final box = tile.source == FeedSource.screen
        ? _ScreenTile(
            key: ValueKey('screen-${tile.key}'),
            screen: tile,
            video: video,
            overlay: plan.overlay,
            radius: _radius,
            pinned: plan.pinned == tile.key,
          )
        : _tile(context, tile, radius: _radius);
    return Padding(padding: EdgeInsets.all(_gap), child: box);
  }

  Widget _withSelf(BuildContext context, Widget stage) {
    final self = plan.self;
    if (self == null) return stage;
    final size = landscape ? const Size(150, 100) : const Size(104, 140);
    return Stack(
      children: [
        Positioned.fill(child: stage),
        Positioned.fill(
          child: FloatingSelf(size: size, insets: selfInsets, child: _tile(context, self, radius: 16)),
        ),
      ],
    );
  }

  Widget _spotlight(BuildContext context) {
    final strip = plan.strip;
    final large = Expanded(child: _large(context, plan.main));
    if (strip.isEmpty) return _withSelf(context, Column(children: [large]));
    final items = [
      for (final (i, t) in strip.indexed)
        SizedBox(
          key: ValueKey('strip-${t.key}'),
          width: landscape ? 160 : 92,
          height: landscape ? 100 : 112,
          child: _tile(context, t),
        ).appear(context, i),
    ];
    final list = ListView.separated(
      key: const Key('room-strip'),
      scrollDirection: landscape ? Axis.vertical : Axis.horizontal,
      padding: EdgeInsets.all(_gap == 0 ? 8 : _gap),
      itemCount: items.length,
      separatorBuilder: (_, _) => const SizedBox(width: 8, height: 8),
      itemBuilder: (_, i) => items[i],
    );
    return _withSelf(
      context,
      landscape
          ? Row(
              children: [
                large,
                SizedBox(width: 176, child: list),
              ],
            )
          : Column(
              children: [
                large,
                SizedBox(height: 128, child: list),
              ],
            ),
    );
  }

  Widget _grid(BuildContext context) => Padding(
    padding: EdgeInsets.all(landscape ? 6 : 8),
    child: LayoutBuilder(
      builder: (context, box) {
        const gap = 8.0;
        final tiles = plan.grid;
        final cols = gridColumns(tiles.length, box.maxWidth, box.maxHeight);
        final rows = (tiles.length + cols - 1) ~/ cols;
        final w = (box.maxWidth - gap * (cols - 1)) / cols;
        final h = (box.maxHeight - gap * (rows - 1)) / rows;
        return Column(
          key: const Key('room-grid'),
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            for (var r = 0; r < rows; r++) ...[
              if (r > 0) const SizedBox(height: gap),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (var i = r * cols; i < (r + 1) * cols && i < tiles.length; i++) ...[
                    if (i > r * cols) const SizedBox(width: gap),
                    SizedBox(
                      key: ValueKey('grid-${tiles[i].key}'),
                      width: w,
                      height: h,
                      child: _tile(context, tiles[i]),
                    ).appear(context, i),
                  ],
                ],
              ),
            ],
          ],
        );
      },
    ),
  );
}

/// A shared screen, whole: pinch to look closer, a double tap to see it all
/// again; the presenter's camera small in its corner.
class _ScreenTile extends StatefulWidget {
  const _ScreenTile({
    required this.screen,
    required this.video,
    required this.overlay,
    required this.radius,
    required this.pinned,
    super.key,
  });

  final Tile screen;
  final TileVideo video;
  final Tile? overlay;
  final double radius;
  final bool pinned;

  @override
  State<_ScreenTile> createState() => _ScreenTileState();
}

class _ScreenTileState extends State<_ScreenTile> {
  final _zoom = TransformationController();

  @override
  void dispose() {
    _zoom.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final overlay = widget.overlay;
    return Stack(
      children: [
        Positioned.fill(
          child: Semantics(
            hint: context.l10n.roomZoomHint,
            child: GestureDetector(
              key: const Key('room-screen'),
              onDoubleTap: () => _zoom.value = Matrix4.identity(),
              child: InteractiveViewer(
                transformationController: _zoom,
                minScale: 1,
                maxScale: 5,
                child: RoomTile(tile: widget.screen, video: widget.video(widget.screen), radius: widget.radius, pinned: widget.pinned),
              ),
            ),
          ),
        ),
        if (overlay != null)
          PositionedDirectional(
            top: 10,
            end: 10,
            width: 112,
            height: 84,
            child: DecoratedBox(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(14),
                boxShadow: const [BoxShadow(color: Color(0x55000000), blurRadius: 12, offset: Offset(0, 4))],
              ),
              child: RoomTile(key: ValueKey('overlay-${overlay.key}'), tile: overlay, video: widget.video(overlay), radius: 14),
            ).appear(context, 1),
          ),
      ],
    );
  }
}
