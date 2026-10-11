import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../games/domain/games.dart';
import '../../../games/presentation/art/art.dart';
import '../../../games/presentation/art/island_art.dart';
import '../../../games/presentation/game_sound.dart';
import '../../../games/presentation/games_ui.dart';
import '../../../games/presentation/kids/island_route.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';
import '../widgets/missions.dart';
import '../widgets/rewards.dart';
import '../widgets/station_sheet.dart';
import 'island_stations.dart';

/// The map is a 390-wide picture scaled to the screen; each level's island
/// sits a step lower than the one before, left and right in turn.
const _width = 390.0;
const _step = 182.0;
const _top = 40.0;

/// A child's journey (their home): the course's levels as islands on the
/// sea (done with a cup, the one they are on with the pin, the ones ahead
/// under clouds), each opening by zooming into it; their stars, rank and
/// buddy at the top; then the missions, the egg and the week's chest.
class KidsJourney extends StatefulWidget {
  const KidsJourney({super.key, required this.map, required this.place, required this.name});

  final JourneyMap map;
  final JourneyPlace place;

  /// The child's first name ("Yousef's journey").
  final String name;

  @override
  State<KidsJourney> createState() => _KidsJourneyState();
}

class _KidsJourneyState extends State<KidsJourney> with SingleTickerProviderStateMixin {
  late final AnimationController _zoom = AnimationController(vsync: this, duration: const Duration(milliseconds: 560));
  final _mapKey = GlobalKey();
  String? _course;
  String? _focus;
  String? _wobble;
  int _wobbles = 0;
  String? _toast;
  bool _muted = GameSound.muted;
  bool _playing = false;
  bool _opening = false;

  JourneyMap get _m => widget.map;

  JourneyCourse? get _now => _m.courses.where((c) => c.enrollmentId == _course).firstOrNull ?? _m.courses.firstOrNull;

  @override
  void initState() {
    super.initState();
    GameSound.load().then((_) {
      if (mounted) setState(() => _muted = GameSound.muted);
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Another tab or the app in the background: the music stops.
    if (!TickerMode.valuesOf(context).enabled && _playing) {
      _playing = false;
      GameSound.stopMusic();
    }
  }

  @override
  void dispose() {
    if (_playing) GameSound.stopMusic();
    _zoom.dispose();
    super.dispose();
  }

  void _say(String text) => setState(() => _toast = text);

  void _music(String? scale) {
    if (_muted || _m.games == null) return;
    _playing = true;
    GameSound.music(scale);
  }

  void _toggleSound() {
    if (_muted) {
      GameSound.setMuted(false);
      setState(() => _muted = false);
      _music(null);
    } else if (!_playing) {
      _music(null);
      setState(() {});
    } else {
      GameSound.setMuted(true);
      setState(() {
        _muted = true;
        _playing = false;
      });
    }
  }

  static Offset _spot(int i, JourneyIsland island) {
    final box = IslandArt.size(island.artKey);
    return Offset(i.isEven ? 24 : _width - box.width - 24, _top + i * _step);
  }

  static Offset _centre(int i, JourneyIsland island) {
    final box = IslandArt.size(island.artKey);
    return _spot(i, island) + Offset(box.width / 2, box.height * 0.62);
  }

  static double _heightOf(List<JourneyIsland> islands) {
    if (islands.isEmpty) return 0;
    return _top + (islands.length - 1) * _step + IslandArt.size(islands.last.artKey).height + 46;
  }

  void _tap(JourneyCourse course, JourneyIsland island, int index) {
    if (!island.open) {
      GameSound.play(GameEffect.locked);
      final before = course.before(island);
      setState(() {
        _wobble = island.id;
        _wobbles++;
        _toast = before == null ? context.l10n.journeyAheadFirst : context.l10n.journeyAheadLocked(before.title);
      });
      return;
    }
    _open(island, index);
  }

  Future<void> _open(JourneyIsland island, int index) async {
    if (_opening) return;
    _opening = true;
    final calm = context.calm;
    GameSound.play(GameEffect.zoom);
    _music(island.art?.music);
    setState(() {
      _focus = island.id;
      _toast = null;
    });
    if (!calm) await _zoom.animateTo(1, curve: const Cubic(0.65, 0, 0.3, 1));
    if (!mounted) return;
    final box = _mapKey.currentContext?.findRenderObject() as RenderBox?;
    final scale = box == null || !box.hasSize ? 1.0 : box.size.width / _width;
    final origin = box == null || !box.hasSize
        ? MediaQuery.sizeOf(context).center(Offset.zero)
        : box.localToGlobal(_centre(index, island) * scale);
    final cubit = context.read<LoadCubit<JourneyMap>>();
    final router = GoRouter.of(context);
    final outcome = await Navigator.of(context, rootNavigator: true).push<String>(
      IslandRoute(
        origin: origin,
        quiet: calm,
        builder: (_) => BlocProvider.value(
          value: cubit,
          child: IslandStations(islandId: island.id, place: widget.place),
        ),
      ),
    );
    if (!mounted) return;
    GameSound.play(GameEffect.back);
    _music(null);
    if (calm) {
      _zoom.value = 0;
    } else {
      await _zoom.animateBack(0, duration: const Duration(milliseconds: 600), curve: const Cubic(0.65, 0, 0.3, 1));
    }
    if (!mounted) return;
    setState(() => _focus = null);
    _opening = false;
    if (outcome != null) router.go(outcome);
  }

  /// A mission's station opens its sheet over the map.
  void _openMission(Mission m) {
    final at = _m.find(m.stationId);
    if (at == null) return;
    showStationSheet(context, map: _m, place: widget.place, island: at.$2, station: at.$3, say: _say);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final course = _now;
    final games = _m.games;
    final rewards = _m.rewards;
    final buddy = games?.buddy?.look ?? course?.here?.creatures.where((c) => c.found).firstOrNull?.look;

    final children = <Widget>[
      _Header(
        name: l.journeyTitle(widget.name),
        buddy: buddy,
        stars: rewards ? _m.stars.total : null,
        rank: rewards ? _m.stars.rank.label : null,
        muted: _muted,
        sound: games != null,
        onAlbum: games == null ? null : () => context.go(widget.place.album),
        onSound: _toggleSound,
      ),
      if (_m.courses.length > 1)
        _CourseChips(courses: _m.courses, selected: course?.enrollmentId, onPick: (id) => setState(() => _course = id)),
      if (_m.lastLesson != null) LastLessonBanner(learnerId: _m.learnerId, lesson: _m.lastLesson!, rewards: rewards),
      if (course == null)
        _SeaNote(text: l.journeyNoCourses)
      else if (!course.hasCurriculum || course.islands.isEmpty)
        _SeaNote(text: l.journeyNoCurriculum)
      else
        _map(course),
      _SeaHeading(l.journeyMissions),
      MissionStrip(map: _m, place: widget.place, openStation: _openMission, say: _say),
      if (rewards && (_m.chest != null || (games != null && !_m.plain)))
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (_m.chest != null)
                Expanded(
                  child: JourneyChestCard(map: _m, place: widget.place, kids: true, say: _say),
                ),
              if (_m.chest != null && games != null) const SizedBox(width: 10),
              if (games != null)
                Expanded(
                  child: EggCard(map: _m, place: widget.place, kids: true, say: _say),
                ),
            ],
          ),
        ),
      if (games != null && !games.allowance.may && !_m.plain)
        Semantics(
          liveRegion: true,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(color: gwInk.withValues(alpha: 0.78), borderRadius: BorderRadius.circular(18)),
            child: Text(
              games.allowance.reasonLabel ?? '',
              textAlign: TextAlign.center,
              style: gwText(context, 14.5, weight: FontWeight.w700, color: Colors.white),
            ),
          ),
        ),
    ];

    return ColoredBox(
      color: gwSea,
      child: Stack(
        children: [
          ListView(
            physics: _focus == null ? null : const NeverScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(14, 6, 14, 28),
            children: [
              for (final (i, w) in children.indexed)
                Padding(padding: const EdgeInsets.only(bottom: 12), child: i < 2 ? w : w.appear(context, i)),
            ],
          ),
          if (_toast != null)
            Positioned(top: 74, left: 20, right: 20, child: GameToast(_toast!, onGone: () => setState(() => _toast = null))),
        ],
      ),
    );
  }

  Widget _map(JourneyCourse course) {
    final islands = course.islands;
    final height = _heightOf(islands);
    final here = islands.indexWhere((i) => i.state == 'here');
    final homework = {
      for (final m in _m.missions)
        if (m.kind == 'homework' && !m.done && m.islandId != null) m.islandId!,
    };
    final lesson = _m.nextLesson;
    final centres = [for (final (i, island) in islands.indexed) _centre(i, island)];
    final world = SizedBox(
      width: _width,
      height: height,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(child: CustomPaint(painter: _Road(centres, here < 0 ? islands.length : here))),
          for (var i = 0; i * 150 < height; i++)
            Positioned(
              left: i.isEven ? 300 : 30,
              top: 110 + i * 150.0,
              child: _Wave(opacity: .55, variant: i % 3),
            ),
          for (var i = 0; i * 210 < height - 120; i++)
            Positioned(
              left: i.isEven ? 210 : 140,
              top: 80 + i * 210.0,
              child: Twinkle(
                delay: Duration(milliseconds: 700 * i),
                child: const SvgArt(_sparkleSvg, width: 12, height: 12),
              ),
            ),
          for (final (i, island) in islands.indexed)
            Positioned(
              left: _spot(i, island).dx,
              top: _spot(i, island).dy,
              child: _IslandButton(
                island: island,
                homework: homework.contains(island.id),
                wobble: _wobble == island.id ? _wobbles : 0,
                delay: (i % 3) * 900,
                onTap: () => _tap(course, island, i),
              ),
            ),
          if (here >= 0) ...[
            Positioned(
              left: centres[here].dx,
              top: centres[here].dy,
              child: _Orbit(looks: [for (final c in islands[here].creatures.where((c) => c.found).take(3)) c.look]),
            ),
            if (lesson != null)
              Positioned(
                left: here.isEven ? 250 : 40,
                top: centres[here].dy - 10,
                child: const IgnorePointer(child: _Boat()),
              ),
          ],
          const Positioned(
            left: 0,
            top: 60,
            child: IgnorePointer(
              child: Drift(
                from: -140,
                to: 420,
                period: Duration(seconds: 70),
                child: Opacity(opacity: .85, child: SvgArt(cloudSvg, width: 120, height: 48)),
              ),
            ),
          ),
        ],
      ),
    );
    final zoomed = AnimatedBuilder(
      animation: _zoom,
      builder: (context, child) {
        final focus = islands.indexWhere((i) => i.id == _focus);
        if (focus < 0 || _zoom.value == 0) return child!;
        final c = centres[focus];
        final k = 1 + 1.7 * _zoom.value;
        final m = Matrix4.identity()
          ..translateByDouble(c.dx, c.dy, 0, 1)
          ..scaleByDouble(k, k, 1, 1)
          ..translateByDouble(-c.dx, -c.dy, 0, 1);
        return Transform(transform: m, child: child);
      },
      child: world,
    );
    return LayoutBuilder(
      builder: (context, box) {
        final scale = math.min(box.maxWidth, 520) / _width;
        return Center(
          child: SizedBox(
            key: _mapKey,
            width: _width * scale,
            height: height * scale,
            child: ClipRect(
              child: FittedBox(fit: BoxFit.fitWidth, alignment: Alignment.topCenter, child: zoomed),
            ),
          ),
        );
      },
    );
  }
}

const _sparkleSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14"><path d="M7 0 L8.6 5.4 L14 7 L8.6 8.6 L7 14 L5.4 8.6 L0 7 L5.4 5.4 Z" fill="#FFFFFF"/></svg>';

/// The dotted way between the islands: brighter up to the one the child is on.
class _Road extends CustomPainter {
  const _Road(this.centres, this.here);

  final List<Offset> centres;
  final int here;

  @override
  void paint(Canvas canvas, Size size) {
    for (var i = 0; i < centres.length - 1; i++) {
      final a = centres[i];
      final b = centres[i + 1];
      final path = Path()
        ..moveTo(a.dx, a.dy)
        ..cubicTo(a.dx, (a.dy + b.dy) / 2, b.dx, (a.dy + b.dy) / 2, b.dx, b.dy);
      final walked = i < here;
      final paint = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = walked ? 6 : 5
        ..strokeCap = StrokeCap.round
        ..color = Colors.white.withValues(alpha: walked ? 0.95 : 0.55);
      for (final metric in path.computeMetrics()) {
        for (var d = 0.0; d < metric.length; d += 15) {
          canvas.drawPath(metric.extractPath(d, math.min(d + 1, metric.length)), paint);
        }
      }
    }
  }

  @override
  bool shouldRepaint(_Road old) => old.here != here || old.centres.length != centres.length;
}

class _Wave extends StatelessWidget {
  const _Wave({required this.opacity, required this.variant});

  final double opacity;
  final int variant;

  @override
  Widget build(BuildContext context) {
    final wave = IgnorePointer(
      child: SvgArt(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 12"><path d="M2 8 Q10 0 18 8 T34 8" stroke="#FFFFFF" stroke-width="3" fill="none" stroke-linecap="round" opacity="$opacity"/></svg>',
        width: 40,
        height: 12,
      ),
    );
    if (context.calm) return wave;
    final ms = const [3200, 4100, 3600][variant];
    return wave
        .animate(onPlay: (c) => c.repeat(reverse: true))
        .moveX(
          begin: -10,
          end: 10,
          duration: Duration(milliseconds: ms),
          curve: Curves.easeInOut,
        );
  }
}

/// A level's island on the map: its art bobbing on the sea (greyed under a
/// cloud while ahead), its label (a cup when done, the pin and the share
/// done where the child is, a lock ahead), and a homework pin.
class _IslandButton extends StatelessWidget {
  const _IslandButton({required this.island, required this.homework, required this.wobble, required this.delay, required this.onTap});

  final JourneyIsland island;
  final bool homework;
  final int wobble;
  final int delay;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final box = IslandArt.size(island.artKey);
    final calm = context.calm;
    final here = island.state == 'here';
    final done = island.state == 'done';
    Widget art = IslandArt(islandKey: island.artKey, colors: island.colors, emoji: island.art?.emoji ?? '', locked: !island.open);
    if (!calm) {
      art = wobble > 0
          ? art
                .animate(key: ValueKey(wobble))
                .custom(
                  duration: 550.ms,
                  builder: (_, v, child) => Transform.rotate(angle: math.sin(v * math.pi * 5) * (1 - v) * 5 * math.pi / 180, child: child),
                )
          : art
                .animate(
                  onPlay: (c) => c.repeat(reverse: true),
                  delay: Duration(milliseconds: delay),
                )
                .moveY(begin: 0, end: -5, duration: 1700.ms, curve: Curves.easeInOut);
    }
    final label = !island.open
        ? _Label(
            kind: _LabelKind.locked,
            bottom: -4,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.lock_rounded, size: 11, color: Colors.white),
                const SizedBox(width: 5),
                Flexible(
                  child: Text(
                    island.title,
                    overflow: TextOverflow.ellipsis,
                    style: gwText(context, 12.5, color: Colors.white),
                  ),
                ),
              ],
            ),
          )
        : here
        ? _Label(
            kind: _LabelKind.current,
            bottom: -8,
            child: Text('${island.title} · ${island.percent}%', overflow: TextOverflow.ellipsis, style: gwText(context, 13)),
          )
        : _Label(
            kind: _LabelKind.open,
            bottom: -8,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.emoji_events_rounded, size: 16, color: Color(0xFFF59F00)),
                const SizedBox(width: 4),
                Flexible(
                  child: Text(island.title, overflow: TextOverflow.ellipsis, style: gwText(context, 13)),
                ),
              ],
            ),
          );
    final aria = !island.open
        ? l.journeyAriaAhead(island.title)
        : here
        ? l.journeyAriaHere(island.title)
        : l.journeyAriaDone(island.title);
    return Semantics(
      button: true,
      label: homework ? '$aria. ${l.gamesHomeworkPin}' : aria,
      excludeSemantics: true,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: SizedBox(
          width: box.width,
          height: box.height,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              art,
              label,
              if (here)
                Positioned(
                  top: -18,
                  left: 0,
                  right: 0,
                  child: Center(
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFF6B6B),
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(color: gwInk, width: 3),
                        boxShadow: const [BoxShadow(color: gwInk, offset: Offset(0, 3))],
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.place_rounded, size: 16, color: Colors.white),
                          const SizedBox(width: 2),
                          Text(l.youAreHere, style: gwText(context, 13, color: Colors.white)),
                        ],
                      ),
                    ).bob(context, by: 5),
                  ),
                ),
              if (done)
                PositionedDirectional(
                  top: 0,
                  start: 6,
                  child: Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      border: Border.all(color: gwInk, width: 3),
                      boxShadow: const [BoxShadow(color: gwInk, offset: Offset(0, 3))],
                    ),
                    child: const Icon(Icons.emoji_events_rounded, color: Color(0xFFF59F00), size: 24),
                  ),
                ),
              if (homework)
                PositionedDirectional(
                  top: here ? 18 : -6,
                  end: 4,
                  child: Container(
                    padding: const EdgeInsetsDirectional.fromSTEB(6, 2, 8, 2),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: gwInk, width: 3),
                      boxShadow: const [BoxShadow(color: gwInk, offset: Offset(0, 3))],
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.flag_rounded, size: 14, color: Color(0xFFE03131)),
                        const SizedBox(width: 3),
                        Text(l.gamesHomeworkPin, style: gwText(context, 12)),
                      ],
                    ),
                  ).bob(context, by: 4),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

enum _LabelKind { locked, current, open }

class _Label extends StatelessWidget {
  const _Label({required this.kind, required this.bottom, required this.child});

  final _LabelKind kind;
  final double bottom;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final pill = Container(
      constraints: const BoxConstraints(maxWidth: 220),
      padding: EdgeInsets.symmetric(horizontal: kind == _LabelKind.locked ? 10 : 12, vertical: kind == _LabelKind.locked ? 1 : 2),
      decoration: BoxDecoration(
        color: switch (kind) {
          _LabelKind.locked => gwInk,
          _LabelKind.current => gwSun,
          _LabelKind.open => Colors.white,
        },
        borderRadius: BorderRadius.circular(999),
        border: kind == _LabelKind.locked ? null : Border.all(color: gwInk, width: 3),
        boxShadow: kind == _LabelKind.current ? const [BoxShadow(color: gwInk, offset: Offset(0, 3))] : null,
      ),
      child: child,
    );
    return Positioned(
      left: -40,
      right: -40,
      bottom: bottom,
      child: Center(
        child: context.calm
            ? pill
            : pill.animate().scaleXY(begin: 0.4, end: 1, duration: 400.ms, curve: const Cubic(0.3, 1.6, 0.55, 1)).fadeIn(),
      ),
    );
  }
}

/// The creatures hatched on the child's island, circling it (a flattened circle).
class _Orbit extends StatelessWidget {
  const _Orbit({required this.looks});

  final List<Look> looks;

  @override
  Widget build(BuildContext context) {
    if (looks.isEmpty) return const SizedBox.shrink();
    const sizes = [36.0, 30.0, 32.0];
    Widget at(double turn) => Stack(
      clipBehavior: Clip.none,
      children: [
        for (var i = 0; i < looks.length; i++)
          Builder(
            builder: (_) {
              final a = (turn + i / 3) * 2 * math.pi;
              final s = sizes[i];
              return Positioned(
                left: 104 * math.cos(a) - s / 2,
                top: 104 * 0.4 * math.sin(a) - s / 2 - 30,
                child: CreatureArt(looks[i], size: s, motion: true),
              );
            },
          ),
      ],
    );
    final still = IgnorePointer(child: SizedBox(width: 0, height: 0, child: at(0.1)));
    if (context.calm) return still;
    return IgnorePointer(
      child: SizedBox(
        width: 0,
        height: 0,
        child: const SizedBox().animate(onPlay: (c) => c.repeat()).custom(duration: 8.seconds, builder: (_, v, _) => at(v)),
      ),
    );
  }
}

/// The lesson's boat, rocking by the child's island while a lesson is coming.
class _Boat extends StatelessWidget {
  const _Boat();

  static const _svg =
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 58 54"><path d="M28 4 V40" stroke="#7A4A22" stroke-width="3"/><path d="M30 6 L52 36 L30 36 Z" fill="#FFFFFF"/><path d="M26 10 L10 34 L26 34 Z" fill="#FFE066"/><path d="M28 2 L38 6 L28 10 Z" fill="#FF6B6B"/><path d="M4 40 H54 L46 52 H12 Z" fill="#C9622B"/><path d="M4 40 H54" stroke="#8C3F16" stroke-width="2"/></svg>';

  @override
  Widget build(BuildContext context) {
    const boat = SvgArt(_svg, width: 58, height: 54);
    if (context.calm) return boat;
    return boat
        .animate(onPlay: (c) => c.repeat())
        .custom(
          duration: 3.seconds,
          builder: (_, v, child) {
            final s = math.sin(v * 2 * math.pi);
            return Transform.translate(
              offset: Offset(0, -4 * (1 - math.cos(v * 2 * math.pi)) / 2),
              child: Transform.rotate(angle: -s * 3 * math.pi / 180, child: child),
            );
          },
        );
  }
}

class _Header extends StatelessWidget {
  const _Header({
    required this.name,
    required this.buddy,
    required this.muted,
    required this.sound,
    required this.onSound,
    this.stars,
    this.rank,
    this.onAlbum,
  });

  final String name;
  final Look? buddy;
  final int? stars;
  final String? rank;
  final bool muted;
  final bool sound;
  final VoidCallback? onAlbum;
  final VoidCallback onSound;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return SizedBox(
      height: 64,
      child: Row(
        children: [
          if (onAlbum != null)
            Semantics(
              button: true,
              label: l.gamesAlbum,
              excludeSemantics: true,
              child: GestureDetector(
                onTap: onAlbum,
                child: Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: gwInk, width: 3),
                  ),
                  alignment: Alignment.center,
                  child: buddy == null ? SvgArt(eggSvg(), width: 26, height: 32) : ClipOval(child: CreatureArt(buddy!, size: 42)),
                ),
              ),
            ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 1),
                  decoration: BoxDecoration(color: gwInk.withValues(alpha: 0.55), borderRadius: BorderRadius.circular(999)),
                  child: Text(
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: gwText(context, 16, color: Colors.white),
                  ),
                ),
                if (rank != null && rank!.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.military_tech_rounded, color: gwSun, size: 16),
                      const SizedBox(width: 2),
                      Text(rank!, style: gwText(context, 12.5, color: Colors.white)),
                    ],
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 6),
          if (stars != null) StarsPill(stars!),
          const SizedBox(width: 6),
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              border: Border.all(color: gwInk, width: 3),
            ),
            child: const IconTheme(
              data: IconThemeData(color: gwInk, size: 20),
              child: FittedBox(child: NotificationBell()),
            ),
          ),
          if (sound) ...[
            const SizedBox(width: 6),
            Button3d(
              width: 40,
              height: 40,
              label: muted ? l.gamesSoundOn : l.gamesSoundOff,
              onTap: onSound,
              child: SvgArt(muted ? _mutedSvg : _musicSvg, width: 20, height: 20),
            ),
          ],
        ],
      ),
    );
  }
}

const _musicSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3" fill="#FFD43B"/><circle cx="18" cy="16" r="3" fill="#FFD43B"/></svg>';
const _mutedSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/><path d="M3 3l18 18"/></svg>';

/// The child's courses, when more than one: each has its own islands.
class _CourseChips extends StatelessWidget {
  const _CourseChips({required this.courses, required this.selected, required this.onPick});

  final List<JourneyCourse> courses;
  final String? selected;
  final ValueChanged<String> onPick;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 46,
    child: ListView(
      scrollDirection: Axis.horizontal,
      children: [
        for (final c in courses)
          Padding(
            padding: const EdgeInsetsDirectional.only(end: 8),
            child: Semantics(
              selected: c.enrollmentId == selected,
              child: Button3d(
                height: 40,
                shrink: true,
                depth: c.enrollmentId == selected ? 0 : 3,
                color: c.enrollmentId == selected ? gwSun : Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                onTap: () => onPick(c.enrollmentId),
                child: Text(c.subject, style: gwText(context, 14.5)),
              ),
            ),
          ),
      ],
    ),
  );
}

class _SeaHeading extends StatelessWidget {
  const _SeaHeading(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Semantics(
    header: true,
    child: Text(text, style: gwText(context, 20, color: Colors.white)),
  );
}

class _SeaNote extends StatelessWidget {
  const _SeaNote({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(22),
      border: Border.all(color: gwInk, width: 3),
    ),
    child: Text(
      text,
      textAlign: TextAlign.center,
      style: gwText(context, 15, weight: FontWeight.w700),
    ),
  );
}
