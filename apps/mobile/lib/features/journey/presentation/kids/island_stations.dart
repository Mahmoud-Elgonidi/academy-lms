import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../games/presentation/art/art.dart';
import '../../../games/presentation/art/scene_art.dart';
import '../../../games/presentation/games_ui.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';
import '../widgets/station_sheet.dart';

/// An opened island (a level of the course): its scene with the creatures
/// hatched there, and its lessons as a path of stations in order (done ✓,
/// review ↺, here 📍, ahead 🔒), a flag on a lesson with homework and the
/// lesson's boat at the station the child is on. A station opens its sheet.
/// Pops with a place to go (the album) or nothing (back to the map).
class IslandStations extends StatefulWidget {
  const IslandStations({super.key, required this.islandId, required this.place});

  final String islandId;
  final JourneyPlace place;

  @override
  State<IslandStations> createState() => _IslandStationsState();
}

class _IslandStationsState extends State<IslandStations> {
  String? _toast;

  void _say(String text) => setState(() => _toast = text);

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final rtl = Directionality.of(context) == TextDirection.rtl;
    return BlocBuilder<LoadCubit<JourneyMap>, LoadState<JourneyMap>>(
      builder: (context, state) {
        final map = state.data;
        JourneyIsland? island;
        for (final c in map?.courses ?? const <JourneyCourse>[]) {
          island ??= c.islands.where((i) => i.id == widget.islandId).firstOrNull;
        }
        if (map == null || island == null) return const SizedBox.shrink();
        final sky = hexColor(island.colors['sky'], const Color(0xFF8ED8FF));
        final found = island.creatures.where((c) => c.found).toList();
        final residents = found.isEmpty
            ? [if (island.creatures.isNotEmpty) island.creatures.first.look]
            : [for (final c in found.take(3)) c.look];
        return Scaffold(
          backgroundColor: sky,
          body: LayoutBuilder(
            builder: (context, box) {
              final scale = math.min(box.maxWidth, 480) / 390;
              return Stack(
                children: [
                  Positioned(
                    top: 0,
                    left: 0,
                    right: 0,
                    height: SceneArt.height * scale,
                    child: FittedBox(
                      fit: BoxFit.fitWidth,
                      alignment: Alignment.topCenter,
                      child: SceneArt(islandKey: island!.artKey, residents: residents, colors: island.colors),
                    ),
                  ),
                  Positioned(
                    top: 250 * scale,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    child: _Sheet(map: map, island: island, place: widget.place, say: _say),
                  ),
                  PositionedDirectional(
                    top: MediaQuery.paddingOf(context).top + 14,
                    start: 14,
                    child: Button3d(
                      height: 44,
                      padding: const EdgeInsetsDirectional.fromSTEB(10, 0, 14, 0),
                      onTap: () => Navigator.of(context).pop(),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(rtl ? Icons.chevron_right_rounded : Icons.chevron_left_rounded, color: gwInk, size: 24),
                          const SizedBox(width: 2),
                          Text(l.gamesToMap, style: gwText(context, 15)),
                        ],
                      ),
                    ),
                  ),
                  if (_toast != null)
                    Positioned(
                      left: 20,
                      right: 20,
                      bottom: MediaQuery.paddingOf(context).bottom + 24,
                      child: GameToast(_toast!, onGone: () => setState(() => _toast = null)),
                    ),
                ],
              );
            },
          ),
        );
      },
    );
  }
}

class _Sheet extends StatelessWidget {
  const _Sheet({required this.map, required this.island, required this.place, required this.say});

  final JourneyMap map;
  final JourneyIsland island;
  final JourneyPlace place;
  final void Function(String text) say;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final calm = context.calm;
    final found = island.creatures.where((c) => c.found).toList();
    final games = map.games;
    Widget rise(int i, Widget child) => calm
        ? child
        : child
              .animate(delay: Duration(milliseconds: 450 + i * 100))
              .moveY(begin: 24, end: 0, duration: 500.ms, curve: const Cubic(0.2, 0.9, 0.3, 1.1))
              .fadeIn(duration: 300.ms);

    final sheet = Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
        border: Border(top: BorderSide(color: gwInk, width: 4)),
      ),
      child: ListView(
        padding: EdgeInsets.fromLTRB(16, 14, 16, 16 + MediaQuery.paddingOf(context).bottom),
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l.journeyLevel(island.index),
                      style: gwText(context, 13.5, weight: FontWeight.w700, color: const Color(0xFF5C4A99)),
                    ),
                    Semantics(header: true, child: Text(island.title, style: gwText(context, 26, height: 1.1))),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF4D6),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: gwInk, width: 2),
                ),
                child: Text(l.journeyLessonsDone(island.done, island.lessons), style: gwText(context, 13.5)),
              ),
            ],
          ),
          const SizedBox(height: 6),
          rise(0, _Path(map: map, island: island, place: place, onTap: (s) => _open(context, s))),
          const SizedBox(height: 10),
          if (games != null && island.creatures.isNotEmpty)
            rise(
              1,
              Row(
                children: [
                  Expanded(
                    child: _MiniCard(
                      color: const Color(0xFFF3F0FF),
                      onTap: () => Navigator.of(context).pop('${place.album}?island=${island.artKey}'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(l.gamesCreaturesCard(found.length, island.creatures.length), style: gwText(context, 13)),
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              for (final c in found.take(3)) CreatureArt(c.look, size: 34),
                              if (island.creatures.length > found.length)
                                Container(
                                  width: 32,
                                  height: 32,
                                  margin: const EdgeInsetsDirectional.only(start: 4),
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    border: Border.all(color: const Color(0xFF7048E8), width: 2),
                                  ),
                                  alignment: Alignment.center,
                                  child: Text(
                                    '+${island.creatures.length - found.length}',
                                    style: gwText(context, 12, color: const Color(0xFF5F3DC4)),
                                  ),
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  if (map.rewards) ...[
                    const SizedBox(width: 10),
                    SizedBox(
                      width: 128,
                      child: _MiniCard(
                        color: const Color(0xFFFFF4D6),
                        onTap: () => Navigator.of(context).pop(place.album),
                        child: island.next != null
                            ? Row(
                                children: [
                                  SvgArt(
                                    eggSvg(
                                      belly: island.next!.colour('belly') ?? '#F3E3C3',
                                      body: island.next!.colour('body') ?? '#E0C79A',
                                    ),
                                    width: 30,
                                    height: 36,
                                  ),
                                  const SizedBox(width: 6),
                                  Expanded(
                                    child: Text(l.gamesEggCard(games.eggHave, games.eggNeed), style: gwText(context, 12.5, height: 1.2)),
                                  ),
                                ],
                              )
                            : Row(
                                children: [
                                  const Icon(Icons.emoji_events_rounded, size: 22, color: Color(0xFFF59F00)),
                                  const SizedBox(width: 4),
                                  Expanded(child: Text(l.gamesIslandDone, style: gwText(context, 12.5, height: 1.2))),
                                ],
                              ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
        ],
      ),
    );
    if (calm) return sheet;
    return sheet.animate(delay: 250.ms).slideY(begin: 1.2, end: 0, duration: 600.ms, curve: const Cubic(0.2, 0.9, 0.25, 1.05));
  }

  Future<void> _open(BuildContext context, JourneyStation s) async {
    // A creature hatched and named: the island closes, then the album opens.
    await showStationSheet(
      context,
      map: map,
      place: place,
      island: island,
      station: s,
      say: say,
      go: (to) => Navigator.of(context).pop(to),
    );
  }
}

class _MiniCard extends StatelessWidget {
  const _MiniCard({required this.color, required this.onTap, required this.child});

  final Color color;
  final VoidCallback onTap;
  final Widget child;

  @override
  Widget build(BuildContext context) => Button3d(
    color: color,
    radius: 20,
    depth: 4,
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
    onTap: onTap,
    child: Align(alignment: AlignmentDirectional.centerStart, child: child),
  );
}

/// The island's lessons as a winding path of stops in their order.
class _Path extends StatelessWidget {
  const _Path({required this.map, required this.island, required this.place, required this.onTap});

  final JourneyMap map;
  final JourneyIsland island;
  final JourneyPlace place;
  final ValueChanged<JourneyStation> onTap;

  static const _row = 112.0;
  static const _xs = [0.5, 0.78, 0.5, 0.22];

  @override
  Widget build(BuildContext context) {
    final stations = island.stations;
    if (stations.isEmpty) return const SizedBox.shrink();
    final rtl = Directionality.of(context) == TextDirection.rtl;
    final homework = {
      for (final m in map.missions)
        if (m.kind == 'homework' && !m.done && m.stationId != null) m.stationId!,
    };
    final lesson = map.nextLesson;
    final walked = stations.lastIndexWhere((s) => s.done) + 1;
    return LayoutBuilder(
      builder: (context, box) {
        final w = box.maxWidth;
        double x(int i) {
          final share = _xs[i % _xs.length];
          return w * (rtl ? 1 - share : share);
        }

        final points = [for (var i = 0; i < stations.length; i++) Offset(x(i), _row * i + 40)];
        return SizedBox(
          height: _row * stations.length,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Positioned.fill(child: CustomPaint(painter: _Way(points, walked))),
              for (var i = 0; i < stations.length; i++) ...[
                Positioned(
                  left: points[i].dx - 75,
                  top: points[i].dy - 34,
                  width: 150,
                  child: _Stop(
                    station: stations[i],
                    sign: i == 0 || stations[i].unit != stations[i - 1].unit ? stations[i].unit : null,
                    homework: homework.contains(stations[i].id),
                    onTap: () => onTap(stations[i]),
                  ),
                ),
                if (lesson != null && lesson.stationId == stations[i].id)
                  Positioned(
                    left: points[i].dx < w / 2 ? points[i].dx + 44 : null,
                    right: points[i].dx >= w / 2 ? w - points[i].dx + 44 : null,
                    top: points[i].dy - 30,
                    child: _LessonBoat(lesson: lesson, map: map, place: place),
                  ),
              ],
            ],
          ),
        );
      },
    );
  }
}

/// One stop: a round marker (done ✓ green, review ↺ orange, here 📍 sun and
/// bouncing, ahead 🔒 grey), its title under it, a sign where a unit starts,
/// and a flag when its homework is open.
class _Stop extends StatelessWidget {
  const _Stop({required this.station, required this.homework, required this.onTap, this.sign});

  final JourneyStation station;
  final String? sign;
  final bool homework;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = station;
    final here = s.here;
    final size = here ? 64.0 : 54.0;
    final colour = switch (s.state) {
      'here' => gwSun,
      'todo' => const Color(0xFFF1F3F5),
      _ when s.review => const Color(0xFFFFC078),
      _ => const Color(0xFF8CE99A),
    };
    Widget dot = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: colour,
        shape: BoxShape.circle,
        border: Border.all(color: s.todo ? const Color(0xFFADB5BD) : gwInk, width: 3),
        boxShadow: s.todo ? null : const [BoxShadow(color: gwInk, offset: Offset(0, 4))],
      ),
      child: Icon(stationIcon(s), color: s.todo ? const Color(0xFFADB5BD) : gwInk, size: here ? 32 : 26),
    );
    if (here) dot = dot.bob(context, by: 5);
    return Semantics(
      button: true,
      label: '${s.title}. ${stationStateLabel(l, s)}${homework ? '. ${l.gamesHomeworkPin}' : ''}',
      excludeSemantics: true,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: Opacity(
          opacity: s.todo ? 0.7 : 1,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                clipBehavior: Clip.none,
                alignment: Alignment.center,
                children: [
                  dot,
                  if (homework)
                    PositionedDirectional(
                      top: -10,
                      end: -16,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFF6B6B),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(color: gwInk, width: 2),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.flag_rounded, size: 12, color: Colors.white),
                            const SizedBox(width: 2),
                            Text(l.gamesHomeworkPin, style: gwText(context, 11, color: Colors.white)),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 1),
                decoration: BoxDecoration(
                  color: here ? gwSun : Colors.white,
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: gwInk, width: 2),
                ),
                child: Text(
                  here ? l.youAreHere : s.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: gwText(context, here ? 13.5 : 12.5),
                ),
              ),
              if (here)
                Text(
                  s.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: gwText(context, 12, weight: FontWeight.w700),
                )
              else if (sign != null && sign!.isNotEmpty)
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.signpost_rounded, size: 13, color: Color(0xFF5C4A99)),
                    const SizedBox(width: 3),
                    Flexible(
                      child: Text(
                        sign!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: gwText(context, 11.5, weight: FontWeight.w700, color: const Color(0xFF5C4A99)),
                      ),
                    ),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// The next lesson's boat at the child's station: when it is, or "join" once the room opens.
class _LessonBoat extends StatelessWidget {
  const _LessonBoat({required this.lesson, required this.map, required this.place});

  final Mission lesson;
  final JourneyMap map;
  final JourneyPlace place;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    if (lesson.joinOpen) {
      return Button3d(
        color: gwSun,
        height: 40,
        shrink: true,
        depth: 4,
        label: l.joinLesson,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        onTap: () => JourneyActions(context, map, place).join(lesson),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.sailing_rounded, color: gwInk, size: 18),
            const SizedBox(width: 4),
            Text(l.joinLesson, style: gwText(context, 14)),
          ],
        ),
      ).shimmerIf(context, true);
    }
    final when = lessonOpens(context, lesson) ?? lesson.startsAt?.label ?? '';
    return Container(
      constraints: const BoxConstraints(maxWidth: 140),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFFD0EBFF),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: gwInk, width: 2),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.sailing_rounded, color: gwInk, size: 16),
          const SizedBox(width: 4),
          Flexible(
            child: Text(when, maxLines: 2, overflow: TextOverflow.ellipsis, style: gwText(context, 11.5, height: 1.2)),
          ),
        ],
      ),
    );
  }
}

/// The road between the stops: green up to the last one done, dotted after.
class _Way extends CustomPainter {
  const _Way(this.points, this.walked);

  final List<Offset> points;
  final int walked;

  @override
  void paint(Canvas canvas, Size size) {
    for (var i = 0; i < points.length - 1; i++) {
      final a = points[i];
      final b = points[i + 1];
      final path = Path()
        ..moveTo(a.dx, a.dy)
        ..cubicTo(a.dx, (a.dy + b.dy) / 2, b.dx, (a.dy + b.dy) / 2, b.dx, b.dy);
      final done = i + 1 < walked;
      final paint = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 7
        ..strokeCap = StrokeCap.round
        ..color = done ? const Color(0xFF8CE99A) : const Color(0xFFDEE2E6);
      if (done) {
        canvas.drawPath(path, paint);
      } else {
        for (final metric in path.computeMetrics()) {
          for (var d = 0.0; d < metric.length; d += 18) {
            canvas.drawPath(metric.extractPath(d, math.min(d + 9, metric.length)), paint);
          }
        }
      }
    }
  }

  @override
  bool shouldRepaint(_Way old) => old.walked != walked || old.points.length != points.length;
}
