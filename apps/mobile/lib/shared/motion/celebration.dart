import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../../app/theme/stage.dart';
import 'motion.dart';

enum Burst { confetti, stars, gems }

/// A burst of confetti, stars or gems over the whole screen for a moment,
/// from a point (the button that was tapped) or the top. Nothing when the
/// phone asks for less motion.
class Celebration {
  Celebration._();

  static void show(BuildContext context, {Burst burst = Burst.confetti, Offset? from, int count = 70}) {
    if (context.calm) return;
    final overlay = Overlay.maybeOf(context, rootOverlay: true);
    if (overlay == null) return;
    final c = context.colors;
    final colours = [c.brand, c.reward, c.attention, c.info, c.danger, Color.lerp(c.brand, Colors.white, 0.4)!];
    final shape = context.shape.stage == Stage.kids && burst == Burst.confetti ? Burst.stars : burst;
    late final OverlayEntry entry;
    entry = OverlayEntry(
      builder: (_) => IgnorePointer(
        child: _Particles(burst: shape, colours: colours, from: from, count: count, onDone: () => entry.remove()),
      ),
    );
    overlay.insert(entry);
  }

  /// The centre of a widget on screen, to burst from it.
  static Offset? centreOf(BuildContext context) {
    final box = context.findRenderObject() as RenderBox?;
    if (box == null || !box.hasSize) return null;
    return box.localToGlobal(box.size.center(Offset.zero));
  }
}

class _Particle {
  _Particle(math.Random r, Offset origin, this.colour, {required bool fromTop})
    : position = origin,
      velocity = fromTop
          ? Offset((r.nextDouble() - 0.5) * 300, r.nextDouble() * 120 + 40)
          : Offset.fromDirection(-math.pi / 2 + (r.nextDouble() - 0.5) * math.pi * 1.3, r.nextDouble() * 520 + 260),
      spin = (r.nextDouble() - 0.5) * 12,
      size = r.nextDouble() * 7 + 6,
      wobble = r.nextDouble() * math.pi * 2;

  Offset position;
  Offset velocity;
  final Color colour;
  final double spin;
  final double size;
  final double wobble;
  double angle = 0;
}

class _Particles extends StatefulWidget {
  const _Particles({required this.burst, required this.colours, required this.count, required this.onDone, this.from});

  final Burst burst;
  final List<Color> colours;
  final Offset? from;
  final int count;
  final VoidCallback onDone;

  @override
  State<_Particles> createState() => _ParticlesState();
}

class _ParticlesState extends State<_Particles> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 2200));
  final _r = math.Random();
  List<_Particle>? _particles;
  Duration _last = Duration.zero;

  @override
  void initState() {
    super.initState();
    _c.addListener(_step);
    _c.forward().whenComplete(widget.onDone);
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  void _step() {
    final now = _c.lastElapsedDuration ?? Duration.zero;
    final dt = (now - _last).inMicroseconds / 1e6;
    _last = now;
    for (final p in _particles ?? const <_Particle>[]) {
      p.velocity = Offset(p.velocity.dx * 0.985, p.velocity.dy + 900 * dt);
      p.position += p.velocity * dt + Offset(math.sin(now.inMilliseconds / 160 + p.wobble) * 0.6, 0);
      p.angle += p.spin * dt;
    }
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    _particles ??= List.generate(widget.count, (i) {
      final fromTop = widget.from == null;
      final origin = widget.from ?? Offset(_r.nextDouble() * size.width, -20);
      return _Particle(_r, origin, widget.colours[i % widget.colours.length], fromTop: fromTop);
    });
    final fade = ((_c.value - 0.65) / 0.35).clamp(0.0, 1.0);
    return CustomPaint(size: size, painter: _Painter(_particles!, widget.burst, 1 - fade));
  }
}

class _Painter extends CustomPainter {
  _Painter(this.particles, this.burst, this.opacity);

  final List<_Particle> particles;
  final Burst burst;
  final double opacity;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint();
    for (final p in particles) {
      paint.color = p.colour.withValues(alpha: opacity.clamp(0, 1));
      canvas
        ..save()
        ..translate(p.position.dx, p.position.dy)
        ..rotate(p.angle);
      switch (burst) {
        case Burst.confetti:
          canvas.drawRRect(
            RRect.fromRectAndRadius(Rect.fromCenter(center: Offset.zero, width: p.size, height: p.size * 0.55), const Radius.circular(1.5)),
            paint,
          );
        case Burst.stars:
          canvas.drawPath(_star(p.size * 0.75), paint);
        case Burst.gems:
          canvas.drawPath(_gem(p.size * 0.8), paint);
      }
      canvas.restore();
    }
  }

  static Path _star(double r) {
    final path = Path();
    for (var i = 0; i < 10; i++) {
      final radius = i.isEven ? r : r * 0.45;
      final a = -math.pi / 2 + i * math.pi / 5;
      final pt = Offset(math.cos(a) * radius, math.sin(a) * radius);
      i == 0 ? path.moveTo(pt.dx, pt.dy) : path.lineTo(pt.dx, pt.dy);
    }
    return path..close();
  }

  static Path _gem(double r) => Path()
    ..moveTo(0, -r)
    ..lineTo(r * 0.8, -r * 0.2)
    ..lineTo(0, r)
    ..lineTo(-r * 0.8, -r * 0.2)
    ..close();

  @override
  bool shouldRepaint(_Painter old) => true;
}
