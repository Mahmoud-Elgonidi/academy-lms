import 'dart:async';

import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../challenges/presentation/widgets/art.dart';
import '../../../challenges/presentation/widgets/chest.dart';
import '../../../games/presentation/art/art.dart';
import '../../../games/presentation/game_sound.dart';
import '../../../games/presentation/games_ui.dart';
import '../../../games/presentation/play/hatch_view.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';

/// The week's chest: how many of the week's missions are done, wiggling
/// when it is ready, and opening under the student's tap (lid up, light,
/// stars) with the stars (and, for kids, a sticker) inside. A guardian sees
/// how far it is; only the student opens it.
class JourneyChestCard extends StatefulWidget {
  const JourneyChestCard({super.key, required this.map, required this.place, this.kids = false, this.say, this.name});

  final JourneyMap map;
  final JourneyPlace place;
  final bool kids;
  final void Function(String text)? say;

  /// The child's first name, on a guardian's screen.
  final String? name;

  @override
  State<JourneyChestCard> createState() => _JourneyChestCardState();
}

class _JourneyChestCardState extends State<JourneyChestCard> with SingleTickerProviderStateMixin {
  late final AnimationController _open = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 750),
    value: widget.map.chest?.opened ?? false ? 1 : 0,
  );
  final _chestKey = GlobalKey();
  bool _busy = false;

  @override
  void didUpdateWidget(JourneyChestCard old) {
    super.didUpdateWidget(old);
    final chest = widget.map.chest;
    if (!_busy && chest != null && old.map.chest?.period != chest.period) _open.value = chest.opened ? 1 : 0;
  }

  @override
  void dispose() {
    _open.dispose();
    super.dispose();
  }

  Future<void> _tap() async {
    final chest = widget.map.chest;
    if (chest == null || !chest.canOpen) {
      Juice.tap();
      return;
    }
    if (_busy) return;
    setState(() => _busy = true);
    final actions = JourneyActions(context, widget.map, widget.place, say: widget.say);
    final from = Celebration.centreOf(_chestKey.currentContext ?? context);
    if (!context.calm) unawaited(_open.animateTo(1, curve: Curves.easeOutBack));
    final opened = await actions.openChest(from: from);
    if (!mounted) return;
    if (opened == null) {
      await _open.animateBack(0);
    } else {
      _open.value = 1;
    }
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final chest = widget.map.chest!;
    final kids = widget.kids;
    final ready = chest.ready && chest.canOpen;
    final size = kids ? 120.0 : 76.0;
    Widget art = AnimatedBuilder(
      animation: _open,
      builder: (context, _) => Stack(
        alignment: Alignment.center,
        clipBehavior: Clip.none,
        children: [
          if (ready || _open.value > 0) SunRays(size: size * 1.5, colour: c.rewardSoft),
          TreasureChest(key: _chestKey, open: _open.value, size: size),
        ],
      ),
    );
    if (ready && !_busy) art = art.wiggle(context);

    final String headline;
    Widget? under;
    if (chest.opened) {
      headline = l.chestOpened;
    } else if (ready) {
      headline = l.chestReady;
    } else if (chest.ready) {
      headline = l.journeyChestWaiting(widget.name ?? '');
    } else if (chest.missed) {
      headline = l.journeyChestMissed;
    } else {
      headline = l.journeyChestProgress(chest.progress, chest.target);
      under = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 6),
          ProgressBar(chest.target == 0 ? 0 : chest.progress / chest.target, colour: c.reward, height: 10),
          const SizedBox(height: 6),
          Text(
            l.journeyChestHint(chest.stars),
            style: kids ? gwText(context, 12.5, weight: FontWeight.w600, color: const Color(0xFF3B4F72)) : context.text.bodySmall,
          ),
        ],
      );
    }
    final Widget line = Text(
      headline,
      textAlign: kids ? TextAlign.center : TextAlign.start,
      style: kids ? gwText(context, 14, weight: FontWeight.w700) : context.text.bodyMedium,
    );
    final words = Column(
      crossAxisAlignment: kids ? CrossAxisAlignment.center : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(l.journeyChestTitle, style: kids ? gwText(context, 18) : context.text.titleMedium),
        const SizedBox(height: 2),
        if (ready) line.bob(context, by: 3) else line,
        ?under,
      ],
    );
    final label = '${l.journeyChestTitle}. $headline';
    if (kids) {
      return Semantics(
        button: ready,
        label: label,
        child: Button3d(
          color: ready ? const Color(0xFFFFF4D6) : Colors.white,
          radius: 24,
          depth: 5,
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
          onTap: _tap,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                height: size * 0.95,
                child: Center(child: art),
              ),
              const SizedBox(height: 6),
              words,
            ],
          ),
        ),
      );
    }
    return Semantics(
      button: ready,
      label: label,
      excludeSemantics: true,
      child: LmsCard(
        color: ready ? c.rewardSoft : null,
        onTap: ready ? _tap : null,
        child: Row(
          children: [
            SizedBox(
              width: size * 1.1,
              height: size,
              child: Center(child: art),
            ),
            const SizedBox(width: 12),
            Expanded(child: words),
          ],
        ),
      ),
    );
  }
}

/// The egg the journey's stars fill: twelve stars hatch it. Ready, it
/// wobbles; a child knocks it three times right here, then it hatches and
/// they name what came out. Middle school taps "hatch it".
class EggCard extends StatefulWidget {
  const EggCard({super.key, required this.map, required this.place, this.kids = false, this.say});

  final JourneyMap map;
  final JourneyPlace place;
  final bool kids;
  final void Function(String text)? say;

  @override
  State<EggCard> createState() => _EggCardState();
}

class _EggCardState extends State<EggCard> {
  int _taps = 0;
  bool _busy = false;

  Future<void> _hatch({bool cracked = false}) async {
    if (_busy) return;
    setState(() => _busy = true);
    await JourneyActions(context, widget.map, widget.place, say: widget.say).hatch(cracked: cracked);
    if (mounted) {
      setState(() {
        _busy = false;
        _taps = 0;
      });
    }
  }

  void _knock() {
    if (_busy || _taps >= 3) return;
    GameSound.play(GameEffect.knock);
    setState(() => _taps++);
    if (_taps >= 3) unawaited(_hatch(cracked: true));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final games = widget.map.games!;
    final ready = games.eggReady;
    final next = widget.map.courses.map((c) => c.here?.next).nonNulls.firstOrNull;
    final egg = eggSvg(belly: next?.colour('belly') ?? '#F3E3C3', body: next?.colour('body') ?? '#E0C79A');
    final share = games.eggNeed == 0 ? 0.0 : (games.eggHave / games.eggNeed).clamp(0.0, 1.0);

    if (widget.kids) {
      if (!ready) {
        return Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFFFF4D6),
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: gwInk, width: 3),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SvgArt(egg, width: 54, height: 66).bob(context, by: 2),
              const SizedBox(height: 6),
              Text(l.gamesEggCardKids(games.eggHave, games.eggNeed), textAlign: TextAlign.center, style: gwText(context, 13.5)),
              const SizedBox(height: 6),
              _EggBar(share: share),
            ],
          ),
        );
      }
      Widget art = SvgArt(eggCrackSvg(_taps), width: 84, height: 105);
      art = _taps == 0
          ? art.wiggle(context)
          : art.animate(key: ValueKey(_taps)).shakeX(hz: 6, amount: 4, duration: const Duration(milliseconds: 400));
      return Semantics(
        button: true,
        label: '${l.journeyEggReady} ${l.gamesHatchEgg(3 - _taps)}',
        excludeSemantics: true,
        child: Button3d(
          color: const Color(0xFFEDE7FF),
          radius: 24,
          depth: 5,
          padding: const EdgeInsets.all(12),
          onTap: _knock,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              art,
              const SizedBox(height: 4),
              Text(l.journeyEggReady, style: gwText(context, 17)),
              Text(
                [l.gamesHatchTap0, l.gamesHatchTap1, l.gamesHatchTap2, l.gamesHatchTap3][_taps.clamp(0, 3)],
                style: gwText(context, 12.5, weight: FontWeight.w700, color: const Color(0xFF5C4A99)),
              ),
            ],
          ),
        ),
      );
    }

    return LmsCard(
      color: ready ? c.rewardSoft : null,
      child: Row(
        children: [
          SvgArt(egg, width: 40, height: 49).wiggle(context),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(ready ? l.journeyEggReady : l.gamesEgg(games.eggHave, games.eggNeed), style: context.text.titleSmall),
                const SizedBox(height: 6),
                if (!ready) ProgressBar(share, colour: c.reward, height: 8) else Text(l.journeyEggHint, style: context.text.bodySmall),
              ],
            ),
          ),
          if (ready) ...[
            const SizedBox(width: 8),
            FilledButton(onPressed: _busy ? null : _hatch, child: Text(l.journeyEggHatch)).shimmerIf(context, !_busy),
          ],
        ],
      ),
    );
  }
}

class _EggBar extends StatelessWidget {
  const _EggBar({required this.share});

  final double share;

  @override
  Widget build(BuildContext context) => Container(
    height: 12,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(999),
      border: Border.all(color: gwInk, width: 2),
    ),
    clipBehavior: Clip.antiAlias,
    child: Align(
      alignment: AlignmentDirectional.centerStart,
      child: FractionallySizedBox(
        widthFactor: share,
        heightFactor: 1,
        child: const ColoredBox(color: gwSun),
      ),
    ),
  );
}

/// The last lesson's stars: on time, staying to the end. The kids see it
/// once per lesson, as a little celebration; the others in a line.
class LastLessonCard extends StatelessWidget {
  const LastLessonCard({super.key, required this.lesson, required this.rewards, this.kids = false, this.onDone, this.title});

  final LastLesson lesson;

  /// Stars are shown (not in the plain style).
  final bool rewards;
  final bool kids;

  /// "Great!": seen.
  final VoidCallback? onDone;

  /// The heading ("Your last lesson", "Ziad's last lesson").
  final String? title;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final labels = lesson.labels.join(' · ');
    final when = lesson.startsAt?.label;
    if (kids) {
      return Container(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: gwInk, width: 3),
          boxShadow: const [BoxShadow(color: gwInk, offset: Offset(0, 5))],
        ),
        child: Column(
          children: [
            Text(
              title ?? l.journeyLastLesson,
              style: gwText(context, 15, weight: FontWeight.w700, color: const Color(0xFF5C4A99)),
            ),
            if (rewards) ...[
              const SizedBox(height: 4),
              StarDots(lesson.stars, size: 34, on: gwSun, off: const Color(0xFFE9ECEF)),
              Text(l.journeyLessonStars(lesson.stars), style: gwText(context, 18)),
            ],
            if (labels.isNotEmpty)
              Text(
                labels,
                textAlign: TextAlign.center,
                style: gwText(context, 14, weight: FontWeight.w700),
              ),
            if (onDone != null) ...[
              const SizedBox(height: 8),
              Button3d(
                color: gwSun,
                height: 40,
                shrink: true,
                depth: 4,
                padding: const EdgeInsets.symmetric(horizontal: 22),
                onTap: onDone,
                child: Text(l.awesome, style: gwText(context, 16)),
              ),
            ],
          ],
        ),
      );
    }
    return Row(
      children: [
        if (rewards) ...[StarDots(lesson.stars, size: 18), const SizedBox(width: 8)],
        Expanded(
          child: Text([title ?? l.journeyLastLesson, ?when, if (labels.isNotEmpty) labels].join(' · '), style: context.text.bodySmall),
        ),
      ],
    );
  }
}

/// The kids' last lesson, once per lesson: stars bursting, then "great!" puts it away.
class LastLessonBanner extends StatefulWidget {
  const LastLessonBanner({super.key, required this.learnerId, required this.lesson, required this.rewards});

  final String learnerId;
  final LastLesson lesson;
  final bool rewards;

  @override
  State<LastLessonBanner> createState() => _LastLessonBannerState();
}

class _LastLessonBannerState extends State<LastLessonBanner> {
  bool _show = false;

  @override
  void initState() {
    super.initState();
    unawaited(_read());
  }

  @override
  void didUpdateWidget(LastLessonBanner old) {
    super.didUpdateWidget(old);
    if (old.lesson.id != widget.lesson.id) unawaited(_read());
  }

  Future<void> _read() async {
    final seen = await SeenLesson.seen(widget.learnerId, widget.lesson.id);
    if (!mounted || seen) return;
    setState(() => _show = true);
    if (widget.rewards && widget.lesson.stars >= 3) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Celebration.show(context, burst: Burst.stars, from: Celebration.centreOf(context), count: 40);
      });
    }
  }

  void _done() {
    Juice.tap();
    setState(() => _show = false);
    unawaited(SeenLesson.see(widget.learnerId, widget.lesson.id));
  }

  @override
  Widget build(BuildContext context) {
    if (!_show) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: LastLessonCard(lesson: widget.lesson, rewards: widget.rewards, kids: true, onDone: _done).appear(context, 1),
    );
  }
}
