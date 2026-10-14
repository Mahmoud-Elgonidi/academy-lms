import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import 'lms.dart';

/// The top of a main tab: an optional line above, the title, and the
/// buttons that are always there (the bell, the account). [leading] stands
/// before them (the academy's logo, on a home only); [bottom] sits under it
/// (the children switcher).
class AppHeader extends StatelessWidget {
  const AppHeader({required this.title, this.eyebrow, this.leading, this.actions = const [], this.bottom, super.key});

  final String title;
  final String? eyebrow;
  final Widget? leading;
  final List<Widget> actions;
  final Widget? bottom;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 56),
        child: Row(
          children: [
            if (leading != null) ...[leading!, const Gap(Space.m)],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (eyebrow != null) Text(eyebrow!, style: context.type.caption, maxLines: 1, overflow: TextOverflow.ellipsis),
                  Semantics(
                    header: true,
                    child: Text(title, style: context.type.display, maxLines: 2, overflow: TextOverflow.ellipsis),
                  ),
                ],
              ),
            ),
            for (final (i, a) in actions.indexed) ...[if (i > 0) const Gap(Space.s), a],
          ],
        ),
      ),
      if (bottom != null) ...[const Gap(Space.m), bottom!],
    ],
  );
}

/// A square button in a header: an icon, an optional count on it, and a
/// spoken name ("الإشعارات، 2 جديد").
class HeaderButton extends StatelessWidget {
  const HeaderButton({required this.icon, required this.label, required this.onPressed, this.count = 0, this.wrap, super.key});

  final IconData icon;
  final String label;
  final VoidCallback? onPressed;
  final int count;

  /// Moves the icon (the bell rings when something new comes in).
  final Widget Function(Widget icon)? wrap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Semantics(
      button: true,
      label: label,
      excludeSemantics: true,
      child: Material(
        color: c.card,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(context.shape.field),
          side: BorderSide(color: c.line),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onPressed,
          child: SizedBox.square(
            dimension: 48,
            child: Center(
              child: Badge(
                isLabelVisible: count > 0,
                label: Text('$count'),
                child: (wrap ?? (i) => i)(Icon(icon, size: 22, color: c.ink)),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// The person's initial as a button to their account, said as such.
class HeaderAvatar extends StatelessWidget {
  const HeaderAvatar({required this.name, required this.label, required this.onPressed, super.key});

  final String name;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    excludeSemantics: true,
    child: InkWell(
      onTap: onPressed,
      customBorder: const CircleBorder(),
      child: SizedBox.square(
        dimension: 48,
        child: Center(child: InitialAvatar(name, size: 40, colour: context.colors.brandDeep)),
      ),
    ),
  );
}
