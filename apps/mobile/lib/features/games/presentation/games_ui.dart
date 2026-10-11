import 'package:flutter/material.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../shared/motion/motion.dart';
import 'art/art.dart';

/// A `#RRGGBB` from the catalog, or [fallback].
Color hexColor(String? hex, Color fallback) {
  if (hex == null || !RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(hex)) return fallback;
  return Color(int.parse('FF${hex.substring(1)}', radix: 16));
}

/// A round's headline by its stars.
String resultTitle(AppLocalizations l, int stars) => switch (stars) {
  <= 0 => l.gamesResult0,
  1 => l.gamesResult1,
  2 => l.gamesResult2,
  _ => l.gamesResult3,
};

/// Seconds as the race writes them ("42s"), or a dash.
String secondsLabel(AppLocalizations l, int? s) => s == null ? '—' : l.gamesSeconds(s);

/// The islands' own type: the look's heading font, navy.
TextStyle gwText(BuildContext context, double size, {FontWeight weight = FontWeight.w800, Color color = gwInk, double? height}) =>
    TextStyle(fontFamily: context.shape.headingFont, fontSize: size, fontWeight: weight, color: color, height: height);

/// A button of the islands: white (or [color]), a navy outline and a shadow
/// under it that it sinks into when pressed.
class Button3d extends StatefulWidget {
  const Button3d({
    super.key,
    required this.child,
    required this.onTap,
    this.color = Colors.white,
    this.ink = gwInk,
    this.radius = 999,
    this.depth = 3,
    this.border = 3,
    this.padding = EdgeInsets.zero,
    this.width,
    this.height,
    this.label,
    this.enabled = true,
    this.shrink = false,
  });

  final Widget child;
  final VoidCallback? onTap;
  final Color color;
  final Color ink;
  final double radius;
  final double depth;
  final double border;
  final EdgeInsetsGeometry padding;
  final double? width;
  final double? height;
  final String? label;
  final bool enabled;

  /// As wide as its words (a chip), not as wide as it may be.
  final bool shrink;

  @override
  State<Button3d> createState() => _Button3dState();
}

class _Button3dState extends State<Button3d> {
  bool _down = false;

  @override
  Widget build(BuildContext context) {
    final on = widget.enabled && widget.onTap != null;
    final sink = _down && on ? widget.depth - 1 : 0.0;
    return Semantics(
      button: true,
      enabled: on,
      label: widget.label,
      child: GestureDetector(
        onTapDown: on ? (_) => setState(() => _down = true) : null,
        onTapCancel: () => setState(() => _down = false),
        onTapUp: on ? (_) => setState(() => _down = false) : null,
        onTap: on ? widget.onTap : null,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 80),
          width: widget.width,
          height: widget.height,
          padding: widget.padding,
          transform: Matrix4.translationValues(0, sink, 0),
          decoration: BoxDecoration(
            color: widget.color,
            borderRadius: BorderRadius.circular(widget.radius),
            border: Border.all(color: widget.ink, width: widget.border),
            boxShadow: on && widget.depth > 0 ? [BoxShadow(color: widget.ink, offset: Offset(0, widget.depth - sink))] : null,
          ),
          alignment: widget.shrink ? null : Alignment.center,
          child: widget.shrink ? Center(widthFactor: 1, heightFactor: widget.height == null ? 1 : null, child: widget.child) : widget.child,
        ),
      ),
    );
  }
}

/// A short message over the world that comes in with a pop, stays a little
/// and goes ([onGone] then clears it). Its wait is the animation's own.
class GameToast extends StatelessWidget {
  const GameToast(this.text, {super.key, required this.onGone, this.dark = false});

  final String text;
  final VoidCallback onGone;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    final box = Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: dark ? const Color(0xFF24124F) : Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: dark ? null : Border.all(color: gwInk, width: 3),
        boxShadow: dark ? null : const [BoxShadow(color: gwInk, offset: Offset(0, 4))],
      ),
      child: Text(
        text,
        textAlign: TextAlign.center,
        style: gwText(context, 15.5, weight: FontWeight.w700, color: dark ? Colors.white : gwInk),
      ),
    );
    final live = Semantics(liveRegion: true, child: box);
    if (context.calm) {
      return live
          .animate(onComplete: (_) => onGone())
          .fadeOut(delay: const Duration(milliseconds: 3200), duration: const Duration(milliseconds: 1));
    }
    return live
        .animate(key: ValueKey(text), onComplete: (_) => onGone())
        .scaleXY(begin: 0.4, end: 1, duration: const Duration(milliseconds: 450), curve: const Cubic(0.3, 1.6, 0.55, 1))
        .fadeIn(duration: const Duration(milliseconds: 200))
        .then(delay: const Duration(milliseconds: 2600))
        .fadeOut(duration: const Duration(milliseconds: 300));
  }
}

/// The stars count: a star and the number counting up.
class StarsPill extends StatelessWidget {
  const StarsPill(this.stars, {super.key});

  final int stars;

  @override
  Widget build(BuildContext context) => Semantics(
    label: context.l10n.gamesStars(stars),
    excludeSemantics: true,
    child: Container(
      height: 34,
      padding: const EdgeInsetsDirectional.fromSTEB(8, 0, 10, 0),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: gwInk, width: 3),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SvgArt(starSvg, width: 18, height: 18),
          const SizedBox(width: 4),
          CountUp(stars, style: gwText(context, 16)),
        ],
      ),
    ),
  );
}

/// Three stars, [stars] of them lit (drawn, so every phone shows them).
class StarRow extends StatelessWidget {
  const StarRow(this.stars, {super.key, this.size = 16, this.on = const Color(0xFFFCC419), this.off = const Color(0xFFDEE2E6)});

  final int stars;
  final double size;
  final Color on;
  final Color off;

  @override
  Widget build(BuildContext context) => Semantics(
    label: context.l10n.gamesStars(stars),
    excludeSemantics: true,
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [for (var i = 0; i < 3; i++) Icon(Icons.star_rounded, size: size, color: i < stars ? on : off)],
    ),
  );
}

/// A small egg drawn in the next creature's colours.
String eggSvg({String belly = '#F3E3C3', String body = '#E0C79A', String line = '#13294B'}) =>
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 22"><ellipse cx="9" cy="12" rx="8" ry="10" fill="$belly" stroke="$line" stroke-width="1.6"/>'
    '<path d="M3 12 L6 9 L9 13 L12 9 L15 12" fill="none" stroke="$line" stroke-width="1.4"/>'
    '<circle cx="6" cy="7" r="1.6" fill="$body"/><circle cx="12" cy="16" r="2" fill="$body"/></svg>';

/// Which picture a game's tile shows: its own for the world's first games,
/// else by the kinds it plays; a lock while it cannot be played.
String gameArt(String key, List<String> kinds, {bool playable = true}) {
  if (!playable) return 'lock';
  return switch (key) {
    'bird-gates' => 'gates',
    'bird-balloons' => 'balloons',
    'bird-twins' => 'cards',
    'turtle-bridge' => 'bridge',
    _ => kinds.contains('pair') ? 'cards' : (kinds.contains('choice') ? 'balloons' : 'gates'),
  };
}

/// A game's picture on its coloured tile (the islands' own art).
class GameTileArt extends StatelessWidget {
  const GameTileArt(this.art, {super.key, this.size = 60});

  /// What [gameArt] picked.
  final String art;
  final double size;

  /// The card colour behind a game of this art.
  static Color cardColor(String art) => (_paint[art] ?? _paint['gates']!).$1;

  @override
  Widget build(BuildContext context) {
    final tile = (_paint[art] ?? _paint['gates']!).$2;
    final lock = art == 'lock';
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: tile,
        borderRadius: BorderRadius.circular(size * 0.3),
        border: Border.all(color: gwInk, width: 3),
      ),
      clipBehavior: Clip.antiAlias,
      alignment: Alignment.center,
      child: SvgArt(_tileSvg[art] ?? _tileSvg['gates']!, width: lock ? size * 0.43 : size * 0.8, height: lock ? size * 0.43 : size * 0.73),
    );
  }
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
