import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/tokens.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/app_mark.dart';
import '../../../../shared/widgets/open_link.dart';
import '../../../onboarding/presentation/language_switch.dart';
import '../../data/auth_repository.dart';
import '../bloc/sign_in_cubit.dart';

export '../../../../shared/widgets/actions.dart' show BusyButton;

/// The frame of every sign-in step: on top the app's mark with the language
/// switch (the first step) or a back button (the others); the title and
/// lead, the step's content and its buttons coming in one after another;
/// and at the foot the platform's terms and privacy notice (the stores ask
/// for them before signing in). With [pinBottom] the step's buttons stay at
/// the foot too. While the keyboard is up everything scrolls together, so a
/// small phone still reaches every button.
///
/// Leaving a step, by its back button or the phone's, clears what went
/// wrong on it: a problem never shows on the step after.
class SignInFrame extends StatelessWidget {
  const SignInFrame({
    required this.title,
    required this.children,
    this.lead,
    this.bottom,
    this.showBack = true,
    this.mark = false,
    this.badge,
    this.centered = false,
    this.pinBottom = false,
    super.key,
  });

  final String title;
  final Widget? lead;
  final List<Widget> children;
  final Widget? bottom;
  final bool showBack;

  /// The app's mark and the language switch on top (the first step, signed out).
  final bool mark;

  /// A picture over the title (what the step is about: a code on its way, a key).
  final IconData? badge;

  /// The title, lead and content in the middle (a step about one thing, like the code).
  final bool centered;

  /// The buttons at the foot of the screen rather than under the content (a list to choose from).
  final bool pinBottom;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final typing = MediaQuery.viewInsetsOf(context).bottom > 0;
    final pinned = pinBottom && !typing;
    final parts = <Widget>[
      if (badge != null)
        Align(
          alignment: centered ? Alignment.center : AlignmentDirectional.centerStart,
          child: Padding(
            padding: const EdgeInsets.only(bottom: 18),
            child: Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(22)),
              child: Icon(badge, size: 34, color: c.brandDeep),
            ),
          ),
        ),
      Semantics(
        header: true,
        child: Text(
          title,
          textAlign: centered ? TextAlign.center : TextAlign.start,
          style: TextStyle(
            fontFamily: context.shape.headingFont,
            fontSize: mark ? 30 : 28,
            height: 1.3,
            fontWeight: FontWeight.w700,
            color: c.ink,
          ),
        ),
      ),
      if (lead != null)
        Padding(
          padding: const EdgeInsets.only(top: Space.s),
          child: lead,
        ),
      const Gap(Space.xl),
      ...children,
      if (bottom != null && !pinned)
        Padding(
          padding: const EdgeInsets.only(top: Space.s),
          child: bottom,
        ),
      if (typing)
        const Padding(
          padding: EdgeInsets.only(top: Space.l),
          child: _LegalLinks(),
        ),
    ];
    return PopScope(
      onPopInvokedWithResult: (popped, _) {
        if (popped) context.read<SignInCubit>().clearProblem();
      },
      child: Scaffold(
        body: SafeArea(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 480),
              child: Column(
                children: [
                  _TopBar(mark: mark, showBack: showBack),
                  Expanded(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(20, 18, 20, Space.l),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [for (final (i, part) in parts.indexed) part is Gap ? part : part.appear(context, i)],
                      ),
                    ),
                  ),
                  if (pinned && bottom != null) Padding(padding: const EdgeInsets.fromLTRB(20, Space.s, 20, 0), child: bottom),
                  if (!typing) const Padding(padding: EdgeInsets.fromLTRB(20, Space.xs, 20, Space.xs), child: _LegalLinks()),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.mark, required this.showBack});

  final bool mark;
  final bool showBack;

  @override
  Widget build(BuildContext context) {
    if (mark) {
      return Padding(
        padding: const EdgeInsetsDirectional.fromSTEB(16, 12, 12, 0),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(color: context.colors.brand, borderRadius: BorderRadius.circular(12)),
              child: const Center(child: AppMark(size: 30)),
            ).popIn(context),
            const Gap(10),
            Expanded(
              child: Text(
                context.l10n.appName,
                style: TextStyle(
                  fontFamily: context.shape.headingFont,
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: context.colors.ink,
                ),
              ),
            ),
            const LanguageSwitch(),
          ],
        ),
      );
    }
    if (!showBack) return const SizedBox(height: 12);
    return Align(
      alignment: AlignmentDirectional.centerStart,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
        child: IconButton.outlined(
          tooltip: context.l10n.back,
          onPressed: () => context.canPop() ? context.pop() : context.go('/welcome'),
          icon: const BackButtonIcon(),
        ),
      ),
    );
  }
}

extension on Widget {
  Widget popIn(BuildContext context) =>
      context.calm ? this : animate().scaleXY(begin: 0.3, end: 1, duration: const Duration(milliseconds: 550), curve: Curves.easeOutBack);
}

/// "By continuing you agree to the terms of use and the privacy notice": the
/// platform's own, in the browser (before signing in no academy is chosen yet).
class _LegalLinks extends StatelessWidget {
  const _LegalLinks();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final auth = context.read<AuthRepository>();
    final quiet = TextStyle(fontSize: 13, color: c.muted, height: 1.4);
    Widget link(String page, String label) => TextButton(
      key: Key('legal-$page'),
      style: TextButton.styleFrom(
        foregroundColor: c.brandDeep,
        minimumSize: const Size(0, 44),
        padding: const EdgeInsets.symmetric(horizontal: 2),
        tapTargetSize: MaterialTapTargetSize.padded,
      ),
      onPressed: () => openLink(context, auth.legalPage(page, context.locale)),
      child: Text(
        label,
        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, decoration: TextDecoration.underline),
      ),
    );
    return Wrap(
      alignment: WrapAlignment.center,
      crossAxisAlignment: WrapCrossAlignment.center,
      spacing: 3,
      children: [
        Text(l.legalAgree, style: quiet),
        link('terms', l.myDataTerms),
        // "and" stays with the link after it when the line breaks.
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(l.legalAnd, style: quiet),
            link('privacy', l.myDataPrivacy),
          ],
        ),
      ],
    );
  }
}
