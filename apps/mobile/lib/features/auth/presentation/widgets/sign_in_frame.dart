import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';

/// The frame of every sign-in step: a back button (after the first), the
/// title and lead, the step's content, and its main button at the bottom.
class SignInFrame extends StatelessWidget {
  const SignInFrame({
    required this.title,
    required this.children,
    this.lead,
    this.bottom,
    this.showBack = true,
    this.mark = false,
    super.key,
  });

  final String title;
  final Widget? lead;
  final List<Widget> children;
  final Widget? bottom;
  final bool showBack;

  /// The app's mark above the title (the first step).
  final bool mark;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 480),
          child: Column(
            children: [
              if (showBack)
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                    child: IconButton.outlined(
                      tooltip: context.l10n.back,
                      onPressed: () => context.canPop() ? context.pop() : context.go('/welcome'),
                      icon: const BackButtonIcon(),
                    ),
                  ),
                ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(22, 20, 22, 20),
                  children: [
                    if (mark) ...[const _Mark(), const SizedBox(height: 28)],
                    Text(title, style: context.text.headlineMedium),
                    if (lead != null) ...[const SizedBox(height: 10), lead!],
                    const SizedBox(height: 24),
                    ...children,
                  ],
                ),
              ),
              if (bottom != null) Padding(padding: const EdgeInsets.fromLTRB(22, 8, 22, 18), child: bottom),
            ],
          ),
        ),
      ),
    ),
  );
}

class _Mark extends StatelessWidget {
  const _Mark();

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 48,
        height: 48,
        decoration: BoxDecoration(color: context.colors.brand, borderRadius: BorderRadius.circular(15)),
        child: const Icon(Icons.auto_stories_rounded, color: Colors.white, size: 26),
      ),
      const SizedBox(width: 12),
      Text(context.l10n.appName, style: context.text.titleLarge),
    ],
  );
}

/// The main button of a step, with a spinner while it works.
class BusyButton extends StatelessWidget {
  const BusyButton({required this.label, required this.busy, required this.onPressed, super.key});

  final String label;
  final bool busy;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: double.infinity,
    child: FilledButton(
      onPressed: busy ? null : onPressed,
      child: busy
          ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
          : Text(label),
    ),
  );
}
