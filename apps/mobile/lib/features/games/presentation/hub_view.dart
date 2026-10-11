import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/motion.dart';
import '../domain/games.dart';
import 'art/art.dart';
import 'game_sound.dart';
import 'games_ui.dart';
import 'play/play_page.dart';
import 'play/result_cards.dart';

/// The constellation's stars: the week's stars light them in order.
const _points = <Offset>[
  Offset(20, 120), Offset(58, 88), Offset(98, 112), Offset(136, 66), Offset(176, 92), Offset(208, 44), Offset(244, 76), //
  Offset(284, 30), Offset(314, 92), Offset(276, 130), Offset(236, 146), Offset(196, 126), Offset(156, 146), Offset(116, 140),
];

/// The games for middle school, secondary school and adult students (the
/// designs Middle and Teen): one dark screen in their look. Middle school
/// races itself on the first quest (your last time against your best, your
/// buddy as the racer); the older ones review on a constellation that the
/// week's stars light up. Then the other quests, every island's games, and
/// the last rounds.
class GamesHub extends StatelessWidget {
  const GamesHub({super.key, required this.world, required this.base});

  final GamesWorld world;

  /// Where the games are in this look (the album is under it).
  final String base;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final w = world;
    final look = w.stage == 'middle' ? 'middle' : (w.stage == 'teen' ? 'teen' : 'classic');
    final race = look == 'middle';
    final p = HubPalette.of(look);
    final quest = w.quests.firstOrNull;
    final may = w.allowance.may;
    final font = context.shape.headingFont;
    TextStyle t(double size, {FontWeight weight = FontWeight.w700, Color? color, double? height}) =>
        TextStyle(fontFamily: font, fontSize: size, fontWeight: weight, color: color ?? const Color(0xFFF1F5F9), height: height);

    Future<void> play(GameCard g, {String? quest, int? best}) async {
      final cubit = context.read<LoadCubit<GamesWorld>>();
      final outcome = await openRound(context, PlayRequest(gameId: g.id, quest: quest, look: look, bestBefore: best));
      unawaited(cubit.load());
      final named = outcome?.named;
      if (named != null && context.mounted) context.go('$base/album?new=$named');
    }

    final title = race
        ? l.gamesRace
        : quest != null
        ? quest.shownTitle
        : (look == 'teen' ? l.gamesTitleTeen : l.gamesTitleClassic);
    final kicker = race ? (quest != null ? l.gamesFrom(quest.shownTitle) : l.gamesTitleMiddle) : l.gamesReviewMinutes(5);

    final children = <Widget>[
      // Head: what this is, and the stars (middle) or the streak of days (older).
      SizedBox(
        height: 70,
        child: Row(
          children: [
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    kicker,
                    style: t(12.5, weight: FontWeight.w600, color: p.muted),
                  ),
                  Semantics(
                    header: true,
                    child: Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: t(21, weight: FontWeight.w800, height: 1.25),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Semantics(
              label: race ? l.gamesStars(w.stars) : l.gamesStreak(w.streak),
              excludeSemantics: true,
              child: Container(
                height: 38,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: p.card,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: p.line, width: 2),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: race
                      ? [
                          const Icon(Icons.star_rounded, color: gwSun, size: 18),
                          const SizedBox(width: 4),
                          CountUp(w.stars, style: t(15, weight: FontWeight.w800)),
                        ]
                      : [
                          Icon(Icons.bolt_rounded, color: p.accent, size: 18),
                          const SizedBox(width: 4),
                          Text(l.gamesStreak(w.streak), style: t(15, weight: FontWeight.w800)),
                        ],
                ),
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              tooltip: l.gamesAlbum,
              onPressed: () {
                GameSound.play(GameEffect.cheep);
                context.go('$base/album');
              },
              icon: w.buddy != null ? CreatureArt(w.buddy!.look, size: 34) : Icon(Icons.collections_bookmark_outlined, color: p.muted),
            ),
          ],
        ),
      ),
      if (!may) _Note(text: w.allowance.reasonLabel ?? '', palette: p),
      if (quest == null)
        _Card(
          palette: p,
          look: look,
          child: Text(l.gamesNone, style: t(13, color: p.muted)),
        )
      else if (race)
        ..._race(context, l, w, quest, p, t, may, () => play(quest.game, quest: quest.key, best: quest.bestSeconds))
      else
        ..._review(context, l, w, quest, p, t, look, may, () => play(quest.game, quest: quest.key)),
      if (w.quests.length > 1) ...[
        _H2(l.gamesMore, p),
        for (final q in w.quests.skip(1))
          _Item(
            palette: p,
            enabled: may,
            onTap: () => play(q.game, quest: q.key, best: q.bestSeconds),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _Tag(text: questReason(l, q.reason), homework: q.reason == 'homework', palette: p),
                const SizedBox(height: 3),
                Text(q.shownTitle, style: t(16, height: 1.4)),
                Text(
                  [q.game.title, l.gamesItems(q.items), if (q.bestSeconds != null) l.gamesBest(secondsLabel(l, q.bestSeconds))].join(' · '),
                  style: t(12.5, weight: FontWeight.w500, color: p.muted),
                ),
              ],
            ),
          ),
      ],
      _H2(l.gamesAllGames, p),
      _GamesGrid(world: w, palette: p, may: may, onPlay: (g) => play(g)),
      if (w.recent.isNotEmpty) ...[
        _H2(l.gamesRecent, p),
        for (final r in w.recent)
          Container(
            margin: const EdgeInsets.only(bottom: 6),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: p.card,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: p.line),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(r.game, style: t(14, weight: FontWeight.w500)),
                    ),
                    Text('${'★' * r.stars}${'☆' * (3 - r.stars).clamp(0, 3)}', style: const TextStyle(color: Color(0xFFFCC419))),
                  ],
                ),
                Text(
                  [l.gamesScore(r.correct, r.total), if (r.at != null) r.at!.label].join(' · '),
                  style: t(12, weight: FontWeight.w500, color: p.muted),
                ),
              ],
            ),
          ),
      ],
    ];

    return ColoredBox(
      color: p.bg,
      child: SafeArea(
        bottom: false,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(12, 0, 12, 24),
          children: [
            for (var i = 0; i < children.length; i++)
              Padding(padding: const EdgeInsets.only(bottom: 12), child: children[i].appear(context, i)),
          ],
        ),
      ),
    );
  }

  List<Widget> _race(
    BuildContext context,
    AppLocalizations l,
    GamesWorld w,
    Quest quest,
    HubPalette p,
    TextStyle Function(double, {FontWeight weight, Color? color, double? height}) t,
    bool may,
    VoidCallback go,
  ) {
    final best = quest.bestSeconds;
    final last = quest.lastSeconds;
    final ref = math.max(30, (math.max(best ?? 0, last ?? 0) * 1.3).round());
    double at(int? s) => s == null ? 0.8 : 0.06 + 0.74 * math.min(1, s / ref);
    final buddy = w.buddy?.look ?? w.islands.firstOrNull?.creatures.firstOrNull?.look ?? const Look({});
    final calm = context.calm;
    return [
      _Card(
        palette: p,
        look: 'middle',
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(l.gamesStart, style: t(12.5, color: p.muted)),
                Text(l.gamesFinish, style: t(12.5, color: p.muted)),
              ],
            ),
            const SizedBox(height: 6),
            SizedBox(
              height: 128,
              child: LayoutBuilder(
                builder: (context, box) {
                  final width = box.maxWidth;
                  // The track goes the way the language reads: start at its start.
                  final rtl = Directionality.of(context) == TextDirection.rtl;
                  double x(double share) => rtl ? width * (1 - share) - 70 : width * share - 7;
                  Widget racer = Column(
                    children: [
                      CreatureArt(buddy, size: 60, motion: true, line: '#0B0726'),
                      Text(l.gamesYou, style: t(11, color: p.alt)),
                    ],
                  );
                  if (!calm) racer = racer.animate().moveY(begin: 30, end: 0, duration: 600.ms, curve: Curves.easeOutBack).fadeIn();
                  return Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Positioned(top: 30, left: 0, right: 0, child: Container(height: 2, color: p.line.withValues(alpha: 0.8))),
                      Positioned(top: 94, left: 0, right: 0, child: Container(height: 2, color: p.line.withValues(alpha: 0.8))),
                      PositionedDirectional(
                        end: 0,
                        top: 4,
                        child: Icon(Icons.sports_score_rounded, color: Colors.white.withValues(alpha: 0.9), size: 26),
                      ),
                      if (best != null)
                        Positioned(
                          left: x(at(best)),
                          top: 0,
                          child: Opacity(
                            opacity: 0.5,
                            child: SizedBox(
                              width: 70,
                              child: Column(
                                children: [
                                  CreatureArt(buddy, size: 56, shadow: true),
                                  Text(l.gamesBestGhost, style: t(11, color: p.muted)),
                                ],
                              ),
                            ),
                          ),
                        ),
                      Positioned(
                        left: x(at(last)),
                        top: 60,
                        child: SizedBox(width: 70, child: racer),
                      ),
                    ],
                  );
                },
              ),
            ),
            Row(
              children: [
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(999),
                    child: LinearProgressIndicator(
                      value: last == null ? 1 : math.min(1, last / ref),
                      minHeight: 10,
                      backgroundColor: Color.lerp(p.bg, Colors.black, 0.3),
                      color: const Color(0xFF3BC9DB),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Text(
                  secondsLabel(l, last ?? best),
                  textDirection: TextDirection.ltr,
                  style: t(15, weight: FontWeight.w800),
                ),
              ],
            ),
          ],
        ),
      ),
      _Card(
        palette: p,
        look: 'middle',
        child: Column(
          children: [
            Text(
              l.gamesRaceReady(l.gamesItems(quest.items), quest.shownTitle),
              textAlign: TextAlign.center,
              style: t(16, weight: FontWeight.w600, color: Color.lerp(Colors.white, p.muted, 0.15)),
            ),
            const SizedBox(height: 4),
            Text(
              best == null ? l.gamesFirstTime : l.gamesBest(secondsLabel(l, best)),
              textAlign: TextAlign.center,
              style: t(26, weight: FontWeight.w800, color: Colors.white),
            ),
            const SizedBox(height: 14),
            HubGoButton(label: l.gamesGo, palette: p, look: 'middle', onTap: may ? go : null),
          ],
        ),
      ),
    ];
  }

  List<Widget> _review(
    BuildContext context,
    AppLocalizations l,
    GamesWorld w,
    Quest quest,
    HubPalette p,
    TextStyle Function(double, {FontWeight weight, Color? color, double? height}) t,
    String look,
    bool may,
    VoidCallback go,
  ) {
    final now = DateTime.now().toUtc();
    final week = w.history.where((r) => r.at != null && now.difference(r.at!.utc).inDays < 7).fold(0, (sum, r) => sum + r.stars);
    final lit = math.min(_points.length, week);
    return [
      _Card(
        palette: p,
        look: look,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text(l.gamesConstellation, style: t(16))),
                Text(
                  l.gamesLit(lit, _points.length),
                  style: t(14, weight: FontWeight.w800, color: p.accent),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Semantics(
              label: l.gamesLit(lit, _points.length),
              excludeSemantics: true,
              child: SizedBox(
                height: 160,
                child: FittedBox(
                  child: SizedBox(
                    width: 334,
                    height: 160,
                    child: CustomPaint(
                      painter: _Constellation(lit: lit, accent: p.accent),
                    ),
                  ),
                ),
              ),
            ),
            Text(
              l.gamesLitHint,
              style: t(12.5, weight: FontWeight.w500, color: p.muted),
            ),
          ],
        ),
      ),
      Container(
        padding: const EdgeInsets.fromLTRB(18, 26, 18, 18),
        decoration: BoxDecoration(
          color: p.card,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFF334155)),
        ),
        child: Column(
          children: [
            Text(questReason(l, quest.reason), style: t(12.5, color: p.alt)),
            const SizedBox(height: 8),
            Text(quest.shownTitle, textAlign: TextAlign.center, style: t(26, height: 1.35)),
            const SizedBox(height: 8),
            Text(
              l.gamesDeck(quest.items, quest.game.title),
              style: t(12.5, weight: FontWeight.w500, color: p.muted),
            ),
            const SizedBox(height: 8),
            HubGoButton(label: l.gamesStartReview, palette: p, look: look, onTap: may ? go : null),
          ],
        ),
      ),
    ];
  }
}

class _Constellation extends CustomPainter {
  const _Constellation({required this.lit, required this.accent});

  final int lit;
  final Color accent;

  @override
  void paint(Canvas canvas, Size size) {
    for (var i = 0; i < _points.length - 1; i++) {
      canvas.drawLine(
        _points[i],
        _points[i + 1],
        Paint()
          ..color = i + 1 < lit ? accent : const Color(0xFF263248)
          ..strokeWidth = 2,
      );
    }
    for (var i = 0; i < _points.length; i++) {
      final on = i < lit;
      if (on)
        canvas.drawCircle(
          _points[i],
          10,
          Paint()
            ..color = accent.withValues(alpha: 0.25)
            ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6),
        );
      canvas.drawCircle(_points[i], on ? 6 : 3.5, Paint()..color = on ? accent : const Color(0xFF475569));
    }
  }

  @override
  bool shouldRepaint(_Constellation old) => old.lit != lit || old.accent != accent;
}

class _Card extends StatelessWidget {
  const _Card({required this.palette, required this.look, required this.child});

  final HubPalette palette;
  final String look;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    decoration: BoxDecoration(
      color: palette.card,
      borderRadius: BorderRadius.circular(look == 'middle' ? 18 : 16),
      border: Border.all(color: palette.line, width: look == 'middle' ? 2 : 1),
    ),
    child: child,
  );
}

class _Note extends StatelessWidget {
  const _Note({required this.text, required this.palette});

  final String text;
  final HubPalette palette;

  @override
  Widget build(BuildContext context) => Semantics(
    liveRegion: true,
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: palette.card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: palette.accent),
      ),
      child: Text(
        text,
        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14),
      ),
    ),
  );
}

class _H2 extends StatelessWidget {
  const _H2(this.text, this.palette);

  final String text;
  final HubPalette palette;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(4, 6, 4, 0),
    child: Semantics(
      header: true,
      child: Text(
        text,
        style: TextStyle(fontFamily: context.shape.headingFont, fontSize: 15, fontWeight: FontWeight.w700, color: palette.muted),
      ),
    ),
  );
}

class _Tag extends StatelessWidget {
  const _Tag({required this.text, required this.homework, required this.palette});

  final String text;
  final bool homework;
  final HubPalette palette;

  @override
  Widget build(BuildContext context) {
    final colour = homework ? const Color(0xFFFFA94D) : palette.alt;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 1),
      decoration: BoxDecoration(color: colour.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(999)),
      child: Text(
        text,
        style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: colour),
      ),
    );
  }
}

class _Item extends StatelessWidget {
  const _Item({required this.palette, required this.enabled, required this.onTap, required this.child});

  final HubPalette palette;
  final bool enabled;
  final VoidCallback onTap;
  final Widget child;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Opacity(
      opacity: enabled ? 1 : 0.55,
      child: Material(
        color: palette.card,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: palette.line),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: enabled ? onTap : null,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: SizedBox(width: double.infinity, child: child),
          ),
        ),
      ),
    ),
  );
}

class _GamesGrid extends StatelessWidget {
  const _GamesGrid({required this.world, required this.palette, required this.may, required this.onPlay});

  final GamesWorld world;
  final HubPalette palette;
  final bool may;
  final ValueChanged<GameCard> onPlay;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = palette;
    final games = [
      for (final i in world.islands)
        for (final g in i.games) (i, g),
    ];
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisExtent: 92,
        crossAxisSpacing: 8,
        mainAxisSpacing: 8,
      ),
      itemCount: games.length,
      itemBuilder: (context, i) {
        final (island, g) = games[i];
        final on = may && g.playable;
        final status = !g.playable
            ? l.gamesNeedsPractice
            : g.plays > 0
            ? '${'★' * g.best}${'☆' * (3 - g.best).clamp(0, 3)}'
            : l.gamesBadgeNew;
        return Opacity(
          opacity: on ? 1 : 0.5,
          child: Material(
            color: p.card,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: BorderSide(color: p.line),
            ),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: on ? () => onPlay(g) : null,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(island.emoji, style: const TextStyle(fontSize: 20, height: 1.1)),
                    Text(
                      g.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: Colors.white),
                    ),
                    Text(
                      status,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 12, color: p.accent),
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
}
