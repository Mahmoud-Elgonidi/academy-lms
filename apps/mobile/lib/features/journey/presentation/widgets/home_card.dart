import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/journey.dart';

/// The way into a learner's journey from the family's home (under its
/// heading, "Salma's journey"): a ring of how far the course is, the level
/// she is on, the station, her stars and the missions waiting.
class JourneyHomeCard extends StatelessWidget {
  const JourneyHomeCard({super.key, required this.name, required this.to, this.map, this.self = false});

  final String name;

  /// The journey tab.
  final String to;

  /// The journey, when it could be read (the card still leads there without it).
  final JourneyMap? map;

  /// The person's own journey (an adult student).
  final bool self;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final m = map;
    final course = m?.courses.firstOrNull;
    final island = course?.here;
    final station = island?.here;
    final open = m?.missions.where((x) => !x.done).length ?? 0;
    final at = island == null || station == null ? -1 : island.stations.indexOf(station);
    final line = [
      if (island != null && at >= 0) l.journeyStationOf(at + 1, island.stations.length),
      if (m != null && m.rewards) l.journeyStarsCount(m.stars.total),
      if (open > 0) l.journeyOpenMissions(open),
    ].join(' · ');
    final percent = course?.percent;
    return LmsCard(
      key: const Key('journey-card'),
      onTap: () => context.go(to),
      child: Row(
        children: [
          if (percent != null) _Ring(percent: percent) else const IconBadge(Icons.map_rounded, size: 56),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  island == null ? (self ? l.navJourney : l.journeyTitle(name)) : '${l.journeyLevel(island.index)}: ${island.title}',
                  style: context.type.cardTitle,
                ),
                if (line.isNotEmpty) ...[
                  const Gap(Space.xs),
                  Text(line, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.type.caption),
                ],
              ],
            ),
          ),
          const Gap(Space.s),
          Icon(Icons.chevron_right_rounded, color: c.faint),
        ],
      ),
    );
  }
}

/// How far a course is, as a ring that fills with the number in it.
class _Ring extends StatelessWidget {
  const _Ring({required this.percent});

  final int percent;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final value = (percent / 100).clamp(0.0, 1.0);
    return SizedBox.square(
      dimension: 56,
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: context.calm ? value : 0, end: value),
        duration: const Duration(milliseconds: 900),
        curve: Curves.easeOutCubic,
        builder: (context, v, _) => CustomPaint(
          painter: _RingPainter(value: v, track: c.chip, colour: c.brand),
          child: Center(
            child: Text(
              '${(v * 100).round()}%',
              style: context.type.label.copyWith(color: c.ink, fontSize: 13, fontWeight: FontWeight.w800),
            ),
          ),
        ),
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  _RingPainter({required this.value, required this.track, required this.colour});

  final double value;
  final Color track;
  final Color colour;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromCircle(center: size.center(Offset.zero), radius: size.shortestSide / 2 - 4);
    final line = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 6
      ..strokeCap = StrokeCap.round;
    canvas.drawArc(rect, 0, 2 * math.pi, false, line..color = track);
    if (value > 0) canvas.drawArc(rect, -math.pi / 2, 2 * math.pi * value, false, line..color = colour);
  }

  @override
  bool shouldRepaint(_RingPainter old) => old.value != value || old.colour != colour;
}
