import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../core/time/local_time.dart';
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
/// the academy chose the calm look): the subject and "your journey" above,
/// the goal as one number (the share of the programme done, when it ends at
/// this pace), where they are now (the level, its lessons as a bar, the
/// next one), the week's plan by day (lessons with their join button,
/// homework, rounds played), the reviews as one row, the whole curriculum a
/// tap away, then the last lesson and the stars, small.
class CalmJourney extends StatefulWidget {
  const CalmJourney({super.key, required this.map, required this.place, required this.name, this.header = true, this.courses = false});

  final JourneyMap map;
  final JourneyPlace place;
  final String name;

  /// Its own head (a student's tab); the family look has its header instead.
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
    final course = _m.courses.where((c) => c.enrollmentId == _course).firstOrNull ?? _m.courses.firstOrNull;
    final here = course?.here;
    final reviews = [
      for (final m in _m.missions)
        if (m.kind == 'review' && !m.done) m,
    ];
    final plan = [
      for (final m in _m.missions)
        if (m.kind != 'review') m,
    ];
    final children = <Widget>[
      if (_m.courses.length > 1)
        CourseChips(courses: _m.courses, selected: course?.enrollmentId, onPick: (id) => setState(() => _course = id)),
      if (course != null && course.days.stand != CourseStand.on)
        Align(alignment: AlignmentDirectional.centerStart, child: CourseStatePill(course.days)),
      if (course == null) EmptyNote(l.journeyNoCourses, icon: Icons.menu_book_outlined) else _GoalCard(course: course),
      if (course != null && course.hasCurriculum && here != null)
        _NowCard(course: course, level: here, onOpen: (s) => _openStation(here, s)),
      SectionHeader(l.weekPlan),
      if (plan.isEmpty)
        EmptyNote(l.journeyNoMissions, icon: Icons.event_available_outlined)
      else
        _WeekPlan(map: _m, place: widget.place, missions: plan, openStation: _openMission),
      if (reviews.isNotEmpty)
        ReviewsRow(
          key: const Key('calm-reviews'),
          title: l.lessonsToReview(reviews.length),
          hint: l.reviewEachRound,
          reviews: reviews,
          map: _m,
          place: widget.place,
          openStation: _openMission,
        ),
      if (course != null && course.hasCurriculum && course.islands.isNotEmpty)
        MenuGroup(
          children: [
            MenuRow(
              key: const Key('whole-curriculum'),
              icon: Icons.menu_book_outlined,
              label: l.wholeCurriculum,
              value: [
                l.levelsCount(course.islands.length),
                l.curriculumLessons(course.lessons > 0 ? course.lessons : course.islands.fold(0, (n, i) => n + i.lessons)),
              ].join(' · '),
              onTap: () => context.go('${widget.place.base}/levels'),
            ),
            if (widget.courses)
              MenuRow(
                key: const Key('journey-courses'),
                icon: Icons.description_outlined,
                label: l.journeyCourses,
                onTap: () => context.go('${widget.place.base}/courses'),
              ),
          ],
        )
      else if (widget.courses)
        MenuGroup(
          children: [
            MenuRow(
              key: const Key('journey-courses'),
              icon: Icons.description_outlined,
              label: l.journeyCourses,
              onTap: () => context.go('${widget.place.base}/courses'),
            ),
          ],
        ),
      if (_m.lastLesson != null)
        LmsCard(
          child: LastLessonCard(lesson: _m.lastLesson!, rewards: _m.rewards),
        ),
      if (_m.rewards) _StarsLine(map: _m, place: widget.place),
      if (_m.rewards && _m.chest != null) JourneyChestCard(map: _m, place: widget.place),
    ];
    final list = ListView(
      padding: pagePadding.copyWith(top: widget.header ? Space.m : null),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [for (final (i, w) in children.indexed) Padding(padding: const EdgeInsets.only(bottom: 12), child: w.appear(context, i))],
    );
    if (!widget.header) return list;
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(18, Space.m, 18, Space.xs),
          child: AppHeader(
            eyebrow: course == null ? null : [course.subject, ?course.program].where((x) => x.isNotEmpty).join(' · '),
            title: l.journeyYours(widget.name),
            actions: const [NotificationBell(framed: true)],
          ),
        ),
        Expanded(child: list),
      ],
    );
  }
}

/// The goal as one number: the share of the programme done, a bar, and when
/// it ends at this pace (or that it is done).
class _GoalCard extends StatelessWidget {
  const _GoalCard({required this.course});

  final JourneyCourse course;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    if (!course.hasCurriculum) return Note(l.journeyNoCurriculum, icon: Icons.map_outlined);
    return LmsCard(
      key: const Key('calm-goal'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.goalTitle, style: context.type.caption),
          const Gap(Space.xs),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Directionality(
                textDirection: TextDirection.ltr,
                child: CountUp(
                  course.percent,
                  suffix: '%',
                  style: context.type.number.copyWith(fontSize: 40, color: c.brandDeep, height: 1),
                ),
              ),
              const Gap(Space.s),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text(course.finished ? l.programDone : l.goalDone, style: context.type.body),
                ),
              ),
            ],
          ),
          const Gap(Space.m),
          ProgressBar(course.percent / 100, height: 6),
          if (!course.finished && course.finishOn != null) ...[
            const Gap(Space.s),
            Text(l.goalPace(course.finishOn!.date(context.locale)), style: context.type.caption),
          ],
        ],
      ),
    );
  }
}

/// Where they are now: the level (its place among the levels), its lessons
/// as a bar of segments, and which lesson of how many with the next one.
/// It opens the lesson they are on.
class _NowCard extends StatelessWidget {
  const _NowCard({required this.course, required this.level, required this.onOpen});

  final JourneyCourse course;
  final JourneyIsland level;
  final ValueChanged<JourneyStation> onOpen;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final stops = level.stations;
    final at = stops.indexWhere((s) => s.here);
    final here = at < 0 ? null : stops[at];
    final next = at < 0 || at + 1 >= stops.length ? null : stops[at + 1];
    return LmsCard(
      key: const Key('calm-now'),
      onTap: here == null ? null : () => onOpen(here),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(l.nowTitle, style: context.type.caption)),
              Text(l.journeyLevelOf(level.index, course.islands.length), style: context.type.caption),
            ],
          ),
          const Gap(Space.xs),
          Text(level.title, style: context.type.cardTitle.copyWith(fontSize: 17)),
          const Gap(Space.m),
          Row(
            children: [
              for (final (i, s) in stops.indexed) ...[
                if (i > 0) const Gap(4),
                Expanded(
                  child: Container(
                    height: 6,
                    decoration: BoxDecoration(
                      color: s.here ? c.brand.withValues(alpha: 0.45) : (s.todo ? c.line : c.brand),
                      borderRadius: BorderRadius.circular(3),
                    ),
                  ),
                ),
              ],
            ],
          ),
          if (here != null) ...[
            const Gap(Space.s),
            Text([l.stationOf(at + 1, stops.length), if (next != null) l.nextIs(next.title)].join(' · '), style: context.type.caption),
          ],
        ],
      ),
    );
  }
}

/// The week ahead by day: each day's lessons (the join button once the room
/// opens), homework (when it is due, handed in or not) and rounds played.
class _WeekPlan extends StatelessWidget {
  const _WeekPlan({required this.map, required this.place, required this.missions, required this.openStation});

  final JourneyMap map;
  final JourneyPlace place;
  final List<Mission> missions;
  final void Function(Mission m) openStation;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    LocalTime? at(Mission m) => m.kind == 'lesson' ? m.startsAt : m.dueAt;
    final days = <String, (String, List<Mission>)>{};
    for (final m in missions) {
      final t = at(m);
      final key = t?.localDate ?? '~';
      days.putIfAbsent(key, () => (t == null ? l.planNoDay : t.shortDay(context), [])).$2.add(m);
    }
    final keys = days.keys.toList()..sort();
    return LmsCard(
      key: const Key('week-plan'),
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (final (i, key) in keys.indexed) ...[
            if (i > 0) const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 64,
                    child: Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(days[key]!.$1, style: context.type.label.copyWith(color: context.colors.muted)),
                    ),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        for (final (j, m) in days[key]!.$2.indexed) ...[
                          if (j > 0) const Gap(Space.m),
                          _PlanItem(mission: m, map: map, place: place, openStation: () => openStation(m)),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// One thing in the plan: what and when, then its state; a lesson's join
/// button under it, a homework opens where it is handed in (or plays).
class _PlanItem extends StatelessWidget {
  const _PlanItem({required this.mission, required this.map, required this.place, required this.openStation});

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
    final station = map.find(m.stationId)?.$3;
    final lesson = m.kind == 'lesson';
    final title = lesson
        ? [m.kindLabel, m.title, if (m.startsAt != null) m.startsAt!.clock(context.locale)].join(' · ')
        : '${m.kindLabel}: ${m.title}';
    final (line, tone) = switch (m) {
      _ when m.done => ([l.doneMark, if (m.result != null) l.gamesScore(m.result!.correct, m.result!.total)].join(' · '), c.muted),
      _ when m.late => (l.journeyLate, c.dangerInk),
      _ when lesson => ([?station?.title, ?lessonOpens(context, m)].join(' · '), c.muted),
      _ => ([l.notHandedIn, if (m.dueAt != null) l.dueBy(m.dueAt!.clock(context.locale))].join(' · '), c.attentionInk),
    };
    final opens = !lesson && !m.done && action != null;
    final body = Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: context.type.cardTitle.copyWith(
                  decoration: m.done ? TextDecoration.lineThrough : null,
                  color: m.done ? c.muted : null,
                ),
              ),
              if (line.isNotEmpty) Text(line, style: context.type.caption.copyWith(color: tone)),
            ],
          ),
        ),
        if (m.done) Icon(Icons.check_rounded, color: c.brand, size: 20) else if (opens) Icon(Icons.chevron_right_rounded, color: c.faint),
      ],
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (opens)
          InkWell(
            key: Key('plan-${m.key}'),
            onTap: PressScale.withPress(context, action.onTap),
            borderRadius: BorderRadius.circular(8),
            child: body,
          )
        else
          KeyedSubtree(key: Key('plan-${m.key}'), child: body),
        if (lesson && action != null) ...[
          const Gap(Space.s),
          PrimaryAction(action.label, icon: action.icon, onPressed: action.onTap).shimmerIf(context, m.joinOpen, color: c.brandSoft),
        ],
      ],
    );
  }
}

/// The stars, small: how many, the rank, the lessons in a row, and the album.
class _StarsLine extends StatelessWidget {
  const _StarsLine({required this.map, required this.place});

  final JourneyMap map;
  final JourneyPlace place;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return Row(
      children: [
        Icon(Icons.star_rounded, color: c.reward, size: 20),
        const Gap(Space.xs),
        Expanded(
          child: Text(
            [
              l.journeyStarsCount(map.stars.total),
              if (map.stars.rank.label.isNotEmpty) map.stars.rank.label,
              l.streak(map.streak),
            ].join(' · '),
            style: context.type.caption,
          ),
        ),
        if (map.games != null)
          TextButton.icon(
            onPressed: () => context.go(place.album),
            icon: const Icon(Icons.pets_rounded, size: 18),
            label: Text(l.gamesAlbum),
          ),
      ],
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
