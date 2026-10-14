import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../shared/motion/juice.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../challenges/data/challenges_repository.dart';
import '../data/games_repository.dart';
import '../domain/games.dart';
import 'art/art.dart';
import 'game_words.dart';

/// A guardian's play time and limits for their child, in the academy's own
/// colours: today's minutes against the limit as a ring, the minutes a day
/// (never more than the academy's), the night lock, and games on or off.
class GuardianLimitsCard extends StatefulWidget {
  const GuardianLimitsCard({super.key, required this.learnerId, required this.allowance, required this.limits, required this.onSaved});

  final String learnerId;
  final Allowance allowance;
  final GameLimits limits;

  /// A choice was saved: read the child's play again.
  final Future<void> Function() onSaved;

  @override
  State<GuardianLimitsCard> createState() => _GuardianLimitsCardState();
}

class _GuardianLimitsCardState extends State<GuardianLimitsCard> {
  bool _saving = false;

  Future<void> _save({bool? enabled, int? minutes, bool keepMinutes = true, bool? night}) async {
    if (_saving) return;
    final now = widget.limits;
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    final l = context.l10n;
    try {
      await context.read<GamesRepository>().setLimits(
        widget.learnerId,
        enabled: enabled ?? now.enabled,
        dailyMinutes: keepMinutes ? now.dailyMinutes : minutes,
        nightLock: night,
      );
      if (!mounted) return;
      await widget.onSaved();
      (messenger..removeCurrentSnackBar()).showSnackBar(SnackBar(content: Text(l.gamesSaved)));
    } on ApiProblem catch (p) {
      if (mounted) (messenger..removeCurrentSnackBar()).showSnackBar(SnackBar(content: Text(context.problemText(p))));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final a = widget.allowance;
    final limits = widget.limits;
    return LmsCard(
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
                          a.why(l, kids: false),
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
            child: Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final (minutes, label) in [for (final m in limits.allowed) (m, l.gamesChoice(m)), (null, l.gamesAcademyChoice)])
                  _Choice(
                    label: label,
                    selected: limits.dailyMinutes == minutes,
                    onTap: _saving ? null : () => _save(minutes: minutes, keepMinutes: false),
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
    );
  }
}

/// What the child practised in the games this week: the lesson practised
/// most, the question hardest first time, the best round.
class GuardianGlanceCard extends StatelessWidget {
  const GuardianGlanceCard({super.key, required this.glance});

  final Glance? glance;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final g = glance;
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.gamesLearned, style: context.text.titleMedium),
          const SizedBox(height: 8),
          if (g == null || g.quiet)
            Text(l.gamesQuiet, style: context.text.bodySmall)
          else ...[
            if (g.practisedTitle != null)
              _Line(
                icon: Icons.check_rounded,
                colour: c.brand,
                soft: c.brandSoft,
                text: l.gamesPractised(g.practisedTitle!, l.gamesRoundsCount(g.practisedRounds), l.gamesGamesCount(g.practisedGames)),
              ),
            if (g.hardest != null)
              _Line(
                icon: Icons.priority_high_rounded,
                colour: c.attentionInk,
                soft: c.attentionSoft,
                text: l.gamesHardest(g.hardest!.length > 60 ? '${g.hardest!.substring(0, 60)}…' : g.hardest!),
              ),
            if (g.bestCorrect != null)
              _Line(
                icon: Icons.star_rounded,
                colour: c.infoInk,
                soft: c.infoSoft,
                text: l.gamesBestRound(g.bestCorrect!, g.bestTotal ?? 0, g.bestGame ?? ''),
              ),
          ],
        ],
      ),
    );
  }
}

/// A creature the child hatched today, with a heart to send them.
class GuardianHatchedCard extends StatefulWidget {
  const GuardianHatchedCard({super.key, required this.learnerId, required this.glance});

  final String learnerId;
  final Glance glance;

  @override
  State<GuardianHatchedCard> createState() => _GuardianHatchedCardState();
}

class _GuardianHatchedCardState extends State<GuardianHatchedCard> {
  int _hearts = 0;

  Future<void> _cheer() async {
    final messenger = ScaffoldMessenger.of(context);
    final l = context.l10n;
    Juice.reward();
    setState(() => _hearts++);
    try {
      await context.read<ChallengesRepository>().cheer(widget.learnerId, 'heart');
      (messenger..removeCurrentSnackBar()).showSnackBar(SnackBar(content: Text(l.gamesCheered)));
    } on ApiProblem catch (p) {
      if (mounted) (messenger..removeCurrentSnackBar()).showSnackBar(SnackBar(content: Text(context.problemText(p))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final h = widget.glance.hatched!;
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
                  h.source == 'mastery'
                      ? l.gamesHatchedMastery(widget.glance.hatchedFound)
                      : l.gamesHatchedStars(widget.glance.hatchedFound),
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

/// The games' promise to a family: nothing to buy, no ads, no strangers.
class GamesSafetyNote extends StatelessWidget {
  const GamesSafetyNote({super.key});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(14)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.verified_user_outlined, color: c.brandDeep, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(context.l10n.gamesSafety, style: context.text.bodySmall?.copyWith(color: c.brandDeep, height: 1.5)),
          ),
        ],
      ),
    );
  }
}

/// One choice of minutes a day: the academy's colour when chosen.
class _Choice extends StatelessWidget {
  const _Choice({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      button: true,
      child: Material(
        color: selected ? c.brand : c.card,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: selected ? c.brand : c.line),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minWidth: 56, minHeight: 44),
            child: Center(
              widthFactor: 1,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Text(label, style: context.text.labelLarge?.copyWith(color: selected ? Colors.white : c.ink)),
              ),
            ),
          ),
        ),
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
