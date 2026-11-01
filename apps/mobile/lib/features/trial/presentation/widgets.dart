import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';

/// Where the trial was opened from (/welcome, or /add for another account), to sign in from there.
class TrialPlace {
  const TrialPlace(this.base);

  final String base;
}

/// The sign-in's first step with the family's number already typed.
String signInWith(BuildContext context, String phone) =>
    Uri(path: context.read<TrialPlace>().base, queryParameters: phone.isEmpty ? null : {'contact': phone}).toString();

/// Parts read out one after another, with the language's own comma.
String joinSpoken(BuildContext context, List<String> parts) => parts.where((p) => p.isNotEmpty).join(context.locale == 'ar' ? '، ' : ', ');

/// One step of the trial: its title and lead, what it asks (coming in one
/// after another), and its buttons kept at the foot of the screen, above the
/// keyboard. Everything above the buttons scrolls, so a small phone with
/// large text still reaches all of it.
class StepBody extends StatelessWidget {
  const StepBody({required this.title, required this.children, this.lead, this.bottom, this.badge, super.key});

  final String title;
  final Widget? lead;
  final List<Widget> children;
  final Widget? bottom;

  /// A picture over the title (a code on its way).
  final IconData? badge;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final parts = <Widget>[
      if (badge != null)
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: Padding(
            padding: const EdgeInsets.only(bottom: Space.l),
            child: Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(20)),
              child: Icon(badge, size: 30, color: c.brandDeep),
            ),
          ),
        ),
      Semantics(
        header: true,
        child: Text(
          title,
          style: TextStyle(fontFamily: context.shape.headingFont, fontSize: 26, height: 1.3, fontWeight: FontWeight.w700, color: c.ink),
        ),
      ),
      if (lead != null)
        Padding(
          padding: const EdgeInsets.only(top: Space.s),
          child: lead,
        ),
      const Gap(Space.xl),
      ...children,
    ];
    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, Space.s, 20, Space.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [for (final (i, part) in parts.indexed) part is Gap ? part : part.appear(context, i)],
            ),
          ),
        ),
        if (bottom != null)
          DecoratedBox(
            decoration: BoxDecoration(
              color: c.page,
              border: Border(top: BorderSide(color: c.line)),
            ),
            child: Padding(padding: const EdgeInsets.fromLTRB(20, Space.m, 20, Space.m), child: bottom),
          ),
      ],
    );
  }
}

/// The step's lead: a calm line under the title.
class Lead extends StatelessWidget {
  const Lead(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(text, style: TextStyle(fontSize: 15.5, height: 1.65, color: context.colors.muted));
}

/// A big card to pick one thing (who is learning, a subject, help choosing):
/// an icon, a title and a line under it; picked, it takes the brand's colour
/// and a check. Read out as a button that is, or is not, selected.
class ChoiceCard extends StatelessWidget {
  const ChoiceCard({
    required this.title,
    required this.icon,
    required this.onTap,
    this.subtitle,
    this.selected = false,
    this.tone = Tone.brand,
    this.trailing,
    super.key,
  });

  final String title;
  final String? subtitle;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;
  final Tone tone;

  /// Shown at the end when not selected (a way on).
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final (soft, ink) = tone.colours(context);
    final radius = BorderRadius.circular(context.shape.card);
    return Semantics(
      button: true,
      selected: selected,
      label: joinSpoken(context, [title, ?subtitle]),
      excludeSemantics: true,
      child: PressScale(
        onTap: onTap,
        child: AnimatedContainer(
          duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          constraints: const BoxConstraints(minHeight: 72),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: selected ? c.brandSoft : c.card,
            borderRadius: radius,
            border: Border.all(color: selected ? c.brand : c.line, width: selected ? 2 : 1),
          ),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(color: selected ? c.card : soft, borderRadius: BorderRadius.circular(14)),
                child: Icon(icon, color: ink, size: 24),
              ),
              const Gap(Space.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: context.type.cardTitle),
                    if (subtitle != null && subtitle!.isNotEmpty) ...[const Gap(2), Text(subtitle!, style: context.type.caption)],
                  ],
                ),
              ),
              const Gap(Space.s),
              AnimatedSwitcher(
                duration: context.calm ? Duration.zero : const Duration(milliseconds: 200),
                transitionBuilder: (child, a) => ScaleTransition(scale: a, child: child),
                child: selected
                    ? Icon(Icons.check_circle_rounded, key: const ValueKey('on'), color: c.brand, size: 26)
                    : (trailing ??
                          Icon(
                            Icons.chevron_right_rounded,
                            key: const ValueKey('off'),
                            color: c.faint,
                            textDirection: Directionality.of(context),
                          )),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A rounded pill to pick among a few (a day, a time of day, a teacher, an age, a time).
class TrialPill extends StatelessWidget {
  const TrialPill({
    required this.label,
    required this.selected,
    required this.onTap,
    this.sub,
    this.semantics,
    this.wide = false,
    super.key,
  });

  final String label;
  final String? sub;
  final bool selected;
  final VoidCallback onTap;

  /// What a screen reader says instead of [label] (a time's whole day and hour).
  final String? semantics;

  /// A bigger target (a time).
  final bool wide;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Semantics(
      button: true,
      selected: selected,
      label: semantics ?? joinSpoken(context, [label, ?sub]),
      excludeSemantics: true,
      child: PressScale(
        onTap: onTap,
        child: AnimatedContainer(
          duration: context.calm ? Duration.zero : const Duration(milliseconds: 180),
          constraints: BoxConstraints(minHeight: 48, minWidth: wide ? 96 : 48),
          padding: EdgeInsets.symmetric(horizontal: wide ? 18 : 14, vertical: 10),
          decoration: BoxDecoration(
            color: selected ? c.brand : c.card,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: selected ? c.brand : c.line, width: 1.5),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                label,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: wide ? 16 : 15,
                  fontWeight: FontWeight.w700,
                  color: selected ? Colors.white : c.ink,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
              if (sub != null)
                Text(
                  sub!,
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 12, color: selected ? Colors.white.withValues(alpha: 0.9) : c.muted),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A heading over a group of pills.
class GroupLabel extends StatelessWidget {
  const GroupLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: Space.s, top: Space.xs),
    child: Semantics(
      header: true,
      child: Text(
        text,
        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: context.colors.ink),
      ),
    ),
  );
}

/// A field's error under it, read out as it appears.
class FieldError extends StatelessWidget {
  const FieldError(this.text, {super.key});

  final String? text;

  @override
  Widget build(BuildContext context) => Semantics(
    liveRegion: true,
    child: AnimatedSize(
      duration: context.calm ? Duration.zero : const Duration(milliseconds: 180),
      child: text == null
          ? const SizedBox(width: double.infinity)
          : Padding(
              padding: const EdgeInsets.only(top: Space.s),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.error_outline_rounded, size: 18, color: context.colors.danger),
                  const Gap(6),
                  Expanded(
                    child: Text(
                      text!,
                      style: TextStyle(fontSize: 14, height: 1.5, color: context.colors.danger, fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
    ),
  );
}

/// "Send a new code in 0:42" with a ring running down, then [ready]. The
/// ring runs on the screen's own animation clock (no timer), and keeps its
/// length when the phone asks for less motion: the wait is real.
class ResendCountdown extends StatefulWidget {
  const ResendCountdown({required this.sentAt, required this.seconds, required this.ready, super.key});

  final DateTime sentAt;
  final int seconds;

  /// What shows once a new code may be asked for.
  final Widget ready;

  @override
  State<ResendCountdown> createState() => _ResendCountdownState();
}

class _ResendCountdownState extends State<ResendCountdown> with SingleTickerProviderStateMixin {
  late final Duration _wait = Duration(seconds: math.max(0, widget.seconds));
  late final AnimationController _c = AnimationController(
    vsync: this,
    animationBehavior: AnimationBehavior.preserve,
    duration: _wait.inMilliseconds > 0 ? _wait : const Duration(milliseconds: 1),
  );

  @override
  void initState() {
    super.initState();
    final gone = DateTime.now().difference(widget.sentAt);
    _c.value = _wait.inMilliseconds <= 0 ? 1 : (gone.inMilliseconds / _wait.inMilliseconds).clamp(0.0, 1.0);
    if (_c.value < 1) _c.forward();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _c,
    builder: (context, _) {
      if (_c.value >= 1) return widget.ready;
      final c = context.colors;
      final seconds = ((1 - _c.value) * _wait.inMilliseconds / 1000).ceil();
      final mmss = '${seconds ~/ 60}:${(seconds % 60).toString().padLeft(2, '0')}';
      return SizedBox(
        height: 48,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox.square(
              dimension: 22,
              child: CustomPaint(
                painter: _Ring(left: 1 - _c.value, track: c.line, colour: c.brand),
              ),
            ),
            const Gap(10),
            Flexible(
              child: Text(context.l10n.resendIn(mmss), style: TextStyle(fontSize: 15, color: c.muted)),
            ),
          ],
        ),
      );
    },
  );
}

class _Ring extends CustomPainter {
  _Ring({required this.left, required this.track, required this.colour});

  final double left;
  final Color track;
  final Color colour;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromCircle(center: size.center(Offset.zero), radius: size.shortestSide / 2 - 2);
    final line = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round;
    canvas.drawArc(rect, 0, 2 * math.pi, false, line..color = track);
    canvas.drawArc(rect, -math.pi / 2, 2 * math.pi * left, false, line..color = colour);
  }

  @override
  bool shouldRepaint(_Ring old) => old.left != left || old.colour != colour;
}
