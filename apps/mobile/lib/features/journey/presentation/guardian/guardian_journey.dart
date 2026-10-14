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
import '../calm/calm_journey.dart';
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
      if (_m.rewards)
        LmsCard(
          child: Row(
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(color: c.rewardSoft, borderRadius: BorderRadius.circular(context.shape.card - 4)),
                child: Icon(Icons.star_rounded, color: c.reward, size: 34),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CountUp(_m.stars.total, style: context.text.headlineSmall),
                    Text(
                      [l.journeyStarsOf(name), if (_m.stars.rank.label.isNotEmpty) _m.stars.rank.label, l.streak(_m.streak)].join(' · '),
                      style: context.text.bodySmall,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      if (_m.courses.length > 1)
        CourseChips(courses: _m.courses, selected: course?.enrollmentId, onPick: (id) => setState(() => _course = id)),
      if (course != null && course.days.stand != CourseStand.on)
        Align(alignment: AlignmentDirectional.centerStart, child: CourseStatePill(course.days)),
      if (course == null)
        EmptyNote(l.journeyNoCourses, icon: Icons.menu_book_outlined)
      else
        LevelCard(
          course: course,
          trailing: Align(
            alignment: AlignmentDirectional.centerEnd,
            child: TextButton(
              onPressed: () => context.push('/reports/${course.enrollmentId}?title=${Uri.encodeComponent(course.subject)}'),
              child: Text(l.reports),
            ),
          ),
        ),
      if (_m.lastLesson != null)
        LmsCard(
          child: LastLessonCard(lesson: _m.lastLesson!, rewards: _m.rewards, title: l.journeyLastLessonOf(name)),
        ),
      SectionLabel(l.journeyMissionsOf(name)),
      MissionList(map: _m, place: widget.place, openStation: _openMission),
      if (_m.rewards && _m.chest != null) JourneyChestCard(map: _m, place: widget.place, name: name),
      LmsCard(
        child: CheerBar(learnerId: _m.learnerId, left: _m.cheersLeft, name: name, onSent: _reload),
      ),
      if (together.isNotEmpty) ...[
        SectionLabel(l.gamesTogether),
        LmsCard(
          padding: EdgeInsets.zero,
          child: Column(
            children: [
              for (final (island, s) in together.take(4))
                ListTile(
                  leading: Icon(stationIcon(s), color: s.here ? c.reward : c.attentionInk),
                  title: Text(s.title, style: context.text.titleSmall),
                  subtitle: Text('${stationStateLabel(l, s)} · ${l.gamesGamesCount(s.games.length)}', style: context.text.bodySmall),
                  trailing: FilledButton(
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
        SectionLabel(l.gamesGuardianTitle(name)),
        GuardianLimitsCard(learnerId: _m.learnerId, allowance: games.allowance, limits: games.limits!, onSaved: _reload),
        GuardianGlanceCard(glance: games.glance),
        if (games.glance?.hatched != null) GuardianHatchedCard(learnerId: _m.learnerId, glance: games.glance!),
        const GamesSafetyNote(),
      ],
      LmsCard(
        padding: EdgeInsets.zero,
        child: ListTile(
          key: const Key('journey-courses'),
          leading: const Icon(Icons.menu_book_outlined),
          title: Text(l.journeyCourses, style: context.text.titleSmall),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.go('${widget.place.base}/courses'),
        ),
      ),
    ];
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: [for (final (i, w) in children.indexed) Padding(padding: const EdgeInsets.only(bottom: 12), child: w.appear(context, i))],
    );
  }
}
