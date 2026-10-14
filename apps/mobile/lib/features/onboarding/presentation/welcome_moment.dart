import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/semantics.dart';

import '../../../app/theme/app_theme.dart';
import '../../../app/theme/branding.dart';
import '../../../core/auth/account.dart';
import '../../../core/l10n/l10n.dart';
import '../../../shared/motion/celebration.dart';
import '../../../shared/motion/juice.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';

/// Signed in just now: the academy's colour rises over the screen from
/// below, its logo pops up, "Hi, Amr" and where they are, with the look's
/// own celebration and sound; then it lifts off the home underneath. It
/// takes about a second and a half (a teacher's is quicker), never holds the
/// screen (taps go through, and the first one ends it), and under less
/// motion it shows still for a moment and goes.
class WelcomeMoment extends StatefulWidget {
  const WelcomeMoment({required this.account, required this.onDone, super.key});

  final Account account;
  final VoidCallback onDone;

  @override
  State<WelcomeMoment> createState() => _WelcomeMomentState();
}

class _WelcomeMomentState extends State<WelcomeMoment> with SingleTickerProviderStateMixin {
  // Its length is how long the greeting stays, also when the phone asks for less motion.
  late final AnimationController _c = AnimationController(vsync: this, animationBehavior: AnimationBehavior.preserve);
  bool _started = false;
  bool _done = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    final flavour = context.flavour;
    _c.duration = switch (flavour) {
      _ when context.calm => const Duration(milliseconds: 1200),
      MotionFlavour.quick => const Duration(milliseconds: 1300),
      MotionFlavour.kids => const Duration(milliseconds: 2000),
      _ => const Duration(milliseconds: 1700),
    };
    _c.forward().whenCompleteOrCancel(_finish);
    final hello = context.l10n.welcomeHello(_firstName);
    SemanticsService.sendAnnouncement(View.of(context), hello, Directionality.of(context));
    switch (flavour) {
      case MotionFlavour.kids:
        Juice.big(Sound.cheer);
      case MotionFlavour.calm:
        Juice.done(sound: Sound.coinSoft, light: true);
      case MotionFlavour.playful || MotionFlavour.quick || MotionFlavour.lively:
        Juice.done();
    }
    // The celebration bursts once the colour has risen.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || context.calm) return;
      final from = MediaQuery.sizeOf(context).center(const Offset(0, -60));
      final count = switch (flavour) {
        MotionFlavour.quick => 20,
        MotionFlavour.calm => 0,
        MotionFlavour.kids => 50,
        _ => 46,
      };
      if (count > 0) {
        Celebration.show(context, from: from, count: count, burst: flavour == MotionFlavour.lively ? Burst.stars : Burst.confetti);
      }
    });
  }

  String get _firstName => widget.account.userName.trim().split(RegExp(r'\s+')).first;

  void _finish() {
    if (_done) return;
    _done = true;
    widget.onDone();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  double _part(double from, double to, [Curve curve = Curves.easeOutCubic]) =>
      curve.transform(((_c.value - from) / (to - from)).clamp(0.0, 1.0));

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    final calm = context.calm;
    final account = widget.account;
    final academy = account.academyName(context.locale);
    return Listener(
      // Taps reach the screen underneath; the first one also ends the greeting.
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => _c.isAnimating && _c.value < 0.8 ? _c.animateTo(1, duration: const Duration(milliseconds: 200)) : null,
      child: IgnorePointer(
        child: AnimatedBuilder(
          animation: _c,
          builder: (context, _) {
            final rise = calm ? 1.0 : _part(0, 0.28, Curves.easeInOutCubic);
            final logo = calm ? 1.0 : _part(0.14, 0.42, Curves.easeOutBack);
            final words = calm ? 1.0 : _part(0.24, 0.5);
            final leave = _part(0.8, 1, Curves.easeInCubic);
            return Opacity(
              opacity: 1 - leave,
              // Over the shell, outside its page: the app's own text style.
              child: Material(
                type: MaterialType.transparency,
                child: ClipPath(
                  clipper: _Rise(rise),
                  child: ColoredBox(
                    color: c.brand,
                    child: SafeArea(
                      child: Center(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 32),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Transform.scale(
                                scale: (0.4 + 0.6 * logo) * (1 + 0.06 * leave),
                                child: _Logo(account: account),
                              ),
                              const SizedBox(height: 24),
                              Opacity(
                                opacity: words,
                                child: Transform.translate(
                                  offset: Offset(0, 18 * (1 - words)),
                                  child: Column(
                                    children: [
                                      Text(
                                        l.welcomeHello(_firstName),
                                        textAlign: TextAlign.center,
                                        style: TextStyle(
                                          fontFamily: context.shape.headingFont,
                                          fontSize: 32,
                                          height: 1.3,
                                          fontWeight: FontWeight.w700,
                                          color: Colors.white,
                                        ),
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        l.welcomeIn(academy),
                                        textAlign: TextAlign.center,
                                        style: TextStyle(fontSize: 17, height: 1.5, color: Colors.white.withValues(alpha: 0.85)),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

/// The academy's logo on a white tile, or its first letter while there is none (or it does not load).
class _Logo extends StatelessWidget {
  const _Logo({required this.account});

  final Account account;

  @override
  Widget build(BuildContext context) {
    final name = account.academyName(context.locale);
    final letter = InitialAvatar(name, colour: Branding.parseColour(account.academyColour) ?? context.colors.brand, size: 72);
    final url = account.logoUrl;
    return Container(
      width: 104,
      height: 104,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(30),
        boxShadow: const [BoxShadow(color: Color(0x33000000), blurRadius: 30, offset: Offset(0, 14))],
      ),
      child: url == null || url.isEmpty
          ? letter
          : Image.network(url, fit: BoxFit.contain, errorBuilder: (_, _, _) => letter, semanticLabel: name),
    );
  }
}

/// A circle growing from near the bottom until it covers the screen.
class _Rise extends CustomClipper<Path> {
  _Rise(this.t);

  final double t;

  @override
  Path getClip(Size size) {
    final from = Offset(size.width / 2, size.height * 0.84);
    final reach = math.sqrt(size.width * size.width + size.height * size.height) * 1.05;
    return Path()..addOval(Rect.fromCircle(center: from, radius: reach * t));
  }

  @override
  bool shouldReclip(_Rise old) => old.t != t;
}
