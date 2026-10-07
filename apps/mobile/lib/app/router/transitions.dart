import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../shared/motion/motion.dart';

/// A pushed screen comes in fading and growing a little; going back reverses it.
Page<void> fadeScalePage(GoRouterState state, Widget child) => CustomTransitionPage<void>(
  key: state.pageKey,
  child: child,
  transitionDuration: const Duration(milliseconds: 320),
  reverseTransitionDuration: const Duration(milliseconds: 240),
  transitionsBuilder: (context, animation, secondary, child) {
    if (context.calm) return child;
    final curved = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic, reverseCurve: Curves.easeInCubic);
    return FadeTransition(
      opacity: curved,
      child: ScaleTransition(scale: Tween(begin: 0.94, end: 1.0).animate(curved), child: child),
    );
  },
);

/// The tabs of a look: each keeps its place (only the shown one is laid
/// out and ticks), and the one switched to fades in, settling from a
/// little lower.
class AnimatedBranches extends StatelessWidget {
  const AnimatedBranches({required this.index, required this.children, super.key});

  final int index;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Stack(
    fit: StackFit.expand,
    children: [
      for (var i = 0; i < children.length; i++)
        Offstage(
          offstage: i != index,
          child: TickerMode(
            enabled: i == index,
            child: _Entrance(shown: i == index, child: children[i]),
          ),
        ),
    ],
  );
}

class _Entrance extends StatefulWidget {
  const _Entrance({required this.shown, required this.child});

  final bool shown;
  final Widget child;

  @override
  State<_Entrance> createState() => _EntranceState();
}

class _EntranceState extends State<_Entrance> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 340), value: 1);
  late final Animation<double> _fade = CurvedAnimation(parent: _c, curve: Curves.easeOut);
  late final Animation<Offset> _slide = Tween(
    begin: const Offset(0, 0.03),
    end: Offset.zero,
  ).animate(CurvedAnimation(parent: _c, curve: Curves.easeOutCubic));

  @override
  void didUpdateWidget(_Entrance old) {
    super.didUpdateWidget(old);
    if (widget.shown && !old.shown && !context.calm) _c.forward(from: 0);
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FadeTransition(
    opacity: _fade,
    child: SlideTransition(position: _slide, child: widget.child),
  );
}
