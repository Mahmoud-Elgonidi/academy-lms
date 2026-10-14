import 'dart:math' as math;

import 'package:flutter/material.dart';

/// A filled circle with a white check drawn in (the families' and the
/// teachers'), or a thin ring and check drawn in the brand's colour (teens').
class CheckPainter extends CustomPainter {
  CheckPainter({required this.progress, required this.colour, this.ring = false});

  final double progress;
  final Color colour;

  /// A thin ring drawn round with the check, instead of a filled circle.
  final bool ring;

  @override
  void paint(Canvas canvas, Size size) {
    final r = size.shortestSide / 2;
    final centre = size.center(Offset.zero);
    final tick = Path()
      ..moveTo(centre.dx - r * 0.42, centre.dy + r * 0.02)
      ..lineTo(centre.dx - r * 0.12, centre.dy + r * 0.32)
      ..lineTo(centre.dx + r * 0.45, centre.dy - r * 0.3);
    final line = Paint()
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    if (ring) {
      line
        ..color = colour
        ..strokeWidth = 3;
      canvas.drawArc(Rect.fromCircle(center: centre, radius: r - 2), -math.pi / 2, 2 * math.pi * progress, false, line);
    } else {
      canvas.drawCircle(centre, r, Paint()..color = colour);
      line
        ..color = Colors.white
        ..strokeWidth = r * 0.16;
    }
    for (final metric in tick.computeMetrics()) {
      canvas.drawPath(metric.extractPath(0, metric.length * progress), line);
    }
  }

  @override
  bool shouldRepaint(CheckPainter old) => old.progress != progress || old.colour != colour;
}
