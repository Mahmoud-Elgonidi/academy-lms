import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';

IconData cheerIcon(String kind) => switch (kind) {
  'star' => Icons.star_rounded,
  'heart' => Icons.favorite_rounded,
  'fire' => Icons.local_fire_department_rounded,
  _ => Icons.celebration_rounded,
};

Color cheerColour(BuildContext context, String kind) {
  final c = context.colors;
  return switch (kind) {
    'star' => c.reward,
    'heart' => c.danger,
    'fire' => c.attention,
    _ => c.brand,
  };
}

String cheerName(AppLocalizations l, String kind) => switch (kind) {
  'star' => l.cheerStar,
  'heart' => l.cheerHeart,
  'fire' => l.cheerFire,
  _ => l.cheerClap,
};

const cheerKinds = ['clap', 'star', 'heart', 'fire'];

/// A sticker of the kids' album: a round die-cut picture with a white
/// edge, a little tilted. Not earned yet: a faint outline with a question mark.
class StickerArt extends StatelessWidget {
  const StickerArt(this.sticker, {this.earned = true, this.size = 72, super.key});

  final String sticker;
  final bool earned;
  final double size;

  static const _art = <String, (IconData, Color)>{
    'lion': (Icons.pets_rounded, Color(0xFFF59E0B)),
    'rocket': (Icons.rocket_launch_rounded, Color(0xFFEF4444)),
    'sun': (Icons.wb_sunny_rounded, Color(0xFFFBBF24)),
    'whale': (Icons.water_rounded, Color(0xFF3B82F6)),
    'cat': (Icons.cruelty_free_rounded, Color(0xFFA855F7)),
    'tree': (Icons.park_rounded, Color(0xFF22C55E)),
    'kite': (Icons.paragliding_rounded, Color(0xFFEC4899)),
    'castle': (Icons.castle_rounded, Color(0xFF8B5CF6)),
    'camel': (Icons.landscape_rounded, Color(0xFFD97706)),
    'owl': (Icons.nightlight_rounded, Color(0xFF6366F1)),
    'turtle': (Icons.spa_rounded, Color(0xFF10B981)),
    'balloon': (Icons.celebration_rounded, Color(0xFFF43F5E)),
    'moon': (Icons.bedtime_rounded, Color(0xFF1E40AF)),
    'fish': (Icons.set_meal_rounded, Color(0xFF06B6D4)),
    'bee': (Icons.emoji_nature_rounded, Color(0xFFEAB308)),
    'rainbow': (Icons.looks_rounded, Color(0xFFF97316)),
    'plane': (Icons.flight_rounded, Color(0xFF0EA5E9)),
    'crown': (Icons.workspace_premium_rounded, Color(0xFFCA8A04)),
    'cupcake': (Icons.cake_rounded, Color(0xFFDB2777)),
    'dino': (Icons.egg_rounded, Color(0xFF16A34A)),
    'flower': (Icons.local_florist_rounded, Color(0xFFE11D48)),
    'robot': (Icons.smart_toy_rounded, Color(0xFF64748B)),
    'boat': (Icons.sailing_rounded, Color(0xFF2563EB)),
    'trophy': (Icons.emoji_events_rounded, Color(0xFFD97706)),
  };

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final (icon, colour) = _art[sticker] ?? (Icons.star_rounded, c.reward);
    final tilt = ((sticker.hashCode % 9) - 4) * 0.025;
    if (!earned) {
      return Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: c.chip,
          border: Border.all(color: c.line, width: 2),
        ),
        child: Icon(Icons.question_mark_rounded, color: c.faint, size: size * 0.42),
      );
    }
    return Transform.rotate(
      angle: tilt,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(colors: [Color.lerp(colour, Colors.white, 0.25)!, colour], center: const Alignment(-0.3, -0.4)),
          border: Border.all(color: Colors.white, width: math.max(3, size * 0.06)),
          boxShadow: [BoxShadow(color: colour.withValues(alpha: 0.35), blurRadius: size * 0.12, offset: Offset(0, size * 0.05))],
        ),
        child: Icon(icon, color: Colors.white, size: size * 0.52),
      ),
    );
  }
}

/// Rays turning slowly behind a reward.
class SunRays extends StatelessWidget {
  const SunRays({required this.size, required this.colour, super.key});

  final double size;
  final Color colour;

  @override
  Widget build(BuildContext context) => CustomPaint(size: Size.square(size), painter: _Rays(colour));
}

class _Rays extends CustomPainter {
  _Rays(this.colour);

  final Color colour;

  @override
  void paint(Canvas canvas, Size size) {
    final centre = size.center(Offset.zero);
    final r = size.width / 2;
    final paint = Paint()
      ..shader = RadialGradient(colors: [colour, colour.withValues(alpha: 0)]).createShader(Rect.fromCircle(center: centre, radius: r));
    const n = 12;
    for (var i = 0; i < n; i++) {
      final a = i * 2 * math.pi / n;
      final path = Path()
        ..moveTo(centre.dx, centre.dy)
        ..lineTo(centre.dx + r * math.cos(a - 0.12), centre.dy + r * math.sin(a - 0.12))
        ..lineTo(centre.dx + r * math.cos(a + 0.12), centre.dy + r * math.sin(a + 0.12))
        ..close();
      canvas.drawPath(path, paint);
    }
  }

  @override
  bool shouldRepaint(_Rays old) => old.colour != colour;
}
