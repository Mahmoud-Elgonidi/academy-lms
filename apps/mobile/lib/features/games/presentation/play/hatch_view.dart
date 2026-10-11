import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../data/games_repository.dart';
import '../../domain/games.dart';
import '../art/art.dart';
import '../game_sound.dart';
import '../games_ui.dart';

const _night = Color(0xFF2B1A66);
const _ink = Color(0xFF24124F);

/// A creature hatched from a round's stars (the design's Hatch): the night, a
/// nest, an egg to tap three times, then the creature comes out among
/// feathers and the child names it and puts it in the album. The picture is
/// 390 × 844, scaled to the screen.
class HatchView extends StatefulWidget {
  const HatchView({super.key, required this.creature, required this.need, required this.onLeave, required this.onNamed});

  final Hatched creature;
  final int need;
  final VoidCallback onLeave;
  final ValueChanged<String> onNamed;

  @override
  State<HatchView> createState() => _HatchViewState();
}

class _HatchViewState extends State<HatchView> {
  int _taps = 0;
  int _knock = 0;
  bool _hatched = false;
  int _name = 0;
  bool _saving = false;
  String? _toast;

  Hatched get _c => widget.creature;

  void _tap() {
    if (_hatched || _taps >= 3) return;
    GameSound.play(GameEffect.knock);
    setState(() {
      _taps++;
      _knock++;
    });
    if (context.calm && _taps >= 3) _open();
  }

  void _open() {
    if (_hatched || !mounted) return;
    GameSound.play(GameEffect.hatch);
    setState(() => _hatched = true);
  }

  Future<void> _keep() async {
    if (_saving) return;
    setState(() => _saving = true);
    GameSound.play(GameEffect.win);
    final names = _c.names.isEmpty ? [_c.name] : _c.names;
    try {
      await context.read<GamesRepository>().name(_c.key, names[_name.clamp(0, names.length - 1)]);
      widget.onNamed(_c.key);
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() {
          _saving = false;
          _toast = context.problemText(p);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final calm = context.calm;
    final hints = [l.gamesHatchTap0, l.gamesHatchTap1, l.gamesHatchTap2, l.gamesHatchTap3];
    final rtl = Directionality.of(context) == TextDirection.rtl;

    Widget egg = Semantics(
      button: true,
      label: l.gamesHatchEgg(math.max(0, 3 - _taps)),
      child: GestureDetector(onTap: _tap, child: SvgArt(_eggSvg(_taps), width: 160, height: 200)),
    );
    if (!calm) {
      egg = _knock == 0
          ? egg
                .animate(onPlay: (c) => c.repeat())
                .custom(
                  duration: 2400.ms,
                  builder: (_, v, child) {
                    final t = v / 0.3;
                    final angle = t < 1 ? math.sin(t * math.pi * 2) * 3 * math.pi / 180 : 0.0;
                    return Transform.rotate(angle: angle, alignment: const Alignment(0, 0.9), child: child);
                  },
                )
          : egg
                .animate(key: ValueKey(_knock), onComplete: (_) => _taps >= 3 ? _open() : null)
                .custom(
                  duration: 450.ms,
                  builder: (_, v, child) {
                    final a = v < 0.25
                        ? -9 * v / 0.25
                        : (v < 0.55 ? -9 + 17 * (v - 0.25) / 0.3 : (v < 0.8 ? 8 - 12 * (v - 0.55) / 0.25 : -4 + 4 * (v - 0.8) / 0.2));
                    final s = 1 + 0.05 * math.sin(v * math.pi);
                    return Transform.rotate(
                      angle: a * math.pi / 180,
                      alignment: const Alignment(0, 0.9),
                      child: Transform.scale(scale: s, child: child),
                    );
                  },
                );
    }

    final feathers = [
      (60.0, 18.0, 34.0, 0, _c.look.colour('body') ?? '#C9A06B'),
      (160.0, 14.0, 28.0, 400, '#FFD43B'),
      (260.0, 18.0, 34.0, 200, _c.look.colour('wing') ?? '#B197FC'),
      (330.0, 14.0, 28.0, 700, _c.look.colour('belly') ?? '#C9A06B'),
      (20.0, 16.0, 30.0, 900, '#63E6BE'),
    ];

    final picture = SizedBox(
      width: 390,
      height: 844,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          // The night sky: stars that twinkle and the moon.
          for (final (x, y, r, d, c) in const [
            (40.0, 160.0, 2.5, 0, Colors.white),
            (350.0, 200.0, 2.0, 800, Colors.white),
            (300.0, 120.0, 3.0, 1500, Color(0xFFFFE8A3)),
            (80.0, 300.0, 2.0, 0, Colors.white),
            (360.0, 380.0, 2.5, 800, Colors.white),
            (20.0, 440.0, 2.0, 1500, Color(0xFFFFE8A3)),
          ])
            Positioned(
              left: x - r,
              top: y - r,
              child: Twinkle(
                delay: Duration(milliseconds: d),
                child: Container(
                  width: r * 2,
                  height: r * 2,
                  decoration: BoxDecoration(color: c, shape: BoxShape.circle),
                ),
              ),
            ),
          const Positioned(left: 304, top: 44, child: SvgArt(_moonSvg, width: 54, height: 52)),

          Positioned(
            top: 76,
            left: 20,
            right: 20,
            child: Semantics(
              liveRegion: true,
              child: Column(
                children: [
                  Text(
                    _hatched ? l.gamesHatchDone : l.gamesHatchReady,
                    textAlign: TextAlign.center,
                    style: gwText(context, 30, color: Colors.white, height: 1.15),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _hatched ? l.gamesHatchDoneSub(widget.need) : l.gamesHatchReadySub(widget.need),
                    textAlign: TextAlign.center,
                    style: gwText(context, 16, weight: FontWeight.w600, color: const Color(0xFFD0BFFF)),
                  ),
                ],
              ),
            ),
          ),

          if (_hatched)
            Positioned(
              left: 35,
              top: 190,
              child: calm
                  ? const SvgArt(_raysSvg, width: 320, height: 320)
                  : const SvgArt(_raysSvg, width: 320, height: 320)
                        .animate(onPlay: (c) => c.repeat())
                        .rotate(duration: 18.seconds)
                        .animate()
                        .scaleXY(begin: 0.4, end: 1, duration: 600.ms, curve: Curves.easeOut)
                        .fadeIn(duration: 600.ms),
            ),

          const Positioned(left: 65, top: 470, child: SvgArt(_nestSvg, width: 260, height: 90)),

          if (!_hatched) ...[
            Positioned(left: 115, top: 300, child: egg),
            Positioned(
              top: 580,
              left: 0,
              right: 0,
              child: Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
                  decoration: BoxDecoration(
                    color: gwSun,
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: _ink, width: 3),
                  ),
                  child: Text(hints[math.min(_taps, 3)], style: gwText(context, 17, color: _ink)),
                ).bob(context, by: 6),
              ),
            ),
          ] else ...[
            const Positioned(left: 115, top: 300, child: SvgArt(_shellBottomSvg, width: 160, height: 200)),
            Positioned(
              left: 115,
              top: 300,
              child: calm
                  ? const SizedBox.shrink()
                  : const SvgArt(_shellTopSvg, width: 160, height: 200).animate().custom(
                      duration: 900.ms,
                      curve: const Cubic(0.3, 0.7, 0.4, 1),
                      builder: (_, v, child) => Opacity(
                        opacity: 1 - v,
                        child: Transform.translate(
                          offset: Offset(-90 * v, -240 * v),
                          child: Transform.rotate(angle: -140 * v * math.pi / 180, child: child),
                        ),
                      ),
                    ),
            ),
            Positioned(
              left: 95,
              top: 210,
              child: Semantics(
                button: true,
                label: _c.name,
                child: GestureDetector(
                  onTap: () => GameSound.play(GameEffect.cheep),
                  child: _emerging(context, CreatureArt(_c.look, size: 200, motion: !calm, line: '#24124F')),
                ),
              ),
            ),
            if (!calm)
              for (final (x, w, h, d, colour) in feathers)
                Positioned(
                  left: x,
                  top: 0,
                  child: IgnorePointer(
                    child: SvgArt(_featherSvg(colour), width: w, height: h)
                        .animate(delay: Duration(milliseconds: d))
                        .custom(
                          duration: 2600.ms,
                          curve: Curves.easeIn,
                          builder: (_, v, child) => Opacity(
                            opacity: v >= 1 ? 0 : 1,
                            child: Transform.translate(
                              offset: Offset(30 * v, -40 + 660 * v),
                              child: Transform.rotate(angle: 260 * v * math.pi / 180, child: child),
                            ),
                          ),
                        ),
                  ),
                ),
            Positioned(left: 16, right: 16, bottom: 22, child: _card(context, l)),
          ],

          Positioned(
            top: 10,
            left: rtl ? null : 16,
            right: rtl ? 16 : null,
            child: Button3d(
              width: 44,
              height: 44,
              ink: _ink,
              label: l.gamesToIsland,
              onTap: () {
                GameSound.play(GameEffect.back);
                widget.onLeave();
              },
              child: Icon(rtl ? Icons.arrow_forward_rounded : Icons.arrow_back_rounded, color: _ink, size: 22),
            ),
          ),
          if (_toast != null)
            Positioned(left: 24, right: 24, top: 140, child: GameToast(_toast!, dark: true, onGone: () => setState(() => _toast = null))),
        ],
      ),
    );

    return ColoredBox(
      color: _night,
      child: SafeArea(
        child: Center(
          child: FittedBox(fit: BoxFit.contain, child: picture),
        ),
      ),
    );
  }

  Widget _emerging(BuildContext context, Widget creature) {
    if (context.calm) return creature;
    return creature
        .animate()
        .callback(delay: 700.ms, duration: 1.ms, callback: (_) => GameSound.play(GameEffect.cheep))
        .moveY(begin: 60, end: 0, delay: 150.ms, duration: 700.ms, curve: const Cubic(0.3, 1.6, 0.55, 1))
        .scaleXY(begin: 0.3, end: 1, delay: 150.ms, duration: 700.ms, curve: const Cubic(0.3, 1.6, 0.55, 1))
        .fadeIn(delay: 150.ms, duration: 300.ms)
        .then()
        .animate(onPlay: (c) => c.repeat(reverse: true))
        .moveY(begin: 0, end: -12, duration: 1100.ms, curve: Curves.easeInOut);
  }

  Widget _card(BuildContext context, AppLocalizations l) {
    final names = _c.names.isEmpty ? [_c.name] : _c.names;
    final card = Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: _ink, width: 4),
        boxShadow: const [BoxShadow(color: Color(0xFF120A33), offset: Offset(0, 6))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l.gamesHatchNew(_c.found, _c.total),
                      style: gwText(context, 13, weight: FontWeight.w700, color: const Color(0xFF5C4A99)),
                    ),
                    Text(_c.name, style: gwText(context, 24, color: _ink, height: 1.2)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            l.gamesHatchAsk,
            style: gwText(context, 15, weight: FontWeight.w700, color: _ink),
          ),
          const SizedBox(height: 6),
          Semantics(
            label: l.gamesHatchAsk,
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (var i = 0; i < names.length; i++)
                  Semantics(
                    inMutuallyExclusiveGroup: true,
                    checked: _name == i,
                    child: Button3d(
                      height: 44,
                      ink: _ink,
                      depth: _name == i ? 4 : 0,
                      color: _name == i ? const Color(0xFFB197FC) : Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      onTap: () {
                        Juice.play(Sound.pop);
                        setState(() => _name = i);
                      },
                      child: Text(names[i], style: gwText(context, 17, color: _ink)),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Button3d(
            height: 54,
            ink: _ink,
            color: gwSun,
            depth: 4,
            enabled: !_saving,
            onTap: _keep,
            child: _saving
                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 3, color: _ink))
                : Text(l.gamesHatchKeep, style: gwText(context, 19, color: _ink)),
          ),
        ],
      ),
    );
    if (context.calm) return card;
    return card.animate(delay: 900.ms).scaleXY(begin: 0.4, end: 1, duration: 400.ms, curve: const Cubic(0.3, 1.6, 0.55, 1)).fadeIn();
  }
}

String _eggSvg(int taps) {
  String crack(String d, int after) =>
      '<path d="$d" fill="none" stroke="#24124F" stroke-width="4" stroke-linejoin="round" stroke-linecap="round" opacity="${taps >= after ? 1 : 0}"/>';
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 200">'
      '<ellipse cx="80" cy="110" rx="62" ry="80" fill="#F3E3C3" stroke="#24124F" stroke-width="5"/>'
      '<circle cx="56" cy="70" r="10" fill="#E0C79A"/><circle cx="110" cy="140" r="14" fill="#E0C79A"/><circle cx="104" cy="58" r="6" fill="#E0C79A"/><circle cx="50" cy="150" r="7" fill="#E0C79A"/>'
      '${crack('M30 96 L44 86 L52 100 L64 90', 1)}${crack('M64 90 L76 104 L90 90 L102 102', 2)}${crack('M102 102 L114 88 L130 100 M90 90 L92 74', 3)}'
      '</svg>';
}

String _featherSvg(String colour) =>
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 34"><path d="M9 2 C18 10 16 24 9 32 C2 24 0 10 9 2 Z" fill="$colour"/><path d="M9 4 V32" stroke="#24124F" stroke-opacity=".35" stroke-width="1.5"/></svg>';

const _moonSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="304 44 54 52"><circle cx="330" cy="70" r="26" fill="#FFE8A3"/><circle cx="342" cy="62" r="24" fill="#2B1A66"/></svg>';

const _nestSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 90"><ellipse cx="130" cy="50" rx="126" ry="36" fill="#6B4423"/>'
    '<path d="M10 44 C60 70 200 70 250 44 M20 56 C80 80 180 80 240 56 M30 36 L70 60 M230 36 L190 60 M90 30 L120 62 M170 30 L140 62" stroke="#9C6B3F" stroke-width="6" stroke-linecap="round" fill="none"/>'
    '<ellipse cx="130" cy="30" rx="96" ry="18" fill="#3E2614"/></svg>';

const _raysSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-160 -160 320 320">'
    '<path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(0)"/><path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(36)"/>'
    '<path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(72)"/><path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(108)"/>'
    '<path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(144)"/><path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(180)"/>'
    '<path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(216)"/><path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(252)"/>'
    '<path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(288)"/><path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate(324)"/>'
    '</svg>';

const _shellBottomSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 200"><path d="M18 112 L32 116 L46 102 L60 116 L74 102 L88 116 L102 100 L116 116 L130 102 L142 112 C142 162 112 190 80 190 C48 190 18 162 18 112 Z" fill="#F3E3C3" stroke="#24124F" stroke-width="5" stroke-linejoin="round"/>'
    '<circle cx="110" cy="150" r="12" fill="#E0C79A"/></svg>';

const _shellTopSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 200"><path d="M18 112 C18 52 50 30 80 30 C110 30 142 52 142 112 L130 102 L116 116 L102 100 L88 116 L74 102 L60 116 L46 102 L32 116 Z" fill="#F3E3C3" stroke="#24124F" stroke-width="5" stroke-linejoin="round"/>'
    '<circle cx="56" cy="70" r="10" fill="#E0C79A"/></svg>';
