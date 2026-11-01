import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/trial.dart';
import '../trial_cubit.dart';
import '../widgets.dart';

/// Who is learning: me, or my child (their name and age); and the name of
/// whoever we talk to. What is typed is kept as it is typed.
class WhoStep extends StatefulWidget {
  const WhoStep({super.key});

  @override
  State<WhoStep> createState() => _WhoStepState();
}

class _WhoStepState extends State<WhoStep> {
  late final TrialCubit _cubit;
  late final TextEditingController _name;
  late final TextEditingController _child;

  /// What is missing, found here before the API sees it.
  Map<String, String> _missing = const {};

  static const ages = [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17];

  @override
  void initState() {
    super.initState();
    _cubit = context.read<TrialCubit>();
    _name = TextEditingController(text: _cubit.state.draft.name);
    _child = TextEditingController(text: _cubit.state.draft.childName);
  }

  @override
  void dispose() {
    _name.dispose();
    _child.dispose();
    super.dispose();
  }

  void _next() {
    final l = context.l10n;
    final draft = _cubit.state.draft;
    String? named(String text) => text.trim().isEmpty ? l.trialNameMissing : (text.trim().length < 2 ? l.trialNameShort : null);
    final missing = <String, String>{
      if (draft.learner == null) 'learner': l.trialWhoMissing,
      if (draft.learner != null && named(_name.text) != null) 'name': named(_name.text)!,
      if (draft.forChild && named(_child.text) != null) 'child_name': named(_child.text)!,
      if (draft.forChild && draft.childAge == null) 'child_birth_year': l.trialAgeMissing,
    };
    setState(() => _missing = missing);
    if (missing.isNotEmpty) return Juice.wrong();
    _cubit.saveWho(name: _name.text, childName: _child.text, childAge: draft.childAge);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<TrialCubit, TrialState>(
      builder: (context, state) {
        final draft = state.draft;
        final p = state.problem;
        String? error(String field) => _missing[field] ?? p?.field(field);
        return StepBody(
          title: l.trialWhoTitle,
          // Another academy than the one remembered on this phone.
          lead: Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(
              key: const Key('trial-other-academy'),
              style: TextButton.styleFrom(minimumSize: const Size(0, 44), padding: const EdgeInsets.symmetric(horizontal: 4)),
              onPressed: _cubit.changeAcademy,
              icon: const Icon(Icons.swap_horiz_rounded, size: 20),
              label: Text(l.trialAcademyChange),
            ),
          ),
          bottom: SizedBox(height: 56, child: PrimaryAction(l.trialNext, onPressed: _next)),
          children: [
            ChoiceCard(
              key: const Key('trial-who-me'),
              title: l.trialWhoMe,
              subtitle: l.trialWhoMeHint,
              icon: Icons.person_rounded,
              selected: draft.learner == Learner.me,
              trailing: const SizedBox.shrink(),
              onTap: () {
                setState(() => _missing = {..._missing}..remove('learner'));
                _cubit.chooseLearner(Learner.me);
              },
            ),
            const Gap(Space.m),
            ChoiceCard(
              key: const Key('trial-who-child'),
              title: l.trialWhoChild,
              subtitle: l.trialWhoChildHint,
              icon: Icons.child_care_rounded,
              selected: draft.learner == Learner.child,
              trailing: const SizedBox.shrink(),
              onTap: () {
                setState(() => _missing = {..._missing}..remove('learner'));
                _cubit.chooseLearner(Learner.child);
              },
            ),
            FieldError(_missing['learner']),
            AnimatedSize(
              duration: context.calm ? Duration.zero : const Duration(milliseconds: 260),
              curve: Curves.easeOutCubic,
              alignment: AlignmentDirectional.topStart,
              child: draft.learner == null
                  ? const SizedBox(width: double.infinity)
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Gap(Space.xl),
                        if (draft.forChild) ...[
                          TextField(
                            key: const Key('trial-child-name'),
                            controller: _child,
                            textCapitalization: TextCapitalization.words,
                            textInputAction: TextInputAction.next,
                            autofillHints: const [AutofillHints.givenName],
                            onChanged: (v) => _cubit.keep(childName: v),
                            decoration: InputDecoration(labelText: l.trialChildName, errorText: error('child_name'), errorMaxLines: 3),
                          ),
                          const Gap(Space.l),
                          GroupLabel(l.trialChildAge),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              for (final age in ages)
                                TrialPill(
                                  key: Key('trial-age-$age'),
                                  label: '$age',
                                  semantics: l.trialAgeYears(age),
                                  selected: draft.childAge == age,
                                  onTap: () {
                                    setState(() => _missing = {..._missing}..remove('child_birth_year'));
                                    _cubit.keep(childAge: age);
                                  },
                                ),
                            ],
                          ),
                          FieldError(error('child_birth_year')),
                          const Gap(Space.xl),
                        ],
                        TextField(
                          key: const Key('trial-name'),
                          controller: _name,
                          textCapitalization: TextCapitalization.words,
                          textInputAction: TextInputAction.done,
                          autofillHints: const [AutofillHints.name],
                          onChanged: (v) => _cubit.keep(name: v),
                          onSubmitted: (_) => _next(),
                          decoration: InputDecoration(
                            labelText: draft.forChild ? l.trialYourNameParent : l.trialYourName,
                            helperText: l.trialYourNameHint,
                            errorText: error('name'),
                            errorMaxLines: 3,
                          ),
                        ),
                      ],
                    ),
            ),
          ],
        );
      },
    );
  }
}
