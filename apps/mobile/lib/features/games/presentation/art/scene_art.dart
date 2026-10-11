import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../shared/motion/motion.dart';
import '../../domain/games.dart';
import 'art.dart';

/// An opened island's scene, 390 × 330 (the web's `shared::games.scene`,
/// the approved design): its sky, land and life, with the creatures the
/// learner hatched there living in it ([residents], up to three; the
/// island's first creature waits there before any hatch).
class SceneArt extends StatelessWidget {
  const SceneArt({super.key, required this.islandKey, required this.residents, this.colors = const {}});

  final String islandKey;
  final List<Look> residents;
  final Map<String, String> colors;

  static const width = 390.0;
  static const height = 330.0;

  /// Where the residents live in each scene: left, top, width, and how they move.
  static List<(double, double, double, _Move)> _spots(String key) => switch (key) {
    'birds' => const [(160, 70, 96, _Move.fig8), (96, 168, 70, _Move.perch), (318, 160, 50, _Move.peek)],
    'turtles' => const [(226, 226, 62, _Move.perch), (296, 262, 50, _Move.perch), (24, 150, 66, _Move.swim)],
    'monkeys' => const [(150, 150, 80, _Move.hop), (40, 196, 66, _Move.perch), (290, 210, 60, _Move.perch)],
    'penguins' => const [(160, 196, 74, _Move.slide), (60, 222, 60, _Move.perch), (290, 214, 64, _Move.perch)],
    'volcano' => const [(60, 220, 70, _Move.perch), (270, 230, 66, _Move.perch), (170, 120, 80, _Move.fig8)],
    _ => const [(150, 170, 84, _Move.perch), (50, 210, 64, _Move.perch), (280, 210, 64, _Move.perch)],
  };

  @override
  Widget build(BuildContext context) {
    final spots = _spots(islandKey);
    return SizedBox(
      width: width,
      height: height,
      child: Stack(
        clipBehavior: Clip.hardEdge,
        children: [
          SvgArt(_svg(islandKey, colors), width: width, height: height),
          ..._extras(islandKey),
          for (final (i, look) in residents.take(3).indexed)
            Positioned(
              left: spots[i].$1,
              top: spots[i].$2,
              child: _moving(context, spots[i].$4, CreatureArt(look, size: spots[i].$3)),
            ),
        ],
      ),
    );
  }

  static Widget _moving(BuildContext context, _Move move, Widget child) => switch (move) {
    _Move.perch => child.bob(context, by: 2),
    _Move.swim => Sway(by: 50, turn: 0, period: const Duration(seconds: 7), flip: true, child: child),
    _Move.slide => Sway(child: child),
    _Move.hop => Hop(child: child),
    _Move.peek =>
      context.calm
          ? child
          : child
                .animate(onPlay: (c) => c.repeat())
                .custom(
                  duration: 5.seconds,
                  builder: (_, v, c) => Transform.translate(offset: Offset(v < .3 || v > .7 ? 26 : 0, 0), child: c),
                ),
    _Move.fig8 =>
      context.calm
          ? child
          : child
                .animate(onPlay: (c) => c.repeat())
                .custom(
                  duration: 9.seconds,
                  builder: (_, v, c) {
                    final t = v * 2 * math.pi;
                    return Transform.translate(
                      offset: Offset(-70 * math.sin(t), -22 * math.sin(2 * t).abs() + 6),
                      child: Transform.rotate(angle: 0.08 * math.cos(t), child: c),
                    );
                  },
                ),
  };

  static Widget _sun() => const Positioned(
    left: 14,
    top: 14,
    child: SvgArt(
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-50 -50 100 100"><path d="M0 -50 L6 -34 L-6 -34 Z M0 50 L6 34 L-6 34 Z M-50 0 L-34 6 L-34 -6 Z M50 0 L34 6 L34 -6 Z M-35 -35 L-21 -27 L-27 -21 Z M35 35 L21 27 L27 21 Z M35 -35 L27 -21 L21 -27 Z M-35 35 L-27 21 L-21 27 Z" fill="#FFE066"/><circle r="26" fill="#FFE066"/></svg>',
      width: 100,
      height: 100,
    ),
  );

  static Widget _cloud() => const Positioned(
    left: 0,
    top: 40,
    child: Drift(child: Opacity(opacity: .9, child: SvgArt(cloudSvg, width: 110, height: 44))),
  );

  static List<Widget> _extras(String key) => switch (key) {
    'birds' => [
      _sun(),
      _cloud(),
      Positioned(
        left: 293,
        top: 118,
        child: Builder(
          builder: (context) => const SvgArt(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 22"><ellipse cx="9" cy="12" rx="8" ry="10" fill="#F3E3C3" stroke="#13294B" stroke-width="2"/><circle cx="6" cy="9" r="2" fill="#E0C79A"/></svg>',
            width: 18,
            height: 22,
          ).wiggle(context),
        ),
      ),
    ],
    'turtles' => [
      const Positioned(
        left: 0,
        top: 140,
        child: SvgArt(
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 390 100"><path d="M10 20 Q30 10 50 20 T90 20 M130 50 Q150 40 170 50 T210 50 M20 80 Q40 70 60 80 T100 80 M250 30 Q270 20 290 30" stroke="#99E9F2" stroke-width="3" fill="none" stroke-linecap="round" opacity=".7"/></svg>',
          width: 390,
          height: 100,
        ),
      ),
      const Positioned(left: 370, top: 196, child: Zzz(size: 16, color: gwInk)),
      Positioned(
        left: 374,
        top: 190,
        child: Zzz(size: 12, color: gwInk, delay: 1.seconds),
      ),
      for (final (x, y, letter, shell, plate, delay) in const [
        (110.0, 160.0, 'أ', '#2F9E44', '#40C057', 0),
        (150.0, 216.0, 'ب', '#0CA678', '#20C997', 1),
        (70.0, 262.0, 'ت', '#2B8A3E', '#37B24D', 2),
      ])
        Positioned(
          left: x,
          top: y,
          child: Sway(
            by: 50,
            turn: 0,
            period: Duration(milliseconds: 7000 + 900 * delay),
            flip: true,
            child: SizedBox(
              width: 58,
              height: 39,
              child: Stack(
                children: [
                  SvgArt(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 90 60"><path d="M30 46 L20 58 L36 52 Z" fill="#69DB7C" stroke="#13294B" stroke-width="2"/><ellipse cx="18" cy="34" rx="12" ry="10" fill="#8CE99A" stroke="#13294B" stroke-width="2.5"/><circle cx="14" cy="31" r="2.6" fill="#13294B"/><ellipse cx="48" cy="34" rx="28" ry="20" fill="$shell" stroke="#13294B" stroke-width="2.5"/><path d="M36 26 L48 20 L60 26 L60 40 L48 46 L36 40 Z" fill="$plate"/></svg>',
                    width: 58,
                    height: 39,
                  ),
                  Positioned(
                    left: 23,
                    top: 13,
                    width: 16,
                    child: Text(
                      letter,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800, height: 1),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
    ],
    'monkeys' => [_sun()],
    'penguins' => [
      for (final (x, y, d) in const [
        (30.0, 20.0, 0),
        (120.0, 60.0, 1),
        (210.0, 10.0, 2),
        (290.0, 80.0, 0),
        (70.0, 110.0, 2),
        (350.0, 30.0, 1),
      ])
        Positioned(
          left: x,
          top: y,
          child: _Snow(delay: Duration(milliseconds: 1500 * d)),
        ),
    ],
    'volcano' => [
      const Positioned(left: 176, top: 20, child: Puff(size: 30, color: Color(0xFFCED4DA))),
      Positioned(
        left: 186,
        top: 16,
        child: Puff(size: 24, color: const Color(0xFFDEE2E6), delay: 1200.ms),
      ),
      Positioned(
        left: 168,
        top: 22,
        child: Puff(size: 26, color: const Color(0xFFCED4DA), delay: 2400.ms),
      ),
    ],
    _ => [_sun(), _cloud()],
  };
}

enum _Move { perch, swim, slide, hop, peek, fig8 }

/// A snowflake falling across the penguins' scene.
class _Snow extends StatelessWidget {
  const _Snow({this.delay = Duration.zero});

  final Duration delay;

  @override
  Widget build(BuildContext context) {
    final flake = Container(
      width: 6,
      height: 6,
      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
    );
    if (context.calm) return flake;
    return flake
        .animate(onPlay: (c) => c.repeat(), delay: delay)
        .move(begin: const Offset(0, -20), end: const Offset(20, 220), duration: 4500.ms)
        .custom(
          duration: 4500.ms,
          builder: (_, v, c) => Opacity(opacity: v < .1 ? v * 10 : 1 - v, child: c),
        );
  }
}

String _hex(String? c, String d) => c != null && RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(c) ? c : d;

String _svg(String key, Map<String, String> colors) {
  const open = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 390 330">';
  return switch (key) {
    'birds' =>
      '$open<rect width="390" height="330" fill="#8ED8FF"/>'
          '<rect y="214" width="390" height="116" fill="#2F9BE3"/>'
          '<path d="M240 216 C252 200 276 198 290 216 Z M320 216 C326 208 338 207 344 216 Z" fill="#5BB0E8"/>'
          '<path d="M-20 330 L-20 236 C40 196 120 176 200 182 C280 188 350 214 410 240 L410 330 Z" fill="#E9B44C"/>'
          '<path d="M-20 330 L-20 250 C50 206 130 188 210 194 C290 200 350 226 410 252 L410 330 Z" fill="#40C057"/>'
          '<path d="M30 238 C80 208 140 196 200 198 C150 206 100 220 60 246 Z" fill="#8CE99A"/>'
          '<path d="M40 286 C40 266 72 262 78 280 C88 266 108 272 104 290 Z M300 300 C304 284 328 282 334 296 C342 288 356 294 352 306 Z" fill="#2F9E44"/>'
          '<circle cx="150" cy="250" r="5" fill="#FF8FA3"/><circle cx="170" cy="262" r="4" fill="#FFD43B"/><circle cx="250" cy="250" r="5" fill="#FFFFFF"/><circle cx="90" cy="252" r="4" fill="#FFD43B"/>'
          '<path d="M318 312 C312 260 316 200 302 136" fill="none" stroke="#7A4A22" stroke-width="13" stroke-linecap="round"/>'
          '<path d="M320 300 C315 256 318 204 306 146" fill="none" stroke="#A86B35" stroke-width="4" stroke-linecap="round"/>'
          '<path d="M302 136 C276 116 236 122 222 146 C250 136 276 134 302 136 Z M302 136 C330 112 370 118 384 144 C356 134 330 134 302 136 Z" fill="#2F9E44"/>'
          '<path d="M302 136 C288 152 270 176 266 196 C282 172 294 156 302 136 Z M302 136 C318 154 334 176 338 196 C324 174 312 156 302 136 Z" fill="#40C057"/>'
          '<path d="M280 134 Q302 150 324 134 Q320 146 302 148 Q284 146 280 134 Z" fill="#B07A3B"/>'
          '</svg>',
    'turtles' =>
      '$open<rect width="390" height="330" fill="#9BE3FF"/>'
          '<circle cx="322" cy="56" r="26" fill="#FFE066"/>'
          '<rect y="118" width="390" height="212" fill="#22B8CF"/>'
          '<rect y="118" width="390" height="10" fill="#66D9E8"/>'
          '<path d="M190 330 L190 238 C230 200 310 190 410 200 L410 330 Z" fill="#FFE08A"/>'
          '<path d="M190 250 C232 214 310 204 410 210 L410 218 C310 214 240 226 196 262 Z" fill="#E9B44C"/>'
          '<ellipse cx="60" cy="300" rx="34" ry="14" fill="#5C7080"/><ellipse cx="54" cy="294" rx="24" ry="10" fill="#7D90A0"/>'
          '<path d="M222 304 L232 290 L242 304 Z" fill="#FF8FA3"/><circle cx="276" cy="296" r="6" fill="#FFFFFF"/><circle cx="276" cy="296" r="2.5" fill="#FFC9C9"/>'
          '<path d="M300 236 L284 250 L306 246 Z M370 236 L388 250 L366 246 Z" fill="#51CF66"/>'
          '<path d="M378 220 C392 212 404 216 406 226 C404 236 392 238 380 232 Z" fill="#8CE99A"/>'
          '<path d="M392 222 Q396 218 400 222" stroke="#13294B" stroke-width="2.5" fill="none" stroke-linecap="round"/>'
          '<path d="M286 238 C286 196 312 178 336 178 C362 178 384 196 384 238 Z" fill="#2B8A3E"/>'
          '<path d="M294 232 C294 200 316 186 336 186 C358 186 376 200 376 232 Z" fill="#37B24D"/>'
          '<path d="M324 202 L336 196 L348 202 L348 214 L336 220 L324 214 Z" fill="#51CF66"/>'
          '</svg>',
    'monkeys' =>
      '$open<rect width="390" height="330" fill="#B8F0A0"/>'
          '<path d="M-10 200 C60 150 140 160 200 180 C260 150 340 150 400 190 L400 330 L-10 330 Z" fill="#69DB7C"/>'
          '<path d="M-10 240 C70 210 150 220 210 236 C280 214 340 214 400 236 L400 330 L-10 330 Z" fill="#40C057"/>'
          '<path d="M-10 290 C80 270 300 270 400 290 L400 330 L-10 330 Z" fill="#2F9E44"/>'
          '<path d="M40 330 C44 250 36 170 52 90" fill="none" stroke="#7A4A22" stroke-width="13" stroke-linecap="round"/>'
          '<path d="M52 90 C30 70 -4 76 -14 98 C14 88 32 88 52 90 Z M52 90 C76 64 114 72 124 96 C98 86 74 86 52 90 Z M52 90 C40 104 26 128 22 146 C36 124 46 108 52 90 Z" fill="#2F9E44"/>'
          '<path d="M340 330 C336 260 346 190 332 120" fill="none" stroke="#7A4A22" stroke-width="12" stroke-linecap="round"/>'
          '<path d="M332 120 C306 100 270 108 260 132 C288 122 310 120 332 120 Z M332 120 C360 98 394 106 404 130 C378 120 354 120 332 120 Z" fill="#2B8A3E"/>'
          '<path d="M120 0 C126 40 116 70 128 110 M250 0 C244 30 256 60 246 96" fill="none" stroke="#2F9E44" stroke-width="5" stroke-linecap="round"/>'
          '<path d="M118 108 C110 116 112 128 122 128 C118 122 118 114 124 110 Z M130 106 C138 114 136 126 128 128 C132 120 130 112 126 108 Z" fill="#FFD43B" stroke="#13294B" stroke-width="2"/>'
          '<path d="M244 94 C236 102 238 114 248 114 C244 108 244 100 250 96 Z" fill="#FFD43B" stroke="#13294B" stroke-width="2"/>'
          '<circle cx="160" cy="300" r="5" fill="#FF8FA3"/><circle cx="220" cy="306" r="4" fill="#FFD43B"/><circle cx="96" cy="296" r="4" fill="#FFFFFF"/>'
          '</svg>',
    'penguins' =>
      '$open<rect width="390" height="330" fill="#D6F1FF"/>'
          '<rect y="170" width="390" height="160" fill="#5AA9E6"/>'
          '<path d="M0 330 L0 250 C60 226 140 220 200 230 C270 220 340 224 390 244 L390 330 Z" fill="#E7F5FF"/>'
          '<path d="M0 260 C70 238 150 234 210 242 C150 246 80 252 0 270 Z" fill="#FFFFFF"/>'
          '<path d="M210 200 L250 120 L280 160 L300 96 L340 170 L360 150 L390 200 Z" fill="#A5D8FF"/>'
          '<path d="M216 198 L250 126 L276 164 L300 104 L336 172 L356 156 L384 198 Z" fill="#E7F5FF"/>'
          '<path d="M40 210 L90 210 L84 196 L46 196 Z M120 186 L150 186 L146 178 L124 178 Z" fill="#FFFFFF" stroke="#A5D8FF" stroke-width="2"/>'
          '<circle cx="322" cy="50" r="24" fill="#FFF3BF"/>'
          '</svg>',
    'volcano' =>
      '$open<rect width="390" height="330" fill="#FFCF9E"/>'
          '<rect y="210" width="390" height="120" fill="#6B2737"/>'
          '<path d="M40 220 L150 60 L230 60 L340 220 Z" fill="#5C4B5E"/>'
          '<path d="M40 220 L150 60 L176 60 L110 220 Z" fill="#7A6680"/>'
          '<path d="M150 60 L230 60 L222 46 L158 46 Z" fill="#3D3140"/>'
          '<path d="M160 48 C176 34 204 34 220 48 L214 58 L166 58 Z" fill="#FF6B3D"/>'
          '<path d="M186 60 C176 100 198 130 182 180" stroke="#FF922B" stroke-width="8" fill="none" stroke-linecap="round"/>'
          '<path d="M-10 330 L-10 250 C60 226 140 230 200 244 C270 228 340 230 400 250 L400 330 Z" fill="#3D2C2E"/>'
          '<circle cx="90" cy="290" r="5" fill="#FF922B"/><circle cx="300" cy="296" r="4" fill="#FF6B3D"/>'
          '</svg>',
    _ =>
      '$open<rect width="390" height="330" fill="${_hex(colors['sky'], '#8ED8FF')}"/>'
          '<rect y="200" width="390" height="130" fill="${_hex(colors['sea'], '#2F9BE3')}"/>'
          '<path d="M-20 330 L-20 250 C50 206 130 188 210 194 C290 200 350 226 410 252 L410 330 Z" fill="${_hex(colors['land'], '#40C057')}"/>'
          '</svg>',
  };
}
