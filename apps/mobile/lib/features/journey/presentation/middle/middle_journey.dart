import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../games/presentation/art/art.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';
import '../widgets/course_chips.dart';
import '../widgets/missions.dart';
import '../widgets/rewards.dart';
import '../widgets/station_sheet.dart';

/// Middle school's journey, as levels: the stars filling a bar toward the
/// next rank, the lessons in a row, the missions, the egg and the week's
/// chest, then each level (an island of the course) as a path of its
/// lessons; a lesson opens its sheet with its games and the race against
/// yourself.
class MiddleJourney extends StatefulWidget {
  const MiddleJourney({super.key, required this.map, required this.place, required this.name});

  final JourneyMap map;
  final JourneyPlace place;
  final String name;

  @override
  State<MiddleJourney> createState() => _MiddleJourneyState();
}

class _MiddleJourneyState extends State<MiddleJourney> {
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
    final games = _m.games;
    final children = <Widget>[
      _StarsCard(map: _m, name: widget.name, place: widget.place),
      if (_m.courses.length > 1)
        CourseChips(courses: _m.courses, selected: course?.enrollmentId, onPick: (id) => setState(() => _course = id)),
      if (_m.lastLesson != null)
        LmsCard(
          child: LastLessonCard(lesson: _m.lastLesson!, rewards: _m.rewards),
        ),
      if (games != null && _m.rewards && games.eggReady) EggCard(map: _m, place: widget.place),
      SectionLabel(l.journeyMissions),
      MissionList(map: _m, place: widget.place, openStation: _openMission),
      if (_m.rewards && _m.chest != null) JourneyChestCard(map: _m, place: widget.place),
      if (games != null && _m.rewards && !games.eggReady) EggCard(map: _m, place: widget.place),
      if (course == null)
        EmptyNote(l.journeyNoCourses, icon: Icons.menu_book_outlined)
      else if (!course.hasCurriculum || course.islands.isEmpty)
        Note(l.journeyNoCurriculum, icon: Icons.map_outlined)
      else ...[
        SectionLabel(l.journeyLevels, trailing: Text('${course.percent}%', style: context.text.titleSmall)),
        for (final island in course.islands) _Level(course: course, island: island, onStation: (s) => _openStation(island, s)),
      ],
    ];
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: [for (final (i, w) in children.indexed) Padding(padding: const EdgeInsets.only(bottom: 12), child: w.appear(context, i))],
    );
  }
}

/// The head: the rank, the stars toward the next one as a bar, the lessons
/// in a row, the buddy (the album) and the bell.
class _StarsCard extends StatelessWidget {
  const _StarsCard({required this.map, required this.name, required this.place});

  final JourneyMap map;
  final String name;
  final JourneyPlace place;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final stars = map.stars;
    final rank = stars.rank;
    final buddy = map.games?.buddy;
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              GestureDetector(onTap: () => context.push('/account'), child: InitialAvatar(name, size: 48)),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (map.rewards && rank.label.isNotEmpty) Text(rank.label, style: context.text.bodySmall),
                    Text(l.journeyTitle(name), style: context.text.headlineSmall),
                  ],
                ),
              ),
              const NotificationBell(),
              if (map.games != null)
                IconButton(
                  tooltip: l.gamesAlbum,
                  onPressed: () => context.go(place.album),
                  icon: buddy != null ? CreatureArt(buddy.look, size: 34) : const Icon(Icons.collections_bookmark_outlined),
                ),
            ],
          ),
          if (map.rewards) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                Icon(Icons.star_rounded, color: c.reward),
                const SizedBox(width: 4),
                Expanded(child: Text(l.journeyYourStars, style: context.text.titleSmall)),
                Directionality(
                  textDirection: TextDirection.ltr,
                  child: CountUp(
                    stars.total,
                    suffix: rank.next == null ? '' : ' / ${rank.next}',
                    style: context.text.titleSmall?.copyWith(color: c.muted),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            ProgressBar(rank.next == null ? 1 : rank.progress / 100, colour: c.reward, height: 12),
            const SizedBox(height: 6),
            Text(
              rank.next == null ? l.topRank : l.journeyStarsTo(rank.next! - stars.total, rank.nextLabel ?? ''),
              style: context.text.bodySmall,
            ),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              Pill(l.streak(map.streak), tone: Tone.attention, icon: Icons.local_fire_department_rounded),
              if (map.games != null && map.rewards)
                Pill(l.gamesOf(map.games!.found, map.games!.total), tone: Tone.info, icon: Icons.pets_rounded),
            ],
          ),
        ],
      ),
    );
  }
}

/// A level: its number, title and share done, then its lessons as a path
/// of stops (done ✓, review ↺, here, ahead locked). A level ahead says what
/// opens it.
class _Level extends StatelessWidget {
  const _Level({required this.course, required this.island, required this.onStation});

  final JourneyCourse course;
  final JourneyIsland island;
  final ValueChanged<JourneyStation> onStation;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final ahead = !island.open;
    final before = course.before(island);
    return Opacity(
      opacity: ahead ? 0.72 : 1,
      child: LmsCard(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: island.state == 'done' ? c.brand : (ahead ? c.chip : c.reward),
                    borderRadius: BorderRadius.circular(context.shape.button.clamp(8, 14)),
                  ),
                  child: island.state == 'done'
                      ? const Icon(Icons.emoji_events_rounded, color: Colors.white)
                      : ahead
                      ? Icon(Icons.lock_rounded, color: c.muted)
                      : Text(
                          '${island.index}',
                          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800),
                        ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l.journeyLevel(island.index), style: context.text.labelMedium),
                      Text(island.title, style: context.text.titleMedium),
                    ],
                  ),
                ),
                Text('${island.percent}%', style: context.text.titleSmall?.copyWith(color: c.brandDeep)),
              ],
            ),
            if (!ahead) ...[const SizedBox(height: 8), ProgressBar(island.percent / 100, height: 6)],
            if (ahead)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Text(before == null ? l.journeyAheadFirst : l.journeyAheadLocked(before.title), style: context.text.bodySmall),
              )
            else ...[
              const SizedBox(height: 6),
              for (final (i, s) in island.stations.indexed)
                _StationNode(
                  station: s,
                  first: i == 0,
                  last: i == island.stations.length - 1,
                  sign: i == 0 || s.unit != island.stations[i - 1].unit ? s.unit : null,
                  onTap: () => onStation(s),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StationNode extends StatelessWidget {
  const _StationNode({required this.station, required this.first, required this.last, required this.onTap, this.sign});

  final JourneyStation station;
  final bool first;
  final bool last;
  final String? sign;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final s = station;
    final colour = switch (s.state) {
      'here' => c.reward,
      'todo' => c.chip,
      _ when s.review => c.attention,
      _ => c.brand,
    };
    Widget node = Container(
      width: s.here ? 30 : 24,
      height: s.here ? 30 : 24,
      decoration: BoxDecoration(
        color: colour,
        shape: BoxShape.circle,
        border: Border.all(color: s.todo ? c.line : colour, width: 2),
      ),
      child: Icon(stationIcon(s), size: s.here ? 18 : 14, color: s.todo ? c.muted : Colors.white),
    );
    if (s.here) node = node.pulse(context, to: 1.12);
    return Semantics(
      button: true,
      label: '${s.title}. ${stationStateLabel(l, s)}',
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(
                width: 34,
                child: Column(
                  children: [
                    Expanded(child: Container(width: 3, color: first ? Colors.transparent : c.line)),
                    node,
                    Expanded(child: Container(width: 3, color: last ? Colors.transparent : c.line)),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (sign != null && sign!.isNotEmpty) Text(sign!, style: context.text.labelSmall?.copyWith(color: c.brandDeep)),
                      Text(
                        s.title,
                        style: context.text.titleSmall?.copyWith(
                          color: s.todo ? c.muted : c.ink,
                          fontWeight: s.here ? FontWeight.w800 : null,
                        ),
                      ),
                      Text(
                        [
                          stationStateLabel(l, s),
                          if (s.race != null && s.race!.bestSeconds != null) l.gamesBest(l.gamesSeconds(s.race!.bestSeconds!)),
                          if (s.missions.isNotEmpty) l.journeyMissionsOn(s.missions.length),
                        ].join(' · '),
                        style: context.text.bodySmall,
                      ),
                    ],
                  ),
                ),
              ),
              Icon(
                Directionality.of(context) == TextDirection.rtl ? Icons.chevron_left_rounded : Icons.chevron_right_rounded,
                color: c.faint,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
