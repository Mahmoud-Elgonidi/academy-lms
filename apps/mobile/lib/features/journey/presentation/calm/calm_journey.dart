import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../core/time/course_days.dart';
import '../../../../shared/widgets/course_state.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';
import '../widgets/course_chips.dart';
import '../widgets/missions.dart';
import '../widgets/rewards.dart';
import '../widgets/station_sheet.dart';

/// The calm journey (secondary school, adult students, and everyone when
/// the academy chose the calm look): the level now and how far, when the
/// course ends at this pace, the next lesson with the last one's stars, the
/// missions as a list, the levels as an accordion of their lessons (games
/// to practise one, if wanted), the stars small and the week's chest.
class CalmJourney extends StatefulWidget {
  const CalmJourney({super.key, required this.map, required this.place, required this.name, this.header = true, this.courses = false});

  final JourneyMap map;
  final JourneyPlace place;
  final String name;

  /// Its own head (a student's tab); the family look has an app bar instead.
  final bool header;

  /// A way to the courses' materials and reports (the family look, where the journey took Progress's place).
  final bool courses;

  @override
  State<CalmJourney> createState() => _CalmJourneyState();
}

class _CalmJourneyState extends State<CalmJourney> {
  String? _course;

  JourneyMap get _m => widget.map;

  void _openStation(JourneyIsland island, JourneyStation s) =>
      showStationSheet(context, map: _m, place: widget.place, island: island, station: s);

  void _openMission(Mission m) {
    final at = _m.find(m.stationId);
    if (at != null) _openStation(at.$2, at.$3);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final course = _m.courses.where((c) => c.enrollmentId == _course).firstOrNull ?? _m.courses.firstOrNull;
    final lesson = _m.nextLesson;
    final children = <Widget>[
      if (widget.header)
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [Text(l.journeyYours(widget.name), style: context.text.headlineSmall)],
              ),
            ),
            const NotificationBell(),
            GestureDetector(onTap: () => context.push('/account'), child: InitialAvatar(widget.name, size: 40)),
          ],
        ),
      if (_m.courses.length > 1)
        CourseChips(courses: _m.courses, selected: course?.enrollmentId, onPick: (id) => setState(() => _course = id)),
      if (course != null && course.days.stand != CourseStand.on)
        Align(alignment: AlignmentDirectional.centerStart, child: CourseStatePill(course.days)),
      if (course == null) EmptyNote(l.journeyNoCourses, icon: Icons.menu_book_outlined) else LevelCard(course: course),
      _NextLesson(map: _m, place: widget.place, lesson: lesson),
      SectionLabel(l.journeyMissions),
      MissionList(map: _m, place: widget.place, openStation: _openMission, skipLesson: true),
      if (course != null && course.hasCurriculum && course.islands.isNotEmpty) ...[
        SectionLabel(l.journeyLevels),
        LmsCard(
          padding: EdgeInsets.zero,
          child: Column(
            children: [
              for (final island in course.islands) CalmLevelTile(course: course, island: island, onStation: (s) => _openStation(island, s)),
            ],
          ),
        ),
      ],
      if (_m.rewards)
        Row(
          children: [
            Icon(Icons.star_rounded, color: c.reward, size: 20),
            const SizedBox(width: 4),
            Expanded(
              child: Text(
                [
                  l.journeyStarsCount(_m.stars.total),
                  if (_m.stars.rank.label.isNotEmpty) _m.stars.rank.label,
                  l.streak(_m.streak),
                ].join(' · '),
                style: context.text.bodySmall,
              ),
            ),
            if (_m.games != null)
              TextButton.icon(
                onPressed: () => context.go(widget.place.album),
                icon: const Icon(Icons.pets_rounded, size: 18),
                label: Text(l.gamesAlbum),
              ),
          ],
        ),
      if (_m.rewards && _m.chest != null) JourneyChestCard(map: _m, place: widget.place),
      if (widget.courses)
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

/// Where a course stands: the level now (its share), the course's share,
/// the lesson now, and when it ends at this pace.
class LevelCard extends StatelessWidget {
  const LevelCard({super.key, required this.course, this.trailing});

  final JourneyCourse course;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final here = course.here;
    return LmsCard(
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
                    Text(course.name, style: context.text.labelMedium),
                    Text(here == null ? course.subject : here.title, style: context.text.titleLarge),
                    if (here != null) Text(l.journeyLevelOf(here.index, course.islands.length), style: context.text.bodySmall),
                  ],
                ),
              ),
              CountUp(
                here?.percent ?? course.percent,
                suffix: '%',
                style: context.text.headlineMedium?.copyWith(color: c.brand),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ProgressBar((here?.percent ?? course.percent) / 100, height: 6),
          const SizedBox(height: 10),
          if (!course.hasCurriculum)
            Text(l.journeyNoCurriculum, style: context.text.bodySmall)
          else if (course.finished)
            Text(l.programDone, style: context.text.titleMedium?.copyWith(color: c.brandDeep))
          else
            Text(
              [
                l.percentDone(course.percent),
                if (course.currentTitle != null) l.hereNow(course.currentTitle!),
                if (course.finishOn != null) '${l.finishOn}: ${course.finishOn!.date(context.locale)}',
              ].join(' · '),
              style: context.text.bodySmall,
            ),
          ?trailing,
        ],
      ),
    );
  }
}

/// The next lesson: when, the join button once the room opens, and the last lesson's stars under it.
class _NextLesson extends StatelessWidget {
  const _NextLesson({required this.map, required this.place, required this.lesson});

  final JourneyMap map;
  final JourneyPlace place;
  final Mission? lesson;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final m = lesson;
    final last = map.lastLesson;
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.nextLesson, style: context.text.labelMedium),
          const SizedBox(height: 4),
          if (m == null)
            Text(l.noNextLesson, style: context.text.bodyMedium?.copyWith(color: c.muted))
          else ...[
            Text(m.startsAt?.when(context) ?? '', style: context.text.titleLarge),
            Text(
              [m.title, ?lessonOpens(context, m), if (m.stars != null && map.rewards) l.journeyLessonCanBring(m.stars!)].join(' · '),
              style: context.text.bodySmall,
            ),
            if (!map.guardian) ...[
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: m.joinOpen ? () => JourneyActions(context, map, place).join(m) : null,
                  icon: const Icon(Icons.videocam_rounded),
                  label: Text(
                    m.joinOpen || m.joinOpensAt == null ? l.joinLesson : '${l.joinLesson} · ${m.joinOpensAt!.clock(context.locale)}',
                  ),
                ).shimmerIf(context, m.joinOpen, color: c.brandSoft),
              ),
            ],
          ],
          if (last != null) ...[const Divider(height: 20), LastLessonCard(lesson: last, rewards: map.rewards)],
        ],
      ),
    );
  }
}

/// A level in the accordion: open where the learner is; its lessons with their state.
class CalmLevelTile extends StatelessWidget {
  const CalmLevelTile({required this.course, required this.island, required this.onStation, super.key});

  final JourneyCourse course;
  final JourneyIsland island;
  final ValueChanged<JourneyStation> onStation;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final before = course.before(island);
    final icon = switch (island.state) {
      'done' => Icon(Icons.check_circle_rounded, color: c.brand),
      'here' => Icon(Icons.place_rounded, color: c.reward),
      _ => Icon(Icons.lock_outline_rounded, color: c.faint),
    };
    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        key: PageStorageKey('level-${island.id}'),
        initiallyExpanded: island.state == 'here',
        leading: icon,
        title: Text('${l.journeyLevel(island.index)} · ${island.title}', style: context.text.titleSmall),
        subtitle: Text(
          island.open
              ? l.journeyLessonsDone(island.done, island.lessons)
              : (before == null ? l.journeyAheadFirst : l.journeyAheadLocked(before.title)),
          style: context.text.bodySmall,
        ),
        childrenPadding: const EdgeInsetsDirectional.only(start: 12, end: 12, bottom: 8),
        children: [
          for (final s in island.stations)
            ListTile(
              dense: true,
              contentPadding: const EdgeInsetsDirectional.only(start: 8, end: 4),
              leading: Icon(
                stationIcon(s),
                size: 20,
                color: switch (s.state) {
                  'here' => c.reward,
                  'todo' => c.faint,
                  _ when s.review => c.attentionInk,
                  _ => c.brand,
                },
              ),
              title: Text(s.title, style: context.text.bodyMedium?.copyWith(color: s.todo ? c.muted : null)),
              subtitle: Text(
                [if (s.unit != null && s.unit!.isNotEmpty) s.unit!, stationStateLabel(l, s)].join(' · '),
                style: context.text.bodySmall,
              ),
              trailing: s.games.isNotEmpty && s.playable ? Icon(Icons.sports_esports_outlined, color: c.brand, size: 20) : null,
              onTap: () => onStation(s),
            ),
        ],
      ),
    );
  }
}
