import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../domain/games.dart';
import '../art/art.dart';
import '../art/island_art.dart';
import '../game_sound.dart';
import '../games_ui.dart';
import 'island_view.dart';

/// Where each island sits on the 390-wide map (left, top), as the design
/// places them; an island added later goes below, left and right in turn.
const _spots = <String, Offset>{
  'volcano': Offset(35, 40),
  'penguins': Offset(194, 120),
  'monkeys': Offset(13, 210),
  'turtles': Offset(187, 390),
  'birds': Offset(18, 470),
};

/// The world's hidden island, as the design keeps it.
const secretIsland = 'volcano';

/// A child's islands world (the design's Main): the sea map with its islands
/// (open ones have a game for this look; the current one holds the first
/// quest), the creatures they hatched flying around, and an island opened by
/// zooming into it. The map is a 390-wide picture scaled to the screen.
class KidsWorld extends StatefulWidget {
  const KidsWorld({super.key, required this.world, required this.name, required this.base, this.island});

  final GamesWorld world;

  /// The child's first name ("Yousef's world").
  final String name;

  /// Where the islands live in this look (the album is under it).
  final String base;

  /// An island to open on arrival (coming back from the album).
  final String? island;

  @override
  State<KidsWorld> createState() => _KidsWorldState();
}

class _KidsWorldState extends State<KidsWorld> with SingleTickerProviderStateMixin {
  late final AnimationController _zoom = AnimationController(vsync: this, duration: const Duration(milliseconds: 560));
  final _scroll = ScrollController();
  final _stageKey = GlobalKey();
  String? _focus;
  Offset _target = const Offset(195, 300);
  String? _wobble;
  int _wobbles = 0;
  String? _toast;
  bool _muted = GameSound.muted;
  bool _playing = false;
  bool _opening = false;

  GamesWorld get _w => widget.world;

  @override
  void initState() {
    super.initState();
    GameSound.load().then((_) {
      if (mounted) setState(() => _muted = GameSound.muted);
    });
    final first = widget.island;
    if (first != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        final island = _w.islands.where((i) => i.key == first && i.open).firstOrNull;
        if (island != null && mounted) _open(island, quiet: true);
      });
    }
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
    _scroll.dispose();
    super.dispose();
  }

  Map<String, Offset> get _places {
    final out = <String, Offset>{..._spots};
    var extra = 0;
    for (final i in _w.islands) {
      if (out.containsKey(i.key)) continue;
      out[i.key] = Offset(extra.isEven ? 190 : 18, 700 + 160.0 * extra);
      extra++;
    }
    return out;
  }

  Offset _centre(String key) {
    final at = _places[key]!;
    final box = IslandArt.size(key);
    return Offset(at.dx + box.width / 2, at.dy + box.height * 0.68);
  }

  double get _height => math.max(760, _w.islands.map((i) => _places[i.key]!.dy + IslandArt.size(i.key).height + 40).fold(0.0, math.max));

  void _music(String? scale) {
    if (_muted) return;
    _playing = true;
    GameSound.music(scale);
  }

  void _tap(Island island) {
    if (!island.open) {
      GameSound.play(GameEffect.locked);
      final l = context.l10n;
      setState(() {
        _wobble = island.key;
        _wobbles++;
        _toast = island.key == secretIsland ? l.gamesSecret : l.gamesLockedIsland(island.name);
      });
      return;
    }
    _open(island);
  }

  Future<void> _open(Island island, {bool quiet = false}) async {
    if (_opening) return;
    _opening = true;
    final calm = context.calm || quiet;
    if (!quiet) GameSound.play(GameEffect.zoom);
    _music(island.music);
    // Zoom so the island comes to the middle of what is on screen.
    final stage = _stageKey.currentContext?.findRenderObject() as RenderBox?;
    final scale = stage == null ? 1.0 : stage.size.width / 390;
    final view = MediaQuery.sizeOf(context);
    final top = _scroll.hasClients ? _scroll.offset : 0.0;
    _target = Offset(195, (top + view.height * 0.42) / scale);
    setState(() {
      _focus = island.key;
      _toast = null;
    });
    if (!calm) await _zoom.animateTo(1, curve: const Cubic(0.65, 0, 0.3, 1));
    if (!mounted) return;
    final origin = stage == null ? view.center(Offset.zero) : stage.localToGlobal(_target * scale);
    final cubit = context.read<LoadCubit<GamesWorld>>();
    final outcome = await Navigator.of(context, rootNavigator: true).push<String>(
      IslandRoute(
        origin: origin,
        quiet: calm,
        builder: (_) => BlocProvider.value(
          value: cubit,
          child: IslandView(islandKey: island.key, base: widget.base),
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
    if (outcome != null) context.go(outcome);
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

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final height = _height;
    final current = _w.current?.key;
    final places = _places;
    final islands = [..._w.islands]..sort((a, b) => places[a.key]!.dy.compareTo(places[b.key]!.dy));
    final birds = (_w.islands.where((i) => i.key == 'birds').firstOrNull?.creatures ?? const <Creature>[])
        .where((c) => c.found)
        .take(3)
        .toList();
    final swimmer = (_w.islands.where((i) => i.key == 'turtles').firstOrNull?.creatures ?? const <Creature>[])
        .where((c) => c.found)
        .firstOrNull;
    final buddy = _w.buddy?.look ?? _w.islands.firstOrNull?.creatures.firstOrNull?.look ?? const Look({});

    final world = SizedBox(
      width: 390,
      height: height,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          SvgArt(_seaSvg(height), width: 390, height: height),
          for (final (x, y, d, o) in const [
            (260.0, 610.0, 0, .6),
            (30.0, 470.0, 1, .6),
            (320.0, 360.0, 2, .6),
            (200.0, 300.0, 0, .5),
            (40.0, 720.0, 1, .55),
            (330.0, 700.0, 2, .55),
            (20.0, 250.0, 0, .5),
          ])
            Positioned(
              left: x,
              top: y,
              child: _Wave(opacity: o, variant: d),
            ),
          for (final (x, y, s, d) in const [(230.0, 410.0, 12.0, 0), (60.0, 690.0, 10.0, 800), (350.0, 560.0, 12.0, 1500)])
            Positioned(
              left: x,
              top: y,
              child: Twinkle(
                delay: Duration(milliseconds: d),
                child: SvgArt(_sparkleSvg, width: s, height: s),
              ),
            ),
          for (final island in islands)
            Positioned(
              left: places[island.key]!.dx,
              top: places[island.key]!.dy,
              child: _IslandButton(
                island: island,
                current: island.key == current,
                wobble: _wobble == island.key ? _wobbles : 0,
                delay: switch (island.key) {
                  'volcano' || 'turtles' => 1100,
                  'monkeys' => 2200,
                  _ => 0,
                },
                onTap: () => _tap(island),
              ),
            ),
          if (birds.isNotEmpty) Positioned(left: 118, top: 518, child: _Orbit(looks: [for (final b in birds) b.look])),
          if (swimmer != null)
            Positioned(
              left: 252,
              top: 524,
              child: IgnorePointer(
                child: Sway(
                  by: 50,
                  turn: 0,
                  period: const Duration(seconds: 9),
                  flip: true,
                  child: CreatureArt(swimmer.look, size: 44, motion: true),
                ),
              ),
            ),
          const Positioned(left: 340, top: 620, child: IgnorePointer(child: _Fish())),
          const Positioned(left: 236, top: 664, child: IgnorePointer(child: _Boat())),
          const Positioned(
            left: 0,
            top: 96,
            child: IgnorePointer(
              child: Drift(
                from: -140,
                to: 420,
                period: Duration(seconds: 70),
                child: Opacity(opacity: .85, child: SvgArt(cloudSvg, width: 120, height: 48)),
              ),
            ),
          ),
          const Positioned(
            left: 0,
            top: 400,
            child: IgnorePointer(
              child: Drift(
                from: -100,
                to: 420,
                period: Duration(seconds: 90),
                delay: Duration(seconds: 8),
                child: Opacity(opacity: .6, child: SvgArt(cloudSvg, width: 90, height: 36)),
              ),
            ),
          ),
        ],
      ),
    );

    final zoomed = AnimatedBuilder(
      animation: _zoom,
      builder: (context, child) {
        final focus = _focus;
        if (focus == null || _zoom.value == 0) return child!;
        final t = _zoom.value;
        final c = _centre(focus);
        final k = 1 + 1.7 * t;
        final m = Matrix4.identity()
          ..translateByDouble(c.dx + (_target.dx - c.dx) * t, c.dy + (_target.dy - c.dy) * t, 0, 1)
          ..scaleByDouble(k, k, 1, 1)
          ..translateByDouble(-c.dx, -c.dy, 0, 1);
        return Transform(transform: m, child: child);
      },
      child: world,
    );

    return LayoutBuilder(
      builder: (context, box) {
        final scale = math.min(box.maxWidth, 520) / 390;
        return ColoredBox(
          color: gwSea,
          child: SingleChildScrollView(
            controller: _scroll,
            physics: _focus == null ? null : const NeverScrollableScrollPhysics(),
            child: Center(
              child: SizedBox(
                key: _stageKey,
                width: 390 * scale,
                height: height * scale,
                child: ClipRect(
                  child: FittedBox(
                    fit: BoxFit.fitWidth,
                    alignment: Alignment.topCenter,
                    child: SizedBox(
                      width: 390,
                      height: height,
                      child: Stack(
                        children: [
                          zoomed,
                          Positioned(
                            top: 0,
                            left: 0,
                            right: 0,
                            child: _Header(
                              name: l.gamesWorld(widget.name),
                              buddy: buddy,
                              stars: _w.stars,
                              muted: _muted,
                              onAlbum: () => context.go('${widget.base}/album'),
                              onSound: _toggleSound,
                            ),
                          ),
                          if (_toast != null)
                            Positioned(
                              top: 74,
                              left: 20,
                              right: 20,
                              child: GameToast(_toast!, onGone: () => setState(() => _toast = null)),
                            ),
                          if (!_w.allowance.may)
                            Positioned(
                              left: 20,
                              right: 20,
                              bottom: 16,
                              child: Semantics(
                                liveRegion: true,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                                  decoration: BoxDecoration(color: gwInk.withValues(alpha: 0.78), borderRadius: BorderRadius.circular(18)),
                                  child: Text(
                                    _w.allowance.reasonLabel ?? '',
                                    textAlign: TextAlign.center,
                                    style: gwText(context, 14.5, weight: FontWeight.w700, color: Colors.white),
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

/// The sea, its lighter shallows round the islands, and the dotted way between them.
String _seaSvg(double height) =>
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 390 ${height.toStringAsFixed(0)}">'
    '<ellipse cx="118" cy="618" rx="128" ry="70" fill="#45ADEE"/><ellipse cx="282" cy="478" rx="118" ry="56" fill="#45ADEE"/>'
    '<ellipse cx="108" cy="340" rx="118" ry="62" fill="#45ADEE"/><ellipse cx="284" cy="226" rx="110" ry="54" fill="#45ADEE"/>'
    '<ellipse cx="120" cy="164" rx="104" ry="56" fill="#3EA4EA"/>'
    '<path d="M150 650 C220 620 270 560 282 506 C270 450 170 420 120 376 C150 300 250 290 282 250 C250 210 180 200 132 186" fill="none" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round" stroke-dasharray="1 14" opacity=".85"/>'
    '</svg>';

const _sparkleSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14"><path d="M7 0 L8.6 5.4 L14 7 L8.6 8.6 L7 14 L5.4 8.6 L0 7 L5.4 5.4 Z" fill="#FFFFFF"/></svg>';

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

/// An island on the map: its art bobbing on the sea, its label (locked,
/// current with how many creatures were found, or open with "new"), and a
/// homework pin. A locked one wobbles when tapped.
class _IslandButton extends StatelessWidget {
  const _IslandButton({required this.island, required this.current, required this.wobble, required this.delay, required this.onTap});

  final Island island;
  final bool current;
  final int wobble;
  final int delay;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final box = IslandArt.size(island.key);
    final calm = context.calm;
    Widget art = IslandArt(islandKey: island.key, colors: island.colors, emoji: island.emoji, locked: !island.open);
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
            bottom: -2,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.lock_rounded, size: 11, color: Colors.white),
                const SizedBox(width: 5),
                Text(island.key == secretIsland ? l.gamesSecretLabel : island.name, style: gwText(context, 12.5, color: Colors.white)),
              ],
            ),
          )
        : current
        ? _Label(
            kind: _LabelKind.current,
            bottom: -6,
            child: Text('${island.name} · ${l.gamesOf(island.found, island.creatures.length)}', style: gwText(context, 13)),
          )
        : _Label(
            kind: _LabelKind.open,
            bottom: -10,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(island.name, style: gwText(context, 13)),
                if (island.fresh) ...[
                  const SizedBox(width: 5),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6),
                    decoration: BoxDecoration(color: const Color(0xFFFF6B6B), borderRadius: BorderRadius.circular(999)),
                    child: Text(l.gamesNew, style: gwText(context, 11, color: Colors.white)),
                  ),
                ],
              ],
            ),
          );
    final aria = island.homework
        ? l.gamesAriaHomework(island.name)
        : island.open
        ? l.gamesAriaOpen(island.name)
        : l.gamesAriaLocked(island.name);
    return Semantics(
      button: true,
      label: aria,
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
              if (island.homework)
                PositionedDirectional(
                  top: -6,
                  end: 10,
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
                        const Icon(Icons.edit_rounded, size: 14, color: gwInk),
                        const SizedBox(width: 3),
                        Text(l.gamesHomeworkPin, style: gwText(context, 12)),
                      ],
                    ),
                  ).bob(context, by: 5),
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

/// The birds a child hatched, circling their island (a flattened circle).
class _Orbit extends StatelessWidget {
  const _Orbit({required this.looks});

  final List<Look> looks;

  @override
  Widget build(BuildContext context) {
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
                left: 86 * math.cos(a) - s / 2,
                top: 86 * 0.42 * math.sin(a) - s / 2,
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
        child: const SizedBox().animate(onPlay: (c) => c.repeat()).custom(duration: 7.seconds, builder: (_, v, _) => at(v)),
      ),
    );
  }
}

class _Fish extends StatelessWidget {
  const _Fish();

  static const _svg =
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 14"><path d="M2 7 C6 1 14 1 18 7 C14 13 6 13 2 7 Z" fill="#FF922B"/><path d="M18 7 L24 2 L24 12 Z" fill="#FF922B"/><circle cx="6" cy="6" r="1.4" fill="#13294B"/></svg>';

  @override
  Widget build(BuildContext context) {
    const fish = SvgArt(_svg, width: 24, height: 14);
    if (context.calm) return const SizedBox.shrink();
    // Jumps out of the water now and then.
    return fish
        .animate(onPlay: (c) => c.repeat())
        .custom(
          duration: 6.seconds,
          builder: (_, v, child) {
            if (v < 0.7 || v > 0.92) return const SizedBox(width: 24, height: 14);
            final t = (v - 0.7) / 0.22;
            final x = -44 * t;
            final y = 30 - 60 * math.sin(t * math.pi) + 10 * t;
            return Transform.translate(
              offset: Offset(x, y),
              child: Transform.rotate(angle: -140 * t * math.pi / 180, child: child),
            );
          },
        );
  }
}

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
    required this.stars,
    required this.muted,
    required this.onAlbum,
    required this.onSound,
  });

  final String name;
  final Look buddy;
  final int stars;
  final bool muted;
  final VoidCallback onAlbum;
  final VoidCallback onSound;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return SizedBox(
      height: 68,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14),
        child: Row(
          children: [
            Semantics(
              button: true,
              label: l.gamesAlbum,
              excludeSemantics: true,
              child: GestureDetector(
                onTap: onAlbum,
                child: Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: gwInk, width: 3),
                  ),
                  alignment: Alignment.center,
                  child: ClipOval(child: CreatureArt(buddy, size: 40)),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Flexible(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                decoration: BoxDecoration(color: gwInk.withValues(alpha: 0.55), borderRadius: BorderRadius.circular(999)),
                child: Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: gwText(context, 16, color: Colors.white),
                ),
              ),
            ),
            const Spacer(),
            StarsPill(stars),
            const SizedBox(width: 8),
            Button3d(
              width: 40,
              height: 40,
              label: muted ? l.gamesSoundOn : l.gamesSoundOff,
              onTap: onSound,
              child: SvgArt(muted ? _mutedSvg : _musicSvg, width: 20, height: 20),
            ),
          ],
        ),
      ),
    );
  }
}

const _musicSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3" fill="#FFD43B"/><circle cx="18" cy="16" r="3" fill="#FFD43B"/></svg>';
const _mutedSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/><path d="M3 3l18 18"/></svg>';
