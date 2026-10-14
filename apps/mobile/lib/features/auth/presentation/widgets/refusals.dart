import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/tokens.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../shared/widgets/actions.dart';
import 'sign_in_frame.dart';

/// The academy would not open an account for a reason the person can act
/// on: a step of its own that says what to do, instead of a line in red.
/// Null for any other problem.
Widget? refusalStep(
  BuildContext context,
  ApiProblem? problem, {
  required VoidCallback onRetry,
  required VoidCallback onRestart,
  bool busy = false,
}) => switch (problem?.type) {
  'guardian-consent-required' => _ConsentNeeded(onRetry: onRetry, onRestart: onRestart, busy: busy),
  'two-factor-setup-required' => _TwoFactorSetup(onRestart: onRestart),
  _ => null,
};

/// A child (or a student under the academy's age) whose guardian has not
/// agreed yet: three steps to get in.
class _ConsentNeeded extends StatelessWidget {
  const _ConsentNeeded({required this.onRetry, required this.onRestart, required this.busy});

  final VoidCallback onRetry;
  final VoidCallback onRestart;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final steps = [l.consentNeededStep1, l.consentNeededStep2(l.navMore, l.privacyTitle), l.consentNeededStep3];
    return SignInFrame(
      title: l.consentNeededTitle,
      badge: Icons.family_restroom_rounded,
      pinBottom: true,
      lead: Text(l.consentNeededLead, style: TextStyle(fontSize: 16, height: 1.65, color: c.muted)),
      bottom: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            height: 56,
            child: PrimaryAction(l.signInAgain, busy: busy, onPressed: onRetry),
          ),
          TextButton(
            style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48), foregroundColor: c.brandDeep),
            onPressed: onRestart,
            child: Text(l.startAgain, style: const TextStyle(fontWeight: FontWeight.w600)),
          ),
        ],
      ),
      children: [
        for (final (i, step) in steps.indexed)
          Padding(
            padding: const EdgeInsets.only(bottom: Space.m),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 32,
                  height: 32,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(color: c.brandSoft, shape: BoxShape.circle),
                  child: Text(
                    '${i + 1}',
                    style: TextStyle(fontWeight: FontWeight.w700, color: c.brandDeep),
                  ),
                ),
                const Gap(Space.m),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(step, style: TextStyle(fontSize: 15, height: 1.55, color: c.ink)),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// An account whose role must have two-step sign-in, without it yet: it is
/// turned on from the academy's website first.
class _TwoFactorSetup extends StatelessWidget {
  const _TwoFactorSetup({required this.onRestart});

  final VoidCallback onRestart;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return SignInFrame(
      title: l.twoFactorSetupTitle,
      badge: Icons.phonelink_lock_rounded,
      pinBottom: true,
      lead: Text(l.twoFactorSetupLead, style: TextStyle(fontSize: 16, height: 1.65, color: context.colors.muted)),
      bottom: SizedBox(height: 56, child: PrimaryAction(l.startAgain, onPressed: onRestart)),
      children: const [],
    );
  }
}
