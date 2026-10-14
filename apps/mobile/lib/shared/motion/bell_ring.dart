import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'motion.dart';

/// Rings its child (a bell) once each time [count] goes up: a swing that
/// settles, the way a real bell stops.
class BellRing extends StatefulWidget {
  const BellRing({required this.count, required this.child, super.key});

  final int count;
  final Widget child;

  @override
  State<BellRing> createState() => _BellRingState();
}

class _BellRingState extends State<BellRing> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));

  @override
  void didUpdateWidget(BellRing old) {
    super.didUpdateWidget(old);
    if (widget.count > old.count && !context.calm) _c.forward(from: 0);
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _c,
    child: widget.child,
    builder: (context, child) {
      final t = _c.value;
      final angle = t == 0 || t == 1 ? 0.0 : math.sin(t * math.pi * 6) * 0.35 * (1 - t);
      return Transform.rotate(angle: angle, alignment: const Alignment(0, -0.8), child: child);
    },
  );
}
