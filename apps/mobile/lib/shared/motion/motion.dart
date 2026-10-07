import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';

import '../../app/theme/app_theme.dart';
import '../../app/theme/stage.dart';

export 'package:flutter_animate/flutter_animate.dart';

extension MotionContext on BuildContext {
  /// The person asked their phone for less motion: show things still.
  bool get calm => MediaQuery.disableAnimationsOf(this);

  /// How things move in this look: springy for kids, lively for middle
  /// school, quick and smooth for everyone else.
  Curve get springCurve => switch (shape.stage) {
    Stage.kids => Curves.elasticOut,
    Stage.middle => Curves.easeOutBack,
    _ => Curves.easeOutCubic,
  };

  Duration get springDuration => switch (shape.stage) {
    Stage.kids => const Duration(milliseconds: 900),
    Stage.middle => const Duration(milliseconds: 560),
    _ => const Duration(milliseconds: 380),
  };
}

extension Appear on Widget {
  /// Comes in after the ones before it: fades, rises and settles in the
  /// look's own way. (The wait is part of the effect, not a timer.)
  Widget appear(BuildContext context, int index, {double rise = 0.12}) {
    if (context.calm) return this;
    final kids = context.shape.stage == Stage.kids;
    final wait = Duration(milliseconds: 70 * index.clamp(0, 10));
    return animate()
        .fadeIn(delay: wait, duration: const Duration(milliseconds: 320))
        .slideY(begin: rise, end: 0, duration: context.springDuration, curve: context.springCurve)
        .scaleXY(begin: kids ? 0.86 : 0.97, end: 1, duration: context.springDuration, curve: context.springCurve);
  }

  /// A gentle, endless bob (something waiting for a tap).
  Widget bob(BuildContext context, {double by = 4}) {
    if (context.calm) return this;
    return animate(onPlay: (c) => c.repeat(reverse: true))
        .moveY(begin: 0, end: -by, duration: const Duration(milliseconds: 900), curve: Curves.easeInOut);
  }

  /// A wiggle every few seconds (the kids' chest when it is ready).
  Widget wiggle(BuildContext context) {
    if (context.calm) return this;
    return animate(onPlay: (c) => c.repeat()).custom(
      duration: const Duration(milliseconds: 2400),
      builder: (context, v, child) {
        final x = v / 0.3;
        return Transform.rotate(angle: x < 1 ? math.sin(x * math.pi * 6) * 0.08 * (1 - x) : 0, child: child);
      },
    );
  }

  /// A short swell now and then (a tab with rewards waiting).
  Widget pulse(BuildContext context, {double to = 1.15}) {
    if (context.calm) return this;
    return animate(onPlay: (c) => c.repeat())
        .scaleXY(begin: 1, end: to, duration: const Duration(milliseconds: 3000), curve: const BlipCurve(0.24));
  }

  /// A soft shine passing over something that is ready.
  Widget shimmerIf(BuildContext context, bool on, {Color? color}) {
    if (!on || context.calm) return this;
    return animate(onPlay: (c) => c.repeat()).shimmer(
      duration: const Duration(milliseconds: 2400),
      curve: const Interval(0, 0.75, curve: Curves.easeInOut),
      color: color ?? Colors.white.withValues(alpha: 0.6),
    );
  }
}

/// 0 → 1 → 0 within the first [share] of the time, then still. Used for
/// effects that repeat with a pause between.
class BlipCurve extends Curve {
  const BlipCurve([this.share = 0.25]);

  final double share;

  @override
  double transform(double t) {
    if (t >= share) return 0;
    final x = t / share;
    return x < 0.5 ? Curves.easeOut.transform(x * 2) : Curves.easeIn.transform((1 - x) * 2);
  }
}

/// Each child comes in after the one before it (the first few; the rest
/// are simply there).
List<Widget> staggered(BuildContext context, List<Widget> children) => [
  for (final (i, w) in children.indexed) w is SizedBox || i > 12 ? w : w.appear(context, i ~/ 2 + 1),
];

/// A number that counts up to its value (from the last one shown).
class CountUp extends StatefulWidget {
  const CountUp(
    this.value, {
    this.from,
    this.style,
    this.prefix = '',
    this.suffix = '',
    this.duration = const Duration(milliseconds: 900),
    super.key,
  });

  final int value;

  /// Where the first count starts (0 if not given).
  final int? from;
  final TextStyle? style;
  final String prefix;
  final String suffix;
  final Duration duration;

  @override
  State<CountUp> createState() => _CountUpState();
}

class _CountUpState extends State<CountUp> {
  late int _from = widget.from ?? 0;

  @override
  void didUpdateWidget(CountUp old) {
    super.didUpdateWidget(old);
    if (old.value != widget.value) _from = old.value;
  }

  @override
  Widget build(BuildContext context) {
    if (context.calm) return Text('${widget.prefix}${widget.value}${widget.suffix}', style: widget.style);
    return TweenAnimationBuilder<double>(
      key: ValueKey(widget.value),
      tween: Tween(begin: _from.toDouble(), end: widget.value.toDouble()),
      duration: widget.duration,
      curve: Curves.easeOutCubic,
      builder: (context, v, _) => Text('${widget.prefix}${v.round()}${widget.suffix}', style: widget.style),
    );
  }
}

/// Shrinks a little under the finger and springs back; a pop and a buzz.
class Pressable extends StatefulWidget {
  const Pressable({required this.child, required this.onTap, this.sound = true, super.key});

  final Widget child;
  final VoidCallback? onTap;
  final bool sound;

  @override
  State<Pressable> createState() => _PressableState();
}

class _PressableState extends State<Pressable> {
  bool _down = false;

  @override
  Widget build(BuildContext context) => GestureDetector(
    behavior: HitTestBehavior.opaque,
    onTapDown: widget.onTap == null ? null : (_) => setState(() => _down = true),
    onTapCancel: () => setState(() => _down = false),
    onTapUp: (_) => setState(() => _down = false),
    onTap: widget.onTap,
    child: AnimatedScale(
      scale: _down && !context.calm ? 0.94 : 1,
      duration: const Duration(milliseconds: 120),
      curve: Curves.easeOut,
      child: widget.child,
    ),
  );
}
