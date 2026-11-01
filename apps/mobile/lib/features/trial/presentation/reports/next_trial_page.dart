import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/trial_reports_repository.dart';
import '../../domain/trial.dart';
import '../widgets.dart';
import 'next_trial_cubit.dart';

/// One more free trial from a trial: the second with another teacher, or a
/// new time after one that did not take place. The same picker as booking
/// the first trial (a strip of days, the day's times), the time chosen in
/// the API's words and the button at the foot; the API's refusal in its own
/// calm sentence instead of times; booked, the time big and what follows.
class NextTrialPage extends StatelessWidget {
  const NextTrialPage({required this.id, required this.base, this.second = true, this.teacher = '', super.key});

  final String id;
  final String base;
  final bool second;

  /// The first trial's teacher, named in the lead ("another teacher than …").
  final String teacher;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => NextTrialCubit(c.read<TrialReportsRepository>(), id)..load(),
    child: BlocBuilder<NextTrialCubit, NextTrialState>(
      buildWhen: (a, b) => (a.booked == null) != (b.booked == null),
      builder: (context, state) => Scaffold(
        appBar: AppBar(title: Text(context.l10n.trialsTitle)),
        body: SafeArea(
          top: false,
          child: state.booked == null ? _Pick(second: second, teacher: teacher) : _Booked(base: base, second: second),
        ),
      ),
    ),
  );
}

class _Pick extends StatefulWidget {
  const _Pick({required this.second, required this.teacher});

  final bool second;
  final String teacher;

  @override
  State<_Pick> createState() => _PickState();
}

class _PickState extends State<_Pick> {
  String? _day;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<NextTrialCubit, NextTrialState>(
      builder: (context, state) {
        final cubit = context.read<NextTrialCubit>();
        final zone = zoneName(context.read<SessionCubit>().state.timezone, context.locale);
        final days = state.days;
        final chosen = state.chosen;
        final open = days.where((d) => d.day == (_day ?? chosen?.day)).firstOrNull ?? days.firstOrNull;
        final ready = state.slots != null && !state.loading && state.refusal == null && days.isNotEmpty;
        return StepBody(
          title: widget.second ? l.trialNextPickSecond : l.trialNextPickRebook,
          lead: Lead(widget.second && widget.teacher.isNotEmpty ? l.trialNextLeadSecond(widget.teacher, zone) : l.trialTimeLead(zone)),
          bottom: ready ? _Bottom(chosen: chosen, busy: state.busy) : null,
          children: [
            if (state.note != null) ...[
              Semantics(
                liveRegion: true,
                child: Note(state.note!, key: const Key('next-note'), tone: Tone.attention, icon: Icons.update_rounded),
              ),
              const Gap(Space.l),
            ],
            if (state.bookProblem != null) ...[
              Semantics(
                liveRegion: true,
                child: Note(context.problemText(state.bookProblem!), key: const Key('next-problem'), tone: Tone.danger),
              ),
              const Gap(Space.l),
            ],
            if (state.loading && state.slots == null)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: Space.xxl),
                child: Center(child: CircularProgressIndicator.adaptive()),
              )
            else if (state.problem != null)
              ErrorState.of(state.problem!, onRetry: cubit.load)
            else if (state.refusal != null)
              EmptyState(
                key: const Key('next-refused'),
                icon: Icons.chat_bubble_outline_rounded,
                title: l.trialRefusedTitle,
                body: state.refusal,
                action: SecondaryAction(
                  l.trialNextOpenHelp,
                  icon: Icons.help_outline_rounded,
                  onPressed: () => context.go('/family/me/help'),
                ),
              )
            else if (days.isEmpty)
              EmptyState(
                key: const Key('next-no-times'),
                icon: Icons.event_busy_rounded,
                title: l.trialNoTimesTitle,
                body: l.trialNextNoTimes,
                action: SecondaryAction(
                  l.trialNextOpenHelp,
                  icon: Icons.help_outline_rounded,
                  onPressed: () => context.go('/family/me/help'),
                ),
              )
            else ...[
              TrialDayStrip(days: days, open: open!, onDay: (d) => setState(() => _day = d)),
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
                        key: Key('next-slot-${slot.startsAt.local}'),
                        label: slot.startsAt.clock(context.locale),
                        semantics: slot.startsAt.label,
                        selected: slot == chosen,
                        wide: true,
                        onTap: () => cubit.choose(slot),
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

class _Bottom extends StatelessWidget {
  const _Bottom({required this.chosen, required this.busy});

  final TrialSlot? chosen;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
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
              key: const Key('next-chosen'),
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14.5,
                fontWeight: chosen == null ? FontWeight.w400 : FontWeight.w700,
                color: chosen == null ? c.muted : c.brandDeep,
              ),
            ),
          ),
        ),
        SizedBox(
          height: 56,
          child: PrimaryAction(
            l.trialBookThis,
            key: const Key('next-book'),
            busy: busy,
            onPressed: chosen == null || busy ? null : () => context.read<NextTrialCubit>().book(),
          ),
        ),
      ],
    );
  }
}

class _Booked extends StatefulWidget {
  const _Booked({required this.base, required this.second});

  final String base;
  final bool second;

  @override
  State<_Booked> createState() => _BookedState();
}

class _BookedState extends State<_Booked> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && !context.calm) Celebration.show(context);
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final booked = context.read<NextTrialCubit>().state.booked!;
    return StepBody(
      title: widget.second ? l.trialNextBookedSecond : l.trialNextBookedTitle,
      badge: Icons.event_available_rounded,
      lead: booked.subject.isEmpty ? null : Lead(booked.subject),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            height: 56,
            child: PrimaryAction(l.trialDone, key: const Key('next-done'), onPressed: () => context.go(widget.base)),
          ),
          TextButton(
            key: const Key('next-lessons'),
            style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44)),
            onPressed: () => context.go('/family/lessons'),
            child: Text(l.trialOpenLessons),
          ),
        ],
      ),
      children: [
        if (booked.startsAt != null) BookedTimeCard(at: booked.startsAt!, teacher: booked.teacher, minutes: booked.minutes),
        const Gap(Space.xl),
        NextSteps(lines: [l.trialNextBooked1, l.trialNextBooked2, l.trialNextBooked3]),
      ],
    );
  }
}
