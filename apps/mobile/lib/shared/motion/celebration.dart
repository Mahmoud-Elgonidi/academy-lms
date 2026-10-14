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

/// One piece, flying from where it starts: its path is worked out from the
/// time alone, so nothing is stepped or rebuilt frame by frame.
class _Particle {
  _Particle(math.Random r, this.origin, this.colour, {required bool fromTop})
    : velocity = fromTop
          ? Offset((r.nextDouble() - 0.5) * 300, r.nextDouble() * 120 + 40)
          : Offset.fromDirection(-math.pi / 2 + (r.nextDouble() - 0.5) * math.pi * 1.3, r.nextDouble() * 520 + 260),
      spin = (r.nextDouble() - 0.5) * 12,
      size = r.nextDouble() * 7 + 6,
      wobble = r.nextDouble() * math.pi * 2;

  final Offset origin;
  final Offset velocity;
  final Color colour;
  final double spin;
  final double size;
  final double wobble;

  /// Where it is [t] seconds in: thrown, slowed by the air, pulled down.
  Offset at(double t) {
    final drag = (1 - math.exp(-1.5 * t)) / 1.5;
    return origin + Offset(velocity.dx * drag + math.sin(t * 6 + wobble) * 6, velocity.dy * drag + 450 * t * t);
  }
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
  late final AnimationController _c = AnimationController(vsync: this, duration: _length);
  final _r = math.Random();
  List<_Particle>? _particles;

  static const _length = Duration(milliseconds: 2200);

  @override
  void initState() {
    super.initState();
    _c.forward().whenComplete(widget.onDone);
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    _particles ??= List.generate(widget.count, (i) {
      final fromTop = widget.from == null;
      final origin = widget.from ?? Offset(_r.nextDouble() * size.width, -20);
      return _Particle(_r, origin, widget.colours[i % widget.colours.length], fromTop: fromTop);
    });
    return RepaintBoundary(
      child: CustomPaint(size: size, painter: _Painter(_particles!, widget.burst, _c)),
    );
  }
}

class _Painter extends CustomPainter {
  _Painter(this.particles, this.burst, this.progress) : super(repaint: progress);

  final List<_Particle> particles;
  final Burst burst;
  final Animation<double> progress;

  static final _star = _starPath();
  static final _gem = Path()
    ..moveTo(0, -1)
    ..lineTo(0.8, -0.2)
    ..lineTo(0, 1)
    ..lineTo(-0.8, -0.2)
    ..close();
  static final _paper = RRect.fromRectAndRadius(Rect.fromCenter(center: Offset.zero, width: 1, height: 0.55), const Radius.circular(0.15));

  @override
  void paint(Canvas canvas, Size size) {
    final t = progress.value * _ParticlesState._length.inMilliseconds / 1000;
    final opacity = 1 - ((progress.value - 0.65) / 0.35).clamp(0.0, 1.0);
    final paint = Paint();
    for (final p in particles) {
      final at = p.at(t);
      paint.color = p.colour.withValues(alpha: opacity);
      canvas
        ..save()
        ..translate(at.dx, at.dy)
        ..rotate(p.spin * t);
      switch (burst) {
        case Burst.confetti:
          canvas
            ..scale(p.size)
            ..drawRRect(_paper, paint);
        case Burst.stars:
          canvas
            ..scale(p.size * 0.75)
            ..drawPath(_star, paint);
        case Burst.gems:
          canvas
            ..scale(p.size * 0.8)
            ..drawPath(_gem, paint);
      }
      canvas.restore();
    }
  }

  static Path _starPath() {
    final path = Path();
    for (var i = 0; i < 10; i++) {
      final radius = i.isEven ? 1.0 : 0.45;
      final a = -math.pi / 2 + i * math.pi / 5;
      final pt = Offset(math.cos(a) * radius, math.sin(a) * radius);
      i == 0 ? path.moveTo(pt.dx, pt.dy) : path.lineTo(pt.dx, pt.dy);
    }
    return path..close();
  }

  @override
  bool shouldRepaint(_Painter old) => old.particles != particles || old.burst != burst;
}
