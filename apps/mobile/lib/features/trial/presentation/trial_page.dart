import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../app/theme/app_theme.dart';
import '../../../app/theme/branding.dart';
import '../../../app/theme/stage.dart';
import '../../../core/auth/session_cubit.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/prefs/app_prefs.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../data/trial_repository.dart';
import 'steps/academy_step.dart';
import 'steps/code_step.dart';
import 'steps/contact_step.dart';
import 'steps/done_step.dart';
import 'steps/subject_step.dart';
import 'steps/time_step.dart';
import 'steps/who_step.dart';
import 'steps/wishes_step.dart';
import 'trial_cubit.dart';
import 'widgets.dart';

/// A free trial lesson, before signing in (docs/architecture/02, section
/// 27): one calm step at a time under a bar that fills as it goes, in the
/// academy's own colours once it is known. The phone's back goes one step
/// back (nothing typed is lost); from the first step it leaves.
class TrialPage extends StatelessWidget {
  const TrialPage({this.base = '/welcome', this.academy, super.key});

  /// Where the sign-in starts (/welcome, or /add for another account).
  final String base;

  /// The academy's code, when it is known already (the account open on this phone).
  final String? academy;

  @override
  Widget build(BuildContext context) {
    final prefs = AppPrefs(SharedPreferencesAsync());
    return RepositoryProvider.value(
      value: TrialPlace(base),
      child: BlocProvider(
        create: (c) => TrialCubit(
          c.read<TrialRepository>(),
          timezone: c.read<SessionCubit>().state.timezone,
          academy: academy,
          recall: prefs.trialAcademy,
          remember: prefs.setTrialAcademy,
        )..start(),
        child: _TrialFrame(base: base),
      ),
    );
  }
}

class _TrialFrame extends StatelessWidget {
  const _TrialFrame({required this.base});

  final String base;

  void _back(BuildContext context) {
    if (context.read<TrialCubit>().back()) return;
    context.canPop() ? context.pop() : context.go(base);
  }

  @override
  Widget build(BuildContext context) => BlocBuilder<TrialCubit, TrialState>(
    buildWhen: (a, b) => a.branding != b.branding,
    builder: (context, state) {
      final frame = BlocBuilder<TrialCubit, TrialState>(
        buildWhen: (a, b) => a.step != b.step || a.page != b.page || a.steps.length != b.steps.length,
        builder: (context, state) => PopScope(
          canPop: state.step == TrialStep.academy || state.step == TrialStep.done || state.steps.indexOf(state.step) <= 0,
          onPopInvokedWithResult: (popped, _) {
            if (!popped) context.read<TrialCubit>().back();
          },
          child: Scaffold(
            body: SafeArea(
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 520),
                  child: Column(
                    children: [
                      _TopBar(
                        state: state,
                        onBack: () => _back(context),
                        onClose: () => context.canPop() ? context.pop() : context.go(base),
                      ),
                      Expanded(
                        child: AnimatedSwitcher(
                          duration: context.calm ? Duration.zero : const Duration(milliseconds: 320),
                          switchInCurve: Curves.easeOutCubic,
                          switchOutCurve: Curves.easeInCubic,
                          transitionBuilder: (child, a) => FadeTransition(
                            opacity: a,
                            child: SlideTransition(
                              position: Tween(begin: const Offset(0, 0.03), end: Offset.zero).animate(a),
                              child: child,
                            ),
                          ),
                          child: KeyedSubtree(key: ValueKey(state.step), child: _step(state.step)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      final json = state.branding;
      // The academy's own colours once known (the app's until then).
      return json == null ? frame : Theme(data: buildTheme(Branding.fromJson(json), Stage.classic), child: frame);
    },
  );

  Widget _step(TrialStep step) => switch (step) {
    TrialStep.academy => const AcademyStep(),
    TrialStep.who => const WhoStep(),
    TrialStep.subject => const SubjectStep(),
    TrialStep.wishes => const WishesStep(),
    TrialStep.time => const TimeStep(),
    TrialStep.contact => const ContactStep(),
    TrialStep.code => const CodeStep(),
    TrialStep.done => DoneStep(base: base),
  };
}

/// Back, the academy's name with "free trial", close; and the bar of steps
/// (read out as "step 2 of 6").
class _TopBar extends StatelessWidget {
  const _TopBar({required this.state, required this.onBack, required this.onClose});

  final TrialState state;
  final VoidCallback onBack;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final steps = state.steps;
    final at = steps.indexOf(state.step);
    final done = state.step == TrialStep.done;
    final counted = at >= 0;
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 6, 8, 4),
      child: Column(
        children: [
          Row(
            children: [
              if (!done)
                IconButton(key: const Key('trial-back'), tooltip: l.back, onPressed: onBack, icon: const BackButtonIcon())
              else
                const SizedBox(width: 48),
              Expanded(
                child: Column(
                  children: [
                    Text(
                      l.trialTitle,
                      textAlign: TextAlign.center,
                      style: TextStyle(fontFamily: context.shape.headingFont, fontSize: 16, fontWeight: FontWeight.w700, color: c.ink),
                    ),
                    if (state.page != null)
                      Text(
                        state.page!.name,
                        key: const Key('trial-academy-name'),
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 13, color: c.muted),
                      ),
                  ],
                ),
              ),
              IconButton(key: const Key('trial-close'), tooltip: l.close, onPressed: onClose, icon: const Icon(Icons.close_rounded)),
            ],
          ),
          if (counted)
            Semantics(
              label: l.trialStepOf(at + 1, steps.length),
              excludeSemantics: true,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 6, 12, 4),
                child: Row(
                  children: [
                    for (var i = 0; i < steps.length; i++) ...[
                      if (i > 0) const Gap(6),
                      Expanded(
                        child: AnimatedContainer(
                          duration: context.calm ? Duration.zero : const Duration(milliseconds: 380),
                          curve: Curves.easeOutCubic,
                          height: 5,
                          decoration: BoxDecoration(color: i <= at ? c.brand : c.chip, borderRadius: BorderRadius.circular(5)),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
