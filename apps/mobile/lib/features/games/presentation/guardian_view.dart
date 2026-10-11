import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/juice.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../challenges/data/challenges_repository.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../data/games_repository.dart';
import '../domain/games.dart';
import 'art/art.dart';
import 'play/play_page.dart';

/// A guardian's view of their child's games (the design's Guardian), in the
/// academy's own colours: today's play time against the limit with the
/// minutes a day, the night lock and games on or off; what the child
/// practised this week; a creature hatched today to cheer; the safety
/// promise; and the quests to play together. Never pay or KPIs.
class GuardianGames extends StatefulWidget {
  const GuardianGames({super.key, required this.world});

  final GamesWorld world;

  @override
  State<GuardianGames> createState() => _GuardianGamesState();
}

class _GuardianGamesState extends State<GuardianGames> {
  bool _saving = false;
  int _hearts = 0;

  GamesWorld get _w => widget.world;

  Future<void> _save({bool? enabled, int? minutes, bool keepMinutes = true, bool? night}) async {
    final now = _w.limits;
    if (now == null || _saving) return;
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    final l = context.l10n;
    try {
      await context.read<GamesRepository>().setLimits(
        _w.learnerId,
        enabled: enabled ?? now.enabled,
        dailyMinutes: keepMinutes ? now.dailyMinutes : minutes,
        nightLock: night,
      );
      if (!mounted) return;
      await context.read<LoadCubit<GamesWorld>>().load();
      messenger.showSnackBar(SnackBar(content: Text(l.gamesSaved)));
    } on ApiProblem catch (p) {
      if (mounted) messenger.showSnackBar(SnackBar(content: Text(context.problemText(p))));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _cheer() async {
    final messenger = ScaffoldMessenger.of(context);
    final l = context.l10n;
    Juice.reward();
    setState(() => _hearts++);
    try {
      await context.read<ChallengesRepository>().cheer(_w.learnerId, 'heart');
      messenger.showSnackBar(SnackBar(content: Text(l.gamesCheered)));
    } on ApiProblem catch (p) {
      if (mounted) messenger.showSnackBar(SnackBar(content: Text(context.problemText(p))));
    }
  }

  Future<void> _play(Quest q) async {
    final cubit = context.read<LoadCubit<GamesWorld>>();
    await openRound(context, PlayRequest(gameId: q.game.id, quest: q.key, look: 'classic', learnerId: _w.learnerId, guardian: true));
    unawaited(cubit.load());
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final a = _w.allowance;
    final limits = _w.limits!;
    final glance = _w.glance;
    final name = context.watch<LearnerCubit>().state.selected?.firstName ?? '';
    final choices = limits.allowed;
    final children = <Widget>[
      Text(l.gamesGuardianTitle(name), style: context.text.headlineSmall),
      // Today's time, and the limits.
      LmsCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _Ring(used: a.used, limit: a.limit, colour: c.brand, track: c.chip),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l.gamesPlayTime, style: context.text.titleMedium),
                      const SizedBox(height: 2),
                      Text(
                        a.limit != null ? l.gamesTimeText(a.used, a.limit!, a.left ?? 0) : l.gamesTimeOpen(a.used),
                        style: context.text.bodySmall,
                      ),
                      if (!a.may && a.reasonLabel != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            a.reasonLabel!,
                            style: context.text.bodySmall?.copyWith(color: c.attentionInk, fontWeight: FontWeight.w600),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(l.gamesMaxTime, style: context.text.labelLarge),
            const SizedBox(height: 6),
            Semantics(
              label: l.gamesMaxTime,
              child: Row(
                children: [
                  for (final (minutes, label) in [for (final m in choices) (m, l.gamesChoice(m)), (null, l.gamesAcademyChoice)])
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsetsDirectional.only(end: 6),
                        child: ChoiceChip(
                          label: SizedBox(
                            width: double.infinity,
                            child: Text(label, textAlign: TextAlign.center),
                          ),
                          selected: limits.dailyMinutes == minutes,
                          showCheckmark: false,
                          onSelected: _saving ? null : (_) => _save(minutes: minutes, keepMinutes: false),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const Divider(height: 20),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              value: limits.nightLock,
              title: Text(l.gamesNightLock, style: context.text.bodyMedium),
              onChanged: _saving ? null : (on) => _save(night: on),
            ),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              value: limits.enabled,
              title: Text(l.gamesEnabled, style: context.text.bodyMedium),
              onChanged: _saving ? null : (on) => _save(enabled: on),
            ),
          ],
        ),
      ),
      // What the child practised this week.
      LmsCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.gamesLearned, style: context.text.titleMedium),
            const SizedBox(height: 8),
            if (glance == null || glance.quiet)
              Text(l.gamesQuiet, style: context.text.bodySmall)
            else ...[
              if (glance.practisedTitle != null)
                _Line(
                  icon: Icons.check_rounded,
                  colour: c.brand,
                  soft: c.brandSoft,
                  text: l.gamesPractised(
                    glance.practisedTitle!,
                    l.gamesRoundsCount(glance.practisedRounds),
                    l.gamesGamesCount(glance.practisedGames),
                  ),
                ),
              if (glance.hardest != null)
                _Line(
                  icon: Icons.priority_high_rounded,
                  colour: c.attentionInk,
                  soft: c.attentionSoft,
                  text: l.gamesHardest(glance.hardest!.length > 60 ? '${glance.hardest!.substring(0, 60)}…' : glance.hardest!),
                ),
              if (glance.bestCorrect != null)
                _Line(
                  icon: Icons.star_rounded,
                  colour: c.infoInk,
                  soft: c.infoSoft,
                  text: l.gamesBestRound(glance.bestCorrect!, glance.bestTotal ?? 0, glance.bestGame ?? ''),
                ),
            ],
          ],
        ),
      ),
      if (glance?.hatched != null) _hatched(context, glance!),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(14)),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.verified_user_outlined, color: c.brandDeep, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(l.gamesSafety, style: context.text.bodySmall?.copyWith(color: c.brandDeep, height: 1.5)),
            ),
          ],
        ),
      ),
      if (_w.quests.isNotEmpty)
        LmsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(l.gamesTogether, style: context.text.titleMedium),
              const SizedBox(height: 8),
              for (final q in _w.quests)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Row(
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(10)),
                        alignment: Alignment.center,
                        child: const Text('🎮'),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(q.shownTitle, style: context.text.titleSmall),
                            Text('${q.reasonLabel} · ${q.game.title}', style: context.text.bodySmall),
                          ],
                        ),
                      ),
                      FilledButton(onPressed: a.may ? () => _play(q) : null, child: Text(l.gamesPlay)),
                    ],
                  ),
                ),
            ],
          ),
        ),
    ];
    return ListView(
      padding: const EdgeInsets.fromLTRB(0, 8, 0, 24),
      children: [
        const LearnerSwitcher(),
        for (var i = 0; i < children.length; i++)
          Padding(padding: const EdgeInsets.fromLTRB(18, 0, 18, 12), child: children[i].appear(context, i)),
      ],
    );
  }

  Widget _hatched(BuildContext context, Glance glance) {
    final l = context.l10n;
    final c = context.colors;
    final h = glance.hatched!;
    return LmsCard(
      child: Row(
        children: [
          Container(
            width: 76,
            height: 72,
            decoration: BoxDecoration(color: c.rewardSoft, borderRadius: BorderRadius.circular(16)),
            alignment: Alignment.center,
            child: Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                CreatureArt(h.look, size: 62, motion: true, line: '#24124F'),
                if (_hearts > 0 && !context.calm)
                  Positioned(
                    top: -10,
                    child: const Text('💗 💖 💗', style: TextStyle(fontSize: 14))
                        .animate(key: ValueKey(_hearts))
                        .moveY(begin: 0, end: -26, duration: 1100.ms, curve: Curves.easeOut)
                        .fadeOut(begin: 1, delay: 300.ms, duration: 800.ms),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.gamesHatchedToday(h.name), style: context.text.titleSmall),
                Text(
                  h.source == 'mastery' ? l.gamesHatchedMastery(glance.hatchedFound) : l.gamesHatchedStars(glance.hatchedFound),
                  style: context.text.bodySmall,
                ),
              ],
            ),
          ),
          FilledButton(onPressed: _cheer, child: Text(l.gamesCheer)),
        ],
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.icon, required this.colour, required this.soft, required this.text});

  final IconData icon;
  final Color colour;
  final Color soft;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(
      children: [
        Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(color: soft, borderRadius: BorderRadius.circular(10)),
          child: Icon(icon, color: colour, size: 18),
        ),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: context.text.bodyMedium)),
      ],
    ),
  );
}

/// Today's minutes against the limit, as a ring that fills.
class _Ring extends StatelessWidget {
  const _Ring({required this.used, required this.limit, required this.colour, required this.track});

  final int used;
  final int? limit;
  final Color colour;
  final Color track;

  @override
  Widget build(BuildContext context) {
    final share = limit == null || limit == 0 ? 0.0 : math.min(1.0, used / limit!);
    return Semantics(
      label: context.l10n.gamesTimeText(used, limit ?? 0, math.max(0, (limit ?? 0) - used)),
      excludeSemantics: true,
      child: SizedBox(
        width: 84,
        height: 84,
        child: TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: share),
          duration: context.calm ? Duration.zero : const Duration(milliseconds: 900),
          curve: const Cubic(0.2, 0.8, 0.3, 1),
          builder: (context, v, _) => CustomPaint(
            painter: _RingPainter(v, colour, track),
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text('$used', style: context.text.titleLarge?.copyWith(height: 1)),
                  Text(context.l10n.gamesMinutesShort, style: context.text.labelSmall),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  const _RingPainter(this.share, this.colour, this.track);

  final double share;
  final Color colour;
  final Color track;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromCircle(center: size.center(Offset.zero), radius: 34);
    final stroke = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 10
      ..strokeCap = StrokeCap.round;
    canvas.drawArc(rect, 0, 2 * math.pi, false, stroke..color = track);
    if (share > 0) canvas.drawArc(rect, -math.pi / 2, 2 * math.pi * share, false, stroke..color = colour);
  }

  @override
  bool shouldRepaint(_RingPainter old) => old.share != share || old.colour != colour;
}
