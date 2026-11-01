import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../core/time/local_time.dart';
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
            _BigTime(at: booked.startsAt!, who: state.draft.learnerName, teacher: booked.teacher, minutes: booked.minutes),
          const Gap(Space.xl),
          _Next(lines: [l.trialBookedNext1, l.trialBookedNext2, l.trialBookedNext3, l.trialBookedNext4]),
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
        _Next(lines: [pick ? l.trialRequestedNext1Pick : l.trialRequestedNext1, l.trialRequestedNext2(hours), l.trialRequestedNext3]),
      ],
    );
  }
}

/// The lesson's time, big: the day, the hour, and whose lesson it is; read out as the API's label.
class _BigTime extends StatelessWidget {
  const _BigTime({required this.at, required this.who, this.teacher, this.minutes});

  final LocalTime at;
  final String who;
  final String? teacher;
  final int? minutes;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    return Semantics(
      label: joinSpoken(context, [
        at.label,
        l.trialSummaryFor(who),
        if (teacher != null) l.trialBookedTeacher(teacher!),
        if (minutes != null) l.minutes(minutes!),
      ]),
      excludeSemantics: true,
      child: Container(
        key: const Key('trial-booked-time'),
        padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 18),
        decoration: BoxDecoration(
          color: c.brandSoft,
          borderRadius: BorderRadius.circular(context.shape.card + 4),
          border: Border.all(color: c.brand.withValues(alpha: 0.35)),
        ),
        child: Column(
          children: [
            Text(
              at.relativeDay(context) == at.day(context.locale)
                  ? at.day(context.locale)
                  : '${at.relativeDay(context)} · ${at.day(context.locale)}',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: c.brandDeep),
            ),
            const Gap(Space.xs),
            // The hour big, the time of day beside it (nothing on a 24-hour clock).
            FittedBox(
              child: Text.rich(
                TextSpan(
                  children: [
                    TextSpan(
                      text: at.hourMinute(context.locale),
                      style: TextStyle(
                        fontFamily: context.shape.headingFont,
                        fontSize: 46,
                        fontWeight: FontWeight.w700,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                    if (at.dayPeriod(context.locale).isNotEmpty)
                      TextSpan(
                        text: ' ${at.dayPeriod(context.locale)}',
                        style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600),
                      ),
                  ],
                ),
                key: const Key('trial-booked-clock'),
                style: TextStyle(color: c.ink, height: 1.5),
              ),
            ),
            if (teacher != null || minutes != null) ...[
              const Gap(Space.xs),
              Text(
                [if (teacher != null) l.trialBookedTeacher(teacher!), if (minutes != null) l.minutes(minutes!)].join(' · '),
                key: const Key('trial-booked-teacher'),
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: c.ink),
              ),
            ],
            const Gap(Space.xs),
            Text(
              '${l.trialSummaryFor(who)} · ${zoneName(at.timezone, context.locale)}',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 14, color: c.muted),
            ),
          ],
        ),
      ),
    );
  }
}

/// What happens next, numbered.
class _Next extends StatelessWidget {
  const _Next({required this.lines});

  final List<String> lines;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        GroupLabel(context.l10n.trialNextTitle),
        const Gap(Space.xs),
        for (final (i, line) in lines.indexed)
          Padding(
            padding: const EdgeInsets.only(bottom: Space.m),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 28,
                  height: 28,
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
                    padding: const EdgeInsets.only(top: 3),
                    child: Text(line, style: TextStyle(fontSize: 15, height: 1.55, color: c.ink)),
                  ),
                ),
              ],
            ),
          ).appear(context, i + 3),
      ],
    );
  }
}
