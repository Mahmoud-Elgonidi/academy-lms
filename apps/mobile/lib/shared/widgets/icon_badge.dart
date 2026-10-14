import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../../app/theme/stage.dart';
import 'lms.dart';

/// An icon on a soft square of its tone: what a row or a card is about.
class IconBadge extends StatelessWidget {
  const IconBadge(this.icon, {this.tone = Tone.brand, this.size = 40, super.key});

  final IconData icon;
  final Tone tone;
  final double size;

  @override
  Widget build(BuildContext context) {
    final (bg, ink) = tone.colours(context);
    final corner = switch (context.shape.stage) {
      Stage.teen => size * 0.22,
      Stage.middle => size * 0.34,
      _ => size * 0.3,
    };
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(corner)),
      child: Icon(icon, size: size * 0.5, color: ink),
    );
  }
}
