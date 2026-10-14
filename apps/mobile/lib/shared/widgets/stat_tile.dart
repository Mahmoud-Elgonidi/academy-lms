import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import 'lms.dart';

/// A number and what it means ("2 · حصص في الرصيد"). It looks pressable,
/// with an arrow, only when it opens something.
class StatTile extends StatelessWidget {
  const StatTile({required this.label, required this.value, this.caption, this.icon, this.tone = Tone.brand, this.onTap, super.key});

  final String label;

  /// The number as shown, with its unit ("758 ر.س").
  final Widget value;
  final String? caption;
  final IconData? icon;
  final Tone tone;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final body = Padding(
      padding: const EdgeInsets.all(Space.m + 2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              if (icon != null) ...[IconBadge(icon!, tone: tone, size: 28), const Gap(Space.s)],
              Expanded(
                child: Text(label, style: context.type.label, maxLines: 2, overflow: TextOverflow.ellipsis),
              ),
              if (onTap != null) Icon(Icons.chevron_right_rounded, color: c.faint, size: 18),
            ],
          ),
          const Gap(Space.s),
          DefaultTextStyle.merge(style: context.type.number, child: value),
          if (caption != null) ...[const Gap(Space.xs), Text(caption!, style: context.type.caption)],
        ],
      ),
    );
    final shape = RoundedRectangleBorder(
      borderRadius: context.shape.cardRadius,
      side: BorderSide(color: c.line),
    );
    return Material(
      color: c.card,
      shape: shape,
      clipBehavior: Clip.antiAlias,
      child: onTap == null ? body : InkWell(onTap: onTap, child: body),
    );
  }
}
