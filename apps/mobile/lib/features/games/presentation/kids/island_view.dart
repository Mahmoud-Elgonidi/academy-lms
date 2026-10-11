import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../domain/games.dart';
import '../art/art.dart';
import '../art/scene_art.dart';
import '../game_sound.dart';
import '../games_ui.dart';
import '../play/play_page.dart';

/// An island opening over the map: a circle grows from where the island now
/// is until it fills the screen; closing shrinks it back.
class IslandRoute<T> extends PageRouteBuilder<T> {
  IslandRoute({required Offset origin, required bool quiet, required WidgetBuilder builder})
    : super(
        opaque: false,
        barrierColor: null,
        transitionDuration: quiet ? Duration.zero : const Duration(milliseconds: 600),
        reverseTransitionDuration: quiet ? Duration.zero : const Duration(milliseconds: 520),
        pageBuilder: (context, _, _) => builder(context),
        transitionsBuilder: (context, animation, _, child) {
          if (context.calm) return child;
          final curved = CurvedAnimation(parent: animation, curve: const Cubic(0.6, 0, 0.3, 1));
          return AnimatedBuilder(
            animation: curved,
            builder: (context, child) {
              final size = MediaQuery.sizeOf(context);
              final r = curved.value * math.sqrt(size.width * size.width + size.height * size.height) * 1.2;
              return ClipPath(clipper: _Circle(origin, r), child: child);
            },
            child: child,
          );
        },
      );
}

class _Circle extends CustomClipper<Path> {
  const _Circle(this.centre, this.radius);

  final Offset centre;
  final double radius;

  @override
  Path getClip(Size size) => Path()..addOval(Rect.fromCircle(center: centre, radius: radius));

  @override
  bool shouldReclip(_Circle old) => old.radius != radius || old.centre != centre;
}

/// An opened island (the design's island screen): its scene with the
/// creatures found there, and a sheet with its games (each says which quest
/// it plays), its creatures and the next egg. Pops with a place to go (the
/// album) or nothing (back to the map).
class IslandView extends StatefulWidget {
  const IslandView({super.key, required this.islandKey, required this.base});

  final String islandKey;
  final String base;

  @override
  State<IslandView> createState() => _IslandViewState();
}

class _IslandViewState extends State<IslandView> {
  String? _toast;

  void _say(String text) => setState(() => _toast = text);

  Future<void> _play(GamesWorld w, Island island, GameCard g) async {
    final l = context.l10n;
    if (!w.allowance.may) {
      GameSound.play(GameEffect.locked);
      return _say(w.allowance.reasonLabel ?? '');
    }
    if (!g.playable) {
      GameSound.play(GameEffect.locked);
      return _say(l.gamesNeedsPractice);
    }
    final cubit = context.read<LoadCubit<GamesWorld>>();
    final outcome = await openRound(context, PlayRequest(gameId: g.id, quest: g.questKey, look: 'kids', sky: island.colors['sky']));
    if (!mounted) return;
    GameSound.music(island.music);
    unawaited(cubit.load());
    final named = outcome?.named;
    if (named != null) Navigator.of(context).pop('${widget.base}/album?new=$named');
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final rtl = Directionality.of(context) == TextDirection.rtl;
    return BlocBuilder<LoadCubit<GamesWorld>, LoadState<GamesWorld>>(
      builder: (context, state) {
        final w = state.data;
        final island = w?.islands.where((i) => i.key == widget.islandKey).firstOrNull;
        if (w == null || island == null) return const SizedBox.shrink();
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
              final sceneHeight = SceneArt.height * scale;
              return Stack(
                children: [
                  Positioned(
                    top: 0,
                    left: 0,
                    right: 0,
                    height: sceneHeight,
                    child: FittedBox(
                      fit: BoxFit.fitWidth,
                      alignment: Alignment.topCenter,
                      child: SceneArt(islandKey: island.key, residents: residents, colors: island.colors),
                    ),
                  ),
                  Positioned(
                    top: 296 * scale,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    child: _Sheet(world: w, island: island, base: widget.base, onPlay: (g) => _play(w, island, g)),
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
  const _Sheet({required this.world, required this.island, required this.base, required this.onPlay});

  final GamesWorld world;
  final Island island;
  final String base;
  final ValueChanged<GameCard> onPlay;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final calm = context.calm;
    final found = island.creatures.where((c) => c.found).toList();
    final questGame = island.games.where((g) => g.questKey != null).firstOrNull;
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
          if (!world.allowance.may) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF4D6),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: gwInk, width: 2),
              ),
              child: Text(world.allowance.reasonLabel ?? '', textAlign: TextAlign.center, style: gwText(context, 14)),
            ),
            const SizedBox(height: 10),
          ],
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Semantics(header: true, child: Text(island.name, style: gwText(context, 26, height: 1.1))),
                    const SizedBox(height: 2),
                    Text(
                      island.story,
                      style: gwText(context, 14, weight: FontWeight.w600, color: const Color(0xFF3B4F72), height: 1.35),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Container(
                constraints: const BoxConstraints(maxWidth: 152),
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF4D6),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: gwInk, width: 2),
                ),
                child: Column(
                  children: [
                    for (final line
                        in questGame != null
                            ? [
                                (questGame.questTitle?.isNotEmpty ?? false) ? questGame.questTitle! : questReason(l, questGame.questReason),
                                _questItems(l, questGame),
                              ]
                            : [l.gamesIslandCreatures, l.gamesOf(found.length, island.creatures.length)])
                      Text(
                        line,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: gwText(context, 13, height: 1.2),
                      ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          for (var i = 0; i < island.games.length; i++) ...[
            rise(i, _GameCardTile(game: island.games[i], onTap: () => onPlay(island.games[i]))),
            const SizedBox(height: 10),
          ],
          rise(
            island.games.length,
            Row(
              children: [
                Expanded(
                  child: _MiniCard(
                    color: const Color(0xFFF3F0FF),
                    onTap: () => Navigator.of(context).pop('$base/album?island=${island.key}'),
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
                const SizedBox(width: 10),
                SizedBox(
                  width: 128,
                  child: _MiniCard(
                    color: const Color(0xFFFFF4D6),
                    onTap: () => Navigator.of(context).pop('$base/album'),
                    child: island.next != null
                        ? Row(
                            children: [
                              SvgArt(
                                eggSvg(belly: island.next!.colour('belly') ?? '#F3E3C3', body: island.next!.colour('body') ?? '#E0C79A'),
                                width: 30,
                                height: 36,
                              ),
                              const SizedBox(width: 6),
                              Expanded(
                                child: Text(l.gamesEggCard(world.eggHave, world.eggNeed), style: gwText(context, 12.5, height: 1.2)),
                              ),
                            ],
                          )
                        : Text('🏆 ${l.gamesIslandDone}', style: gwText(context, 12.5, height: 1.2)),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
    if (calm) return sheet;
    return sheet.animate(delay: 250.ms).slideY(begin: 1.2, end: 0, duration: 600.ms, curve: const Cubic(0.2, 0.9, 0.25, 1.05));
  }

  String _questItems(AppLocalizations l, GameCard g) {
    final quest = world.quests.where((q) => q.key == g.questKey).firstOrNull;
    return quest == null ? questReason(l, g.questReason) : l.gamesItems(quest.items);
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

/// One game of the island: its picture, its title and badge (homework, new,
/// soon, or the best stars), which quest it plays, and the play button.
class _GameCardTile extends StatelessWidget {
  const _GameCardTile({required this.game, required this.onTap});

  final GameCard game;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final g = game;
    final art = gameArt(g);
    final (bg, tile) = _paint[art]!;
    final (badgeKind, badge) = g.questReason == 'homework'
        ? ('homework', l.gamesBadgeHomework)
        : !g.playable
        ? ('soon', l.gamesBadgeSoon)
        : g.plays == 0
        ? ('new', l.gamesBadgeNew)
        : ('stars', l.gamesStars(g.best));
    final what = g.questKey != null
        ? '${questReason(l, g.questReason)}: ${(g.questTitle?.isNotEmpty ?? false) ? g.questTitle : g.summary}'
        : (g.playable ? g.summary : l.gamesNeedsPractice);
    return Opacity(
      opacity: g.playable ? 1 : 0.75,
      child: Button3d(
        color: bg,
        radius: 22,
        depth: g.playable ? 5 : 0,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        label: '${g.title}. $badge. $what',
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 60),
          child: Row(
            children: [
              Container(
                width: 60,
                height: 60,
                decoration: BoxDecoration(
                  color: tile,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: gwInk, width: 3),
                ),
                clipBehavior: Clip.antiAlias,
                alignment: Alignment.center,
                child: SvgArt(_tileSvg[art]!, width: art == 'lock' ? 26 : 48, height: art == 'lock' ? 26 : 44),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 6,
                      runSpacing: 2,
                      children: [
                        Text(g.title, style: gwText(context, 19, height: 1.15)),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: badgeKind == 'homework' ? const Color(0xFFFF6B6B) : gwSun,
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(color: gwInk, width: 2),
                          ),
                          child: Text(
                            badge,
                            style: gwText(context, 11.5, color: badgeKind == 'homework' ? Colors.white : gwInk, height: 1),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      what,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: gwText(context, 13.5, weight: FontWeight.w600, height: 1.3),
                    ),
                  ],
                ),
              ),
              if (g.playable) ...[
                const SizedBox(width: 8),
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: gwSun,
                    shape: BoxShape.circle,
                    border: Border.all(color: gwInk, width: 3),
                  ),
                  child: const Icon(Icons.play_arrow_rounded, color: gwInk, size: 24),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Which picture a game's tile shows: its own for the world's first games,
/// else by the kinds it plays; a lock while it cannot be played.
String gameArt(GameCard g) {
  if (!g.playable) return 'lock';
  return switch (g.key) {
    'bird-gates' => 'gates',
    'bird-balloons' => 'balloons',
    'bird-twins' => 'cards',
    'turtle-bridge' => 'bridge',
    _ => g.kinds.contains('pair') ? 'cards' : (g.kinds.contains('choice') ? 'balloons' : 'gates'),
  };
}

const _paint = <String, (Color, Color)>{
  'gates': (Color(0xFFD0EBFF), Color(0xFF74C0FC)),
  'balloons': (Color(0xFFFFE8CC), Color(0xFFFFA94D)),
  'cards': (Color(0xFFC3FAE8), Color(0xFF38D9A9)),
  'bridge': (Color(0xFFC3FAE8), Color(0xFF63E6BE)),
  'lock': (Color(0xFFF1F3F5), Color(0xFFDEE2E6)),
};

const _tileSvg = <String, String>{
  'gates': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 44"><ellipse cx="34" cy="22" rx="10" ry="16" fill="none" stroke="#FFD43B" stroke-width="5"/><g transform="translate(2 12) scale(0.3)"><ellipse cx="50" cy="44" rx="30" ry="25" fill="#FFFFFF" stroke="#13294B" stroke-width="7"/><path d="M22 40 L6 46 L22 52 Z" fill="#FF922B"/><path d="M48 40 C50 14 80 8 84 24 C78 34 66 42 56 46 Z" fill="#4DABF7" stroke="#13294B" stroke-width="7"/></g></svg>',
  'balloons': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 46 46"><ellipse cx="16" cy="18" rx="11" ry="13" fill="#FF6B6B" stroke="#13294B" stroke-width="2.5"/><ellipse cx="32" cy="16" rx="10" ry="12" fill="#38D9A9" stroke="#13294B" stroke-width="2.5"/><path d="M16 31 Q18 38 15 44 M32 28 Q34 36 31 44" stroke="#13294B" stroke-width="1.6" fill="none"/></svg>',
  'cards': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 46 46"><g transform="rotate(-12 18 24)"><rect x="6" y="8" width="22" height="30" rx="4" fill="#6236E0" stroke="#13294B" stroke-width="2.5"/></g><g transform="rotate(10 30 24)"><rect x="20" y="6" width="22" height="30" rx="4" fill="#FFFFFF" stroke="#13294B" stroke-width="2.5"/><path d="M24 16 h14 M24 22 h10" stroke="#13294B" stroke-width="2.5" stroke-linecap="round"/></g></svg>',
  'bridge': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 90 60"><ellipse cx="18" cy="34" rx="12" ry="10" fill="#8CE99A" stroke="#13294B" stroke-width="3"/><ellipse cx="48" cy="34" rx="28" ry="20" fill="#2F9E44" stroke="#13294B" stroke-width="3"/></svg>',
  'lock': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.6" stroke-linecap="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
};
