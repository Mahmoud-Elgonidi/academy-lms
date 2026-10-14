import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../motion/motion.dart';
import 'lms.dart';

/// A grey block where something is on its way, with a light passing over
/// it (still when the phone asks for less motion).
class Skeleton extends StatelessWidget {
  const Skeleton({this.width, this.height = 12, this.radius = 6, super.key});

  final double? width;
  final double height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final block = Container(
      width: width,
      height: height,
      decoration: BoxDecoration(color: context.colors.chip, borderRadius: BorderRadius.circular(radius)),
    );
    if (context.calm) return block;
    return block
        .animate(onPlay: (c) => c.repeat())
        .shimmer(duration: const Duration(milliseconds: 1300), color: Colors.white.withValues(alpha: 0.7));
  }
}

/// Cards shaped like the ones on their way: a square and two lines each.
class SkeletonList extends StatelessWidget {
  const SkeletonList({this.count = 3, this.padding = pagePadding, super.key});

  final int count;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) => Semantics(
    label: MaterialLocalizations.of(context).refreshIndicatorSemanticLabel,
    child: ListView(
      padding: padding,
      physics: const NeverScrollableScrollPhysics(),
      children: [
        for (var i = 0; i < count; i++) ...[
          if (i > 0) const Gap(Space.m),
          LmsCard(
            child: Row(
              children: [
                const Skeleton(width: 40, height: 40, radius: 12),
                const Gap(Space.m),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      FractionallySizedBox(widthFactor: i.isEven ? 0.7 : 0.55, child: const Skeleton()),
                      const Gap(Space.s),
                      FractionallySizedBox(widthFactor: i.isEven ? 0.4 : 0.5, child: const Skeleton(height: 10)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    ),
  );
}
