import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';

/// A white card with the stage's corners and a hairline border.
class LmsCard extends StatelessWidget {
  const LmsCard({required this.child, this.padding = const EdgeInsets.all(16), this.onTap, this.color, super.key});

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final radius = context.shape.cardRadius;
    return Material(
      color: color ?? context.colors.card,
      shape: RoundedRectangleBorder(
        borderRadius: radius,
        side: BorderSide(color: context.colors.line),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}

enum Tone { brand, attention, danger, info, neutral, reward }

extension ToneColors on Tone {
  (Color bg, Color ink) colours(BuildContext context) {
    final c = context.colors;
    return switch (this) {
      Tone.brand => (c.brandSoft, c.brandDeep),
      Tone.attention => (c.attentionSoft, c.attentionInk),
      Tone.danger => (c.dangerSoft, c.dangerInk),
      Tone.info => (c.infoSoft, c.infoInk),
      Tone.neutral => (c.chip, c.muted),
      Tone.reward => (c.rewardSoft, const Color(0xFF7A4B00)),
    };
  }
}

/// A small rounded label: a status, a count.
class Pill extends StatelessWidget {
  const Pill(this.label, {this.tone = Tone.neutral, this.icon, super.key});

  final String label;
  final Tone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final (bg, ink) = tone.colours(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 14, color: ink), const SizedBox(width: 4)],
          Flexible(
            child: Text(
              label,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: ink),
            ),
          ),
        ],
      ),
    );
  }
}

/// A coloured note across the screen: a holiday, a clock change, a warning.
class Note extends StatelessWidget {
  const Note(this.text, {this.tone = Tone.info, this.icon = Icons.info_outline_rounded, this.action, super.key});

  final String text;
  final Tone tone;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final (bg, ink) = tone.colours(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(context.shape.card - 2)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Icon(icon, size: 18, color: ink),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(text, style: TextStyle(color: ink, fontSize: 13.5, height: 1.6)),
              ),
            ],
          ),
          if (action != null) ...[const SizedBox(height: 10), action!],
        ],
      ),
    );
  }
}

/// A square with a name's first letter, in a colour (an academy, a person).
class InitialAvatar extends StatelessWidget {
  const InitialAvatar(this.name, {this.colour, this.size = 44, super.key});

  final String name;
  final Color? colour;
  final double size;

  @override
  Widget build(BuildContext context) {
    final letter = name.trim().isEmpty ? '?' : name.trim().characters.first;
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: colour ?? context.colors.brand,
        borderRadius: BorderRadius.circular(context.shape.stage.name == 'kids' ? size / 2 : size * 0.32),
      ),
      child: Text(
        letter,
        style: TextStyle(fontFamily: context.shape.headingFont, color: Colors.white, fontWeight: FontWeight.w700, fontSize: size * 0.42),
      ),
    );
  }
}

/// A small heading over a group of cards.
class SectionLabel extends StatelessWidget {
  const SectionLabel(this.text, {this.trailing, super.key});

  final String text;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsetsDirectional.only(start: 4, end: 4, top: 6, bottom: 8),
    child: Row(
      children: [
        Expanded(
          child: Text(text, style: context.text.labelMedium?.copyWith(fontSize: 13, fontWeight: FontWeight.w700)),
        ),
        ?trailing,
      ],
    ),
  );
}

/// Nothing to show, said plainly.
class EmptyNote extends StatelessWidget {
  const EmptyNote(this.text, {this.icon = Icons.event_available_outlined, super.key});

  final String text;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 24),
    child: Column(
      children: [
        Icon(icon, size: 36, color: context.colors.faint),
        const SizedBox(height: 10),
        Text(
          text,
          textAlign: TextAlign.center,
          style: context.text.bodyMedium?.copyWith(color: context.colors.muted),
        ),
      ],
    ),
  );
}

/// A bar that fills to [value] (0..1).
class ProgressBar extends StatelessWidget {
  const ProgressBar(this.value, {this.colour, this.height = 8, super.key});

  final double value;
  final Color? colour;
  final double height;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(height),
    child: TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: value.clamp(0, 1)),
      duration: MediaQuery.disableAnimationsOf(context) ? Duration.zero : const Duration(milliseconds: 700),
      curve: Curves.easeOutCubic,
      builder: (context, v, _) =>
          LinearProgressIndicator(value: v, minHeight: height, color: colour ?? context.colors.brand, backgroundColor: context.colors.chip),
    ),
  );
}

/// Page padding with room for the tab bar.
const pagePadding = EdgeInsets.fromLTRB(18, 8, 18, 28);

void showSnack(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}
