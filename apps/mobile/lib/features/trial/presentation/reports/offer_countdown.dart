import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';

/// What is left of a duration, in whole days, hours, minutes and seconds.
typedef TimeLeft = ({int days, int hours, int minutes, int seconds});

TimeLeft timeLeft(Duration d) {
  final s = d.isNegative ? 0 : d.inSeconds;
  return (days: s ~/ 86400, hours: s % 86400 ~/ 3600, minutes: s % 3600 ~/ 60, seconds: s % 60);
}

/// The offer's time left as four tiles (days, hours, minutes, seconds),
/// running down on the screen's own animation clock: one controller as long
/// as the time left, no timer. When it reaches the end it calls [onEnd]
/// (the report turns into the plain price). When the phone asks for less
/// motion it stands still: days, hours and minutes as they were, without
/// seconds; the screen's reload at the end (`refreshAt`) does the rest.
/// Read out as one sentence, never second by second.
class OfferCountdown extends StatefulWidget {
  const OfferCountdown({required this.endsAt, this.onEnd, this.clock = DateTime.now, super.key});

  final DateTime endsAt;
  final VoidCallback? onEnd;
  final DateTime Function() clock;

  @override
  State<OfferCountdown> createState() => _OfferCountdownState();
}

class _OfferCountdownState extends State<OfferCountdown> with SingleTickerProviderStateMixin {
  AnimationController? _c;
  late Duration _total;
  int _second = -1;

  Duration get _left => widget.endsAt.toUtc().difference(widget.clock().toUtc());

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _arm();
  }

  @override
  void didUpdateWidget(OfferCountdown old) {
    super.didUpdateWidget(old);
    if (old.endsAt != widget.endsAt) {
      _c?.dispose();
      _c = null;
      _arm();
    }
  }

  void _arm() {
    if (context.calm) {
      _c?.dispose();
      _c = null;
      return;
    }
    if (_c != null) return;
    _total = _left;
    if (_total <= Duration.zero) return;
    _c = AnimationController(vsync: this, duration: _total)
      ..addListener(_tick)
      ..addStatusListener((s) {
        if (s == AnimationStatus.completed) widget.onEnd?.call();
      })
      ..forward();
  }

  /// Rebuilds once a second, not every frame.
  void _tick() {
    final second = (_total * (1 - _c!.value)).inSeconds;
    if (second != _second) setState(() => _second = second);
  }

  @override
  void dispose() {
    _c?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = _c;
    final calm = c == null;
    final left = timeLeft(calm ? _left : _total * (1 - c.value));
    final spoken = l.trialOfferLeftSpoken(l.trialDaysCount(left.days), l.inHours(left.hours), l.inMinutes(left.minutes));
    return Semantics(
      label: '${l.trialOfferLeft}: $spoken',
      excludeSemantics: true,
      child: Row(
        key: const Key('offer-countdown'),
        children: [
          _Tile(value: left.days, unit: l.trialOfferDays),
          const Gap(Space.s),
          _Tile(value: left.hours, unit: l.trialOfferHours),
          const Gap(Space.s),
          _Tile(value: left.minutes, unit: l.trialOfferMinutes),
          if (!calm) ...[const Gap(Space.s), _Tile(value: left.seconds, unit: l.trialOfferSeconds, live: true)],
        ],
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.value, required this.unit, this.live = false});

  final int value;
  final String unit;

  /// The seconds: each new value slides in.
  final bool live;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final text = Text(
      value.toString().padLeft(2, '0'),
      key: ValueKey(value),
      style: TextStyle(
        fontFamily: context.shape.headingFont,
        fontSize: 24,
        fontWeight: FontWeight.w700,
        color: c.ink,
        fontFeatures: const [FontFeature.tabularFigures()],
      ),
    );
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: c.card,
          borderRadius: BorderRadius.circular(context.shape.card - 2),
          border: Border.all(color: c.attention.withValues(alpha: 0.35)),
        ),
        child: Column(
          children: [
            ClipRect(
              child: live
                  ? AnimatedSwitcher(
                      duration: const Duration(milliseconds: 280),
                      transitionBuilder: (child, a) => SlideTransition(
                        position: Tween(begin: const Offset(0, -0.5), end: Offset.zero).animate(a),
                        child: FadeTransition(opacity: a, child: child),
                      ),
                      child: text,
                    )
                  : text,
            ),
            Text(unit, maxLines: 1, overflow: TextOverflow.ellipsis, style: context.type.caption),
          ],
        ),
      ),
    );
  }
}
