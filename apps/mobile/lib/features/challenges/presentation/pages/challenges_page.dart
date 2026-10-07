import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../dashboard/data/family_repository.dart';
import '../../../dashboard/domain/journey.dart';
import '../../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../../dashboard/presentation/widgets/badge_tile.dart';
import '../../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../data/challenges_repository.dart';
import '../../domain/challenges.dart';
import '../widgets/art.dart';
import '../widgets/cheers.dart';
import '../widgets/chest_card.dart';
import '../widgets/tiles.dart';

/// A learner's challenges in their stage's way: the kids' missions and
/// treasure chest, middle school's quests and bosses, the older students'
/// goals. A student's own tab (the board loads with their area), or a
/// guardian's look at a child from the home.
class ChallengesPage extends StatelessWidget {
  const ChallengesPage({super.key});

  @override
  Widget build(BuildContext context) {
    final learners = context.read<LearnerCubit>();
    if (learners.scope == LearnerScope.self) return const _Screen(pushed: false);
    final id = context.learnerId();
    return BlocProvider(
      key: ValueKey(id),
      create: (c) => LoadCubit<ChallengeBoard>(() => c.read<ChallengesRepository>().board(learnerId: id))..load(),
      child: const CheersListener(child: _Screen(pushed: true)),
    );
  }
}

class _Screen extends StatelessWidget {
  const _Screen({required this.pushed});

  final bool pushed;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final learner = context.watch<LearnerCubit>().state.selected;
    return Scaffold(
      appBar: pushed
          ? AppBar(title: Text(learner == null || learner.isSelf ? l.myChallenges : l.childChallenges(learner.firstName)))
          : null,
      body: SafeArea(
        top: !pushed,
        child: LoadView<ChallengeBoard>(
          builder: (context, board) => switch (board.stage) {
            'kids' => _Kids(board: board, pushed: pushed),
            'middle' => _Middle(board: board, pushed: pushed),
            _ => _Goals(board: board, pushed: pushed),
          },
        ),
      ),
    );
  }
}

/// The points of the board, counting up, with the stage's unit.
class _Points extends StatelessWidget {
  const _Points({required this.board});

  final ChallengeBoard board;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Semantics(
      label: '${board.points}',
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(color: c.rewardSoft, borderRadius: BorderRadius.circular(999)),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(unitIcon(board.stage), color: c.reward, size: 22),
            const SizedBox(width: 4),
            CountUp(board.points, style: context.text.titleMedium),
          ],
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.title, required this.board, this.trailing});

  final String title;
  final ChallengeBoard board;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final days = daysLeft(board.weekEndsAt);
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: context.text.headlineSmall),
                if (board.weekEndsAt != null) Text(context.l10n.weekEnds(days), style: context.text.bodySmall),
              ],
            ),
          ),
          ?trailing,
          const SizedBox(width: 8),
          _Points(board: board),
        ],
      ),
    );
  }
}

/// Kids: the chest first, then the missions; last week's rewards still waiting on top.
class _Kids extends StatelessWidget {
  const _Kids({required this.board, required this.pushed});

  final ChallengeBoard board;
  final bool pushed;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final chests = board.challenges.where((c) => c.key == 'chest').toList();
    final hero = chests.where((c) => c.claimable).firstOrNull ?? chests.where((c) => c.group == 'week').firstOrNull ?? chests.firstOrNull;
    final earlier = board.challenges.where((c) => c.earlier && c != hero).toList();
    final week = board.challenges.where((c) => c.group == 'week' && c.key != 'chest').toList();
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        _Header(
          title: l.missionsTitle,
          board: board,
          trailing: board.stickers == null || pushed
              ? null
              : IconButton.filledTonal(
                  tooltip: l.album,
                  onPressed: () => context.push('/kids/missions/album'),
                  icon: const Icon(Icons.collections_bookmark_rounded),
                ),
        ),
        const SizedBox(height: 10),
        if (hero != null) ChestCard(chest: hero, canClaim: board.canClaim),
        if (earlier.isNotEmpty) ...[
          const SizedBox(height: 14),
          SectionLabel(l.fromLastWeek),
          for (final c in earlier) ...[
            c.key == 'chest' ? ChestCard(chest: c, canClaim: board.canClaim) : MissionTile(challenge: c, canClaim: board.canClaim),
            const SizedBox(height: 10),
          ],
        ],
        const SizedBox(height: 14),
        if (earlier.isNotEmpty && week.isNotEmpty) SectionLabel(l.thisWeek),
        for (final c in week) ...[MissionTile(challenge: c, canClaim: board.canClaim), const SizedBox(height: 12)],
        if (week.isEmpty && hero == null && earlier.isEmpty) EmptyNote(l.noChallenges, icon: Icons.redeem_rounded),
      ]),
    );
  }
}

/// Middle school: rank and XP, rewards waiting, the week's quests, the
/// bosses of the levels, and badges.
class _Middle extends StatelessWidget {
  const _Middle({required this.board, required this.pushed});

  final ChallengeBoard board;
  final bool pushed;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final rank = board.rank;
    final waiting = board.challenges.where((x) => x.claimable && (x.earlier || x.group == 'world')).toList();
    final week = board.challenges.where((x) => x.group == 'week').toList();
    final bosses = board.challenges.where((x) => x.group == 'world' && !waiting.contains(x)).toList();
    Widget tile(Challenge x) =>
        x.group == 'world' ? BossTile(challenge: x, canClaim: board.canClaim) : QuestTile(challenge: x, canClaim: board.canClaim);
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        _Header(title: l.questsTitle, board: board),
        const SizedBox(height: 10),
        LmsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(color: c.brand, borderRadius: BorderRadius.circular(16)),
                    child: const Icon(Icons.military_tech_rounded, color: Colors.white, size: 32),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(rank.label, style: context.text.titleLarge),
                        Text(rank.next == null ? l.topRank : l.pointsTo(rank.nextLabel ?? ''), style: context.text.bodySmall),
                      ],
                    ),
                  ),
                  CountUp(
                    board.points,
                    suffix: ' XP',
                    style: context.text.titleLarge?.copyWith(color: c.brandDeep),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              ProgressBar(rank.progress / 100, colour: c.reward, height: 12),
              if (rank.next != null) ...[
                const SizedBox(height: 6),
                Text(l.pointsLeft(rank.next! - board.points), style: context.text.bodySmall),
              ],
            ],
          ),
        ),
        if (waiting.isNotEmpty) ...[
          const SizedBox(height: 14),
          SectionLabel(l.waitingRewards),
          for (final x in waiting) ...[tile(x), const SizedBox(height: 10)],
        ],
        const SizedBox(height: 14),
        if (waiting.isNotEmpty && week.isNotEmpty) SectionLabel(l.thisWeek),
        for (final x in week) ...[tile(x), const SizedBox(height: 10)],
        if (bosses.isNotEmpty) ...[
          const SizedBox(height: 6),
          SectionLabel(l.bossFights),
          for (final x in bosses) ...[tile(x), const SizedBox(height: 10)],
        ],
        if (week.isEmpty && waiting.isEmpty && bosses.isEmpty) EmptyNote(l.noChallenges, icon: Icons.sports_esports_rounded),
        const SizedBox(height: 6),
        _Badges(learnerId: board.canClaim ? null : board.learnerId, canOpen: !pushed),
      ]),
    );
  }
}

class _Badges extends StatelessWidget {
  const _Badges({required this.learnerId, required this.canOpen});

  final String? learnerId;
  final bool canOpen;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<Journey>(() => c.read<FamilyRepository>().journey(learnerId: learnerId))..load(),
    child: BlocBuilder<LoadCubit<Journey>, LoadState<Journey>>(
      builder: (context, state) {
        final badges = state.data?.badges ?? const <Achievement>[];
        if (badges.isEmpty) return const SizedBox.shrink();
        final l = context.l10n;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SectionLabel(
              '${l.navBadges} · ${l.badgesCount(badges.where((b) => b.earned).length, badges.length)}',
              trailing: canOpen ? TextButton(onPressed: () => context.push('/middle/quests/badges'), child: Text(l.allBadges)) : null,
            ),
            LmsCard(
              child: Wrap(
                alignment: WrapAlignment.spaceAround,
                spacing: 8,
                runSpacing: 12,
                children: [for (final (i, b) in badges.take(6).indexed) SizedBox(width: 84, child: BadgeTile(badge: b).appear(context, i))],
              ),
            ),
          ],
        );
      },
    ),
  );
}

/// Teens and adults: points and rank, rewards waiting, the week's goals, the month's.
class _Goals extends StatelessWidget {
  const _Goals({required this.board, required this.pushed});

  final ChallengeBoard board;
  final bool pushed;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final waiting = board.challenges.where((x) => x.claimable && x.earlier).toList();
    final week = board.challenges.where((x) => x.group == 'week').toList();
    final month = board.challenges.where((x) => x.group == 'month').toList();
    Widget card(List<Challenge> items) => LmsCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (final (i, x) in items.indexed) ...[
            if (i > 0) const Divider(height: 1),
            GoalRow(challenge: x, stage: board.stage, canClaim: board.canClaim),
          ],
        ],
      ),
    );
    final rank = board.rank;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        _Header(title: l.goalsTitle, board: board),
        const SizedBox(height: 10),
        LmsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(pointsName(l, board.stage, self: board.canClaim), style: context.text.labelMedium),
                        CountUp(board.points, style: context.text.headlineMedium?.copyWith(color: c.brand)),
                      ],
                    ),
                  ),
                  Pill(rank.label, tone: Tone.reward, icon: Icons.military_tech_rounded),
                ],
              ),
              const SizedBox(height: 10),
              ProgressBar(rank.progress / 100, height: 6),
              const SizedBox(height: 6),
              Text(rank.next == null ? l.topRank : l.pointsLeft(rank.next! - board.points), style: context.text.bodySmall),
            ],
          ),
        ),
        if (waiting.isNotEmpty) ...[const SizedBox(height: 14), SectionLabel(l.waitingRewards), card(waiting)],
        if (week.isNotEmpty) ...[const SizedBox(height: 14), SectionLabel(l.thisWeek), card(week)],
        if (month.isNotEmpty) ...[const SizedBox(height: 14), SectionLabel(l.monthGoal), card(month)],
        if (week.isEmpty && month.isEmpty && waiting.isEmpty) EmptyNote(l.noChallenges, icon: Icons.track_changes_rounded),
      ]),
    );
  }
}
