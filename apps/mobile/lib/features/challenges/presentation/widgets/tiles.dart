import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/local_time.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/challenges.dart';
import 'art.dart';
import 'reward.dart';

/// How far along, as steps: a star per step when there are few (kids), a
/// segmented bar otherwise. Steps reached pop in.
class ProgressSteps extends StatelessWidget {
  const ProgressSteps({required this.challenge, this.stars = false, super.key});

  final Challenge challenge;
  final bool stars;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final target = challenge.target;
    if (target == 0) return const SizedBox.shrink();
    if (stars && target <= 7) {
      return Row(
        children: [
          for (var i = 0; i < target; i++)
            Padding(
              padding: const EdgeInsetsDirectional.only(end: 6),
              child: i < challenge.progress
                  ? Icon(Icons.star_rounded, color: c.reward, size: 30).appear(context, i + 2)
                  : Icon(Icons.star_outline_rounded, color: c.line, size: 30),
            ),
        ],
      );
    }
    if (target > 10) return ProgressBar(challenge.fraction, colour: challenge.finished ? c.reward : c.brand, height: 10);
    return Row(
      children: [
        for (var i = 0; i < target; i++) ...[
          if (i > 0) const SizedBox(width: 4),
          Expanded(
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 500),
              curve: Curves.easeOutCubic,
              height: 10,
              decoration: BoxDecoration(
                color: i < challenge.progress ? (challenge.finished ? c.reward : c.brand) : c.chip,
                borderRadius: BorderRadius.circular(5),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// Where a challenge stands, as a small label: collected, missed (try next
/// time), or done for someone who only watches.
class ChallengeState extends StatelessWidget {
  const ChallengeState({required this.challenge, required this.canClaim, super.key});

  final Challenge challenge;
  final bool canClaim;

  @override
  Widget build(BuildContext context) {
    if (challenge.claimed) return Pill(challenge.stateLabel, tone: Tone.brand, icon: Icons.check_rounded);
    if (challenge.missed) return Pill(challenge.stateLabel, tone: Tone.attention, icon: Icons.refresh_rounded);
    if (challenge.done && !canClaim) return Pill(context.l10n.doneMark, tone: Tone.reward, icon: Icons.check_rounded);
    return const SizedBox.shrink();
  }
}

class _RewardChip extends StatelessWidget {
  const _RewardChip({required this.challenge, required this.stage});

  final Challenge challenge;
  final String stage;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(color: c.rewardSoft, borderRadius: BorderRadius.circular(999)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(unitIcon(stage), size: 16, color: const Color(0xFF7A4B00)),
          const SizedBox(width: 3),
          Text(
            stage == 'middle' ? '+${challenge.reward} XP' : '+${challenge.reward}',
            textDirection: TextDirection.ltr,
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFF7A4B00)),
          ),
        ],
      ),
    );
  }
}

/// A kids' mission: a big round icon, stars to fill, gems to win.
class MissionTile extends StatelessWidget {
  const MissionTile({required this.challenge, required this.canClaim, super.key});

  final Challenge challenge;
  final bool canClaim;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final ch = challenge;
    final bubble = ch.finished ? c.reward : (ch.missed ? c.chip : c.brandSoft);
    final ink = ch.finished ? Colors.white : (ch.missed ? c.faint : c.brandDeep);
    return LmsCard(
      color: ch.done ? c.rewardSoft : null,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(shape: BoxShape.circle, color: bubble),
                child: Icon(challengeIcon(ch.icon), color: ink, size: 30),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(ch.title, style: context.text.titleLarge?.copyWith(fontSize: 18)),
                    Text(ch.hint, style: context.text.bodySmall),
                  ],
                ),
              ),
              _RewardChip(challenge: ch, stage: 'kids'),
            ],
          ),
          const SizedBox(height: 10),
          ProgressSteps(challenge: ch, stars: true),
          if (ch.claimable) ...[
            const SizedBox(height: 10),
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: CollectButton(challenge: ch, stage: 'kids'),
            ),
          ] else ...[
            const SizedBox(height: 6),
            ChallengeState(challenge: ch, canClaim: canClaim),
          ],
        ],
      ),
    );
  }
}

/// A middle-school quest: a square icon, a segmented bar, XP.
class QuestTile extends StatelessWidget {
  const QuestTile({required this.challenge, required this.canClaim, super.key});

  final Challenge challenge;
  final bool canClaim;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final ch = challenge;
    return LmsCard(
      color: ch.done ? c.rewardSoft : null,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: ch.finished ? c.reward : (ch.missed ? c.chip : c.brand),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(challengeIcon(ch.icon), color: ch.missed ? c.faint : Colors.white, size: 26),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(ch.title, style: context.text.titleMedium),
                    Text(ch.hint, style: context.text.bodySmall),
                  ],
                ),
              ),
              _RewardChip(challenge: ch, stage: 'middle'),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: ProgressSteps(challenge: ch)),
              const SizedBox(width: 10),
              Text('${ch.progress}/${ch.target}', textDirection: TextDirection.ltr, style: context.text.titleSmall),
            ],
          ),
          if (ch.claimable) ...[
            const SizedBox(height: 10),
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: CollectButton(challenge: ch, stage: 'middle'),
            ),
          ] else if (ch.claimed || ch.missed || ch.done) ...[
            const SizedBox(height: 8),
            ChallengeState(challenge: ch, canClaim: canClaim),
          ],
        ],
      ),
    );
  }
}

/// The boss of a level: its health goes down with every lesson of the
/// level done; beaten, it pays big.
class BossTile extends StatelessWidget {
  const BossTile({required this.challenge, required this.canClaim, super.key});

  final Challenge challenge;
  final bool canClaim;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final ch = challenge;
    final health = 1 - ch.fraction;
    return LmsCard(
      color: c.brandDeep,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.workspace_premium_rounded, color: c.reward, size: 40).bob(context, by: 3),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(ch.title, style: context.text.titleMedium?.copyWith(color: Colors.white)),
                    Text(ch.hint, style: context.text.bodySmall?.copyWith(color: Colors.white70)),
                  ],
                ),
              ),
              _RewardChip(challenge: ch, stage: 'middle'),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Icon(Icons.favorite_rounded, color: c.danger, size: 18),
              const SizedBox(width: 6),
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: TweenAnimationBuilder<double>(
                    tween: Tween(begin: 1, end: health),
                    duration: context.calm ? Duration.zero : const Duration(milliseconds: 1100),
                    curve: Curves.easeOutCubic,
                    builder: (context, v, _) => LinearProgressIndicator(
                      value: v,
                      minHeight: 12,
                      color: c.danger,
                      backgroundColor: Colors.white.withValues(alpha: 0.18),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text(
                '${ch.progress}/${ch.target}',
                textDirection: TextDirection.ltr,
                style: context.text.titleSmall?.copyWith(color: Colors.white),
              ),
            ],
          ),
          if (ch.claimable) ...[
            const SizedBox(height: 12),
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: CollectButton(challenge: ch, stage: 'middle'),
            ),
          ] else if (ch.done) ...[
            const SizedBox(height: 8),
            ChallengeState(challenge: ch, canClaim: canClaim),
          ],
        ],
      ),
    );
  }
}

/// A teen's (or adult's) goal: a ring filling up, quiet until it is done.
class GoalRow extends StatelessWidget {
  const GoalRow({required this.challenge, required this.stage, required this.canClaim, super.key});

  final Challenge challenge;
  final String stage;
  final bool canClaim;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final ch = challenge;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          SizedBox.square(
            dimension: 46,
            child: Stack(
              fit: StackFit.expand,
              children: [
                TweenAnimationBuilder<double>(
                  tween: Tween(begin: 0, end: ch.fraction),
                  duration: context.calm ? Duration.zero : const Duration(milliseconds: 900),
                  curve: Curves.easeOutCubic,
                  builder: (context, v, _) => CircularProgressIndicator(
                    value: v,
                    strokeWidth: 4,
                    strokeCap: StrokeCap.round,
                    color: ch.finished ? c.reward : (ch.missed ? c.faint : c.brand),
                    backgroundColor: c.chip,
                  ),
                ),
                Icon(
                  ch.claimed ? Icons.check_rounded : challengeIcon(ch.icon),
                  size: 22,
                  color: ch.finished ? const Color(0xFF7A4B00) : (ch.missed ? c.faint : c.brandDeep),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(ch.title, style: context.text.titleSmall),
                Text(ch.hint, style: context.text.bodySmall),
                if (ch.claimed || ch.missed || (ch.done && !canClaim)) ...[
                  const SizedBox(height: 4),
                  ChallengeState(challenge: ch, canClaim: canClaim),
                ],
              ],
            ),
          ),
          const SizedBox(width: 8),
          if (ch.claimable)
            CollectButton(challenge: ch, stage: stage, compact: true)
          else
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text('${ch.progress}/${ch.target}', textDirection: TextDirection.ltr, style: context.text.titleSmall),
                Text(ch.rewardLabel, style: context.text.bodySmall),
              ],
            ),
        ],
      ),
    );
  }
}

/// Whole days left before [end] (the week's end), for the countdown under a board's title.
int daysLeft(LocalTime? end) {
  if (end == null) return 0;
  final hours = end.utc.difference(DateTime.now().toUtc()).inHours;
  return hours < 24 ? 0 : hours ~/ 24;
}
