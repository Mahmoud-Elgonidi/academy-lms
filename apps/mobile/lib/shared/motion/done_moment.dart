import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/semantics.dart';

import '../../app/theme/app_theme.dart';
import 'celebration.dart';
import 'juice.dart';
import 'motion.dart';

/// Something just got done (a receipt sent, a lesson cancelled, a report
/// written): a check draws itself on a card over the screen with the
/// look's own celebration, then it goes. The screen underneath keeps
/// working; a screen reader hears [title].
class DoneMoment {
  DoneMoment._();

  static void show(BuildContext context, {required String title, String? subtitle}) {
    final flavour = context.flavour;
    SemanticsService.sendAnnouncement(View.of(context), title, Directionality.of(context));
    switch (flavour) {
      case MotionFlavour.kids:
        Juice.big(Sound.cheer);
      case MotionFlavour.calm:
        Juice.done(sound: Sound.coinSoft, light: true);
      case MotionFlavour.playful || MotionFlavour.quick || MotionFlavour.lively:
        Juice.done();
    }
    final overlay = Overlay.maybeOf(context, rootOverlay: true);
    if (overlay == null) return;
    final centre = MediaQuery.sizeOf(context).center(const Offset(0, -40));
    if (!context.calm) {
      switch (flavour) {
        case MotionFlavour.kids:
          Celebration.show(context, burst: Burst.stars, from: centre, count: 40);
        case MotionFlavour.playful:
          Celebration.show(context, from: centre, count: 44);
        case MotionFlavour.quick:
          Celebration.show(context, from: centre, count: 18);
        case MotionFlavour.lively:
          Celebration.show(context, burst: Burst.stars, from: centre, count: 30);
        case MotionFlavour.calm:
          break;
      }
    }
    late final OverlayEntry entry;
    entry = OverlayEntry(
      builder: (_) => IgnorePointer(
        child: _Card(title: title, subtitle: subtitle, flavour: flavour, onDone: () => entry.remove()),
      ),
    );
    overlay.insert(entry);
  }

  /// How long the card stays, by look: a teacher's is the shortest.
  static Duration lengthFor(MotionFlavour flavour) => switch (flavour) {
    MotionFlavour.quick => const Duration(milliseconds: 1000),
    MotionFlavour.calm => const Duration(milliseconds: 1500),
    _ => const Duration(milliseconds: 1600),
  };
}

class _Card extends StatefulWidget {
  const _Card({required this.title, required this.flavour, required this.onDone, this.subtitle});

  final String title;
  final String? subtitle;
  final MotionFlavour flavour;
  final VoidCallback onDone;

  @override
  State<_Card> createState() => _CardState();
}

class _CardState extends State<_Card> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: DoneMoment.lengthFor(widget.flavour));

  @override
  void initState() {
    super.initState();
    // The wait before it goes is part of its own timeline, not a timer.
    _c.forward().whenComplete(widget.onDone);
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final still = context.calm;
    final calmLook = widget.flavour == MotionFlavour.calm;
    final enter = calmLook ? Curves.easeOutCubic : Curves.easeOutBack;
    return Center(
      child: AnimatedBuilder(
        animation: _c,
        builder: (context, _) {
          final t = _c.value;
          final shown = still ? 1.0 : enter.transform((t / 0.2).clamp(0.0, 1.0));
          final draw = still ? 1.0 : Curves.easeInOut.transform(((t - 0.12) / 0.3).clamp(0.0, 1.0));
          final leave = still ? 1.0 : 1 - ((t - 0.85) / 0.15).clamp(0.0, 1.0);
          return Opacity(
            opacity: (leave * math.min(1, shown * 2)).clamp(0.0, 1.0),
            child: Transform.scale(
              scale: calmLook ? 0.96 + 0.04 * shown : 0.6 + 0.4 * shown,
              child: Material(
                color: c.card,
                elevation: 12,
                shadowColor: Colors.black26,
                borderRadius: BorderRadius.circular(context.shape.card + 6),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(28, 24, 28, 22),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      SizedBox.square(
                        dimension: calmLook ? 64 : 76,
                        child: CustomPaint(
                          painter: _Check(progress: draw, colour: c.brand, calm: calmLook),
                        ),
                      ),
                      const SizedBox(height: 14),
                      ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 240),
                        child: Text(widget.title, textAlign: TextAlign.center, style: context.text.titleMedium?.copyWith(fontSize: 17)),
                      ),
                      if (widget.subtitle != null) ...[
                        const SizedBox(height: 4),
                        ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 240),
                          child: Text(widget.subtitle!, textAlign: TextAlign.center, style: context.text.bodySmall),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

/// A filled circle with a white check drawn in (the families' and the
/// teachers'), or a thin ring and check drawn in the brand's colour (teens').
class _Check extends CustomPainter {
  _Check({required this.progress, required this.colour, required this.calm});

  final double progress;
  final Color colour;
  final bool calm;

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
    if (calm) {
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
  bool shouldRepaint(_Check old) => old.progress != progress || old.colour != colour;
}
