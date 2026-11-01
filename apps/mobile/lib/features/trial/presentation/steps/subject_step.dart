import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../trial_cubit.dart';
import '../widgets.dart';

/// The subject, one tap away (and on to the next step), or "help me choose":
/// the academy chooses it with the family and calls them.
class SubjectStep extends StatelessWidget {
  const SubjectStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<TrialCubit, TrialState>(
      builder: (context, state) {
        final cubit = context.read<TrialCubit>();
        final subjects = state.page?.subjects ?? const [];
        final refused = state.problem?.field('subject_id');
        // Help choosing starts with the academy's own subject while families book; else its staff call.
        final helpWith = state.page?.trials.familyBooks ?? false
            ? subjects.where((s) => s.id == state.page?.trials.helpChooseSubjectId).firstOrNull?.name
            : null;
        return StepBody(
          title: l.trialSubjectTitle,
          lead: Lead(l.trialSubjectLead),
          children: [
            if (refused != null) ...[Note(refused, tone: Tone.danger, icon: Icons.error_outline_rounded), const Gap(Space.l)],
            if (subjects.isEmpty) ...[Note(l.trialNoSubjects), const Gap(Space.l)],
            for (final subject in subjects) ...[
              ChoiceCard(
                key: Key('trial-subject-${subject.id}'),
                title: subject.name,
                subtitle: [
                  if (subject.categories.isNotEmpty) subject.categories.take(3).join(' · '),
                  if (subject.levels.isNotEmpty) l.trialSubjectLevels(subject.levels.length),
                ].join(' · '),
                icon: Icons.menu_book_rounded,
                selected: !state.draft.helpChoose && state.draft.subjectId == subject.id,
                onTap: () => cubit.chooseSubject(subject.id),
              ),
              const Gap(Space.m),
            ],
            const Gap(Space.s),
            ChoiceCard(
              key: const Key('trial-help-choose'),
              title: l.trialHelpChoose,
              subtitle: helpWith != null ? l.trialHelpChooseWith(helpWith) : l.trialHelpChooseHint,
              icon: Icons.support_agent_rounded,
              tone: Tone.info,
              selected: state.draft.helpChoose,
              onTap: cubit.helpMeChoose,
            ),
          ],
        );
      },
    );
  }
}
