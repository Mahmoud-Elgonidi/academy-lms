import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/done_moment.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../trial/presentation/widgets.dart';
import '../../domain/start.dart';
import '../start_cubit.dart';
import '../widgets.dart';
import 'choose_steps.dart';

/// Everything chosen, before anything is made: who, the subject, the plan,
/// the teacher (or group) and the weekly times with the first lesson, each
/// with a way back to change it; what the first invoice comes to (its
/// discounts, before VAT); when to pay and what happens if not; and that
/// nothing is charged now.
class ReviewStep extends StatelessWidget {
  const ReviewStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final state = context.watch<StartCubit>().state;
    final cubit = context.read<StartCubit>();
    final plan = state.plan;
    final learner = state.learner;
    final group = state.group;
    final times = state.pickedTimes;
    final first = state.firstPicked?.first;
    final hours = state.options?.unpaidCancelHours ?? 0;
    final many = (state.options?.learners.length ?? 0) > 1;
    if (plan == null) return const SizedBox.shrink();
    return StepBody(
      title: l.startReviewTitle,
      lead: Lead(l.startReviewLead),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (state.submitProblem != null) ...[
            Note(
              context.problemText(state.submitProblem!),
              key: const Key('start-problem'),
              tone: Tone.danger,
              icon: Icons.error_outline_rounded,
            ),
            const Gap(Space.s),
          ],
          StepButton(
            key: const Key('start-confirm'),
            label: state.priceChanged ? l.startConfirmAgain : l.startConfirm,
            icon: Icons.lock_outline_rounded,
            busy: state.busy,
            onPressed: state.busy ? null : cubit.confirm,
          ),
        ],
      ),
      children: [
        const StepNote(),
        LmsCard(
          key: const Key('review-summary'),
          padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.s),
          child: Column(
            children: [
              if (learner != null && many)
                SummaryRow(
                  label: l.startReviewLearner,
                  value: learner.isSelf ? l.startWhoSelf : learner.name,
                  icon: Icons.person_outline_rounded,
                  onChange: () => cubit.edit(StartStep.learner),
                ),
              SummaryRow(
                label: l.startReviewSubject,
                value: state.subject?.name ?? '',
                icon: Icons.menu_book_rounded,
                onChange: () => cubit.edit(StartStep.subject),
              ),
              SummaryRow(
                label: l.startReviewPlan,
                value: plan.name,
                detail: [
                  plan.group ? l.groupPlan : l.formatPrivate,
                  if (plan.perWeek > 0) l.trialPlanPerWeek(plan.perWeek),
                  if (plan.sessionMinutes > 0) l.trialPlanLength(l.minutes(plan.sessionMinutes)),
                ].join(' · '),
                icon: plan.group ? Icons.groups_outlined : Icons.workspace_premium_outlined,
                onChange: () => cubit.edit(StartStep.plan),
              ),
              if (plan.group && group != null) ...[
                SummaryRow(
                  label: l.startReviewGroup,
                  value: group.name,
                  detail: [l.startGroupWith(group.teacher), ...group.times.map((t) => t.label)].join('\n'),
                  icon: Icons.groups_rounded,
                  onChange: () => cubit.edit(StartStep.group),
                ),
                if (group.forming)
                  Padding(
                    padding: const EdgeInsets.only(bottom: Space.s),
                    child: Note(l.startGroupToStart(group.toStart), tone: Tone.info, icon: Icons.hourglass_top_rounded),
                  ),
              ] else ...[
                SummaryRow(
                  label: l.startReviewTeacher,
                  value: state.teacher?.name ?? '',
                  icon: Icons.school_outlined,
                  onChange: () => cubit.edit(StartStep.teacher),
                ),
                SummaryRow(
                  key: const Key('review-times'),
                  label: l.startReviewTimes,
                  value: times.map((t) => t.label).join('\n'),
                  icon: Icons.event_repeat_rounded,
                  onChange: () => cubit.edit(StartStep.times),
                ),
                if (first != null) SummaryRow(label: l.startReviewFirst, value: first.label, icon: Icons.flag_outlined),
              ],
            ],
          ),
        ),
        const Gap(Space.l),
        GroupLabel(l.startReviewMoney),
        LmsCard(
          key: const Key('review-money'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              MoneyRow(label: '${l.startBlockPrice} · ${l.lessonsCount(plan.perBlock)}', amount: plan.price.text),
              for (final d in plan.discounts) MoneyRow(label: d.label, amount: d.amount.text, discount: true),
              Divider(height: Space.l, color: c.line),
              MoneyRow(label: l.startReviewTotal, amount: (plan.firstTotal ?? plan.price).text, total: true),
              const Gap(Space.xs),
              Text(l.startReviewVat, style: context.type.caption),
              if (plan.renewsAt > 0) ...[const Gap(Space.s), Text(l.startRenews(plan.renewsAt), style: context.type.caption)],
            ],
          ),
        ),
        const Gap(Space.l),
        Note(
          [hours > 0 ? l.startReviewPayBy(hours) : l.startReviewPayAny, l.startReviewCancel].join(' '),
          key: const Key('review-pay-by'),
          icon: Icons.schedule_rounded,
        ),
      ],
    );
  }
}

/// A child who waits for a guardian's consent: the wording in full, then "I
/// agree" sends the same start again.
class ConsentStep extends StatelessWidget {
  const ConsentStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final state = context.watch<StartCubit>().state;
    final consent = state.consent;
    if (consent == null) return const SizedBox.shrink();
    final text = consent.text;
    return StepBody(
      badge: Icons.verified_user_outlined,
      title: l.startConsentTitle(givenName(consent.name)),
      lead: state.note == null ? null : Lead(state.note!.text),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (state.consentProblem != null) ...[
            Note(context.problemText(state.consentProblem!), tone: Tone.danger, icon: Icons.error_outline_rounded),
            const Gap(Space.s),
          ],
          if (state.submitProblem != null) ...[
            Note(context.problemText(state.submitProblem!), tone: Tone.danger, icon: Icons.error_outline_rounded),
            const Gap(Space.s),
          ],
          StepButton(
            key: const Key('consent-agree'),
            label: l.startConsentAgree,
            icon: Icons.check_rounded,
            busy: state.busy,
            onPressed: state.busy ? null : context.read<StartCubit>().agree,
          ),
        ],
      ),
      children: [
        Container(
          key: const Key('consent-text'),
          padding: const EdgeInsets.all(Space.l),
          decoration: BoxDecoration(
            color: c.page,
            borderRadius: BorderRadius.circular(context.shape.card),
            border: Border.all(color: c.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(text.title, style: context.type.cardTitle),
              const Gap(Space.s),
              Text(text.intro, style: context.type.body.copyWith(height: 1.7)),
              for (final s in text.sections) ...[
                const Gap(Space.m),
                Text(s.title, style: context.type.body.copyWith(fontWeight: FontWeight.w700)),
                const Gap(Space.xs),
                Text(s.body, style: context.type.body.copyWith(height: 1.7, color: c.muted)),
              ],
              const Gap(Space.m),
              Text(text.agree, style: context.type.body.copyWith(fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      ],
    );
  }
}

/// A refusal with a way on of its own: a course in the subject already (open
/// it, pay it, or call off one the family started), or the academy not
/// taking students this way now (message it).
class BlockedStep extends StatelessWidget {
  const BlockedStep({super.key});

  Future<void> _cancel(BuildContext context, String enrollmentId) async {
    final l = context.l10n;
    final sure = await confirmDestructive(
      context,
      title: l.startCancelTitle,
      body: l.startCancelBody,
      confirm: l.startCancelYes,
      keep: l.startCancelKeep,
    );
    if (!sure || !context.mounted) return;
    final done = await context.read<StartCubit>().cancelPending(enrollmentId);
    if (done != null && context.mounted) DoneMoment.show(context, title: done);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = context.watch<StartCubit>().state;
    final block = state.block;
    if (block == null) return const SizedBox.shrink();
    final (icon, tone) = switch (block.reason) {
      StartReason.coursePendingOwn || StartReason.coursePending => (Icons.receipt_long_rounded, Tone.attention),
      StartReason.courseExists => (Icons.event_available_rounded, Tone.brand),
      _ => (Icons.mail_outline_rounded, Tone.info),
    };
    final enrollment = block.enrollmentId;
    final actions = <Widget>[
      switch (block.reason) {
        StartReason.coursePendingOwn || StartReason.coursePending => StepButton(
          key: const Key('blocked-money'),
          label: l.trialOpenMoney,
          icon: Icons.account_balance_wallet_outlined,
          onPressed: () => context.go('/family/money'),
        ),
        StartReason.courseExists => StepButton(
          key: const Key('blocked-lessons'),
          label: l.startOpenLessons,
          icon: Icons.calendar_month_outlined,
          onPressed: () => context.go('/family/lessons'),
        ),
        _ when block.code == 'family_only' => StepButton(label: l.close, onPressed: () => context.go('/')),
        _ => StepButton(
          key: const Key('blocked-help'),
          label: l.startOffHelp,
          icon: Icons.forum_outlined,
          onPressed: () => context.go('/family/me/help'),
        ),
      },
      if (block.reason == StartReason.coursePendingOwn && enrollment != null) ...[
        const Gap(Space.s),
        SecondaryAction(
          l.startCancelPending,
          key: const Key('blocked-cancel'),
          icon: Icons.event_busy_outlined,
          onPressed: state.busy ? null : () => _cancel(context, enrollment),
        ),
      ],
    ];
    return StepBody(
      title: l.startBlockedTitle,
      bottom: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: actions),
      children: [
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: IconBadge(icon, tone: tone, size: 64),
        ),
        const Gap(Space.l),
        Text(block.text, key: const Key('blocked-text'), style: context.type.body.copyWith(height: 1.75, fontSize: 16)),
        if (state.submitProblem != null) ...[
          const Gap(Space.l),
          Note(context.problemText(state.submitProblem!), tone: Tone.danger, icon: Icons.error_outline_rounded),
        ],
      ],
    );
  }
}

/// The academy enrols students itself: its words, and the way to message it.
class OffStep extends StatelessWidget {
  const OffStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final why = context.select<StartCubit, String?>((c) => c.state.options?.whyOff);
    return StepBody(
      badge: Icons.support_agent_rounded,
      title: l.startOffTitle,
      lead: why == null ? null : Lead(why),
      bottom: StepButton(
        key: const Key('off-help'),
        label: l.startOffHelp,
        icon: Icons.forum_outlined,
        onPressed: () => context.go('/family/me/help'),
      ),
      children: const [],
    );
  }
}
