import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../consent/data/consent_repository.dart';
import '../data/start_repository.dart';
import 'start_cubit.dart';
import 'steps/choose_steps.dart';
import 'steps/end_steps.dart';
import 'steps/times_step.dart';

/// Where the family starts a course on its own (docs/architecture/02,
/// section 27, J3): one calm step at a time under a bar that fills as it
/// goes, as the free trial is asked for. A trial's report opens it on the
/// plan with the recommended one and the trial's teacher chosen. Once the
/// course is made, the invoice takes its place (back goes home, not into the
/// steps again). Only the classic look comes here: the router keeps a
/// child's own look away, and the API refuses it too.
class StartPage extends StatelessWidget {
  const StartPage({this.learnerId, this.subjectId, this.planId, this.teacherId, super.key});

  final String? learnerId;
  final String? subjectId;
  final String? planId;
  final String? teacherId;

  /// The flow's address with what is known already.
  static String location({String? learner, String? subject, String? plan, String? teacher}) => Uri(
    path: '/family/start',
    queryParameters: {'learner': ?learner, 'subject': ?subject, 'plan': ?plan, 'teacher': ?teacher},
  ).toString().replaceFirst(RegExp(r'\?$'), '');

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => StartCubit(
      c.read<StartRepository>(),
      c.read<ConsentRepository>(),
      initialLearner: learnerId,
      initialSubject: subjectId,
      initialPlan: planId,
      initialTeacher: teacherId,
    )..load(),
    child: const _StartFrame(),
  );
}

class _StartFrame extends StatelessWidget {
  const _StartFrame();

  void _close(BuildContext context) => context.canPop() ? context.pop() : context.go('/family/home');

  @override
  Widget build(BuildContext context) => BlocListener<StartCubit, StartState>(
    listenWhen: (a, b) => a.started == null && b.started != null,
    listener: (context, state) {
      final started = state.started!;
      context.pushReplacement('/family/pay/${started.invoice?.id ?? 'none'}', extra: started);
    },
    child: BlocBuilder<StartCubit, StartState>(
      buildWhen: (a, b) => a.step != b.step || a.history.length != b.history.length || a.busy != b.busy || a.options != b.options,
      builder: (context, state) => PopScope(
        canPop: state.history.isEmpty && !state.busy,
        onPopInvokedWithResult: (popped, _) {
          if (!popped) context.read<StartCubit>().back();
        },
        child: Scaffold(
          body: SafeArea(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 560),
                child: Column(
                  children: [
                    _TopBar(
                      state: state,
                      onBack: () {
                        if (!context.read<StartCubit>().back()) _close(context);
                      },
                      onClose: () => _close(context),
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
    ),
  );

  Widget _step(StartStep step) => switch (step) {
    StartStep.loading => const _Loading(),
    StartStep.failed => const _Failed(),
    StartStep.off => const OffStep(),
    StartStep.learner => const LearnerStep(),
    StartStep.subject => const SubjectStep(),
    StartStep.plan => const PlanStep(),
    StartStep.teacher => const TeacherStep(),
    StartStep.group => const GroupStep(),
    StartStep.times => const TimesStep(),
    StartStep.review => const ReviewStep(),
    StartStep.consent => const ConsentStep(),
    StartStep.blocked => const BlockedStep(),
  };
}

/// Back, "Start a course" with the learner's name, close; and the bar of steps (read out as "step 2 of 5").
class _TopBar extends StatelessWidget {
  const _TopBar({required this.state, required this.onBack, required this.onClose});

  final StartState state;
  final VoidCallback onBack;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final steps = state.path;
    final at = steps.indexOf(state.step == StartStep.consent ? StartStep.review : state.step);
    final learner = state.learner;
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 6, 8, 4),
      child: Column(
        children: [
          Row(
            children: [
              IconButton(
                key: const Key('start-back'),
                tooltip: l.back,
                onPressed: state.busy ? null : onBack,
                icon: const BackButtonIcon(),
              ),
              Expanded(
                child: Column(
                  children: [
                    Text(
                      l.startTitle,
                      textAlign: TextAlign.center,
                      style: TextStyle(fontFamily: context.shape.headingFont, fontSize: 16, fontWeight: FontWeight.w700, color: c.ink),
                    ),
                    if (learner != null && !learner.isSelf && (state.options?.learners.length ?? 0) > 0)
                      Text(
                        learner.name,
                        key: const Key('start-learner-name'),
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 13, color: c.muted),
                      ),
                  ],
                ),
              ),
              IconButton(
                key: const Key('start-close'),
                tooltip: l.close,
                onPressed: state.busy ? null : onClose,
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
          if (at >= 0)
            Semantics(
              label: l.startStepOf(at + 1, steps.length),
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

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) => ListView(
    key: const Key('start-loading'),
    padding: const EdgeInsets.fromLTRB(20, Space.l, 20, Space.l),
    children: const [
      Skeleton(height: 30, width: 220, radius: 10),
      Gap(Space.m),
      Skeleton(height: 16, width: 280),
      Gap(Space.xl),
      Skeleton(height: 150, radius: 18),
      Gap(Space.m),
      Skeleton(height: 150, radius: 18),
    ],
  );
}

class _Failed extends StatelessWidget {
  const _Failed();

  @override
  Widget build(BuildContext context) {
    final state = context.watch<StartCubit>().state;
    return ErrorState.of(
      state.problem ?? ApiProblem.unreadable(),
      onRetry: context.read<StartCubit>().load,
      key: const Key('start-failed'),
    );
  }
}
