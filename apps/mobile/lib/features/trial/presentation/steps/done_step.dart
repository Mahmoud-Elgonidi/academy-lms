import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../trial_cubit.dart';
import '../widgets.dart';

/// The end: a trial booked (its time big, what happens next, another child,
/// signing in with this number now or later), or a request sent (when the
/// academy gets in touch, and what follows).
class DoneStep extends StatefulWidget {
  const DoneStep({required this.base, super.key});

  final String base;

  @override
  State<DoneStep> createState() => _DoneStepState();
}

class _DoneStepState extends State<DoneStep> {
  @override
  void initState() {
    super.initState();
    if (context.read<TrialCubit>().state.booked != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && !context.calm) Celebration.show(context);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = context.read<TrialCubit>().state;
    final cubit = context.read<TrialCubit>();
    final hours = l.inHours(state.page?.trials.responseHours ?? 4);
    final booked = state.booked;
    final another = OutlinedButton.icon(
      key: const Key('trial-another'),
      style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(52)),
      onPressed: cubit.anotherChild,
      icon: Icon(state.draft.forChild ? Icons.child_care_rounded : Icons.add_rounded),
      label: Text(state.draft.forChild ? l.trialAnotherChild : l.trialAnotherTrial),
    );
    if (booked != null) {
      return StepBody(
        title: l.trialBookedTitle,
        badge: Icons.event_available_rounded,
        lead: Lead(l.trialBookedWith(booked.subject, state.page?.name ?? '')),
        bottom: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              height: 56,
              child: PrimaryAction(
                l.trialSignIn,
                key: const Key('trial-sign-in'),
                icon: Icons.login_rounded,
                onPressed: () => context.go(signInWith(context, state.draft.phone)),
              ),
            ),
            const Gap(Space.s),
            another,
            TextButton(
              key: const Key('trial-later'),
              style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44)),
              onPressed: () => context.go(widget.base),
              child: Text(l.trialLater),
            ),
          ],
        ),
        children: [
          if (booked.startsAt != null)
            BookedTimeCard(at: booked.startsAt!, who: state.draft.learnerName, teacher: booked.teacher, minutes: booked.minutes),
          const Gap(Space.xl),
          NextSteps(lines: [l.trialBookedNext1, l.trialBookedNext2, l.trialBookedNext3, l.trialBookedNext4]),
        ],
      );
    }
    final pick = state.canPick;
    return StepBody(
      title: l.trialRequestedTitle,
      badge: Icons.mark_chat_read_outlined,
      lead: Lead(state.draft.helpChoose ? l.trialRequestedHelp(hours) : l.trialRequestedBody(hours)),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            height: 56,
            child: PrimaryAction(l.trialDone, key: const Key('trial-done'), onPressed: () => context.go(widget.base)),
          ),
          const Gap(Space.s),
          another,
        ],
      ),
      children: [
        NextSteps(lines: [pick ? l.trialRequestedNext1Pick : l.trialRequestedNext1, l.trialRequestedNext2(hours), l.trialRequestedNext3]),
      ],
    );
  }
}
