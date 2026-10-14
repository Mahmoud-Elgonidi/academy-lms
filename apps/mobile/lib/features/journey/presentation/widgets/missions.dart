import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../games/presentation/art/art.dart';
import '../../../games/presentation/games_ui.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';

/// What a mission's button does now, if anything: join the lesson, play or
/// hand in the homework, review the lesson (its station's games).
({String label, IconData icon, VoidCallback onTap})? missionAction(
  BuildContext context,
  JourneyMap map,
  JourneyPlace place,
  Mission m, {
  required VoidCallback openStation,
  void Function(String text)? say,
}) {
  if (map.guardian || m.done) return null;
  final l = context.l10n;
  final actions = JourneyActions(context, map, place, say: say);
  return switch (m.kind) {
    'lesson' when m.joinOpen => (label: l.joinLesson, icon: Icons.videocam_rounded, onTap: () => actions.join(m)),
    'homework' when m.practice && map.playable => (
      label: l.gamesPlay,
      icon: Icons.play_arrow_rounded,
      onTap: () => actions.playHomework(m),
    ),
    'homework' when !m.byPlaying => (label: l.handIn, icon: Icons.edit_rounded, onTap: actions.handIn),
    'review' when map.playable && map.find(m.stationId) != null => (label: l.journeyReview, icon: Icons.replay_rounded, onTap: openStation),
    _ => null,
  };
}

/// What a mission says under its title: when, how long until the room opens, done or late, and how its round went.
String missionLine(BuildContext context, Mission m) {
  final l = context.l10n;
  return [
    ?missionWhen(context, m),
    ?lessonOpens(context, m),
    if (m.done) l.doneMark else if (m.late) l.journeyLate,
    if (m.result != null) l.gamesScore(m.result!.correct, m.result!.total),
  ].join(' · ');
}

IconData missionIcon(Mission m) => switch (m.kind) {
  'lesson' => Icons.videocam_outlined,
  'homework' => m.practice ? Icons.sports_esports_outlined : Icons.edit_note_rounded,
  _ => Icons.replay_rounded,
};

/// The kids' missions under the map: a strip of cards (the lesson, the
/// homework, the reviews), each opening its station or doing its job.
class MissionStrip extends StatelessWidget {
  const MissionStrip({super.key, required this.map, required this.place, required this.openStation, this.say});

  final JourneyMap map;
  final JourneyPlace place;
  final void Function(Mission m) openStation;
  final void Function(String text)? say;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final missions = map.missions;
    if (missions.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: gwInk, width: 3),
        ),
        child: Text(l.journeyNoMissions, textAlign: TextAlign.center, style: gwText(context, 15)),
      );
    }
    return SizedBox(
      height: 178,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        clipBehavior: Clip.none,
        padding: const EdgeInsets.only(bottom: 6),
        itemCount: missions.length,
        separatorBuilder: (_, _) => const SizedBox(width: 10),
        itemBuilder: (context, i) => _KidsMissionCard(
          mission: missions[i],
          map: map,
          place: place,
          say: say,
          openStation: () => openStation(missions[i]),
        ).appear(context, i),
      ),
    );
  }
}

class _KidsMissionCard extends StatelessWidget {
  const _KidsMissionCard({required this.mission, required this.map, required this.place, required this.openStation, this.say});

  final Mission mission;
  final JourneyMap map;
  final JourneyPlace place;
  final VoidCallback openStation;
  final void Function(String text)? say;

  @override
  Widget build(BuildContext context) {
    final m = mission;
    final action = missionAction(context, map, place, m, openStation: openStation, say: say);
    final colour = switch (m.kind) {
      'lesson' => const Color(0xFFD0EBFF),
      'homework' => const Color(0xFFFFF4D6),
      _ => const Color(0xFFF3F0FF),
    };
    final line = missionLine(context, m);
    return SizedBox(
      width: 228,
      child: Button3d(
        color: colour,
        radius: 22,
        depth: 5,
        label: '${m.kindLabel}: ${m.title}. $line',
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
        onTap: map.find(m.stationId) != null ? openStation : action?.onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: gwInk, width: 2.5),
                  ),
                  child: Icon(missionIcon(m), color: gwInk, size: 19),
                ),
                const SizedBox(width: 8),
                Expanded(child: Text(m.kindLabel, style: gwText(context, 14))),
                if (m.done)
                  const Icon(Icons.check_circle_rounded, color: Color(0xFF2F9E44))
                else if (m.stars != null && map.rewards)
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('+${m.stars}', style: gwText(context, 13)),
                      const Icon(Icons.star_rounded, color: Color(0xFFF59F00), size: 18),
                    ],
                  ),
              ],
            ),
            const SizedBox(height: 6),
            Text(m.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: gwText(context, 16, height: 1.2)),
            const Spacer(),
            if (line.isNotEmpty)
              Text(
                line,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: gwText(context, 12, weight: FontWeight.w700, color: const Color(0xFF3B4F72)),
              ),
            if (action != null) ...[
              const SizedBox(height: 6),
              Button3d(
                color: gwSun,
                height: 36,
                depth: 3,
                label: action.label,
                onTap: action.onTap,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(action.icon, color: gwInk, size: 18),
                    const SizedBox(width: 4),
                    Text(action.label, style: gwText(context, 14.5)),
                  ],
                ),
              ).shimmerIf(context, m.kind == 'lesson' && m.joinOpen),
            ],
          ],
        ),
      ),
    );
  }
}

/// The missions as a list, in the academy's colours (middle school, the
/// calm look, a guardian's view: no buttons for a guardian).
class MissionList extends StatelessWidget {
  const MissionList({super.key, required this.map, required this.place, required this.openStation, this.skipLesson = false});

  final JourneyMap map;
  final JourneyPlace place;
  final void Function(Mission m) openStation;

  /// The next lesson has its own card on this screen.
  final bool skipLesson;

  @override
  Widget build(BuildContext context) {
    final missions = [
      for (final m in map.missions)
        if (!(skipLesson && m.kind == 'lesson')) m,
    ];
    if (missions.isEmpty) return EmptyNote(context.l10n.journeyNoMissions, icon: Icons.flag_outlined);
    return Column(
      children: [
        for (final m in missions)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: MissionTile(mission: m, map: map, place: place, openStation: () => openStation(m)),
          ),
      ],
    );
  }
}

class MissionTile extends StatelessWidget {
  const MissionTile({super.key, required this.mission, required this.map, required this.place, required this.openStation});

  final Mission mission;
  final JourneyMap map;
  final JourneyPlace place;
  final VoidCallback openStation;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final m = mission;
    final action = missionAction(context, map, place, m, openStation: openStation);
    final tone = switch (m.kind) {
      'lesson' => Tone.info,
      'homework' => m.late ? Tone.danger : Tone.attention,
      _ => Tone.brand,
    };
    final (bg, ink) = tone.colours(context);
    final line = missionLine(context, m);
    return LmsCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      onTap: map.find(m.stationId) != null ? openStation : null,
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(context.shape.card.clamp(8, 14))),
            child: Icon(missionIcon(m), color: ink, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${m.kindLabel} · ${m.title}', style: context.text.titleSmall, maxLines: 2, overflow: TextOverflow.ellipsis),
                if (line.isNotEmpty) Text(line, style: context.text.bodySmall),
              ],
            ),
          ),
          const SizedBox(width: 8),
          if (action != null)
            FilledButton.icon(
              style: FilledButton.styleFrom(minimumSize: const Size(56, 38), padding: const EdgeInsets.symmetric(horizontal: 12)),
              onPressed: action.onTap,
              icon: Icon(action.icon, size: 18),
              label: Text(action.label),
            ).shimmerIf(context, m.kind == 'lesson' && m.joinOpen, color: c.brandSoft)
          else if (m.done)
            m.stars != null && map.rewards
                ? Pill('+${m.stars}', tone: Tone.reward, icon: Icons.star_rounded)
                : Pill(l.doneMark, tone: Tone.brand, icon: Icons.check_rounded)
          else if (m.late)
            Pill(l.journeyLate, tone: Tone.danger),
        ],
      ),
    );
  }
}

/// The reviews waiting as one row (each a short round on its lesson); it
/// opens them in a sheet.
class ReviewsRow extends StatelessWidget {
  const ReviewsRow({
    super.key,
    required this.title,
    required this.hint,
    required this.reviews,
    required this.map,
    required this.place,
    required this.openStation,
  });

  final String title;
  final String hint;
  final List<Mission> reviews;
  final JourneyMap map;
  final JourneyPlace place;
  final void Function(Mission m) openStation;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final stars = map.rewards ? reviews.fold<int>(0, (n, m) => n + (m.stars ?? 0)) : 0;
    return LmsCard(
      onTap: () => _showReviews(context),
      child: Row(
        children: [
          const IconBadge(Icons.replay_rounded, tone: Tone.info, size: 40),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: context.type.cardTitle),
                Text([hint, if (stars > 0) '+$stars ★'].join(' · '), style: context.type.caption),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: c.faint),
        ],
      ),
    );
  }

  Future<void> _showReviews(BuildContext context) => showActionSheet<void>(
    context,
    builder: (sheet) => SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(Space.xl - 4, 0, Space.xl - 4, Space.xl),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const IconBadge(Icons.replay_rounded, tone: Tone.info, size: 44),
              const Gap(Space.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Semantics(header: true, child: Text(title, style: context.type.title)),
                    Text(hint, style: context.type.caption),
                  ],
                ),
              ),
            ],
          ),
          const Gap(Space.l),
          for (final m in reviews)
            Padding(
              padding: const EdgeInsets.only(bottom: Space.s),
              child: MissionTile(
                mission: m,
                map: map,
                place: place,
                openStation: () {
                  Navigator.of(sheet).pop();
                  openStation(m);
                },
              ),
            ),
        ],
      ),
    ),
  );
}
