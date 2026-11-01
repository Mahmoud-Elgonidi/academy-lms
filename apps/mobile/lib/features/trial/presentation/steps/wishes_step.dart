import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/trial.dart';
import '../trial_cubit.dart';
import '../widgets.dart';

/// The days and the time of day that suit the family, and the teacher they
/// would rather have; all optional. While the family books, they narrow the
/// times shown next; otherwise they go with the request.
class WishesStep extends StatefulWidget {
  const WishesStep({super.key});

  @override
  State<WishesStep> createState() => _WishesStepState();
}

class _WishesStepState extends State<WishesStep> {
  /// The academy's week: Saturday to Friday (ISO weekdays).
  static const week = [6, 7, 1, 2, 3, 4, 5];

  /// A weekday's name in the reader's language (1 January 2024 was a Monday).
  static String dayName(int iso, String locale) => DateFormat('EEEE', locale).format(DateTime(2024, 1, iso));

  String _partLabel(DayPart part) => switch (part) {
    DayPart.any => context.l10n.trialPartAny,
    DayPart.morning => context.l10n.trialPartMorning,
    DayPart.afternoon => context.l10n.trialPartAfternoon,
    DayPart.evening => context.l10n.trialPartEvening,
  };

  String _teacherLabel(TeacherGender g) => switch (g) {
    TeacherGender.any => context.l10n.trialTeacherAny,
    TeacherGender.female => context.l10n.trialTeacherFemale,
    TeacherGender.male => context.l10n.trialTeacherMale,
  };

  String _hh(int h) => '${h.toString().padLeft(2, '0')}:00';

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final cubit = context.read<TrialCubit>();
    final refused = cubit.state.problem;
    final error = refused == null
        ? null
        : (refused.field('preferred_days') ??
              refused.field('preferred_from') ??
              refused.field('preferred_to') ??
              refused.field('teacher_gender'));
    return BlocBuilder<TrialCubit, TrialState>(
      builder: (context, state) {
        final days = state.draft.days;
        final chosenPart = state.draft.dayPart;
        final teacher = state.draft.teacherGender;
        return StepBody(
          title: l.trialWishesTitle,
          lead: Lead(l.trialWishesLead),
          bottom: SizedBox(
            height: 56,
            child: PrimaryAction(l.trialNext, busy: state.slotsLoading, onPressed: cubit.saveWishes),
          ),
          children: [
            if (error != null) ...[Note(error, tone: Tone.danger, icon: Icons.error_outline_rounded), const Gap(Space.l)],
            GroupLabel(l.trialDays),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                TrialPill(
                  key: const Key('trial-day-any'),
                  label: l.trialDaysAny,
                  selected: days.isEmpty,
                  onTap: () => cubit.keepWishes(days: {}),
                ),
                for (final d in week)
                  TrialPill(
                    key: Key('trial-day-$d'),
                    label: dayName(d, context.locale),
                    selected: days.contains(d),
                    onTap: () => cubit.keepWishes(days: days.contains(d) ? ({...days}..remove(d)) : {...days, d}),
                  ),
              ],
            ),
            const Gap(Space.xl),
            GroupLabel(l.trialDayPart),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final part in DayPart.values)
                  TrialPill(
                    key: Key('trial-part-${part.name}'),
                    label: _partLabel(part),
                    sub: part.from == null ? null : wallRange(_hh(part.from!), _hh(part.to!), context.locale),
                    selected: chosenPart == part,
                    onTap: () => cubit.keepWishes(dayPart: part),
                  ),
              ],
            ),
            const Gap(Space.xl),
            GroupLabel(l.trialTeacher),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final g in TeacherGender.values)
                  TrialPill(
                    key: Key('trial-teacher-${g.name}'),
                    label: _teacherLabel(g),
                    selected: teacher == g,
                    onTap: () => cubit.keepWishes(teacherGender: g),
                  ),
              ],
            ),
          ],
        );
      },
    );
  }
}
