import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/challenges_repository.dart';
import '../../domain/challenges.dart';
import 'art.dart';
import 'cheers.dart';
import 'chest.dart';
import 'reward.dart';
import '../../../../core/realtime/live.dart';

/// On a student's home: how the week's challenges are going, and the
/// rewards waiting (from the board their area loaded). Opens [route].
class ChallengesTeaser extends StatelessWidget {
  const ChallengesTeaser({required this.route, super.key});

  final String route;

  @override
  Widget build(BuildContext context) => BlocBuilder<LoadCubit<ChallengeBoard>, LoadState<ChallengeBoard>>(
    builder: (context, state) {
      final b = state.data;
      if (b == null) return const SizedBox.shrink();
      final l = context.l10n;
      final c = context.colors;
      final week = b.challenges.where((x) => x.group == 'week' && x.key != 'chest').toList();
      if (week.isEmpty && b.ready == 0) return const SizedBox.shrink();
      final done = week.where((x) => x.finished).length;
      final ready = b.ready > 0;
      final (title, line) = switch (b.stage) {
        'kids' => (l.navMissions, l.missionsOf(done, week.length)),
        'middle' => (l.questsTitle, l.challengesDone(done, week.length)),
        _ => (l.goalsTitle, l.challengesDone(done, week.length)),
      };
      final Widget picture = b.stage == 'kids'
          ? SizedBox(width: 72, child: const TreasureChest(size: 72).wiggleIf(context, ready))
          : Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(color: ready ? c.reward : c.brandSoft, borderRadius: BorderRadius.circular(14)),
              child: Icon(
                b.stage == 'middle' ? Icons.sports_esports_rounded : Icons.track_changes_rounded,
                color: ready ? Colors.white : c.brandDeep,
              ),
            );
      return LmsCard(
        color: ready ? c.rewardSoft : null,
        onTap: () => context.go(route),
        child: Row(
          children: [
            picture,
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: context.text.titleMedium),
                  const SizedBox(height: 2),
                  Text(line, style: context.text.bodySmall),
                  if (week.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    ProgressBar(week.isEmpty ? 0 : done / week.length, colour: c.reward, height: 8),
                  ],
                ],
              ),
            ),
            if (ready) ...[
              const SizedBox(width: 10),
              Pill(l.readyCount(b.ready), tone: Tone.reward, icon: unitIcon(b.stage)).pulse(context, to: 1.08),
            ],
          ],
        ),
      );
    },
  );
}

extension on Widget {
  Widget wiggleIf(BuildContext context, bool on) => on ? wiggle(context) : this;
}

/// On the family home: the picked learner's challenges for this week — a
/// guardian follows and cheers; an adult student collects.
class FamilyChallengesCard extends StatelessWidget {
  const FamilyChallengesCard({required this.learnerId, required this.name, super.key});

  final String? learnerId;
  final String name;

  @override
  Widget build(BuildContext context) => BlocProvider(
    key: ValueKey(learnerId),
    create: (c) => LoadCubit<ChallengeBoard>(
      () => c.read<ChallengesRepository>().board(learnerId: learnerId),
      refreshOn: c.read<Live>().on({Live.lessons, Live.homework}),
    )..load(),
    child: CheersListener(child: _FamilyCard(name: name)),
  );
}

class _FamilyCard extends StatelessWidget {
  const _FamilyCard({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) => BlocBuilder<LoadCubit<ChallengeBoard>, LoadState<ChallengeBoard>>(
    builder: (context, state) {
      final b = state.data;
      if (b == null) return const SizedBox.shrink();
      final l = context.l10n;
      final c = context.colors;
      final current = b.challenges.where((x) => x.group == 'week' || x.group == 'month' || x.claimable).take(4).toList();
      final week = b.challenges.where((x) => x.group == 'week' && x.key != 'chest').toList();
      final done = week.where((x) => x.finished).length;
      return LmsCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(child: Text(b.canClaim ? l.myChallenges : l.childChallenges(name), style: context.text.titleMedium)),
                TextButton(onPressed: () => context.push('/family/home/challenges'), child: Text(l.seeAll)),
              ],
            ),
            Row(
              children: [
                Icon(unitIcon(b.stage), color: c.reward, size: 22),
                const SizedBox(width: 6),
                CountUp(b.points, style: context.text.titleLarge),
                const SizedBox(width: 8),
                Pill(b.rank.label, tone: Tone.reward),
                const Spacer(),
                if (week.isNotEmpty) Text(l.challengesDone(done, week.length), style: context.text.bodySmall),
              ],
            ),
            if (current.isNotEmpty) const SizedBox(height: 10),
            for (final x in current)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 5),
                child: Row(
                  children: [
                    Icon(
                      x.finished ? Icons.check_circle_rounded : challengeIcon(x.icon),
                      size: 20,
                      color: x.finished ? c.reward : (x.missed ? c.faint : c.brand),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 3,
                      child: Text(x.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: context.text.bodyMedium),
                    ),
                    const SizedBox(width: 8),
                    if (x.claimable)
                      CollectButton(challenge: x, stage: b.stage, compact: true)
                    else
                      Expanded(
                        flex: 2,
                        child: ProgressBar(x.fraction, colour: x.finished ? c.reward : (x.missed ? c.faint : c.brand), height: 6),
                      ),
                  ],
                ),
              ),
            if (!b.canClaim) ...[const Divider(height: 24), CheerBar(board: b, name: name)],
          ],
        ),
      );
    },
  );
}
