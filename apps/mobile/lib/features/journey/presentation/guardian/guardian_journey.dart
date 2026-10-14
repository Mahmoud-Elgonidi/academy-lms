import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../core/time/course_days.dart';
import '../../../../shared/widgets/course_state.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../challenges/presentation/widgets/cheers.dart';
import '../../../games/presentation/guardian_cards.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';
import '../widgets/course_chips.dart';
import '../widgets/missions.dart';
import '../widgets/rewards.dart';
import '../widgets/station_sheet.dart';

/// A guardian's look at their child's journey (the classic look): where
/// each course stands, the missions and how they went, the last lesson's
/// punctuality and stars, the stars, the week's chest (only the child opens
/// it), cheering, the games' limits and the week's glance, and playing
/// together on a lesson. Never pay or KPIs.
class GuardianJourney extends StatefulWidget {
  const GuardianJourney({super.key, required this.map, required this.place, required this.name});

  final JourneyMap map;
  final JourneyPlace place;

  /// The child's first name.
  final String name;

  @override
  State<GuardianJourney> createState() => _GuardianJourneyState();
}

class _GuardianJourneyState extends State<GuardianJourney> {
  String? _course;

  JourneyMap get _m => widget.map;

  void _openStation(JourneyIsland island, JourneyStation s) =>
      showStationSheet(context, map: _m, place: widget.place, island: island, station: s);

  void _openMission(Mission m) {
    final at = _m.find(m.stationId);
    if (at != null) _openStation(at.$2, at.$3);
  }

  Future<void> _reload() => context.read<LoadCubit<JourneyMap>>().load();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final name = widget.name;
    final course = _m.courses.where((c) => c.enrollmentId == _course).firstOrNull ?? _m.courses.firstOrNull;
    final games = _m.games;
    final together = <(JourneyIsland, JourneyStation)>[
      if (_m.playable)
        for (final cr in _m.courses)
          for (final island in cr.islands)
            for (final s in island.stations)
              if (s.playable && s.games.isNotEmpty && (s.here || s.review)) (island, s),
    ]..sort((a, b) => (b.$2.here ? 1 : 0).compareTo(a.$2.here ? 1 : 0));

    final children = <Widget>[
      if (course != null) _Hero(map: _m, course: course, name: name),
      if (_m.courses.length > 1)
        CourseChips(courses: _m.courses, selected: course?.enrollmentId, onPick: (id) => setState(() => _course = id)),
      if (course != null && course.days.stand != CourseStand.on)
        Align(alignment: AlignmentDirectional.centerStart, child: CourseStatePill(course.days)),
      if (course == null) EmptyNote(l.journeyNoCourses, icon: Icons.menu_book_outlined),
      if (_m.lastLesson != null)
        LmsCard(
          child: LastLessonCard(lesson: _m.lastLesson!, rewards: _m.rewards, title: l.journeyLastLessonOf(name)),
        ),
      SectionHeader(l.journeyMissionsOf(name)),
      MissionList(map: _m, place: widget.place, openStation: _openMission),
      if (_m.rewards && _m.chest != null) JourneyChestCard(map: _m, place: widget.place, name: name),
      LmsCard(
        child: CheerBar(learnerId: _m.learnerId, left: _m.cheersLeft, name: name, onSent: _reload),
      ),
      if (together.isNotEmpty) ...[
        SectionHeader(l.gamesTogether),
        LmsCard(
          padding: EdgeInsets.zero,
          child: Column(
            children: [
              for (final (island, s) in together.take(4))
                ListTile(
                  leading: Icon(stationIcon(s), color: s.here ? c.reward : c.attentionInk),
                  title: Text(s.title, style: context.text.titleSmall),
                  subtitle: Text('${stationStateLabel(l, s)} · ${l.gamesGamesCount(s.games.length)}', style: context.text.bodySmall),
                  trailing: FilledButton.tonal(
                    onPressed: games?.allowance.may ?? false ? () => _openStation(island, s) : null,
                    child: Text(l.gamesPlay),
                  ),
                  onTap: () => _openStation(island, s),
                ),
            ],
          ),
        ),
      ],
      if (games != null && games.limits != null) ...[
        SectionHeader(l.gamesGuardianTitle(name)),
        GuardianLimitsCard(learnerId: _m.learnerId, allowance: games.allowance, limits: games.limits!, onSaved: _reload),
        GuardianGlanceCard(glance: games.glance),
        if (games.glance?.hatched != null) GuardianHatchedCard(learnerId: _m.learnerId, glance: games.glance!),
        const GamesSafetyNote(),
      ],
      MenuGroup(
        children: [
          MenuRow(
            key: const Key('journey-courses'),
            icon: Icons.menu_book_outlined,
            label: l.journeyCourses,
            onTap: () => context.go('${widget.place.base}/courses'),
          ),
        ],
      ),
    ];
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: [for (final (i, w) in children.indexed) Padding(padding: const EdgeInsets.only(bottom: 12), child: w.appear(context, i))],
    );
  }
}

/// Where the child stands, the one surface in the academy's colour: the
/// course and level, its title, how far along it, the station and what is
/// next, and their stars (the rank and the lessons in a row).
class _Hero extends StatelessWidget {
  const _Hero({required this.map, required this.course, required this.name});

  final JourneyMap map;
  final JourneyCourse course;
  final String name;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    const ink = Colors.white;
    final island = course.here;
    final station = island?.here;
    final at = island == null || station == null ? -1 : island.stations.indexOf(station);
    final next = at >= 0 && at + 1 < island!.stations.length ? island.stations[at + 1] : null;
    final percent = island?.percent ?? course.percent;
    return Container(
      key: const Key('journey-hero'),
      padding: const EdgeInsets.fromLTRB(Space.l + 4, Space.l + 2, Space.l + 4, Space.l + 4),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(context.shape.card + 6),
        gradient: LinearGradient(
          colors: [c.brand, Color.lerp(c.brand, Colors.black, 0.18)!],
          begin: AlignmentDirectional.topStart,
          end: AlignmentDirectional.bottomEnd,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  [course.name, if (island != null) l.journeyLevel(island.index)].join(' · '),
                  style: TextStyle(color: ink.withValues(alpha: 0.88), fontWeight: FontWeight.w700, fontSize: 14),
                ),
              ),
              if (map.rewards)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: ink.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(999)),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.star_rounded, size: 16, color: c.reward),
                      const Gap(Space.xs),
                      CountUp(
                        map.stars.total,
                        style: const TextStyle(color: ink, fontSize: 13, fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
            ],
          ),
          const Gap(Space.m),
          Text(
            island?.title ?? course.subject,
            style: TextStyle(fontFamily: context.shape.headingFont, color: ink, fontSize: 26, fontWeight: FontWeight.w800, height: 1.25),
          ),
          const Gap(Space.m),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: (percent / 100).clamp(0.0, 1.0),
              minHeight: 8,
              color: ink,
              backgroundColor: ink.withValues(alpha: 0.25),
            ),
          ),
          const Gap(Space.s),
          Row(
            children: [
              if (at >= 0)
                Text(
                  l.journeyStationOf(at + 1, island!.stations.length),
                  style: TextStyle(color: ink.withValues(alpha: 0.9), fontSize: 13),
                ),
              const Spacer(),
              if (next != null)
                Flexible(
                  child: Text(
                    l.journeyNextStation(next.title),
                    textAlign: TextAlign.end,
                    style: TextStyle(color: ink.withValues(alpha: 0.9), fontSize: 13),
                  ),
                ),
            ],
          ),
          if (map.rewards) ...[
            const Gap(Space.s),
            Text(
              [l.journeyStarsOf(name), if (map.stars.rank.label.isNotEmpty) map.stars.rank.label, l.streak(map.streak)].join(' · '),
              style: TextStyle(color: ink.withValues(alpha: 0.85), fontSize: 12.5),
            ),
          ],
        ],
      ),
    );
  }
}
