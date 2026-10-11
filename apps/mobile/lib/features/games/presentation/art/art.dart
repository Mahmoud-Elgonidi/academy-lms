import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../../../shared/motion/motion.dart';
import '../../domain/games.dart';
import 'creature_art.dart';

/// The navy of the islands' outlines, their buttons and words (the games'
/// own art, the same for every academy, like the games themselves).
const gwInk = Color(0xFF13294B);

/// The sun yellow of the islands' buttons and stars.
const gwSun = Color(0xFFFFD43B);

/// The sea of the map.
const gwSea = Color(0xFF2F9BE3);

/// An SVG drawn at a size (the art is the web's, `shared::games.*`).
class SvgArt extends StatelessWidget {
  const SvgArt(this.svg, {super.key, required this.width, required this.height});

  final String svg;
  final double width;
  final double height;

  @override
  Widget build(BuildContext context) =>
      SvgPicture.string(svg, width: width, height: height, fit: BoxFit.contain, excludeFromSemantics: true, clipBehavior: Clip.none);
}

/// A creature: its look drawn, a shadow when not hatched yet, and alive
/// (a soft bob) when [motion].
class CreatureArt extends StatelessWidget {
  const CreatureArt(this.look, {super.key, this.size = 84, this.shadow = false, this.motion = false, this.line = '#13294B'});

  final Look look;

  /// Its width; the height follows.
  final double size;
  final bool shadow;
  final bool motion;
  final String line;

  @override
  Widget build(BuildContext context) {
    final art = SvgArt(
      creatureSvg(look, shadow: shadow, line: line),
      width: size,
      height: size * 112 / 128,
    );
    return motion ? art.bob(context, by: size / 30) : art;
  }
}

/// Greys an island under the clouds (the web's `saturate(.35) brightness(1.05)`).
const lockedFilter = ColorFilter.matrix(<double>[
  0.48845 * 1.05, 0.46475 * 1.05, 0.0468 * 1.05, 0, 0, //
  0.13845 * 1.05, 0.81475 * 1.05, 0.0468 * 1.05, 0, 0, //
  0.13845 * 1.05, 0.46475 * 1.05, 0.3968 * 1.05, 0, 0, //
  0, 0, 0, 1, 0,
]);

/// A white cloud (the map's and the scenes').
const cloudSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 110 44"><path d="M14 40 C2 40 2 24 14 24 C14 10 34 6 40 18 C46 4 72 4 74 20 C86 14 100 22 96 32 C106 34 104 40 96 40 Z" fill="#FFFFFF"/></svg>';

/// The star of the stars count.
const starSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#FFD43B" stroke="#13294B" stroke-width="2" stroke-linejoin="round"/></svg>';

/// A puff that rises and fades, again and again (a volcano's smoke).
class Puff extends StatelessWidget {
  const Puff({super.key, required this.size, required this.color, this.delay = Duration.zero});

  final double size;
  final Color color;
  final Duration delay;

  @override
  Widget build(BuildContext context) {
    final dot = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
    if (context.calm) return dot;
    return dot
        .animate(onPlay: (c) => c.repeat(), delay: delay)
        .moveY(begin: 0, end: -60, duration: 3600.ms, curve: Curves.easeOut)
        .scaleXY(begin: 0.5, end: 1.6, duration: 3600.ms, curve: Curves.easeOut)
        .fadeOut(begin: 0.9, duration: 3600.ms, curve: Curves.easeOut);
  }
}

/// A "z" that floats up from a sleeping turtle.
class Zzz extends StatelessWidget {
  const Zzz({super.key, required this.size, this.color = Colors.white, this.delay = Duration.zero});

  final double size;
  final Color color;
  final Duration delay;

  @override
  Widget build(BuildContext context) {
    final z = Text(
      'z',
      style: TextStyle(color: color, fontSize: size, fontWeight: FontWeight.w800, height: 1),
    );
    if (context.calm) return z;
    return z
        .animate(onPlay: (c) => c.repeat(), delay: delay)
        .move(begin: Offset.zero, end: const Offset(14, -34), duration: 3000.ms, curve: Curves.easeOut)
        .scaleXY(begin: 0.6, end: 1.2, duration: 3000.ms)
        .custom(
          duration: 3000.ms,
          builder: (_, v, child) => Opacity(opacity: v < 0.3 ? v / 0.3 : (1 - v) / 0.7, child: child),
        );
  }
}

/// Swings side to side and back (a penguin sliding, a turtle swimming).
class Sway extends StatelessWidget {
  const Sway({super.key, required this.child, this.by = 24, this.turn = 0.14, this.period = const Duration(seconds: 4), this.flip = false});

  final Widget child;
  final double by;
  final double turn;
  final Duration period;

  /// Faces the way it goes (a swimmer).
  final bool flip;

  @override
  Widget build(BuildContext context) {
    if (context.calm) return child;
    return child
        .animate(onPlay: (c) => c.repeat())
        .custom(
          duration: period,
          builder: (_, v, c) {
            final s = math.sin(v * 2 * math.pi);
            final going = math.cos(v * 2 * math.pi) > 0;
            Widget out = Transform.translate(
              offset: Offset(s * by, 0),
              child: Transform.rotate(angle: s * turn, child: c),
            );
            if (flip) out = Transform.flip(flipX: !going, child: out);
            return out;
          },
        );
  }
}

/// Hops on the spot (a monkey on its island).
class Hop extends StatelessWidget {
  const Hop({super.key, required this.child, this.height = 18});

  final Widget child;
  final double height;

  @override
  Widget build(BuildContext context) {
    if (context.calm) return child;
    return child
        .animate(onPlay: (c) => c.repeat())
        .custom(
          duration: 1100.ms,
          builder: (_, v, c) {
            final up = math.sin(v * math.pi);
            final squash = v < 0.1 || v > 0.9 ? 1.1 : 1.0;
            return Transform.translate(
              offset: Offset(0, -height * up),
              child: Transform.scale(scaleX: squash, scaleY: 2 - squash, alignment: Alignment.bottomCenter, child: c),
            );
          },
        );
  }
}

/// Twinkles: fades and grows in a slow loop.
class Twinkle extends StatelessWidget {
  const Twinkle({super.key, required this.child, this.delay = Duration.zero});

  final Widget child;
  final Duration delay;

  @override
  Widget build(BuildContext context) {
    if (context.calm) return child;
    return child
        .animate(onPlay: (c) => c.repeat(reverse: true), delay: delay)
        .fade(begin: 0.15, end: 1, duration: 1300.ms)
        .scaleXY(begin: 0.6, end: 1.1, duration: 1300.ms);
  }
}

/// A cloud drifting across (the map's and the scenes').
class Drift extends StatelessWidget {
  const Drift({
    super.key,
    required this.child,
    this.from = -200,
    this.to = 600,
    this.period = const Duration(seconds: 60),
    this.delay = Duration.zero,
  });

  final Widget child;
  final double from;
  final double to;
  final Duration period;
  final Duration delay;

  @override
  Widget build(BuildContext context) {
    if (context.calm) return Transform.translate(offset: Offset(from / 4 + 40, 0), child: child);
    return child.animate(onPlay: (c) => c.repeat(), delay: delay).moveX(begin: from, end: to, duration: period);
  }
}
