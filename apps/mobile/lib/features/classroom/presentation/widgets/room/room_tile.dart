import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../../app/theme/app_theme.dart';
import '../../../../../core/l10n/l10n.dart';
import '../../../../../shared/motion/motion.dart';
import '../../../domain/room_stage.dart';
import 'room_colors.dart';

/// Draws a tile's picture: the room hands LiveKit's renderer for it (fitted
/// as [Tile.fit] says), null when there is no picture (the initial shows).
/// Tests hand plain boxes.
typedef TileVideo = Widget? Function(Tile tile);

/// One box on the stage: the picture (or the person's initial), their name
/// in a pill with a muted microphone beside it, and a ring in the academy's
/// colour while they speak. A tap pins it large (or lets it go).
class RoomTile extends StatelessWidget {
  const RoomTile({required this.tile, required this.video, this.onTap, this.pinned = false, this.radius = 18, super.key});

  final Tile tile;
  final Widget? video;
  final VoidCallback? onTap;
  final bool pinned;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final person = tile.person;
    final who = person.isLocal ? l.you : person.name;
    final name = tile.source == FeedSource.screen ? l.roomScreenOf(who) : who;
    final muted = tile.source == FeedSource.camera && !person.micOn;
    final speaking = tile.source == FeedSource.camera && person.speaking;
    final shape = BorderRadius.circular(radius);

    return Semantics(
      container: true,
      button: onTap != null,
      label: muted ? '$name، ${l.roomMicMuted}' : name,
      hint: onTap == null ? null : (pinned ? l.roomUnpin : l.roomPin),
      child: GestureDetector(
        onTap: onTap,
        child: LayoutBuilder(
          builder: (context, box) {
            final side = math.min(box.maxWidth, box.maxHeight);
            final small = side < 140;
            return Container(
              clipBehavior: Clip.antiAlias,
              decoration: BoxDecoration(color: RoomColors.tile, borderRadius: shape),
              child: Stack(
                children: [
                  Positioned.fill(
                    child: ExcludeSemantics(
                      child:
                          video ??
                          Center(
                            child: RoomInitial(
                              name: person.name,
                              size: (side * 0.38).clamp(28, 120).toDouble(),
                              colour: person.isLocal ? c.attention : c.brandDeep,
                            ),
                          ),
                    ),
                  ),
                  PositionedDirectional(
                    bottom: small ? 6 : 10,
                    start: small ? 6 : 10,
                    end: small ? 6 : 10,
                    child: ExcludeSemantics(
                      child: Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: _NamePill(name: name, muted: muted, small: small),
                      ),
                    ),
                  ),
                  if (pinned)
                    PositionedDirectional(
                      top: 8,
                      start: 8,
                      child: Container(
                        padding: const EdgeInsets.all(5),
                        decoration: const BoxDecoration(color: RoomColors.pill, shape: BoxShape.circle),
                        child: const Icon(Icons.push_pin_rounded, size: 14, color: RoomColors.ink),
                      ),
                    ),
                  // The speaking ring: over the picture, never in the way of a tap.
                  Positioned.fill(
                    child: IgnorePointer(
                      child: AnimatedContainer(
                        key: speaking ? const Key('speaking') : null,
                        duration: context.calm ? Duration.zero : const Duration(milliseconds: 180),
                        decoration: BoxDecoration(
                          borderRadius: shape,
                          border: Border.all(color: speaking ? c.brand : Colors.transparent, width: 3),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _NamePill extends StatelessWidget {
  const _NamePill({required this.name, required this.muted, required this.small});

  final String name;
  final bool muted;
  final bool small;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.symmetric(horizontal: small ? 6 : 10, vertical: small ? 2 : 4),
    decoration: BoxDecoration(color: RoomColors.pill, borderRadius: BorderRadius.circular(999)),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (muted) ...[
          Icon(Icons.mic_off_rounded, key: const Key('tile-muted'), size: small ? 12 : 15, color: RoomColors.red),
          SizedBox(width: small ? 3 : 5),
        ],
        Flexible(
          child: Text(
            name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(color: RoomColors.ink, fontSize: small ? 11 : 13, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

/// A person's first letter in a circle, when there is no picture of them.
class RoomInitial extends StatelessWidget {
  const RoomInitial({required this.name, required this.size, required this.colour, super.key});

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
