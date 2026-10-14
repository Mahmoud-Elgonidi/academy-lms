import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/app_mark.dart';

/// The app's first screen while it opens the saved accounts: the phone's
/// launch screen carried on (the same green, the same mark in the same
/// place), the star twinkling, two rings going out and the app's name
/// rising under it. It waits for nothing of its own: the router moves on
/// the moment the app is ready, wherever the animation is.
class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1400));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (context.calm) {
      _c.value = 1;
    } else if (!_c.isAnimating && _c.value == 0) {
      _c.forward();
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  /// [t]'s share of the time between [from] and [to], eased.
  double _part(double from, double to, [Curve curve = Curves.easeOutCubic]) =>
      curve.transform(((_c.value - from) / (to - from)).clamp(0.0, 1.0));

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(
    value: SystemUiOverlayStyle.light,
    child: Semantics(
      label: context.l10n.appName,
      container: true,
      excludeSemantics: true,
      child: ColoredBox(
        color: productGreen,
        child: AnimatedBuilder(
          animation: _c,
          builder: (context, _) {
            // The star swells and makes a quarter turn (it looks the same after one), then settles.
            final twinkle = _part(0.1, 0.55, Curves.easeInOutCubic);
            final swell = 1 + 0.28 * (twinkle < 0.5 ? twinkle * 2 : (1 - twinkle) * 2);
            final name = _part(0.35, 0.75);
            return Stack(
              alignment: Alignment.center,
              children: [
                if (!context.calm)
                  for (final start in const [0.15, 0.4]) _Ring(_part(start, start + 0.6, Curves.easeOut)),
                AppMark(star: swell, turn: twinkle),
                Transform.translate(
                  // Under the mark, which stays where the launch screen put it.
                  offset: Offset(0, 104 + 16 * (1 - name)),
                  child: Opacity(
                    opacity: name,
                    child: Text(
                      context.l10n.appName,
                      style: TextStyle(
                        fontFamily: context.shape.headingFont,
                        fontSize: 30,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    ),
  );
}

/// A thin ring going out from the mark and fading as it goes.
class _Ring extends StatelessWidget {
  const _Ring(this.t);

  final double t;

  @override
  Widget build(BuildContext context) => t == 0 || t == 1
      ? const SizedBox.shrink()
      : Transform.scale(
          scale: 0.85 + 1.05 * t,
          child: Container(
            width: 116,
            height: 116,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white.withValues(alpha: 0.7 * (1 - t)), width: 2),
            ),
          ),
        );
}
