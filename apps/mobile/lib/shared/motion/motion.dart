import 'dart:math' as math;

import 'package:flutter/gestures.dart' show kTouchSlop;
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../app/theme/app_theme.dart';
import '../../app/theme/stage.dart';
import '../../core/auth/session_cubit.dart';
import 'juice.dart';

export 'package:flutter_animate/flutter_animate.dart';

/// How a look moves and celebrates: the kids' own world; a guardian's (and
/// an adult student's) playful bounce; a teacher's quick one that never
/// slows the work; middle school's lively spring with stars; teens' calm.
enum MotionFlavour { kids, playful, quick, lively, calm }

extension MotionContext on BuildContext {
  /// The person asked their phone for less motion: show things still.
  bool get calm => MediaQuery.disableAnimationsOf(this);

  MotionFlavour get flavour => switch (shape.stage) {
    Stage.kids => MotionFlavour.kids,
    Stage.middle => MotionFlavour.lively,
    Stage.teen => MotionFlavour.calm,
    Stage.classic => _teaching(this) ? MotionFlavour.quick : MotionFlavour.playful,
  };

  /// How things move in this look: springy for kids, lively for middle
  /// school, a confident bounce for families, quick for teachers, smooth
  /// for teens.
  Curve get springCurve => switch (flavour) {
    MotionFlavour.kids => Curves.elasticOut,
    MotionFlavour.lively || MotionFlavour.playful => Curves.easeOutBack,
    MotionFlavour.quick || MotionFlavour.calm => Curves.easeOutCubic,
  };

  Duration get springDuration => switch (flavour) {
    MotionFlavour.kids => const Duration(milliseconds: 900),
    MotionFlavour.lively => const Duration(milliseconds: 560),
    MotionFlavour.playful => const Duration(milliseconds: 420),
    MotionFlavour.quick => const Duration(milliseconds: 280),
    MotionFlavour.calm => const Duration(milliseconds: 360),
  };
}

/// Whether the account on screen teaches (no session around a widget
/// alone: no).
bool _teaching(BuildContext context) {
  try {
    return BlocProvider.of<SessionCubit>(context).state.current?.isTeacher ?? false;
  } on Object {
    return false;
  }
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

/// A number that counts up to its value (from the last one shown), with
/// [decimals] places or in its own [format] ("758 ر.س").
class CountUp extends StatefulWidget {
  const CountUp(
    this.value, {
    this.from,
    this.style,
    this.prefix = '',
    this.suffix = '',
    this.decimals = 0,
    this.format,
    this.duration = const Duration(milliseconds: 900),
    super.key,
  });

  final num value;

  /// Where the first count starts (0 if not given).
  final num? from;
  final TextStyle? style;
  final String prefix;
  final String suffix;
  final int decimals;
  final String Function(num value)? format;
  final Duration duration;

  @override
  State<CountUp> createState() => _CountUpState();
}

class _CountUpState extends State<CountUp> {
  late num _from = widget.from ?? 0;

  @override
  void didUpdateWidget(CountUp old) {
    super.didUpdateWidget(old);
    if (old.value != widget.value) _from = old.value;
  }

  String _text(num v) {
    final shown = widget.decimals == 0 ? v.round() : num.parse(v.toStringAsFixed(widget.decimals));
    return '${widget.prefix}${widget.format?.call(shown) ?? (widget.decimals == 0 ? '$shown' : shown.toStringAsFixed(widget.decimals))}${widget.suffix}';
  }

  @override
  Widget build(BuildContext context) {
    if (context.calm) return Text(_text(widget.value), style: widget.style);
    return TweenAnimationBuilder<double>(
      key: ValueKey(widget.value),
      tween: Tween(begin: _from.toDouble(), end: widget.value.toDouble()),
      duration: widget.duration,
      curve: Curves.easeOutCubic,
      builder: (context, v, _) => Text(_text(v), style: widget.style),
    );
  }
}

/// Shrinks a little under the finger and springs back, as deep as the look
/// wants. With [onTap] it handles the tap itself (and makes the look's tap
/// sound); without, it only follows the finger over a child that handles
/// its own tap (a button, an InkWell: wrap that callback with [withPress]).
class PressScale extends StatefulWidget {
  const PressScale({required this.child, this.onTap, this.sound = true, super.key});

  final Widget child;
  final VoidCallback? onTap;
  final bool sound;

  /// [onTap] with the look's tap sound and buzz before it.
  static VoidCallback? withPress(BuildContext context, VoidCallback? onTap) {
    if (onTap == null) return null;
    final kids = context.flavour == MotionFlavour.kids;
    return () {
      kids ? Juice.tap() : Juice.press();
      onTap();
    };
  }

  @override
  State<PressScale> createState() => _PressScaleState();
}

class _PressScaleState extends State<PressScale> {
  bool _down = false;
  Offset? _at;

  void _set(bool down) {
    if (_down != down) setState(() => _down = down);
  }

  double _depth(BuildContext context) => switch (context.flavour) {
    MotionFlavour.kids => 0.94,
    MotionFlavour.lively => 0.93,
    MotionFlavour.playful => 0.96,
    MotionFlavour.quick => 0.97,
    MotionFlavour.calm => 0.98,
  };

  @override
  Widget build(BuildContext context) {
    final scaled = AnimatedScale(
      scale: _down && !context.calm ? _depth(context) : 1,
      duration: Duration(milliseconds: _down ? 110 : 260),
      curve: _down ? Curves.easeOut : Curves.easeOutBack,
      child: widget.child,
    );
    final onTap = widget.onTap;
    if (onTap != null) {
      return GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTapDown: (_) => _set(true),
        onTapCancel: () => _set(false),
        onTapUp: (_) => _set(false),
        onTap: widget.sound ? PressScale.withPress(context, onTap) : onTap,
        child: scaled,
      );
    }
    // Only follow the finger; the child takes the tap. A drag (a scroll)
    // lets go.
    return Listener(
      onPointerDown: (e) {
        _at = e.position;
        _set(true);
      },
      onPointerMove: (e) {
        if (_at != null && (e.position - _at!).distance > kTouchSlop) _set(false);
      },
      onPointerUp: (_) => _set(false),
      onPointerCancel: (_) => _set(false),
      child: scaled,
    );
  }
}

/// The name the students' screens already use.
typedef Pressable = PressScale;
