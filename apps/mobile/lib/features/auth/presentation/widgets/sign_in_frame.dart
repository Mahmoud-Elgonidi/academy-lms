import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/open_link.dart';
import '../../data/auth_repository.dart';

export '../../../../shared/widgets/actions.dart' show BusyButton;

/// The frame of every sign-in step: a back button (after the first), the
/// title and lead, the step's content, its main button at the bottom, and
/// the privacy notice and terms under it (the stores ask for them before
/// signing in).
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
              if (bottom != null) Padding(padding: const EdgeInsets.fromLTRB(22, 8, 22, 0), child: bottom),
              const Padding(padding: EdgeInsets.fromLTRB(22, 0, 22, 6), child: _LegalLinks()),
            ],
          ),
        ),
      ),
    ),
  );
}

/// The platform's privacy notice and terms, in the browser: before signing in no academy is chosen yet.
class _LegalLinks extends StatelessWidget {
  const _LegalLinks();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final auth = context.read<AuthRepository>();
    Widget link(String page, String label) => TextButton(
      key: Key('legal-$page'),
      style: TextButton.styleFrom(
        foregroundColor: context.colors.muted,
        textStyle: context.text.bodySmall,
        visualDensity: VisualDensity.compact,
      ),
      onPressed: () => openLink(context, auth.legalPage(page, context.locale)),
      child: Text(label),
    );
    return Wrap(
      alignment: WrapAlignment.center,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        link('privacy', l.myDataPrivacy),
        Text('·', style: context.text.bodySmall),
        link('terms', l.myDataTerms),
      ],
    );
  }
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
