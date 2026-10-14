import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';

/// Too many tries: what happened, and a ring running down the seconds the
/// API asked to wait (its Retry-After). Then it calls [onDone] and the step
/// works again. The countdown runs on the screen's own clock, not a timer.
class RetryWait extends StatefulWidget {
  const RetryWait({required this.seconds, required this.onDone, super.key});

  final int seconds;
  final VoidCallback onDone;

  @override
  State<RetryWait> createState() => _RetryWaitState();
}

class _RetryWaitState extends State<RetryWait> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: Duration(seconds: math.max(1, widget.seconds)),
    // A real wait: it keeps its length when the phone asks for less motion.
    animationBehavior: AnimationBehavior.preserve,
  );

  @override
  void initState() {
    super.initState();
    _c.forward().whenComplete(() {
      if (mounted) widget.onDone();
    });
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: c.attentionSoft,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: c.attention.withValues(alpha: 0.35)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.hourglass_top_rounded, color: c.attentionInk, size: 22),
                const SizedBox(width: 8),
                Text(
                  l.tooManyTitle,
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: c.attentionInk),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(l.tooManyBody, style: TextStyle(fontSize: 14, height: 1.55, color: c.ink)),
            const SizedBox(height: 10),
            AnimatedBuilder(
              animation: _c,
              builder: (context, _) {
                final left = ((1 - _c.value) * widget.seconds).ceil();
                final mmss = '${left ~/ 60}:${(left % 60).toString().padLeft(2, '0')}';
                return Row(
                  children: [
                    SizedBox.square(
                      dimension: 22,
                      child: CustomPaint(
                        painter: _Ring(left: 1 - _c.value, track: c.line, colour: c.attentionInk),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Text(
                      l.retryIn(mmss),
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: c.ink),
                    ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _Ring extends CustomPainter {
  _Ring({required this.left, required this.track, required this.colour});

  final double left;
  final Color track;
  final Color colour;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromCircle(center: size.center(Offset.zero), radius: size.shortestSide / 2 - 2);
    final line = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round;
    canvas.drawArc(rect, 0, 2 * math.pi, false, line..color = track);
    canvas.drawArc(rect, -math.pi / 2, 2 * math.pi * left, false, line..color = colour);
  }

  @override
  bool shouldRepaint(_Ring old) => old.left != left;
}
