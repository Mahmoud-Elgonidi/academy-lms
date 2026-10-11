import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/challenges_repository.dart';
import 'art.dart';

/// "A cheer from your family!": the cheers ([kinds]: clap, star, heart,
/// fire), big and bouncing, with a burst and a sound.
Future<void> showCheers(BuildContext context, List<String> kinds) async {
  if (kinds.isEmpty) return;
  final l = context.l10n;
  Juice.big(Sound.cheer);
  Celebration.show(context, burst: Burst.stars);
  await showGeneralDialog<void>(
    context: context,
    barrierDismissible: true,
    barrierLabel: l.close,
    barrierColor: Colors.black.withValues(alpha: 0.45),
    transitionDuration: context.calm ? Duration.zero : context.springDuration,
    transitionBuilder: (context, a, _, child) => ScaleTransition(
      scale: CurvedAnimation(parent: a, curve: context.springCurve),
      child: child,
    ),
    pageBuilder: (context, _, _) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Material(
            color: context.colors.card,
            borderRadius: BorderRadius.circular(context.shape.card + 6),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(22, 26, 22, 20),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Wrap(
                    alignment: WrapAlignment.center,
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      for (final (i, kind) in kinds.take(6).indexed)
                        Container(
                          width: 72,
                          height: 72,
                          decoration: BoxDecoration(shape: BoxShape.circle, color: cheerColour(context, kind).withValues(alpha: 0.14)),
                          child: Icon(cheerIcon(kind), size: 44, color: cheerColour(context, kind)),
                        ).appear(context, i).wiggle(context),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text(l.cheerFromFamily, textAlign: TextAlign.center, style: context.text.headlineSmall),
                  const SizedBox(height: 6),
                  Text(
                    kinds.toSet().map((k) => cheerName(l, k)).join(' · '),
                    textAlign: TextAlign.center,
                    style: context.text.bodyMedium?.copyWith(color: context.colors.muted),
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(onPressed: () => Navigator.of(context).pop(), child: Text(l.cheerThanks)),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    },
  );
}

/// A guardian's four cheer buttons: a tap sends one, and it flies off.
class CheerBar extends StatefulWidget {
  const CheerBar({required this.learnerId, required this.left, required this.name, this.onSent, super.key});

  final String learnerId;

  /// Cheers they may still send today.
  final int left;
  final String name;

  /// A cheer was sent: read again how many are left.
  final VoidCallback? onSent;

  @override
  State<CheerBar> createState() => _CheerBarState();
}

class _CheerBarState extends State<CheerBar> {
  bool _busy = false;
  final _keys = {for (final k in cheerKinds) k: GlobalKey()};

  Future<void> _send(String kind) async {
    if (_busy) return;
    setState(() => _busy = true);
    Juice.tap();
    _fly(kind);
    final l = context.l10n;
    try {
      await context.read<ChallengesRepository>().cheer(widget.learnerId, kind);
      if (!mounted) return;
      showSnack(context, l.cheerSent(widget.name));
      widget.onSent?.call();
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// A few copies of the icon rise from the button and fade.
  void _fly(String kind) {
    final from = Celebration.centreOf(_keys[kind]!.currentContext ?? context);
    final overlay = Overlay.maybeOf(context, rootOverlay: true);
    if (from == null || overlay == null || context.calm) return;
    final colour = cheerColour(context, kind);
    final r = math.Random();
    for (var i = 0; i < 5; i++) {
      late final OverlayEntry entry;
      final drift = (r.nextDouble() - 0.5) * 120;
      entry = OverlayEntry(
        builder: (_) => Positioned(
          left: from.dx - 16,
          top: from.dy - 16,
          child: IgnorePointer(
            child: Icon(cheerIcon(kind), size: 32, color: colour)
                .animate(onComplete: (_) => entry.remove())
                .move(
                  delay: Duration(milliseconds: i * 70),
                  begin: Offset.zero,
                  end: Offset(drift, -170 - r.nextDouble() * 80),
                  duration: const Duration(milliseconds: 950),
                  curve: Curves.easeOutCubic,
                )
                .scaleXY(begin: 0.6, end: 1.6)
                .fadeOut(
                  delay: Duration(milliseconds: 500 + i * 70),
                  duration: const Duration(milliseconds: 450),
                ),
          ),
        ),
      );
      overlay.insert(entry);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final left = widget.left;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.cheerOn(widget.name), style: context.text.titleSmall),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            for (final kind in cheerKinds)
              Semantics(
                button: true,
                label: cheerName(l, kind),
                child: Pressable(
                  key: _keys[kind],
                  onTap: left == 0 || _busy ? null : () => _send(kind),
                  child: AnimatedOpacity(
                    opacity: left == 0 ? 0.4 : 1,
                    duration: const Duration(milliseconds: 200),
                    child: Column(
                      children: [
                        Container(
                          width: 54,
                          height: 54,
                          decoration: BoxDecoration(shape: BoxShape.circle, color: cheerColour(context, kind).withValues(alpha: 0.13)),
                          child: Icon(cheerIcon(kind), color: cheerColour(context, kind), size: 30),
                        ),
                        const SizedBox(height: 4),
                        Text(cheerName(l, kind), style: context.text.bodySmall),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 8),
        Text(l.cheersLeft(left), textAlign: TextAlign.center, style: context.text.bodySmall),
      ],
    );
  }
}
