import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../core/time/local_time.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../trial/presentation/widgets.dart';
import '../../domain/start.dart';
import '../start_cubit.dart';
import 'choose_steps.dart';

/// The teacher's weekly times on the family's clock, by day: exactly as many
/// as the plan has a week, one a day (a second on a day takes the first's
/// place), the suggested pattern already chosen, each time with its first
/// lesson. A time taken meanwhile comes back here with the API's words, the
/// other times still chosen.
class TimesStep extends StatelessWidget {
  const TimesStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = context.watch<StartCubit>().state;
    final cubit = context.read<StartCubit>();
    final slots = state.slots;
    final shared = state.plan?.shared ?? false;
    final perWeek = state.perWeek;
    final suggestedKept = slots != null && slots.suggested.isNotEmpty && slots.suggested.every(state.picked.contains);
    final first = state.firstPicked?.first;
    return StepBody(
      title: shared ? l.startTimesTitleShared : l.startTimesTitle(perWeek),
      lead: Lead(l.startTimesLead),
      bottom: slots == null || slots.days.isEmpty
          ? null
          : Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _Counter(picked: state.picked.length, total: perWeek, first: first?.label),
                const Gap(Space.s),
                StepButton(
                  key: const Key('times-next'),
                  label: l.startContinue,
                  icon: Icons.arrow_forward_rounded,
                  onPressed: state.timesDone ? cubit.timesNext : null,
                ),
              ],
            ),
      children: [
        const StepNote(),
        if (state.slotsLoading && slots == null)
          const Padding(
            padding: EdgeInsets.all(Space.xl),
            child: Center(child: CircularProgressIndicator.adaptive()),
          )
        else if (state.slotsProblem != null)
          ErrorState.of(state.slotsProblem!, onRetry: cubit.loadSlots)
        else if (slots != null && slots.days.isEmpty)
          EmptyState(
            key: const Key('start-no-times'),
            icon: Icons.event_busy_rounded,
            title: l.startTimesNone,
            action: SecondaryAction(l.startOtherTeacher, icon: Icons.person_search_rounded, onPressed: cubit.back),
          )
        else if (slots != null) ...[
          // Fewer free days than the plan has lessons a week: said at once, with the two ways on.
          if (!shared && slots.days.length < perWeek) ...[
            Note(
              l.startTimesTooFew,
              key: const Key('times-too-few'),
              tone: Tone.attention,
              icon: Icons.event_busy_rounded,
              action: Wrap(
                spacing: Space.s,
                runSpacing: Space.xs,
                children: [
                  OutlinedButton(onPressed: cubit.back, child: Text(l.startOtherTeacher)),
                  OutlinedButton(onPressed: () => cubit.edit(StartStep.plan), child: Text(l.startOtherPlan)),
                ],
              ),
            ),
            const Gap(Space.l),
          ],
          if (suggestedKept && state.note == null) ...[
            Note(l.startTimesSuggested, key: const Key('times-suggested'), tone: Tone.brand, icon: Icons.auto_awesome_rounded),
            const Gap(Space.m),
          ],
          if (slots.firstDay != null) ...[
            Text(l.startTimesFrom(slots.firstDay!.day(context.locale)), style: context.type.caption),
            const Gap(Space.l),
          ],
          for (final (i, day) in slots.days.indexed) _Day(day: day, picked: state.picked).appear(context, i + 2),
          if (state.dayFull)
            Semantics(
              liveRegion: true,
              child: Note(l.startTimesDayFull, key: const Key('times-day-full'), tone: Tone.attention, icon: Icons.info_outline_rounded),
            ),
        ],
      ],
    );
  }
}

class _Day extends StatelessWidget {
  const _Day({required this.day, required this.picked});

  final SlotDay day;
  final List<String> picked;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final chosen = day.times.where((t) => picked.contains(t.key)).firstOrNull;
    return Padding(
      padding: const EdgeInsets.only(bottom: Space.l),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              AnimatedContainer(
                duration: context.calm ? Duration.zero : const Duration(milliseconds: 200),
                width: 10,
                height: 10,
                decoration: BoxDecoration(shape: BoxShape.circle, color: chosen != null ? c.brand : c.chip),
              ),
              const Gap(Space.s),
              Expanded(child: GroupLabel(day.name)),
              if (chosen?.first != null)
                Text(
                  context.l10n.startTimesFirst(_date(chosen!.first!, context.locale)),
                  key: Key('first-${chosen.key}'),
                  style: context.type.caption.copyWith(color: c.brandDeep, fontWeight: FontWeight.w700),
                ),
            ],
          ),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final t in day.times)
                TrialPill(
                  key: Key('time-${t.key}'),
                  label: wallClock(t.start, context.locale),
                  sub: t.first == null ? null : _date(t.first!, context.locale),
                  semantics: joinSpoken(context, [t.label, if (t.first != null) context.l10n.startTimesFirst(t.first!.label)]),
                  selected: picked.contains(t.key),
                  wide: true,
                  onTap: () => context.read<StartCubit>().toggleTime(t.key),
                ),
            ],
          ),
        ],
      ),
    );
  }

  /// "7 نوفمبر": the first lesson's day on the wall clock the API wrote.
  static String _date(LocalTime first, String locale) => DateFormat('d MMMM', locale).format(first.wall);
}

/// "Chosen 2 of 3", and the first lesson of what is chosen.
class _Counter extends StatelessWidget {
  const _Counter({required this.picked, required this.total, this.first});

  final int picked;
  final int total;
  final String? first;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final done = picked >= total;
    return Semantics(
      liveRegion: true,
      child: Row(
        children: [
          for (var i = 0; i < total; i++) ...[
            AnimatedContainer(
              duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
              width: 22,
              height: 8,
              decoration: BoxDecoration(color: i < picked ? c.brand : c.chip, borderRadius: BorderRadius.circular(8)),
            ),
            const Gap(4),
          ],
          const Gap(Space.s),
          Text(
            context.l10n.startTimesCount(picked, total),
            key: const Key('times-count'),
            style: context.type.label.copyWith(color: done ? c.brandDeep : c.muted),
          ),
          if (first != null) ...[
            const Gap(Space.s),
            Expanded(
              child: Text(
                context.l10n.startTimesFirst(first!),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.end,
                style: context.type.caption,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
