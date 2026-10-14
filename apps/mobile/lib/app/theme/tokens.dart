import 'package:flutter/material.dart';

import 'app_theme.dart';
import 'stage.dart';

/// The spaces between things, so every screen keeps the same rhythm:
/// 4 inside a chip, 8 between lines, 12 inside a card, 16 between cards,
/// 24 between sections, 32 before something new.
abstract final class Space {
  static const double xs = 4;
  static const double s = 8;
  static const double m = 12;
  static const double l = 16;
  static const double xl = 24;
  static const double xxl = 32;
}

/// An empty square of [size]: a gap in a row or a column alike.
class Gap extends StatelessWidget {
  const Gap(this.size, {super.key});

  final double size;

  @override
  Widget build(BuildContext context) => SizedBox(width: size, height: size);
}

/// What each kind of text is, over the stage's own fonts: a screen's title,
/// a section's heading, a card's title, body, captions, labels, numbers.
/// Weights follow the look: heavier for middle school, lighter for teens.
@immutable
class TypeRoles {
  const TypeRoles({
    required this.display,
    required this.title,
    required this.section,
    required this.cardTitle,
    required this.body,
    required this.caption,
    required this.label,
    required this.number,
  });

  factory TypeRoles.of(BuildContext context) {
    final c = context.colors;
    final s = context.shape;
    final body = Theme.of(context).textTheme.bodyMedium?.fontFamily;
    final (heavy, strong) = switch (s.stage) {
      Stage.kids || Stage.middle => (FontWeight.w800, FontWeight.w700),
      Stage.teen => (FontWeight.w700, FontWeight.w600),
      Stage.classic => (FontWeight.w700, FontWeight.w700),
    };
    TextStyle heading(double size) => TextStyle(fontFamily: s.headingFont, fontSize: size, fontWeight: heavy, color: c.ink, height: 1.25);
    TextStyle text(double size, {FontWeight? weight, Color? colour, double height = 1.5}) =>
        TextStyle(fontFamily: body, fontSize: size, fontWeight: weight, color: colour ?? c.ink, height: height);
    return TypeRoles(
      display: heading(24),
      title: heading(20),
      section: text(13.5, weight: strong, colour: c.muted, height: 1.3),
      cardTitle: text(16, weight: strong, height: 1.35),
      body: text(15),
      caption: text(13, colour: c.muted, height: 1.45),
      label: text(12, weight: FontWeight.w600, colour: c.muted, height: 1.3),
      number: heading(24).copyWith(fontFeatures: const [FontFeature.tabularFigures()], height: 1.1),
    );
  }

  /// The title on top of a main tab.
  final TextStyle display;

  /// A sheet's or a pushed screen's title.
  final TextStyle title;

  /// The heading over a group of cards.
  final TextStyle section;
  final TextStyle cardTitle;
  final TextStyle body;
  final TextStyle caption;
  final TextStyle label;

  /// Counts and amounts: figures of one width, so they do not jump as they count.
  final TextStyle number;
}

extension TypeContext on BuildContext {
  TypeRoles get type => TypeRoles.of(this);

  /// The kids' look, which keeps its own drawing and is left as it is.
  bool get isKids => shape.stage == Stage.kids;
}
