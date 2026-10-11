import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../games/presentation/art/art.dart';
import '../../../games/presentation/games_ui.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';

/// What the learner picked on a station's sheet; it runs once the sheet is closed.
sealed class _Pick {
  const _Pick();
}

class _Play extends _Pick {
  const _Play(this.game);
  final StationGame game;
}

class _PlayHomework extends _Pick {
  const _PlayHomework(this.mission);
  final Mission mission;
}

class _Join extends _Pick {
  const _Join(this.mission);
  final Mission mission;
}

class _HandIn extends _Pick {
  const _HandIn();
}

/// A station's sheet: the lesson (and its unit), where it stands, its
/// missions (the next lesson to join, the homework to play or hand in, a
/// review), the games that play its own practice, and the race against
/// yourself. The kids' look draws it as the islands do.
Future<void> showStationSheet(
  BuildContext context, {
  required JourneyMap map,
  required JourneyPlace place,
  required JourneyIsland island,
  required JourneyStation station,
  void Function(String text)? say,
  void Function(String location)? go,
}) async {
  final kids = map.look == JourneyLook.kids;
  final pick = await showModalBottomSheet<_Pick>(
    context: context,
    useRootNavigator: true,
    isScrollControlled: true,
    showDragHandle: !kids,
    backgroundColor: kids ? Colors.transparent : context.colors.card,
    barrierColor: kids ? gwInk.withValues(alpha: 0.45) : null,
    builder: (_) => _StationSheet(map: map, island: island, station: station, kids: kids),
  );
  if (pick == null || !context.mounted) return;
  final actions = JourneyActions(context, map, place, say: say, go: go);
  final sky = island.colors['sky'];
  switch (pick) {
    case _Play(:final game):
      await actions.playStation(station, game, sky: sky);
    case _PlayHomework(:final mission):
      await actions.playHomework(mission, sky: sky);
    case _Join(:final mission):
      await actions.join(mission);
    case _HandIn():
      actions.handIn();
  }
}

class _StationSheet extends StatelessWidget {
  const _StationSheet({required this.map, required this.island, required this.station, required this.kids});

  final JourneyMap map;
  final JourneyIsland island;
  final JourneyStation station;
  final bool kids;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = station;
    final missions = map.missionsOn(s);
    final games = map.playable && s.playable ? s.games : const <StationGame>[];
    final allowance = map.games?.allowance;
    final self = !map.guardian;
    TextStyle title(double size) => kids ? gwText(context, size, height: 1.2) : context.text.titleLarge!.copyWith(fontSize: size - 2);
    TextStyle body() =>
        kids ? gwText(context, 14.5, weight: FontWeight.w600, color: const Color(0xFF3B4F72), height: 1.35) : context.text.bodyMedium!;
    TextStyle heading() => kids ? gwText(context, 16) : context.text.titleSmall!;

    final children = <Widget>[
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  [l.journeyLevel(island.index), if (s.unit != null && s.unit!.isNotEmpty) s.unit!].join(' · '),
                  style: kids ? gwText(context, 13, weight: FontWeight.w700, color: const Color(0xFF5C4A99)) : context.text.labelMedium,
                ),
                Semantics(header: true, child: Text(s.title, style: title(24))),
              ],
            ),
          ),
          const SizedBox(width: 10),
          _StateChip(station: s, kids: kids),
        ],
      ),
      if (s.todo) Text(l.journeyStationTodoNote, style: body()),
      if (s.review && !s.todo && map.playable) Text(l.journeyStationReviewNote, style: body()),
      if (s.practice != null && s.practice!.rounds > 0)
        Text(l.journeyPractised(l.gamesRoundsCount(s.practice!.rounds), s.practice!.percent), style: body()),
      for (final m in missions) _MissionRow(mission: m, map: map, kids: kids, self: self),
      if (games.isNotEmpty) ...[
        Text(map.guardian ? l.gamesTogether : l.journeyPlayStation, style: heading()),
        if (allowance != null && !allowance.may) _Refused(text: allowance.reasonLabel ?? '', kids: kids),
        for (final g in games)
          kids
              ? _KidsGameTile(game: g, onTap: () => Navigator.of(context).pop(_Play(g)))
              : _GameTile(game: g, onTap: () => Navigator.of(context).pop(_Play(g))),
      ] else if (map.playable && s.playable && !map.guardian)
        Text(l.journeyNoGames, style: body()),
      if (s.race != null && s.race!.rounds > 0 && map.playable) _RaceLine(race: s.race!, kids: kids),
    ];

    final list = SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(18, kids ? 18 : 0, 18, 18 + MediaQuery.paddingOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [for (final (i, w) in children.indexed) Padding(padding: const EdgeInsets.only(bottom: 12), child: w.appear(context, i))],
      ),
    );
    // At most most of the screen: the sheet's own room, not the window's size.
    final sheet = LayoutBuilder(
      builder: (context, box) => ConstrainedBox(
        constraints: BoxConstraints(maxHeight: box.maxHeight * 0.85),
        child: kids
            ? Container(
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
                  border: Border(top: BorderSide(color: gwInk, width: 4)),
                ),
                child: list,
              )
            : list,
      ),
    );
    return SafeArea(top: false, child: sheet);
  }
}

class _StateChip extends StatelessWidget {
  const _StateChip({required this.station, required this.kids});

  final JourneyStation station;
  final bool kids;

  @override
  Widget build(BuildContext context) {
    final label = stationStateLabel(context.l10n, station);
    if (!kids) {
      final tone = switch (station.state) {
        'here' => Tone.reward,
        'todo' => Tone.neutral,
        _ when station.review => Tone.attention,
        _ => Tone.brand,
      };
      return Pill(label, tone: tone, icon: stationIcon(station));
    }
    final colour = switch (station.state) {
      'here' => gwSun,
      'todo' => const Color(0xFFE9ECEF),
      _ when station.review => const Color(0xFFFFC078),
      _ => const Color(0xFF8CE99A),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: colour,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: gwInk, width: 2),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(stationIcon(station), size: 15, color: gwInk),
          const SizedBox(width: 4),
          Text(label, style: gwText(context, 13)),
        ],
      ),
    );
  }
}

/// A station's state as an icon: done ✓, review ↺, here 📍, todo 🔒.
IconData stationIcon(JourneyStation s) => switch (s.state) {
  'here' => Icons.place_rounded,
  'todo' => Icons.lock_rounded,
  _ when s.review => Icons.replay_rounded,
  _ => Icons.check_rounded,
};

/// A mission on the station: the lesson to join, the homework to play or hand in.
class _MissionRow extends StatelessWidget {
  const _MissionRow({required this.mission, required this.map, required this.kids, required this.self});

  final Mission mission;
  final JourneyMap map;
  final bool kids;
  final bool self;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final m = mission;
    if (m.kind == 'review') return const SizedBox.shrink();
    final when = missionWhen(context, m);
    final opens = lessonOpens(context, m);
    Widget? action;
    if (self && !m.done) {
      if (m.kind == 'lesson' && m.joinOpen) {
        action = _ActionButton(
          label: l.joinLesson,
          icon: Icons.videocam_rounded,
          kids: kids,
          onTap: () => Navigator.of(context).pop(_Join(m)),
        );
      } else if (m.kind == 'homework' && m.practice && map.playable) {
        action = _ActionButton(
          label: l.gamesPlay,
          icon: Icons.play_arrow_rounded,
          kids: kids,
          onTap: () => Navigator.of(context).pop(_PlayHomework(m)),
        );
      } else if (m.kind == 'homework' && !m.byPlaying) {
        action = _ActionButton(
          label: l.handIn,
          icon: Icons.edit_rounded,
          kids: kids,
          onTap: () => Navigator.of(context).pop(const _HandIn()),
        );
      }
    }
    final lines = <String>[
      ?when,
      ?opens,
      if (m.done) l.doneMark else if (m.late) l.journeyLate,
      if (m.result != null) l.gamesScore(m.result!.correct, m.result!.total),
    ];
    final icon = m.kind == 'lesson' ? Icons.videocam_outlined : Icons.edit_note_rounded;
    final box = Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: kids ? (m.kind == 'lesson' ? const Color(0xFFD0EBFF) : const Color(0xFFFFF4D6)) : c.chip,
        borderRadius: BorderRadius.circular(kids ? 20 : context.shape.card - 4),
        border: kids ? Border.all(color: gwInk, width: 2) : null,
      ),
      child: Row(
        children: [
          Icon(icon, color: kids ? gwInk : c.brandDeep),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  m.kind == 'lesson' ? '${m.kindLabel} · ${m.title}' : '${m.kindLabel}: ${m.title}',
                  style: kids ? gwText(context, 15.5) : context.text.titleSmall,
                ),
                if (lines.isNotEmpty)
                  Text(
                    lines.join(' · '),
                    style: kids ? gwText(context, 12.5, weight: FontWeight.w600, color: const Color(0xFF3B4F72)) : context.text.bodySmall,
                  ),
              ],
            ),
          ),
          if (action != null) ...[const SizedBox(width: 8), action],
          if (m.done && m.stars != null && map.rewards) ...[
            const SizedBox(width: 8),
            Text('+${m.stars}', style: kids ? gwText(context, 14) : context.text.labelLarge),
            Icon(Icons.star_rounded, color: kids ? gwSun : context.colors.reward, size: 20),
          ],
        ],
      ),
    );
    return box;
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.label, required this.icon, required this.kids, required this.onTap});

  final String label;
  final IconData icon;
  final bool kids;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    if (kids) {
      return Button3d(
        color: gwSun,
        height: 42,
        shrink: true,
        depth: 4,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        onTap: onTap,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: gwInk, size: 20),
            const SizedBox(width: 4),
            Text(label, style: gwText(context, 15)),
          ],
        ),
      );
    }
    return FilledButton.icon(
      style: FilledButton.styleFrom(minimumSize: const Size(64, 40), padding: const EdgeInsets.symmetric(horizontal: 12)),
      onPressed: onTap,
      icon: Icon(icon, size: 18),
      label: Text(label),
    );
  }
}

class _Refused extends StatelessWidget {
  const _Refused({required this.text, required this.kids});

  final String text;
  final bool kids;

  @override
  Widget build(BuildContext context) => kids
      ? Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: const Color(0xFFFFF4D6),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: gwInk, width: 2),
          ),
          child: Text(text, textAlign: TextAlign.center, style: gwText(context, 14)),
        )
      : Note(text, tone: Tone.attention, icon: Icons.hourglass_bottom_rounded);
}

/// A game of the station in the kids' look: its picture, its name, how many questions, and the play button.
class _KidsGameTile extends StatelessWidget {
  const _KidsGameTile({required this.game, required this.onTap});

  final StationGame game;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final g = game;
    final art = gameArt(g.key, g.kinds);
    return Button3d(
      color: GameTileArt.cardColor(art),
      radius: 22,
      depth: 5,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      label: '${g.title}. ${g.summary}',
      onTap: onTap,
      child: Row(
        children: [
          GameTileArt(art),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 6,
                  runSpacing: 2,
                  children: [
                    Text(g.title, style: gwText(context, 19, height: 1.15)),
                    if (g.chosen)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFF6B6B),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(color: gwInk, width: 2),
                        ),
                        child: Text(l.gamesBadgeHomework, style: gwText(context, 11.5, color: Colors.white, height: 1)),
                      ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  '${l.gamesItems(g.items)} · ${g.summary}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: gwText(context, 13.5, weight: FontWeight.w600, height: 1.3),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: gwSun,
              shape: BoxShape.circle,
              border: Border.all(color: gwInk, width: 3),
            ),
            child: const Icon(Icons.play_arrow_rounded, color: gwInk, size: 24),
          ),
        ],
      ),
    );
  }
}

/// A game of the station in the other looks: a row in the academy's colours.
class _GameTile extends StatelessWidget {
  const _GameTile({required this.game, required this.onTap});

  final StationGame game;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final g = game;
    return LmsCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      onTap: onTap,
      child: Row(
        children: [
          GameTileArt(gameArt(g.key, g.kinds), size: 44),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(g.title, style: context.text.titleSmall),
                Text([l.gamesItems(g.items), if (g.chosen) l.gamesBadgeHomework].join(' · '), style: context.text.bodySmall),
              ],
            ),
          ),
          Icon(Icons.play_circle_fill_rounded, color: context.colors.brand, size: 34),
        ],
      ),
    );
  }
}

/// The race against yourself on this lesson: rounds, the best time, the last.
class _RaceLine extends StatelessWidget {
  const _RaceLine({required this.race, required this.kids});

  final Race race;
  final bool kids;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final text = [
      l.gamesRoundsCount(race.rounds),
      if (race.bestSeconds != null) l.gamesBest(secondsLabel(l, race.bestSeconds)),
      if (race.lastSeconds != null) l.journeyRaceLast(secondsLabel(l, race.lastSeconds)),
    ].join(' · ');
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: kids ? const Color(0xFFF3F0FF) : c.infoSoft,
        borderRadius: BorderRadius.circular(kids ? 20 : context.shape.card - 4),
        border: kids ? Border.all(color: gwInk, width: 2) : null,
      ),
      child: Row(
        children: [
          Icon(Icons.timer_outlined, color: kids ? gwInk : c.infoInk),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.gamesRace, style: kids ? gwText(context, 15) : context.text.titleSmall),
                Text(text, style: kids ? gwText(context, 12.5, weight: FontWeight.w600) : context.text.bodySmall),
              ],
            ),
          ),
          if (race.bestStars > 0) StarRow(race.bestStars, size: 16),
        ],
      ),
    );
  }
}
