import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../../app/theme/tokens.dart';

/// The heading over a group of cards, with what to do with all of them at
/// its end ("كل الحصص").
class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {this.action, this.onAction, super.key});

  final String title;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsetsDirectional.only(start: Space.xs, top: Space.s, bottom: Space.xs),
    child: Row(
      children: [
        Expanded(
          child: Semantics(header: true, child: Text(title, style: context.type.section)),
        ),
        if (action != null && onAction != null)
          TextButton(
            onPressed: onAction,
            style: TextButton.styleFrom(
              foregroundColor: context.colors.brandDeep,
              padding: const EdgeInsets.symmetric(horizontal: Space.s),
            ),
            child: Row(mainAxisSize: MainAxisSize.min, children: [Text(action!), const Icon(Icons.chevron_right_rounded, size: 18)]),
          ),
      ],
    ),
  );
}
