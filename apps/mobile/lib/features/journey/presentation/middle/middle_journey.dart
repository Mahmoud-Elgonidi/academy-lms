import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../core/time/course_days.dart';
import '../../../../shared/widgets/course_state.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../games/presentation/art/art.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';
import '../widgets/course_chips.dart';
import '../widgets/missions.dart';
import '../widgets/rewards.dart';
import '../widgets/station_sheet.dart';

/// Middle school's journey: the header (their rank, "Zeyad's journey", the
/// bell; their initial opens More), the stars toward the next rank as the
/// one surface in the academy's colour with the buddy, today (the lesson,
/// homework, and the reviews as one row that opens them all), the last
/// lesson's stars, the egg and the week's chest, then the level they are in
/// as a path of its lessons, and all the levels a tap away.
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
    final reviews = [
      for (final m in _m.missions)
        if (m.kind == 'review' && !m.done) m,
    ];
    final grouped = reviews.length > 1;
    final today = [
      for (final m in _m.missions)
        if (!(grouped && m.kind == 'review' && !m.done)) m,
    ];
    final island = course?.here;
    final children = <Widget>[
      _StarsCard(map: _m, place: widget.place),
      if (_m.courses.length > 1)
        CourseChips(courses: _m.courses, selected: course?.enrollmentId, onPick: (id) => setState(() => _course = id)),
      if (course != null && course.days.stand != CourseStand.on)
        Align(alignment: AlignmentDirectional.centerStart, child: CourseStatePill(course.days)),
      SectionHeader(l.today),
      if (today.isEmpty && !grouped) EmptyNote(l.journeyNoMissions, icon: Icons.flag_outlined),
      for (final m in today) MissionTile(mission: m, map: _m, place: widget.place, openStation: () => _openMission(m)),
      if (grouped) _ReviewsRow(reviews: reviews, onOpen: () => _showReviews(reviews)),
      if (_m.lastLesson != null)
        LmsCard(
          child: LastLessonCard(lesson: _m.lastLesson!, rewards: _m.rewards),
        ),
      if (games != null && _m.rewards && games.eggReady) EggCard(map: _m, place: widget.place),
      if (_m.rewards && _m.chest != null) JourneyChestCard(map: _m, place: widget.place),
      if (games != null && _m.rewards && !games.eggReady) EggCard(map: _m, place: widget.place),
      if (course == null)
        EmptyNote(l.journeyNoCourses, icon: Icons.menu_book_outlined)
      else if (!course.hasCurriculum || course.islands.isEmpty)
        Note(l.journeyNoCurriculum, icon: Icons.map_outlined)
      else if (island != null) ...[
        SectionHeader(l.levelNamed(island.index, island.title), note: l.gamesOf(island.done, island.lessons)),
        _LevelPath(island: island, onStation: (s) => _openStation(island, s)),
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: TextButton.icon(
            key: const Key('all-levels'),
            onPressed: () => context.go('${widget.place.base}/levels'),
            icon: const Icon(Icons.bar_chart_rounded),
            label: Text(l.allLevels),
          ),
        ),
      ],
    ];
    return Column(
      children: [
        _Header(map: _m, name: widget.name),
        Expanded(
          child: ListView(
            padding: pagePadding.copyWith(top: Space.m),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              for (final (i, w) in children.indexed) Padding(padding: const EdgeInsets.only(bottom: 12), child: w.appear(context, i)),
            ],
          ),
        ),
      ],
    );
  }

  /// The reviews waiting, each a short game, in one sheet.
  Future<void> _showReviews(List<Mission> reviews) {
    final l = context.l10n;
    return showActionSheet<void>(
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
                      Semantics(header: true, child: Text(l.reviewsWaiting(reviews.length), style: context.type.title)),
                      Text(l.reviewsHint, style: context.type.caption),
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
                  map: _m,
                  place: widget.place,
                  openStation: () {
                    Navigator.of(sheet).pop();
                    _openMission(m);
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// The top of the tab: their initial (with the bell beside it; the initial
/// opens More), the rank above "Zeyad's journey".
class _Header extends StatelessWidget {
  const _Header({required this.map, required this.name});

  final JourneyMap map;
  final String name;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final rank = map.stars.rank;
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, Space.m, 18, Space.xs),
      child: AppHeader(
        leading: HeaderAvatar(name: name, label: l.navMore, onPressed: () => context.go('/middle/more')),
        eyebrow: map.rewards && rank.label.isNotEmpty ? rank.label : null,
        title: l.journeyTitle(name),
        actions: const [NotificationBell(framed: true)],
      ),
    );
  }
}

/// The stars toward the next rank, in the academy's colour: how many of how
/// many, a gold bar, what is left, the lessons in a row and the creatures
/// found, and the buddy (the album).
class _StarsCard extends StatelessWidget {
  const _StarsCard({required this.map, required this.place});

  final JourneyMap map;
  final JourneyPlace place;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final stars = map.stars;
    final rank = stars.rank;
    final buddy = map.games?.buddy;
    const ink = Colors.white;
    Widget chip(IconData icon, String text, Color tint) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(color: tint.withValues(alpha: 0.22), borderRadius: BorderRadius.circular(999)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: tint),
          const Gap(Space.xs),
          Text(
            text,
            style: const TextStyle(color: ink, fontSize: 12.5, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
    return Container(
      key: const Key('middle-stars'),
      padding: const EdgeInsets.fromLTRB(Space.l + 2, Space.l, Space.l + 2, Space.l),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(context.shape.card + 4),
        gradient: LinearGradient(
          colors: [c.brandDeep, Color.lerp(c.brandDeep, Colors.black, 0.3)!],
          begin: AlignmentDirectional.topStart,
          end: AlignmentDirectional.bottomEnd,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l.journeyYourStars,
                      style: TextStyle(color: c.reward, fontSize: 13, fontWeight: FontWeight.w700),
                    ),
                    const Gap(Space.xs),
                    if (map.rewards)
                      Row(
                        children: [
                          Icon(Icons.star_rounded, color: c.reward, size: 30),
                          const Gap(Space.xs),
                          Directionality(
                            textDirection: TextDirection.ltr,
                            child: CountUp(stars.total, style: context.type.number.copyWith(color: ink, fontSize: 34)),
                          ),
                          if (rank.next != null)
                            Text(
                              ' / ${rank.next}',
                              style: TextStyle(color: ink.withValues(alpha: 0.7), fontSize: 18, fontWeight: FontWeight.w700),
                            ),
                        ],
                      ),
                  ],
                ),
              ),
              if (map.games != null)
                Tooltip(
                  message: l.gamesAlbum,
                  child: InkWell(
                    onTap: () => context.go(place.album),
                    customBorder: const CircleBorder(),
                    child: SizedBox.square(
                      dimension: 64,
                      child: Center(
                        child: buddy != null ? CreatureArt(buddy.look, size: 56) : const Icon(Icons.pets_rounded, color: ink, size: 32),
                      ),
                    ),
                  ),
                ),
            ],
          ),
          if (map.rewards) ...[
            const Gap(Space.m),
            ProgressBar(rank.next == null ? 1 : rank.progress / 100, colour: c.reward, height: 10),
            const Gap(Space.s),
            Text(
              rank.next == null ? l.topRank : l.journeyStarsTo(rank.next! - stars.total, rank.nextLabel ?? ''),
              style: TextStyle(color: ink.withValues(alpha: 0.85), fontSize: 13),
            ),
          ],
          const Gap(Space.m),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              chip(Icons.local_fire_department_rounded, l.streak(map.streak), c.reward),
              if (map.games != null && map.rewards) chip(Icons.pets_rounded, l.gamesOf(map.games!.found, map.games!.total), c.brandSoft),
            ],
          ),
        ],
      ),
    );
  }
}

/// The reviews waiting, as one row that opens them.
class _ReviewsRow extends StatelessWidget {
  const _ReviewsRow({required this.reviews, required this.onOpen});

  final List<Mission> reviews;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final stars = reviews.fold<int>(0, (n, m) => n + (m.stars ?? 0));
    return LmsCard(
      key: const Key('middle-reviews'),
      onTap: onOpen,
      child: Row(
        children: [
          const IconBadge(Icons.replay_rounded, tone: Tone.info, size: 40),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.reviewsWaiting(reviews.length), style: context.type.cardTitle),
                Text([l.reviewsHint, if (stars > 0) '+$stars ★'].join(' · '), style: context.type.caption),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: c.faint),
        ],
      ),
    );
  }
}

/// The level they are in as a row of stops: done with a check, here in gold
/// with its number, ahead grey; where they are and what comes next under it.
class _LevelPath extends StatelessWidget {
  const _LevelPath({required this.island, required this.onStation});

  final JourneyIsland island;
  final ValueChanged<JourneyStation> onStation;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final stops = island.stations;
    final at = stops.indexWhere((s) => s.here);
    final here = at < 0 ? null : stops[at];
    final next = at < 0 || at + 1 >= stops.length ? null : stops[at + 1];
    return LmsCard(
      key: const Key('middle-level'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (final (i, s) in stops.indexed) ...[
                  if (i > 0) Container(width: 18, height: 3, color: s.todo ? c.line : c.brand),
                  Semantics(
                    button: true,
                    label: '${s.title}. ${stationStateLabel(l, s)}',
                    excludeSemantics: true,
                    child: InkWell(
                      onTap: () => onStation(s),
                      customBorder: const CircleBorder(),
                      child: _Stop(station: s, number: i + 1),
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (here != null) ...[
            const Gap(Space.m),
            Text(next == null ? l.hereOnly(here.title) : l.hereAndNext(here.title, next.title), style: context.type.caption),
          ],
        ],
      ),
    );
  }
}

class _Stop extends StatelessWidget {
  const _Stop({required this.station, required this.number});

  final JourneyStation station;
  final int number;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final s = station;
    final size = s.here ? 44.0 : 34.0;
    Widget stop = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: s.here ? c.reward : (s.todo ? c.chip : (s.review ? c.attention : c.brand)),
        shape: BoxShape.circle,
        border: Border.all(color: s.todo ? c.line : Colors.transparent, width: 2),
      ),
      child: s.here
          ? Text(
              '$number',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 17),
            )
          : (s.todo ? null : Icon(s.review ? Icons.replay_rounded : Icons.check_rounded, color: Colors.white, size: 18)),
    );
    if (s.here) stop = stop.pulse(context, to: 1.08);
    return SizedBox(height: 48, child: Center(child: stop));
  }
}

/// A level: its number, title and share done, then its lessons as a path
/// of stops (done ✓, review ↺, here, ahead locked). A level ahead says what
/// opens it.
class MiddleLevel extends StatelessWidget {
  const MiddleLevel({required this.course, required this.island, required this.onStation, super.key});

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
              Icon(Icons.chevron_right_rounded, color: c.faint),
            ],
          ),
        ),
      ),
    );
  }
}
