import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../../app/theme/tokens.dart';

/// The heading over a group of cards, with what to do with all of them at
/// its end ("كل الحصص"), or a quiet word about them ([note]: "يُفتح الجمعة").
class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {this.action, this.onAction, this.note, this.actionKey, super.key});

  final String title;
  final String? action;
  final VoidCallback? onAction;
  final String? note;
  final Key? actionKey;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Padding(
      padding: const EdgeInsetsDirectional.only(start: Space.xs, top: Space.l, bottom: Space.s),
      child: Row(
        children: [
          Expanded(
            child: Semantics(header: true, child: Text(title, style: context.type.cardTitle.copyWith(fontSize: 17))),
          ),
          if (action != null && onAction != null)
            TextButton(
              key: actionKey,
              onPressed: onAction,
              style: TextButton.styleFrom(
                foregroundColor: c.brandDeep,
                minimumSize: const Size(48, 48),
                padding: const EdgeInsets.symmetric(horizontal: Space.s),
              ),
              child: Text(
                action!,
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: c.brandDeep),
              ),
            )
          else if (note != null)
            Text(note!, style: context.type.caption),
        ],
      ),
    );
  }
}
