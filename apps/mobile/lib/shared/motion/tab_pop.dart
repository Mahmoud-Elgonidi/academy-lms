import 'package:flutter/material.dart';

import 'motion.dart';

/// The icon of the tab just picked springs up from small, in the look's own
/// way (a big bounce for kids, a quick one for teachers, a soft one for
/// teens).
class TabPop extends StatelessWidget {
  const TabPop({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (context.calm) return child;
    final from = switch (context.flavour) {
      MotionFlavour.kids || MotionFlavour.lively => 0.55,
      MotionFlavour.playful => 0.7,
      MotionFlavour.quick || MotionFlavour.calm => 0.85,
    };
    return child.animate().scaleXY(begin: from, end: 1, duration: context.springDuration, curve: context.springCurve);
  }
}
