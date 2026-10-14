import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../../core/l10n/l10n.dart';
import '../motion/motion.dart';

/// A screen's one main action: full width, the brand's colour, a spinner
/// while it works (and no second tap meanwhile).
class PrimaryAction extends StatelessWidget {
  const PrimaryAction(this.label, {required this.onPressed, this.busy = false, this.icon, this.danger = false, super.key});

  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final IconData? icon;

  /// Something that cannot be taken back (cancelling, removing).
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final style = danger ? FilledButton.styleFrom(backgroundColor: context.colors.danger, foregroundColor: Colors.white) : null;
    final child = busy
        ? SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(strokeWidth: 2.5, color: danger ? Colors.white : Theme.of(context).colorScheme.onPrimary),
          )
        : Text(label);
    final pressed = busy ? null : PressScale.withPress(context, onPressed);
    return PressScale(
      child: SizedBox(
        width: double.infinity,
        child: icon == null || busy
            ? FilledButton(style: style, onPressed: pressed, child: child)
            : FilledButton.icon(style: style, onPressed: pressed, icon: Icon(icon), label: child),
      ),
    );
  }
}

/// The other way forward, next to or under a [PrimaryAction].
class SecondaryAction extends StatelessWidget {
  const SecondaryAction(this.label, {required this.onPressed, this.icon, super.key});

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: double.infinity,
    child: icon == null
        ? OutlinedButton(onPressed: onPressed, child: Text(label))
        : OutlinedButton.icon(onPressed: onPressed, icon: Icon(icon), label: Text(label)),
  );
}

/// The main button of a step, with a spinner while it works.
class BusyButton extends StatelessWidget {
  const BusyButton({required this.label, required this.busy, required this.onPressed, super.key});

  final String label;
  final bool busy;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => PrimaryAction(label, busy: busy, onPressed: onPressed);
}

/// Asks before something that cannot be taken back, in red, over the whole
/// app (not under the tabs). True only when the person confirmed.
Future<bool> confirmDestructive(BuildContext context, {required String title, String? body, required String confirm, String? keep}) async {
  final yes = await showDialog<bool>(
    context: context,
    useRootNavigator: true,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: body == null ? null : Text(body),
      actions: [
        TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: Text(keep ?? context.l10n.notNow)),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: ctx.colors.danger, foregroundColor: Colors.white, minimumSize: const Size(64, 44)),
          onPressed: () => Navigator.of(ctx).pop(true),
          child: Text(confirm),
        ),
      ],
    ),
  );
  return yes ?? false;
}
