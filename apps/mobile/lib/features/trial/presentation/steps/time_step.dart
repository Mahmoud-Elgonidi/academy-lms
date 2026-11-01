import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/trial.dart';
import '../trial_cubit.dart';
import '../widgets.dart';

/// The times on offer, soonest first: a strip of days, then that day's
/// times as chips (each read out with the API's whole label). The days and
/// hours the family gave narrow them, unless nothing fits. A time taken
/// meanwhile comes back here with the API's words over the fresh times.
/// No time at all: the request goes instead, and the academy arranges one.
class TimeStep extends StatefulWidget {
  const TimeStep({super.key});

  @override
  State<TimeStep> createState() => _TimeStepState();
}

class _TimeStepState extends State<TimeStep> {
  /// The day on screen (null: the chosen time's day, else the first).
  String? _day;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<TrialCubit, TrialState>(
      builder: (context, state) {
        final cubit = context.read<TrialCubit>();
        final zone = zoneName(cubit.timezone, context.locale);
        final days = state.days;
        final chosen = state.chosen;
        final open = days.where((d) => d.day == (_day ?? chosen?.day)).firstOrNull ?? days.firstOrNull;
        final ready = state.slots != null && !state.slotsLoading;
        return StepBody(
          title: l.trialTimeTitle,
          lead: Lead(l.trialTimeLead(zone)),
          bottom: ready && days.isNotEmpty ? _Bottom(chosen: chosen) : null,
          children: [
            if (state.timeNote != null) ...[
              Semantics(
                liveRegion: true,
                child: Note(state.timeNote!, tone: Tone.attention, icon: Icons.update_rounded),
              ),
              const Gap(Space.l),
            ],
            if (state.slotsLoading && state.slots == null)
              const _Looking(key: Key('trial-times-loading'))
            else if (state.slotsProblem != null)
              ErrorState.of(state.slotsProblem!, onRetry: cubit.loadSlots)
            else if (days.isEmpty)
              _NoTimes(state: state)
            else ...[
              if (state.draft.hasWishes) ...[
                if (state.anySuits)
                  Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: FilterChip(
                      key: const Key('trial-only-suiting'),
                      label: Text(l.trialOnlySuiting),
                      selected: state.onlySuiting,
                      onSelected: (on) {
                        setState(() => _day = null);
                        cubit.showAllTimes(!on);
                      },
                    ),
                  )
                else
                  Note(l.trialNoneSuit),
                const Gap(Space.m),
              ],
              _DayStrip(days: days, open: open!, onDay: (d) => setState(() => _day = d)),
              const Gap(Space.l),
              AnimatedSwitcher(
                duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
                child: Wrap(
                  key: ValueKey(open.day),
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    for (final slot in open.slots)
                      TrialPill(
                        key: Key('trial-slot-${slot.startsAt.local}'),
                        label: slot.startsAt.clock(context.locale),
                        semantics: slot.startsAt.label,
                        selected: slot == chosen,
                        wide: true,
                        onTap: () => cubit.chooseSlot(slot),
                      ),
                  ],
                ),
              ),
            ],
          ],
        );
      },
    );
  }
}

/// The chosen time in the API's words, and the button to book it; or a way to send the request instead.
class _Bottom extends StatelessWidget {
  const _Bottom({required this.chosen});

  final TrialSlot? chosen;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final cubit = context.read<TrialCubit>();
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(
          liveRegion: true,
          child: Padding(
            padding: const EdgeInsets.only(bottom: Space.s),
            child: Text(
              chosen == null ? l.trialPickFirst : l.trialChosen(chosen!.startsAt.label),
              key: const Key('trial-chosen'),
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14.5,
                fontWeight: chosen == null ? FontWeight.w400 : FontWeight.w700,
                color: chosen == null ? context.colors.muted : context.colors.brandDeep,
              ),
            ),
          ),
        ),
        SizedBox(height: 56, child: PrimaryAction(l.trialBookThis, onPressed: chosen == null ? null : cubit.confirmTime)),
        TextButton(
          key: const Key('trial-none-suit'),
          style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44)),
          onPressed: cubit.sendInstead,
          child: Text(l.trialNoTimeSuits),
        ),
      ],
    );
  }
}

/// The days with times: today, tomorrow, then the weekday and date, each with how many times it has.
class _DayStrip extends StatelessWidget {
  const _DayStrip({required this.days, required this.open, required this.onDay});

  final List<SlotDay> days;
  final SlotDay open;
  final ValueChanged<String> onDay;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    return SizedBox(
      height: 92 * MediaQuery.textScalerOf(context).scale(1).clamp(1.0, 1.6),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: days.length,
        separatorBuilder: (_, _) => const Gap(Space.s),
        itemBuilder: (context, i) {
          final day = days[i];
          final at = day.first;
          final selected = day.day == open.day;
          final top = switch (at.relativeDay(context)) {
            final r when r == l.today || r == l.tomorrow => r,
            _ => DateFormat('EEEE', context.locale).format(at.wall),
          };
          return Semantics(
            button: true,
            selected: selected,
            label: joinSpoken(context, [at.relativeDay(context), at.day(context.locale), l.trialTimesCount(day.slots.length)]),
            excludeSemantics: true,
            child: PressScale(
              onTap: () => onDay(day.day),
              child: AnimatedContainer(
                key: Key('trial-day-${day.day}'),
                duration: context.calm ? Duration.zero : const Duration(milliseconds: 200),
                width: 84,
                padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 6),
                decoration: BoxDecoration(
                  color: selected ? c.brand : c.card,
                  borderRadius: BorderRadius.circular(context.shape.card),
                  border: Border.all(color: selected ? c.brand : c.line, width: 1.5),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      top,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: selected ? Colors.white : c.muted),
                    ),
                    Text(
                      DateFormat('d', context.locale).format(at.wall),
                      style: TextStyle(
                        fontFamily: context.shape.headingFont,
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                        color: selected ? Colors.white : c.ink,
                      ),
                    ),
                    Text(
                      DateFormat('MMM', context.locale).format(at.wall),
                      maxLines: 1,
                      style: TextStyle(fontSize: 12, color: selected ? Colors.white.withValues(alpha: 0.9) : c.muted),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

/// Looking for times: a calm line while the API answers.
class _Looking extends StatelessWidget {
  const _Looking({super.key});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: Space.xxl),
    child: Column(
      children: [
        SizedBox.square(dimension: 28, child: CircularProgressIndicator(strokeWidth: 3, color: context.colors.brand)),
        const Gap(Space.m),
        Text(context.l10n.trialLoadingTimes, style: TextStyle(color: context.colors.muted)),
      ],
    ),
  );
}

/// No time in the next two weeks: any teacher (when one was asked for), or the request instead.
class _NoTimes extends StatelessWidget {
  const _NoTimes({required this.state});

  final TrialState state;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final cubit = context.read<TrialCubit>();
    final hours = l.inHours(state.page?.trials.responseHours ?? 4);
    final gender = state.draft.teacherGender != TeacherGender.any;
    return EmptyState(
      key: const Key('trial-no-times'),
      icon: Icons.event_busy_rounded,
      title: l.trialNoTimesTitle,
      body: gender ? l.trialNoTimesGender : l.trialNoTimesBody(hours),
      action: Column(
        children: [
          if (gender) ...[
            SizedBox(height: 52, child: PrimaryAction(l.trialAnyTeacher, onPressed: cubit.anyTeacher)),
            const Gap(Space.s),
            SecondaryAction(l.trialSendInstead, onPressed: cubit.sendInstead),
          ] else
            SizedBox(height: 52, child: PrimaryAction(l.trialSendInstead, onPressed: cubit.sendInstead)),
        ],
      ),
    );
  }
}
