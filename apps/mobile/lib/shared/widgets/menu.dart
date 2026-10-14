import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import 'lms.dart';

/// Rows that belong together on one card, under an optional heading.
class MenuGroup extends StatelessWidget {
  const MenuGroup({required this.children, this.title, super.key});

  final String? title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (title != null) SectionHeader(title!),
      LmsCard(
        padding: EdgeInsets.zero,
        child: Column(
          children: [
            for (final (i, row) in children.indexed) ...[if (i > 0) const Divider(height: 1, indent: Space.l, endIndent: Space.l), row],
          ],
        ),
      ),
    ],
  );
}

/// One row of a menu: an icon, what it is, an optional line under it, a
/// value or a count at its end, and the arrow only when it opens something.
class MenuRow extends StatelessWidget {
  const MenuRow({
    required this.label,
    this.icon,
    this.subtitle,
    this.value,
    this.count = 0,
    this.onTap,
    this.tone = Tone.brand,
    this.danger = false,
    super.key,
  });

  final String label;
  final IconData? icon;
  final String? subtitle;
  final String? value;
  final int count;
  final VoidCallback? onTap;
  final Tone tone;

  /// Signing out, removing: in red, without an arrow.
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final row = ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 56),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.s),
        child: Row(
          children: [
            if (icon != null) ...[
              danger ? Icon(icon, color: c.danger, size: 22) : IconBadge(icon!, tone: tone, size: 36),
              const Gap(Space.m),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    label,
                    style: context.type.body.copyWith(color: danger ? c.danger : null, fontWeight: FontWeight.w500),
                  ),
                  if (subtitle != null) Text(subtitle!, style: context.type.caption),
                ],
              ),
            ),
            if (value != null) ...[const Gap(Space.s), Text(value!, style: context.type.caption)],
            if (count > 0) ...[
              const Gap(Space.s),
              Container(
                constraints: const BoxConstraints(minWidth: 22),
                height: 22,
                padding: const EdgeInsets.symmetric(horizontal: 7),
                alignment: Alignment.center,
                decoration: BoxDecoration(color: c.brand, borderRadius: BorderRadius.circular(11)),
                child: Text(
                  '$count',
                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700),
                ),
              ),
            ],
            if (onTap != null && !danger) ...[const Gap(Space.xs), Icon(Icons.chevron_right_rounded, color: c.faint, size: 22)],
          ],
        ),
      ),
    );
    if (onTap == null) return row;
    return InkWell(onTap: onTap, child: row);
  }
}
