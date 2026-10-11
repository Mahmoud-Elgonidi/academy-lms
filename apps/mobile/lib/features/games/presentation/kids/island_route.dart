import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../shared/motion/motion.dart';

/// An island opening over the map: a circle grows from where the island now
/// is until it fills the screen; closing shrinks it back.
class IslandRoute<T> extends PageRouteBuilder<T> {
  IslandRoute({required Offset origin, required bool quiet, required WidgetBuilder builder})
    : super(
        opaque: false,
        barrierColor: null,
        transitionDuration: quiet ? Duration.zero : const Duration(milliseconds: 600),
        reverseTransitionDuration: quiet ? Duration.zero : const Duration(milliseconds: 520),
        pageBuilder: (context, _, _) => builder(context),
        transitionsBuilder: (context, animation, _, child) {
          if (context.calm) return child;
          final curved = CurvedAnimation(parent: animation, curve: const Cubic(0.6, 0, 0.3, 1));
          return AnimatedBuilder(
            animation: curved,
            builder: (context, child) {
              final size = MediaQuery.sizeOf(context);
              final r = curved.value * math.sqrt(size.width * size.width + size.height * size.height) * 1.2;
              return ClipPath(clipper: _Circle(origin, r), child: child);
            },
            child: child,
          );
        },
      );
}

class _Circle extends CustomClipper<Path> {
  const _Circle(this.centre, this.radius);

  final Offset centre;
  final double radius;

  @override
  Path getClip(Size size) => Path()..addOval(Rect.fromCircle(center: centre, radius: radius));

  @override
  bool shouldReclip(_Circle old) => old.radius != radius || old.centre != centre;
}
