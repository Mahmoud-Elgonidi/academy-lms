import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/juice.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/load_view.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../data/games_repository.dart';
import '../domain/games.dart';
import 'art/art.dart';
import 'game_sound.dart';
import 'games_ui.dart';

const _ink = Color(0xFF24124F);
const _purple = Color(0xFF6236E0);
const _sub = Color(0xFF5C4A99);
const _paper = Color(0xFFFFF4D6);

/// Twelve stars hatch an egg (the API's own count, for the hint).
const _starsPerEgg = 12;

/// The album (the design's Album): all the world's creatures, hatched ones
/// in colour (their name or the one the child gave, where they live), the
/// rest as a shadow with "?"; tabs by rarity and chips by island; a creature
/// opens in a sheet with its voice and story, to make it the buddy or name
/// it. A guardian sees the same without changing anything.
class GamesAlbumPage extends StatelessWidget {
  const GamesAlbumPage({super.key, required this.base, this.island, this.fresh});

  /// Where the islands are in this look (the way back).
  final String base;
  final String? island;

  /// A creature just hatched and named: its sheet opens.
  final String? fresh;

  @override
  Widget build(BuildContext context) {
    final id = context.learnerId();
    return BlocProvider(
      key: ValueKey(id),
      create: (c) =>
          LoadCubit<List<Creature>>(() => c.read<GamesRepository>().creatures(learnerId: id), refreshOn: c.read<Live>().on({Live.practice}))
            ..load(),
      child: Scaffold(
        backgroundColor: _paper,
        body: SafeArea(
          bottom: false,
          child: LoadView<List<Creature>>(
            builder: (context, all) => _Album(all: all, base: base, island: island, fresh: fresh, guardian: id != null),
          ),
        ),
      ),
    );
  }
}

class _Album extends StatefulWidget {
  const _Album({required this.all, required this.base, required this.guardian, this.island, this.fresh});

  final List<Creature> all;
  final String base;
  final bool guardian;
  final String? island;
  final String? fresh;

  @override
  State<_Album> createState() => _AlbumState();
}

class _AlbumState extends State<_Album> {
  String _tab = 'all';
  late String? _island = widget.island;
  String? _toast;

  @override
  void initState() {
    super.initState();
    final fresh = widget.all.where((c) => c.key == widget.fresh).firstOrNull;
    if (fresh != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _open(fresh, quiet: true);
      });
    }
  }

  Future<void> _open(Creature c, {bool quiet = false}) async {
    if (c.found) {
      GameSound.play(GameEffect.cheep);
    } else if (!quiet) {
      GameSound.play(GameEffect.locked);
    }
    final said = await showModalBottomSheet<String>(
      context: context,
      useRootNavigator: true,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      barrierColor: _ink.withValues(alpha: 0.5),
      builder: (_) => BlocProvider.value(
        value: context.read<LoadCubit<List<Creature>>>(),
        child: _CreatureSheet(creatureKey: c.key, guardian: widget.guardian),
      ),
    );
    if (said != null && mounted) setState(() => _toast = said);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final all = widget.all;
    final found = all.where((c) => c.found).length;
    final islands = <String, String>{for (final c in all) c.island ?? '': c.islandName ?? ''}..remove('');
    final shown = [
      for (final c in all)
        if ((_tab == 'all' || _tab == c.rarity) && (_island == null || _island == c.island)) c,
    ];
    final learner = context.watch<LearnerCubit>().state.selected;
    final rtl = Directionality.of(context) == TextDirection.rtl;
    return Stack(
      children: [
        CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              sliver: SliverList.list(
                children: [
                  if (widget.guardian) const Padding(padding: EdgeInsets.only(top: 8), child: LearnerSwitcher()),
                  SizedBox(
                    height: 68,
                    child: Row(
                      children: [
                        Button3d(
                          width: 44,
                          height: 44,
                          ink: _ink,
                          label: l.back,
                          onTap: () => context.canPop() ? context.pop() : context.go(widget.base),
                          child: Icon(rtl ? Icons.arrow_forward_rounded : Icons.arrow_back_rounded, color: _ink, size: 22),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Semantics(
                            header: true,
                            child: Text(
                              l.gamesAlbumTitle,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: gwText(context, 24, color: _ink),
                            ),
                          ),
                        ),
                        Container(
                          height: 36,
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          decoration: BoxDecoration(color: _purple, borderRadius: BorderRadius.circular(999)),
                          alignment: Alignment.center,
                          child: Text(l.gamesOf(found, all.length), style: gwText(context, 16, color: Colors.white)),
                        ),
                      ],
                    ),
                  ),
                  Semantics(
                    value: '$found / ${all.length}',
                    child: Container(
                      height: 12,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1E3C0),
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(color: _ink, width: 2),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: FractionallySizedBox(
                          widthFactor: all.isEmpty ? 0 : found / all.length,
                          heightFactor: 1,
                          child: const ColoredBox(color: _purple),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    widget.guardian ? l.gamesAlbumGuardian(learner?.firstName ?? '') : l.gamesAlbumHint(_starsPerEgg),
                    style: gwText(context, 13, weight: FontWeight.w700, color: _sub),
                  ),
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: _ink, width: 2),
                    ),
                    child: Row(
                      children: [
                        for (final (key, label) in [
                          ('all', l.gamesAlbumAll),
                          ('common', l.gamesRarityCommon),
                          ('rare', l.gamesRarityRare),
                          ('legendary', l.gamesRarityLegendary),
                        ])
                          Expanded(
                            child: Semantics(
                              selected: _tab == key,
                              button: true,
                              child: GestureDetector(
                                onTap: () {
                                  Juice.play(Sound.pop);
                                  setState(() => _tab = key);
                                },
                                child: AnimatedContainer(
                                  duration: const Duration(milliseconds: 180),
                                  height: 38,
                                  decoration: BoxDecoration(
                                    color: _tab == key ? _purple : Colors.transparent,
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                  alignment: Alignment.center,
                                  child: Text(label, style: gwText(context, 15, color: _tab == key ? Colors.white : _ink)),
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    height: 36,
                    child: ListView(
                      scrollDirection: Axis.horizontal,
                      children: [
                        for (final (key, name) in [(null, l.gamesAllIslands), for (final e in islands.entries) (e.key, e.value)])
                          Padding(
                            padding: const EdgeInsetsDirectional.only(end: 6),
                            child: Semantics(
                              selected: _island == key,
                              button: true,
                              child: GestureDetector(
                                onTap: () => setState(() => _island = key),
                                child: Container(
                                  height: 32,
                                  padding: const EdgeInsets.symmetric(horizontal: 12),
                                  decoration: BoxDecoration(
                                    color: _island == key ? gwSun : Colors.white,
                                    borderRadius: BorderRadius.circular(999),
                                    border: Border.all(color: _ink, width: 2),
                                  ),
                                  alignment: Alignment.center,
                                  child: Text(name, style: gwText(context, 13.5, color: _ink)),
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
              ),
            ),
            SliverPadding(
              padding: EdgeInsets.fromLTRB(16, 0, 16, 24 + MediaQuery.paddingOf(context).bottom),
              sliver: SliverGrid.builder(
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                  maxCrossAxisExtent: 140,
                  mainAxisExtent: 134,
                  crossAxisSpacing: 10,
                  mainAxisSpacing: 10,
                ),
                itemCount: shown.length,
                itemBuilder: (context, i) {
                  final tile = _Tile(creature: shown[i], onTap: () => _open(shown[i]));
                  if (context.calm) return tile;
                  return tile
                      .animate(delay: Duration(milliseconds: 30 * i.clamp(0, 20)))
                      .scaleXY(begin: 0.4, end: 1, duration: 350.ms, curve: const Cubic(0.3, 1.6, 0.55, 1))
                      .fadeIn();
                },
              ),
            ),
          ],
        ),
        if (_toast != null)
          Positioned(
            left: 24,
            right: 24,
            bottom: 24 + MediaQuery.paddingOf(context).bottom,
            child: GameToast(_toast!, dark: true, onGone: () => setState(() => _toast = null)),
          ),
      ],
    );
  }
}

const _ring = {'common': _ink, 'rare': Color(0xFF1C7ED6), 'legendary': Color(0xFFF08C00)};
const _rarityBg = {'common': Color(0xFFF1F3F5), 'rare': Color(0xFFD0EBFF), 'legendary': Color(0xFFFFE8A3)};

class _Tile extends StatelessWidget {
  const _Tile({required this.creature, required this.onTap});

  final Creature creature;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = creature;
    final ring = _ring[c.rarity] ?? _ink;
    final body = Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          SizedBox(
            width: 84,
            height: 74,
            child: Stack(
              alignment: Alignment.center,
              children: [
                CreatureArt(c.look, size: 84, shadow: !c.found, line: '#24124F'),
                if (!c.found) Text('؟', style: gwText(context, 30, color: _purple)),
              ],
            ),
          ),
          Text(
            c.found ? c.shownName : l.gamesNotYet,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: gwText(context, 14.5, color: _ink, height: 1.1),
          ),
          Text(
            c.islandName ?? '',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: gwText(context, 11.5, weight: FontWeight.w700, color: _sub, height: 1.15),
          ),
        ],
      ),
    );
    return Semantics(
      button: true,
      label: c.found ? l.gamesAriaFound(c.shownName, c.islandName ?? '') : l.gamesAriaShadow(c.islandName ?? ''),
      excludeSemantics: true,
      child: GestureDetector(
        onTap: onTap,
        child: Stack(
          children: [
            Positioned.fill(
              child: c.found
                  ? Container(
                      decoration: BoxDecoration(
                        color: hexColor(c.look.colour('belly'), Colors.white),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: ring, width: 3),
                        boxShadow: [BoxShadow(color: ring, offset: const Offset(0, 4))],
                      ),
                    )
                  : CustomPaint(painter: _Dashed()),
            ),
            Positioned.fill(child: body),
            if (c.rarity != 'common')
              Positioned(
                top: 6,
                left: 6,
                child: Twinkle(
                  child: SvgArt(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14"><path d="M7 0 L8.6 5.4 L14 7 L8.6 8.6 L7 14 L5.4 8.6 L0 7 L5.4 5.4 Z" fill="${_hexOf(ring)}"/></svg>',
                    width: 16,
                    height: 16,
                  ),
                ),
              ),
            if (c.buddy) const PositionedDirectional(top: 4, end: 6, child: Text('💛', style: TextStyle(fontSize: 14))),
          ],
        ),
      ),
    );
  }
}

String _hexOf(Color c) => '#${(c.toARGB32() & 0xFFFFFF).toRadixString(16).padLeft(6, '0')}';

/// The dashed outline of a creature not hatched yet.
class _Dashed extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final r = RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(20));
    canvas.drawRRect(r, Paint()..color = const Color(0xFFF8F5FF));
    final path = Path()..addRRect(r.deflate(1));
    final dash = Paint()
      ..color = const Color(0xFFB197FC)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    for (final metric in path.computeMetrics()) {
      for (var d = 0.0; d < metric.length; d += 10) {
        canvas.drawPath(metric.extractPath(d, d + 6), dash);
      }
    }
  }

  @override
  bool shouldRepaint(_Dashed old) => false;
}

/// A creature in its sheet: big and alive (tap it to hear it), its story,
/// where it lives and how it hatched; the child names it or makes it their
/// buddy. One not hatched yet says how to hatch it.
class _CreatureSheet extends StatefulWidget {
  const _CreatureSheet({required this.creatureKey, required this.guardian});

  final String creatureKey;
  final bool guardian;

  @override
  State<_CreatureSheet> createState() => _CreatureSheetState();
}

class _CreatureSheetState extends State<_CreatureSheet> {
  int _sing = 0;
  bool _busy = false;

  void _hear() {
    GameSound.play(GameEffect.cheep);
    setState(() => _sing++);
  }

  Future<void> _save(Future<Creature> Function(GamesRepository r) call, String said) async {
    if (_busy) return;
    setState(() => _busy = true);
    final cubit = context.read<LoadCubit<List<Creature>>>();
    try {
      await call(context.read<GamesRepository>());
      await cubit.load();
      if (mounted) Navigator.of(context).pop(said);
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() => _busy = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.problemText(p))));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final all = context.watch<LoadCubit<List<Creature>>>().state.data ?? const [];
    final c = all.where((x) => x.key == widget.creatureKey).firstOrNull;
    if (c == null) return const SizedBox.shrink();
    final island = c.islandName ?? '';
    final how = switch (c.rarity) {
      'rare' => l.gamesHowRare(island),
      'legendary' => l.gamesHowLegendary(island),
      _ => l.gamesHowCommon(island, _starsPerEgg),
    };
    final shown = c.found ? c.shownName : l.gamesNotYet;
    final names = c.names.isEmpty ? [c.name] : c.names;
    Widget art = CreatureArt(c.look, size: 170, motion: true, line: '#24124F');
    if (!context.calm && _sing > 0) {
      art = art.animate(key: ValueKey(_sing)).scaleXY(begin: 0.6, end: 1, duration: 500.ms, curve: const Cubic(0.3, 1.6, 0.55, 1));
    }
    return Container(
      constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.92, maxWidth: 560),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
        border: Border(top: BorderSide(color: _ink, width: 4)),
      ),
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(18, 14, 18, 24 + MediaQuery.paddingOf(context).bottom),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Center(
              child: Container(
                width: 48,
                height: 5,
                decoration: BoxDecoration(color: const Color(0xFFD0C7EE), borderRadius: BorderRadius.circular(999)),
              ),
            ),
            const SizedBox(height: 6),
            Container(
              height: 170,
              decoration: BoxDecoration(
                color: c.found ? hexColor(c.look.colour('belly'), const Color(0xFFF3F0FF)) : const Color(0xFFF8F5FF),
                borderRadius: BorderRadius.circular(24),
              ),
              alignment: Alignment.center,
              child: c.found
                  ? Semantics(
                      button: true,
                      label: l.gamesVoice,
                      child: GestureDetector(onTap: _hear, child: art),
                    )
                  : Text('؟', style: gwText(context, 72, color: _purple)),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: Text(shown, style: gwText(context, 26, color: _ink)),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
                  decoration: BoxDecoration(
                    color: _rarityBg[c.rarity] ?? const Color(0xFFF1F3F5),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: _ink, width: 2),
                  ),
                  child: Text(switch (c.rarity) {
                    'rare' => l.gamesRarityRare,
                    'legendary' => l.gamesRarityLegendary,
                    _ => l.gamesRarityCommon,
                  }, style: gwText(context, 14, color: _ink)),
                ),
              ],
            ),
            const SizedBox(height: 4),
            if (c.found) ...[
              Text.rich(
                TextSpan(
                  children: [
                    TextSpan(text: c.story),
                    if ((c.nickname?.isNotEmpty ?? false) && c.nickname != c.name)
                      TextSpan(
                        text: ' (${c.name})',
                        style: const TextStyle(color: _sub),
                      ),
                  ],
                ),
                style: gwText(context, 16, weight: FontWeight.w600, color: _ink, height: 1.45),
              ),
              Text(
                '$island · ${c.source == 'mastery' ? l.gamesFromMastery : l.gamesFromStars}',
                style: gwText(context, 13, weight: FontWeight.w700, color: _sub),
              ),
              const SizedBox(height: 12),
              if (!widget.guardian) ...[
                Wrap(
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    Text(l.gamesNameIt, style: gwText(context, 14, color: _ink)),
                    for (final name in names)
                      Semantics(
                        inMutuallyExclusiveGroup: true,
                        checked: c.shownName == name,
                        child: Button3d(
                          height: 36,
                          shrink: true,
                          ink: _ink,
                          depth: c.shownName == name ? 4 : 0,
                          color: c.shownName == name ? const Color(0xFFB197FC) : Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          enabled: !_busy,
                          onTap: () {
                            Juice.play(Sound.pop);
                            _save((r) => r.name(c.key, name == c.name ? '' : name), l.gamesNamed(name));
                          },
                          child: Text(name, style: gwText(context, 15, color: _ink)),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 12),
              ],
              Row(
                children: [
                  Expanded(
                    child: Button3d(
                      height: 54,
                      ink: _ink,
                      depth: 4,
                      onTap: _hear,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.music_note_rounded, color: _ink, size: 20),
                          const SizedBox(width: 6),
                          Text(l.gamesVoice, style: gwText(context, 17, color: _ink)),
                        ],
                      ),
                    ),
                  ),
                  if (!widget.guardian) ...[
                    const SizedBox(width: 10),
                    Expanded(
                      child: Button3d(
                        height: 54,
                        ink: _ink,
                        depth: c.buddy ? 0 : 4,
                        color: gwSun,
                        enabled: !c.buddy && !_busy,
                        onTap: () {
                          GameSound.play(GameEffect.win);
                          _save((r) => r.chooseBuddy(c.key), l.gamesBuddyDone(c.shownName));
                        },
                        child: Text(c.buddy ? l.gamesIsBuddy : l.gamesMakeBuddy, style: gwText(context, 17, color: _ink)),
                      ),
                    ),
                  ],
                ],
              ),
            ] else ...[
              Text(
                l.gamesShadowStory,
                style: gwText(context, 16, weight: FontWeight.w600, color: _ink),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF3F0FF),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFF9775FA), width: 2),
                ),
                child: Text(
                  how,
                  style: gwText(context, 16, weight: FontWeight.w700, color: _ink),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
