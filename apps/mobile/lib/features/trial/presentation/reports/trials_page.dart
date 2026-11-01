import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../data/trial_reports_repository.dart';
import '../../domain/trial_report.dart';

/// Where a look keeps the trials: the family's More, or a student's.
String trialsBase(String areaPrefix) => areaPrefix == '/family' ? '/family/me/trials' : '$areaPrefix/more/trials';

/// The learner's free trials (`GET family/trials`), latest first: each with
/// its subject, time, teacher and how it went in words, and what can be done
/// from it: read the report, book another time, try another teacher. A
/// guardian sees the child the family tabs show, another a tap away. It
/// follows lessons (a trial is one) and the inbox (a report's message) live.
class TrialsPage extends StatelessWidget {
  const TrialsPage({required this.base, super.key});

  /// Where this list is (`/family/me/trials`, `/teen/more/trials`, …).
  final String base;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final learnerId = context.learnerId();
    final family = base.startsWith('/family');
    final kids = context.isKids;
    return Scaffold(
      appBar: AppBar(title: Text(kids ? l.trialsKidsTitle : l.trialsTitle)),
      body: BlocProvider(
        key: ValueKey(learnerId),
        create: (c) => LoadCubit<FamilyTrials>(
          () => c.read<TrialReportsRepository>().trials(learnerId: learnerId),
          refreshOn: c.read<Live>().on({Live.lessons, Live.inbox}),
        )..load(),
        child: LoadView<FamilyTrials>(
          skeleton: const SkeletonList(),
          builder: (context, list) {
            final self = context.read<LearnerCubit>().state.selected?.isSelf ?? !family;
            return ListView(
              padding: pagePadding,
              physics: const AlwaysScrollableScrollPhysics(),
              children: staggered(context, [
                if (family) ...[const LearnerSwitcher(padding: EdgeInsets.zero), const Gap(Space.m)],
                Text(
                  self || list.learnerName.isEmpty ? l.trialsLeadSelf : l.trialsLead(list.learnerName.split(' ').first),
                  style: context.type.body.copyWith(color: context.colors.muted),
                ),
                const Gap(Space.l),
                if (list.trials.isEmpty)
                  EmptyState(icon: Icons.school_outlined, title: l.trialsEmptyTitle, body: l.trialsEmptyBody)
                else
                  for (final t in list.trials) ...[TrialCard(trial: t, base: base, classic: list.classic), const Gap(Space.m)],
              ]),
            );
          },
        ),
      ),
    );
  }
}

/// How a trial went, in words and a tone.
(String, Tone, IconData) trialState(BuildContext context, FamilyTrial t) {
  final l = context.l10n;
  return switch (t.status) {
    TrialStatus.booked => (l.trialStateBooked, Tone.info, Icons.event_rounded),
    TrialStatus.attended when t.reportReady => (l.trialStateReady, Tone.brand, Icons.description_rounded),
    TrialStatus.attended => (l.trialStateWriting, Tone.neutral, Icons.edit_note_rounded),
    _ => switch (t.outcome) {
      'student_absent' => (l.trialStateStudentAbsent, Tone.attention, Icons.event_busy_rounded),
      'teacher_absent' => (l.trialStateTeacherAbsent, Tone.neutral, Icons.event_busy_rounded),
      'family' => (l.trialStateCancelledFamily, Tone.neutral, Icons.event_busy_rounded),
      'academy' => (l.trialStateCancelledAcademy, Tone.neutral, Icons.event_busy_rounded),
      _ => (l.trialStateMissed, Tone.neutral, Icons.event_busy_rounded),
    },
  };
}

/// One trial: subject and how it went at the top, when and with whom, a line
/// on what happens next, and its way on (the report, or one more trial).
class TrialCard extends StatelessWidget {
  const TrialCard({required this.trial, required this.base, required this.classic, this.openReport = true, super.key});

  final FamilyTrial trial;
  final String base;
  final bool classic;

  /// Whether reading the report is offered (not on the report's own page).
  final bool openReport;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final t = trial;
    final (state, tone, icon) = trialState(context, t);
    final next = t.next;
    final read = openReport && t.reportReady ? () => context.go('$base/${t.id}') : null;
    final kind = switch (t.kind) {
      'second' => l.trialKindSecond,
      'rebook' => l.trialKindRebook,
      _ => null,
    };
    return LmsCard(
      key: Key('trial-${t.id}'),
      onTap: read,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon, tone: tone, size: 44),
              const Gap(Space.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(t.subject, style: context.type.cardTitle),
                    const Gap(2),
                    Text(
                      [?t.startsAt?.label, if (t.teacher.isNotEmpty) l.trialBookedTeacher(keepTogether(t.teacher))].join(' · '),
                      style: context.type.caption,
                    ),
                  ],
                ),
              ),
              const Gap(Space.s),
              StatusChip(state, tone: tone),
            ],
          ),
          if (kind != null) ...[
            const Gap(Space.s),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: StatusChip(kind, tone: Tone.info, icon: Icons.swap_horiz_rounded),
            ),
          ],
          if (t.reportComing) ...[const Gap(Space.m), _Line(l.trialWritingNote, icon: Icons.hourglass_top_rounded)],
          if (t.status == TrialStatus.booked) ...[const Gap(Space.m), _Line(l.trialBookedNote, icon: Icons.videocam_outlined)],
          if (t.notTheirs) ...[const Gap(Space.m), _Line(l.trialNotCounted, icon: Icons.verified_outlined)],
          if (classic && next != null && !next.available && next.whyNot != null && !t.reportReady) ...[
            const Gap(Space.m),
            Note(next.whyNot!, key: Key('trial-why-${t.id}'), icon: Icons.chat_bubble_outline_rounded),
          ],
          if (read != null || (classic && (next?.available ?? false))) ...[
            const Gap(Space.m),
            Wrap(
              spacing: Space.s,
              runSpacing: Space.s,
              children: [
                if (read != null)
                  FilledButton.icon(
                    key: Key('trial-read-${t.id}'),
                    onPressed: PressScale.withPress(context, read),
                    icon: const Icon(Icons.description_outlined, size: 20),
                    label: Text(l.trialReadReport),
                  ),
                if (classic && next != null && next.available)
                  OutlinedButton.icon(
                    key: Key('trial-next-${t.id}'),
                    onPressed: () => context.go(nextTrialLocation(base, t.id, second: next.second, teacher: t.teacher)),
                    icon: Icon(next.second ? Icons.swap_horiz_rounded : Icons.event_repeat_rounded, size: 20),
                    label: Text(next.second ? l.trialTryAnother : l.trialBookAgain),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// A name with its title ("أ. منى") kept on one line.
String keepTogether(String name) => name.replaceAll('. ', '.\u00A0');

/// The picker for one more trial from [id].
String nextTrialLocation(String base, String id, {required bool second, String teacher = ''}) => Uri(
  path: '$base/$id/next',
  queryParameters: {'kind': second ? 'second' : 'rebook', if (teacher.isNotEmpty) 'teacher': teacher},
).toString();

class _Line extends StatelessWidget {
  const _Line(this.text, {required this.icon});

  final String text;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 18, color: context.colors.muted),
      const Gap(Space.s),
      Expanded(child: Text(text, style: context.type.caption.copyWith(height: 1.55))),
    ],
  );
}

/// A student's way to their free trials in More: shown only once they had
/// one (most students came through a trial; the others never see it).
class TrialsEntry extends StatelessWidget {
  const TrialsEntry({required this.base, super.key});

  final String base;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<FamilyTrials>(() => c.read<TrialReportsRepository>().trials())..load(),
    child: BlocBuilder<LoadCubit<FamilyTrials>, LoadState<FamilyTrials>>(
      builder: (context, state) {
        final list = state.data;
        if (list == null || list.trials.isEmpty) return const SizedBox.shrink();
        final l = context.l10n;
        final ready = list.trials.where((t) => t.reportReady).length;
        return Padding(
          padding: const EdgeInsets.only(top: Space.m),
          child: MenuGroup(
            children: [
              MenuRow(
                key: const Key('more-trials'),
                icon: Icons.school_outlined,
                tone: Tone.info,
                label: context.isKids ? l.trialsKidsTitle : l.trialsTitle,
                subtitle: context.isKids ? null : l.trialsRowHint,
                count: ready,
                onTap: () => context.go(base),
              ),
            ],
          ),
        );
      },
    ),
  );
}

/// The family's home speaks of a recent trial while there is something to
/// do with it: its report ready to read, or one more trial to book (after one
/// that did not take place, or the second with another teacher).
class TrialHomeCard extends StatelessWidget {
  const TrialHomeCard({required this.trial, super.key});

  final FamilyTrial trial;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final t = trial;
    final base = trialsBase('/family');
    final next = t.next;
    final (title, body, icon, go) = t.reportReady
        ? (l.trialHomeReady, l.trialHomeReadyBody(t.subject, keepTogether(t.teacher)), Icons.description_rounded, '$base/${t.id}')
        : next != null && next.second
        ? (
            l.trialHomeSecond(t.subject),
            l.trialSecondBodyNoDate,
            Icons.swap_horiz_rounded,
            nextTrialLocation(base, t.id, second: true, teacher: t.teacher),
          )
        : (l.trialHomeRebook(t.subject), l.trialHomeRebookBody, Icons.event_repeat_rounded, nextTrialLocation(base, t.id, second: false));
    return Material(
      key: const Key('home-trial'),
      color: c.brandSoft,
      shape: RoundedRectangleBorder(
        borderRadius: context.shape.cardRadius,
        side: BorderSide(color: c.brand.withValues(alpha: 0.3)),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: PressScale.withPress(context, () => context.go(go)),
        child: Padding(
          padding: const EdgeInsets.all(Space.l),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(color: c.brand, borderRadius: BorderRadius.circular(14)),
                child: Icon(icon, color: c.onBrand),
              ),
              const Gap(Space.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: context.type.cardTitle.copyWith(color: c.brandDeep)),
                    const Gap(2),
                    Text(body, style: context.type.caption.copyWith(color: c.ink)),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: c.brandDeep, textDirection: Directionality.of(context)),
            ],
          ),
        ),
      ),
    );
  }
}
