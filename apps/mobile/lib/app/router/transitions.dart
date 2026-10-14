import 'package:flutter/cupertino.dart';
import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';

import '../../shared/motion/motion.dart';

/// A pushed screen comes in fading and growing a little; going back reverses it.
/// On iPhones it slides in the platform's way instead, so a swipe from the
/// edge goes back.
Page<void> fadeScalePage(GoRouterState state, Widget child) => defaultTargetPlatform == TargetPlatform.iOS
    ? CupertinoPage<void>(key: state.pageKey, child: child)
    : CustomTransitionPage<void>(
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

/// A step of a flow (signing in, writing a report, cancelling): the next
/// step comes in from the side the reading goes, the one before goes back
/// out that way. iPhones slide in their own way, with the swipe back.
Page<void> stepPage(GoRouterState state, Widget child) => defaultTargetPlatform == TargetPlatform.iOS
    ? CupertinoPage<void>(key: state.pageKey, child: child)
    : CustomTransitionPage<void>(
        key: state.pageKey,
        child: child,
        transitionDuration: const Duration(milliseconds: 300),
        reverseTransitionDuration: const Duration(milliseconds: 260),
        transitionsBuilder: (context, animation, secondary, child) {
          if (context.calm) return child;
          final forward = Directionality.of(context) == TextDirection.rtl ? -1.0 : 1.0;
          final inCurve = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic, reverseCurve: Curves.easeInCubic);
          final outCurve = CurvedAnimation(parent: secondary, curve: Curves.easeOutCubic, reverseCurve: Curves.easeInCubic);
          // In from the reading side; the step underneath moves a little the other way and fades.
          return SlideTransition(
            position: Tween(begin: Offset(0.18 * forward, 0), end: Offset.zero).animate(inCurve),
            child: FadeTransition(
              opacity: inCurve,
              child: SlideTransition(
                position: Tween(begin: Offset.zero, end: Offset(-0.12 * forward, 0)).animate(outCurve),
                child: FadeTransition(opacity: Tween(begin: 1.0, end: 0.0).animate(outCurve), child: child),
              ),
            ),
          );
        },
      );
