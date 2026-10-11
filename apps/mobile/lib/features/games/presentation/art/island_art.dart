import 'package:flutter/material.dart';

import '../../../../shared/motion/motion.dart';
import 'art.dart';

/// An island on the map, drawn as the approved design draws it (the web's
/// `shared::games.island`): its key picks the art, an island added later in
/// the studio gets the plain one in its own colours, and a locked island is
/// greyed under a cloud. Its box is [size].
class IslandArt extends StatelessWidget {
  const IslandArt({super.key, required this.islandKey, this.colors = const {}, this.emoji = '', this.locked = false});

  final String islandKey;
  final Map<String, String> colors;
  final String emoji;
  final bool locked;

  /// The box each island's art takes on the map.
  static Size size(String key) => switch (key) {
    'volcano' => const Size(170, 150),
    'penguins' => const Size(180, 140),
    'monkeys' => const Size(190, 170),
    'turtles' => const Size(190, 130),
    'birds' => const Size(200, 190),
    _ => const Size(180, 140),
  };

  @override
  Widget build(BuildContext context) {
    final box = size(islandKey);
    Widget main = SvgArt(_svg(islandKey, colors), width: box.width, height: box.height);
    if (locked) main = ColorFiltered(colorFilter: lockedFilter, child: main);
    final (Offset at, double width, double opacity) cloud = switch (islandKey) {
      'volcano' => (const Offset(0, 78), 170, .92),
      'penguins' => (const Offset(10, 78), 160, .85),
      'monkeys' => (const Offset(20, 100), 150, .8),
      'turtles' => (const Offset(20, 70), 150, .85),
      'birds' => (const Offset(25, 110), 150, .85),
      _ => (const Offset(15, 76), 150, .85),
    };
    return SizedBox(
      width: box.width,
      height: box.height,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          main,
          ..._extras(locked),
          if (locked)
            Positioned(
              left: cloud.$1.dx,
              top: cloud.$1.dy,
              child: Opacity(
                opacity: cloud.$3,
                child: SvgArt(cloudSvg, width: cloud.$2, height: cloud.$2 * 44 / 110),
              ),
            ),
        ],
      ),
    );
  }

  List<Widget> _extras(bool locked) => switch (islandKey) {
    'volcano' => [
      const Positioned(left: 74, top: 8, child: Puff(size: 22, color: Color(0xFFCED4DA))),
      Positioned(
        left: 80,
        top: 6,
        child: Puff(size: 18, color: const Color(0xFFDEE2E6), delay: 1200.ms),
      ),
      Positioned(
        left: 70,
        top: 10,
        child: Puff(size: 20, color: const Color(0xFFCED4DA), delay: 2400.ms),
      ),
    ],
    'penguins' => [
      const Positioned(
        left: 96,
        top: 66,
        child: Sway(
          child: SvgArt(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 44 56"><ellipse cx="22" cy="32" rx="16" ry="20" fill="#1F2A44"/><ellipse cx="22" cy="36" rx="10" ry="14" fill="#FFFFFF"/><circle cx="17" cy="20" r="2.6" fill="#FFFFFF"/><circle cx="27" cy="20" r="2.6" fill="#FFFFFF"/><path d="M19 25 L25 25 L22 29 Z" fill="#FF922B"/></svg>',
            width: 26,
            height: 32,
          ),
        ),
      ),
    ],
    'monkeys' => [
      const Positioned(
        left: 30,
        top: 6,
        child: Hop(
          child: SvgArt(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 64"><path d="M44 44 C58 44 58 30 50 28" fill="none" stroke="#8C5A2B" stroke-width="4" stroke-linecap="round"/><ellipse cx="30" cy="46" rx="14" ry="13" fill="#A9733E"/><circle cx="14" cy="22" r="6" fill="#A9733E"/><circle cx="46" cy="22" r="6" fill="#A9733E"/><circle cx="30" cy="24" r="15" fill="#A9733E"/><ellipse cx="30" cy="28" rx="10" ry="8" fill="#F3D7AE"/><circle cx="25" cy="22" r="2.5" fill="#13294B"/><circle cx="35" cy="22" r="2.5" fill="#13294B"/></svg>',
            width: 30,
            height: 32,
          ),
        ),
      ),
    ],
    'turtles' => [
      const Positioned(left: 172, top: 56, child: Zzz(size: 13)),
      Positioned(left: 176, top: 52, child: Zzz(size: 10, delay: 1000.ms)),
    ],
    'birds' => const [],
    _ => [
      Positioned(
        left: 0,
        right: 0,
        top: 30,
        child: Text(emoji, textAlign: TextAlign.center, style: const TextStyle(fontSize: 30, height: 1)),
      ),
    ],
  };
}

String _hex(String? c, String d) => c != null && RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(c) ? c : d;

String _svg(String key, Map<String, String> colors) {
  const open = '<svg xmlns="http://www.w3.org/2000/svg"';
  return switch (key) {
    'volcano' =>
      '$open viewBox="-85 -110 170 150">'
          '<ellipse cx="0" cy="26" rx="80" ry="18" fill="#BFE6FF"/>'
          '<ellipse cx="0" cy="22" rx="72" ry="16" fill="#6B5B4D"/>'
          '<path d="M-66 18 L-22 -74 L22 -74 L66 18 Z" fill="#5C4B5E"/>'
          '<path d="M-66 18 L-22 -74 L-8 -74 L-34 18 Z" fill="#7A6680"/>'
          '<path d="M-22 -74 L22 -74 L17 -84 L-17 -84 Z" fill="#3D3140"/>'
          '<path d="M-15 -82 C-8 -90 8 -90 15 -82 L12 -75 L-12 -75 Z" fill="#FF6B3D"/>'
          '<path d="M-3 -75 C-8 -55 2 -42 -6 -22" stroke="#FF922B" stroke-width="5" fill="none" stroke-linecap="round"/>'
          '</svg>',
    'penguins' =>
      '$open viewBox="-90 -95 180 140">'
          '<ellipse cx="0" cy="26" rx="88" ry="20" fill="#BFE6FF"/>'
          '<path d="M-76 18 L-60 -6 L-30 -12 L-10 -40 L8 -20 L30 -54 L52 -18 L74 -8 L80 18 Z" fill="#A5D8FF"/>'
          '<path d="M-72 12 L-58 -4 L-30 -9 L-10 -36 L6 -17 L30 -50 L50 -15 L72 -5 L76 12 Z" fill="#E7F5FF"/>'
          '<path d="M30 -50 L50 -15 L36 -10 Z M-10 -36 L6 -17 L-4 -12 Z" fill="#D0EBFF"/>'
          '<path d="M-52 8 A18 18 0 0 1 -16 8 Z" fill="#FFFFFF"/>'
          '<path d="M-38 8 A6 7 0 0 1 -30 8 Z" fill="#4DABF7"/>'
          '</svg>',
    'monkeys' =>
      '$open viewBox="-95 -120 190 170">'
          '<ellipse cx="0" cy="28" rx="90" ry="20" fill="#BFE6FF"/>'
          '<ellipse cx="0" cy="22" rx="84" ry="20" fill="#E9B44C"/>'
          '<ellipse cx="0" cy="17" rx="82" ry="18" fill="#FFE08A"/>'
          '<path d="M-72 12 C-70 -30 -40 -58 -4 -58 C34 -58 70 -34 72 10 C40 22 -40 22 -72 12 Z" fill="#2B8A3E"/>'
          '<path d="M-56 -8 C-48 -36 -24 -50 0 -50 C-20 -40 -36 -26 -44 -4 Z" fill="#40C057"/>'
          '<path d="M-30 -50 C-34 -74 -36 -90 -44 -104" fill="none" stroke="#7A4A22" stroke-width="7" stroke-linecap="round"/>'
          '<path d="M-44 -104 C-60 -116 -78 -110 -84 -98 C-70 -102 -56 -102 -44 -104 Z M-44 -104 C-30 -118 -12 -114 -6 -102 C-20 -104 -32 -104 -44 -104 Z M-44 -104 C-52 -94 -60 -82 -62 -72 C-54 -84 -48 -94 -44 -104 Z" fill="#2F9E44"/>'
          '<circle cx="-46" cy="-98" r="4" fill="#8C5A2B"/><circle cx="-40" cy="-97" r="4" fill="#8C5A2B"/>'
          '<path d="M36 -46 C40 -66 42 -80 48 -92" fill="none" stroke="#7A4A22" stroke-width="6" stroke-linecap="round"/>'
          '<path d="M48 -92 C34 -104 18 -100 12 -90 C24 -92 36 -92 48 -92 Z M48 -92 C60 -104 76 -100 82 -88 C70 -90 58 -90 48 -92 Z" fill="#2F9E44"/>'
          '<path d="M10 -20 C16 -28 26 -28 30 -20 C24 -24 16 -24 10 -20 Z" fill="#FFD43B"/>'
          '</svg>',
    'turtles' =>
      '$open viewBox="-95 -80 190 130">'
          '<ellipse cx="0" cy="26" rx="92" ry="24" fill="#BFE6FF"/>'
          '<ellipse cx="0" cy="22" rx="84" ry="21" fill="#E9B44C"/>'
          '<ellipse cx="0" cy="17" rx="82" ry="19" fill="#FFE08A"/>'
          '<path d="M-60 14 L-74 30 L-46 22 Z M36 16 L52 32 L26 24 Z" fill="#51CF66"/>'
          '<path d="M54 0 C70 -8 86 -4 88 6 C86 16 70 18 56 12 Z" fill="#8CE99A"/>'
          '<path d="M74 2 Q78 -2 82 2" stroke="#13294B" stroke-width="2.5" fill="none" stroke-linecap="round"/>'
          '<path d="M-64 12 C-64 -40 -30 -62 0 -62 C34 -62 62 -40 62 12 Z" fill="#2B8A3E"/>'
          '<path d="M-56 6 C-56 -36 -26 -54 0 -54 C28 -54 54 -36 54 6 Z" fill="#37B24D"/>'
          '<path d="M-14 -42 L0 -50 L14 -42 L14 -26 L0 -18 L-14 -26 Z M-44 -16 L-30 -24 L-18 -16 L-18 -2 L-30 4 L-44 -2 Z M18 -16 L30 -24 L44 -16 L44 -2 L30 4 L18 -2 Z" fill="#51CF66"/>'
          '<path d="M-2 -56 C0 -66 -2 -72 -6 -78" stroke="#7A4A22" stroke-width="4" fill="none" stroke-linecap="round"/>'
          '<path d="M-6 -78 C-16 -84 -26 -80 -28 -72 C-20 -76 -12 -76 -6 -78 Z M-6 -78 C2 -86 12 -82 14 -74 C6 -76 0 -76 -6 -78 Z" fill="#2F9E44"/>'
          '</svg>',
    'birds' =>
      '$open viewBox="-100 -140 200 190">'
          '<ellipse cx="0" cy="22" rx="96" ry="28" fill="#BFE6FF"/>'
          '<ellipse cx="0" cy="16" rx="86" ry="24" fill="#E9B44C"/>'
          '<ellipse cx="0" cy="10" rx="84" ry="22" fill="#FFE08A"/>'
          '<path d="M-70 8 C-66 -36 -26 -58 4 -58 C40 -58 70 -32 72 6 C40 18 -40 20 -70 8 Z" fill="#2F9E44"/>'
          '<path d="M-66 2 C-62 -38 -24 -56 4 -56 C38 -56 66 -32 68 0 C36 10 -38 12 -66 2 Z" fill="#51CF66"/>'
          '<path d="M-48 -18 C-40 -40 -16 -50 6 -50 C-12 -42 -28 -32 -36 -12 Z" fill="#8CE99A"/>'
          '<path d="M22 -48 C28 -80 26 -100 18 -122" fill="none" stroke="#7A4A22" stroke-width="9" stroke-linecap="round"/>'
          '<path d="M23 -52 C28 -80 26 -98 19 -118" fill="none" stroke="#A86B35" stroke-width="3" stroke-linecap="round"/>'
          '<path d="M18 -122 C0 -136 -26 -130 -36 -114 C-18 -120 0 -120 18 -122 Z M18 -122 C36 -138 60 -132 68 -116 C52 -122 36 -122 18 -122 Z" fill="#2F9E44"/>'
          '<path d="M18 -122 C8 -112 -6 -96 -10 -84 C2 -98 10 -108 18 -122 Z M18 -122 C30 -110 42 -96 46 -84 C34 -98 26 -108 18 -122 Z" fill="#40C057"/>'
          '<path d="M4 -122 Q18 -112 32 -122 Q30 -114 18 -112 Q6 -114 4 -122 Z" fill="#B07A3B"/>'
          '<ellipse cx="18" cy="-127" rx="6" ry="8" fill="#F3E3C3"/>'
          '<circle cx="-40" cy="-16" r="4" fill="#FF8FA3"/><circle cx="-28" cy="-28" r="3" fill="#FFD43B"/><circle cx="44" cy="-10" r="3.5" fill="#FF8FA3"/>'
          '<ellipse cx="-76" cy="14" rx="8" ry="5" fill="#9AA5B1"/><ellipse cx="72" cy="18" rx="6" ry="4" fill="#9AA5B1"/>'
          '</svg>',
    _ =>
      '$open viewBox="-90 -95 180 140">'
          '<ellipse cx="0" cy="26" rx="88" ry="22" fill="#BFE6FF"/>'
          '<ellipse cx="0" cy="20" rx="80" ry="19" fill="#E9B44C"/>'
          '<ellipse cx="0" cy="15" rx="78" ry="17" fill="#FFE08A"/>'
          '<path d="M-62 12 C-60 -32 -30 -56 0 -56 C32 -56 62 -32 62 10 C34 20 -34 20 -62 12 Z" fill="${_hex(colors['land'], '#51CF66')}"/>'
          '<path d="M-48 -6 C-42 -32 -20 -46 2 -46 C-16 -38 -30 -26 -36 -4 Z" fill="#FFFFFF" opacity=".25"/>'
          '<circle cx="-22" cy="-14" r="4" fill="${_hex(colors['accent'], '#FFD43B')}"/><circle cx="26" cy="-6" r="3.5" fill="${_hex(colors['accent'], '#FFD43B')}"/>'
          '</svg>',
  };
}
