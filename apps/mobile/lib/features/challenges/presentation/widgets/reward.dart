import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/challenges_repository.dart';
import '../../domain/challenges.dart';
import 'art.dart';

/// Collects a finished challenge, then the moment: a burst from where it
/// was tapped, the reward counting into the points, a new sticker, a new
/// rank. The board reloads after. Null when it could not be collected (the
/// reason is shown).
Future<ClaimResult?> collectReward(BuildContext context, Challenge challenge, {required String stage, Offset? from}) async {
  final repo = context.read<ChallengesRepository>();
  final board = context.read<LoadCubit<ChallengeBoard>>();
  try {
    final result = await repo.claim(challenge);
    if (!context.mounted) return result;
    Juice.reward();
    Celebration.show(context, burst: stage == 'kids' ? Burst.gems : Burst.confetti, from: from);
    await showReward(context, result, stage: stage);
    unawaited(board.load());
    return result;
  } on ApiProblem catch (p) {
    if (context.mounted) showSnack(context, context.problemText(p));
    unawaited(board.load());
    return null;
  }
}

/// The reward, big: what it brought, the points counting up, a sticker
/// turning into view, and a new rank.
Future<void> showReward(BuildContext context, ClaimResult result, {required String stage}) {
  if (result.rankedUp) {
    Future<void>.delayed(const Duration(milliseconds: 700), () => Juice.big(Sound.levelup));
  }
  return showGeneralDialog<void>(
    context: context,
    barrierDismissible: true,
    barrierLabel: context.l10n.close,
    barrierColor: Colors.black.withValues(alpha: 0.45),
    transitionDuration: context.calm ? Duration.zero : context.springDuration,
    transitionBuilder: (context, a, _, child) => FadeTransition(
      opacity: CurvedAnimation(parent: a, curve: Curves.easeOut),
      child: ScaleTransition(
        scale: CurvedAnimation(parent: a, curve: context.springCurve),
        child: child,
      ),
    ),
    pageBuilder: (context, _, _) => _RewardCard(result: result, stage: stage),
  );
}

class _RewardCard extends StatelessWidget {
  const _RewardCard({required this.result, required this.stage});

  final ClaimResult result;
  final String stage;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final sticker = result.sticker;
    final calm = context.calm;
    Widget badge = Stack(
      alignment: Alignment.center,
      children: [
        if (calm)
          SunRays(size: 170, colour: c.rewardSoft)
        else
          SunRays(size: 170, colour: c.rewardSoft).animate(onPlay: (a) => a.repeat()).rotate(duration: const Duration(seconds: 12)),
        if (sticker != null)
          StickerArt(sticker.key, size: 104)
        else
          Container(
            width: 92,
            height: 92,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: c.reward,
              border: Border.all(color: Colors.white, width: 5),
              boxShadow: [BoxShadow(color: c.reward.withValues(alpha: 0.4), blurRadius: 18, offset: const Offset(0, 6))],
            ),
            child: Icon(unitIcon(stage), color: Colors.white, size: 50),
          ),
      ],
    );
    if (!calm) {
      badge = badge
          .animate()
          .scaleXY(begin: 0.3, end: 1, duration: const Duration(milliseconds: 900), curve: Curves.elasticOut)
          .rotate(begin: sticker == null ? 0 : -0.15, end: 0, duration: const Duration(milliseconds: 900), curve: Curves.elasticOut);
    }
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Material(
          color: c.card,
          borderRadius: BorderRadius.circular(context.shape.card + 6),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(22, 16, 22, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                badge,
                Text(stage == 'kids' ? l.rewardKids : l.rewardTitle, style: context.text.headlineSmall, textAlign: TextAlign.center),
                const SizedBox(height: 4),
                Text(
                  plusReward(result.reward, result.rewardLabel),
                  textAlign: TextAlign.center,
                  style: context.text.titleLarge?.copyWith(color: const Color(0xFF7A4B00)),
                ),
                if (sticker != null) ...[
                  const SizedBox(height: 10),
                  Pill(l.newSticker, tone: Tone.reward, icon: Icons.auto_awesome_rounded),
                  const SizedBox(height: 4),
                  Text(sticker.name, style: context.text.titleMedium),
                ],
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(color: c.chip, borderRadius: BorderRadius.circular(999)),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(unitIcon(stage), color: c.reward, size: 22),
                      const SizedBox(width: 8),
                      Text(pointsName(l, stage, self: true), style: context.text.bodyMedium?.copyWith(color: c.muted)),
                      const SizedBox(width: 8),
                      CountUp(result.points, from: result.pointsBefore, style: context.text.titleLarge),
                    ],
                  ),
                ),
                if (result.rankedUp) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    decoration: BoxDecoration(color: c.brand, borderRadius: BorderRadius.circular(context.shape.button.clamp(0, 18))),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.military_tech_rounded, color: Colors.white),
                        const SizedBox(width: 8),
                        Text(l.rankUp(result.rank.label), style: context.text.titleMedium?.copyWith(color: Colors.white)),
                      ],
                    ),
                  ).appear(context, 8).shimmerIf(context, true),
                ],
                const SizedBox(height: 18),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(onPressed: () => Navigator.of(context).pop(), child: Text(l.awesome)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// The collect button: bobs while waiting, a tap sends it once.
class CollectButton extends StatefulWidget {
  const CollectButton({required this.challenge, required this.stage, this.compact = false, super.key});

  final Challenge challenge;
  final String stage;
  final bool compact;

  @override
  State<CollectButton> createState() => _CollectButtonState();
}

class _CollectButtonState extends State<CollectButton> {
  bool _busy = false;

  Future<void> _collect() async {
    if (_busy) return;
    setState(() => _busy = true);
    Juice.tap();
    await collectReward(context, widget.challenge, stage: widget.stage, from: Celebration.centreOf(context));
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final label = widget.compact ? context.l10n.collect : '${context.l10n.collect} ${widget.challenge.rewardLabel}';
    final button = FilledButton.icon(
      style: FilledButton.styleFrom(
        backgroundColor: c.reward,
        foregroundColor: const Color(0xFF3D2600),
        minimumSize: Size(widget.compact ? 64 : 120, widget.compact ? 40 : 48),
        padding: const EdgeInsets.symmetric(horizontal: 14),
      ),
      onPressed: _busy ? null : _collect,
      icon: _busy
          ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2.4))
          : Icon(unitIcon(widget.stage), size: 20),
      label: Text(label),
    );
    return button.shimmerIf(context, !_busy).bob(context, by: widget.stage == 'kids' ? 4 : 2);
  }
}
