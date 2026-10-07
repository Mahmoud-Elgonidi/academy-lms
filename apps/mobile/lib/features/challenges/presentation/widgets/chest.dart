import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';

/// The kids' treasure chest, drawn: wooden, gold bands and lock. [open]
/// lifts and tips the lid back (0 shut, 1 open) with light from inside.
class TreasureChest extends StatelessWidget {
  const TreasureChest({this.open = 0, this.size = 120, super.key});

  final double open;
  final double size;

  @override
  Widget build(BuildContext context) =>
      CustomPaint(size: Size(size, size * 0.86), painter: _ChestPainter(open, context.colors.reward, context.colors.rewardSoft));
}

class _ChestPainter extends CustomPainter {
  _ChestPainter(this.open, this.gold, this.glow);

  final double open;
  final Color gold;
  final Color glow;

  static const _wood = Color(0xFFB4693A);
  static const _woodDark = Color(0xFF8A4B26);

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final body = RRect.fromRectAndRadius(Rect.fromLTRB(w * 0.08, h * 0.42, w * 0.92, h * 0.98), Radius.circular(w * 0.07));

    // Light from inside, once the lid moves.
    if (open > 0) {
      final centre = Offset(w / 2, h * 0.42);
      canvas.drawCircle(
        centre,
        w * 0.62 * open,
        Paint()
          ..shader = RadialGradient(colors: [glow, glow.withValues(alpha: 0)])
              .createShader(Rect.fromCircle(center: centre, radius: w * 0.62)),
      );
      canvas.drawRect(Rect.fromLTRB(w * 0.1, h * 0.36, w * 0.9, h * 0.44), Paint()..color = _woodDark);
    }

    canvas.drawRRect(body, Paint()..color = _wood);
    canvas.drawRRect(
      body,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = w * 0.025
        ..color = _woodDark,
    );
    final band = Paint()..color = gold;
    for (final x in [0.24, 0.76]) {
      canvas.drawRect(Rect.fromLTRB(w * (x - 0.045), h * 0.42, w * (x + 0.045), h * 0.98), band);
    }

    // The lid tips back around its back edge.
    canvas.save();
    final hinge = Offset(w / 2, h * 0.42);
    canvas.translate(hinge.dx, hinge.dy - h * 0.16 * open);
    canvas.scale(1, 1 - 0.75 * open);
    canvas.translate(-hinge.dx, -hinge.dy);
    final lid = Path()
      ..moveTo(w * 0.06, h * 0.44)
      ..lineTo(w * 0.06, h * 0.3)
      ..quadraticBezierTo(w * 0.5, h * 0.02, w * 0.94, h * 0.3)
      ..lineTo(w * 0.94, h * 0.44)
      ..close();
    canvas.drawPath(lid, Paint()..color = Color.lerp(_wood, Colors.white, 0.08)!);
    canvas.drawPath(
      lid,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = w * 0.025
        ..color = _woodDark,
    );
    for (final x in [0.24, 0.76]) {
      canvas.drawRect(Rect.fromLTRB(w * (x - 0.045), h * 0.2, w * (x + 0.045), h * 0.44), band);
    }
    canvas.restore();

    // The lock.
    if (open < 0.5) {
      final lock = RRect.fromRectAndRadius(
        Rect.fromCenter(center: Offset(w / 2, h * 0.47), width: w * 0.16, height: h * 0.19),
        Radius.circular(w * 0.03),
      );
      canvas.drawRRect(lock, band);
      canvas.drawCircle(Offset(w / 2, h * 0.45), w * 0.022, Paint()..color = _woodDark);
      canvas.drawRect(Rect.fromCenter(center: Offset(w / 2, h * 0.5), width: w * 0.016, height: h * 0.05), Paint()..color = _woodDark);
    }

    // Sparkles on a shut chest's lid edge.
    if (open == 0) {
      final spark = Paint()..color = Colors.white.withValues(alpha: 0.8);
      for (final (x, y, r) in [(0.3, 0.24, 0.02), (0.68, 0.2, 0.014)]) {
        _star(canvas, Offset(w * x, h * y), w * r * 2, spark);
      }
    }
  }

  void _star(Canvas canvas, Offset c, double r, Paint paint) {
    final path = Path();
    for (var i = 0; i < 8; i++) {
      final radius = i.isEven ? r : r * 0.35;
      final a = i * math.pi / 4;
      final p = c + Offset(math.cos(a) * radius, math.sin(a) * radius);
      i == 0 ? path.moveTo(p.dx, p.dy) : path.lineTo(p.dx, p.dy);
    }
    canvas.drawPath(path..close(), paint);
  }

  @override
  bool shouldRepaint(_ChestPainter old) => old.open != open || old.gold != gold;
}
