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
import 'chest.dart';
import 'reward.dart';
import 'tiles.dart';

/// The week's treasure chest, big: locked with how many missions are
/// left, wiggling when they are all done, and opening under the child's
/// tap (lid up, light, stars) to show the sticker inside.
class ChestCard extends StatefulWidget {
  const ChestCard({required this.chest, required this.canClaim, super.key});

  final Challenge chest;
  final bool canClaim;

  @override
  State<ChestCard> createState() => _ChestCardState();
}

class _ChestCardState extends State<ChestCard> with SingleTickerProviderStateMixin {
  late final AnimationController _open = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 750),
    value: widget.chest.claimed ? 1 : 0,
  );
  final _chestKey = GlobalKey();
  bool _busy = false;

  @override
  void didUpdateWidget(ChestCard old) {
    super.didUpdateWidget(old);
    if (old.chest.id != widget.chest.id && !_busy) _open.value = widget.chest.claimed ? 1 : 0;
  }

  @override
  void dispose() {
    _open.dispose();
    super.dispose();
  }

  Future<void> _tap() async {
    final chest = widget.chest;
    if (!chest.claimable || !widget.canClaim) {
      Juice.tap();
      return;
    }
    if (_busy) return;
    setState(() => _busy = true);
    final repo = context.read<ChallengesRepository>();
    final board = context.read<LoadCubit<ChallengeBoard>>();
    Juice.big(Sound.chest);
    final claim = repo.claim(chest);
    try {
      if (!context.calm) await _open.animateTo(1, curve: Curves.easeOutBack);
      final result = await claim;
      _open.value = 1;
      if (!mounted) return;
      Celebration.show(context, burst: Burst.stars, from: Celebration.centreOf(_chestKey.currentContext ?? context), count: 90);
      await showReward(context, result, stage: 'kids');
    } on ApiProblem catch (p) {
      await _open.animateBack(0);
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
      unawaited(board.load());
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final chest = widget.chest;
    final ready = chest.done;
    Widget art = AnimatedBuilder(
      animation: _open,
      builder: (context, _) => Stack(
        alignment: Alignment.center,
        clipBehavior: Clip.none,
        children: [
          if (ready || _open.value > 0) SunRays(size: 210, colour: c.rewardSoft),
          if (_open.value > 0.4 && chest.sticker != null && chest.claimed)
            Positioned(top: -6 - 26 * _open.value, child: StickerArt(chest.sticker!.key, size: 64)),
          TreasureChest(key: _chestKey, open: _open.value, size: 150),
        ],
      ),
    );
    if (ready && !_busy) art = art.wiggle(context);
    return LmsCard(
      color: ready ? c.rewardSoft : null,
      onTap: _tap,
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 18),
      child: Column(
        children: [
          SizedBox(height: 150, child: Center(child: art)),
          const SizedBox(height: 8),
          Text(chest.title, style: context.text.headlineSmall),
          const SizedBox(height: 4),
          if (chest.claimed)
            Text(l.chestOpened, style: context.text.bodyMedium?.copyWith(color: c.muted))
          else if (ready && widget.canClaim)
            Text(l.chestReady, style: context.text.titleMedium?.copyWith(color: const Color(0xFF7A4B00))).bob(context, by: 3)
          else if (ready)
            ChallengeState(challenge: chest, canClaim: false)
          else ...[
            Text(l.missionsOf(chest.progress, chest.target), style: context.text.titleMedium),
            const SizedBox(height: 8),
            ProgressSteps(challenge: chest, stars: true),
            const SizedBox(height: 6),
            Text(l.chestSurprise, style: context.text.bodySmall),
          ],
        ],
      ),
    );
  }
}
