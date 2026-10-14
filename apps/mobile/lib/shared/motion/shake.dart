import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'motion.dart';

/// Shakes its child side to side, a little less each time, whenever [count]
/// goes up: "no, not like that" (a wrong code, a field left empty). Under
/// less motion it stays still; the words beside it say what is wrong.
class Shake extends StatefulWidget {
  const Shake({required this.count, required this.child, super.key});

  final int count;
  final Widget child;

  @override
  State<Shake> createState() => _ShakeState();
}

class _ShakeState extends State<Shake> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 420));

  @override
  void didUpdateWidget(Shake old) {
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
      final x = t == 0 || t == 1 ? 0.0 : math.sin(t * math.pi * 5) * 10 * (1 - t);
      return Transform.translate(offset: Offset(x, 0), child: child);
    },
  );
}
