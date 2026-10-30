import 'package:flutter/material.dart';

import '../../../../../core/l10n/l10n.dart';
import '../../../../../shared/motion/motion.dart';
import '../../../domain/room_stage.dart';
import 'room_colors.dart';

/// Over the stage: who the lesson is with, the recording sign (a red dot
/// that breathes), how good the line is, and the time in the room.
class RoomTopBar extends StatelessWidget {
  const RoomTopBar({required this.title, required this.elapsed, required this.recording, required this.quality, super.key});

  final String title;
  final Duration elapsed;
  final bool recording;
  final RoomQuality quality;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
      child: Row(
        children: [
          Expanded(
            child: Semantics(
              header: true,
              child: Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: RoomColors.ink, fontSize: 16, fontWeight: FontWeight.w700),
              ),
            ),
          ),
          if (recording) ...[const _RecordingSign(), const SizedBox(width: 8)],
          if (quality != RoomQuality.unknown) ...[
            Tooltip(
              message: switch (quality) {
                RoomQuality.good => l.roomQualityGood,
                RoomQuality.fair => l.roomQualityFair,
                _ => l.roomQualityPoor,
              },
              child: Icon(
                key: Key('quality-${quality.name}'),
                switch (quality) {
                  RoomQuality.good => Icons.signal_cellular_alt_rounded,
                  RoomQuality.fair => Icons.signal_cellular_alt_2_bar_rounded,
                  _ => Icons.signal_cellular_alt_1_bar_rounded,
                },
                size: 20,
                color: switch (quality) {
                  RoomQuality.good => RoomColors.good,
                  RoomQuality.fair => RoomColors.fair,
                  _ => RoomColors.red,
                },
              ),
            ),
            const SizedBox(width: 8),
          ],
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(color: RoomColors.control, borderRadius: BorderRadius.circular(999)),
            child: Text(
              roomClock(elapsed),
              key: const Key('room-clock'),
              textDirection: TextDirection.ltr,
              style: const TextStyle(color: RoomColors.ink, fontWeight: FontWeight.w600, fontFeatures: [FontFeature.tabularFigures()]),
            ),
          ),
        ],
      ),
    );
  }
}

class _RecordingSign extends StatelessWidget {
  const _RecordingSign();

  @override
  Widget build(BuildContext context) {
    Widget dot = Container(
      width: 8,
      height: 8,
      decoration: const BoxDecoration(color: RoomColors.ink, shape: BoxShape.circle),
    );
    if (!context.calm) {
      dot = dot
          .animate(onPlay: (c) => c.repeat(reverse: true))
          .fade(begin: 1, end: 0.25, duration: const Duration(milliseconds: 900), curve: Curves.easeInOut);
    }
    return Semantics(
      liveRegion: true,
      child: Container(
        key: const Key('recording-on'),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(color: RoomColors.red, borderRadius: BorderRadius.circular(999)),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            dot,
            const SizedBox(width: 6),
            Text(
              context.l10n.recordingOn,
              style: const TextStyle(color: RoomColors.ink, fontWeight: FontWeight.w700, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
