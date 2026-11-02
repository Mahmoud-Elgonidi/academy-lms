import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../trial/presentation/widgets.dart';
import '../../domain/start.dart';
import '../start_cubit.dart';
import '../widgets.dart';

/// Why the family is back on a step (a time taken meanwhile, a teacher gone),
/// or what just happened (a course called off), read out as it appears.
class StepNote extends StatelessWidget {
  const StepNote({super.key});

  @override
  Widget build(BuildContext context) {
    final note = context.select<StartCubit, StartNote?>((c) => c.state.note);
    final shown = note == null || note.text.isEmpty
        ? const SizedBox(width: double.infinity)
        : Padding(
            padding: const EdgeInsets.only(bottom: Space.l),
            child: Note(
              note.text,
              key: const Key('start-note'),
              tone: note.warning ? Tone.attention : Tone.brand,
              icon: note.warning ? Icons.info_outline_rounded : Icons.check_circle_outline_rounded,
            ),
          );
    return Semantics(
      liveRegion: true,
      child: context.calm ? shown : AnimatedSize(duration: const Duration(milliseconds: 200), child: shown),
    );
  }
}

/// The step's main button, at the foot of the screen.
class StepButton extends StatelessWidget {
  const StepButton({required this.label, required this.onPressed, this.busy = false, this.icon, super.key});

  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 54,
    child: PrimaryAction(label, icon: icon, busy: busy, onPressed: onPressed),
  );
}

/// Who the course is for: each child, or the adult themself.
class LearnerStep extends StatelessWidget {
  const LearnerStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = context.watch<StartCubit>().state;
    final cubit = context.read<StartCubit>();
    return StepBody(
      title: l.startWhoTitle,
      lead: Lead(l.startWhoLead),
      children: [
        const StepNote(),
        for (final x in state.options?.learners ?? const <StartLearner>[]) ...[
          ChoiceCard(
            key: Key('learner-${x.id}'),
            title: x.isSelf ? l.startWhoSelf : x.name,
            subtitle: x.consentWaiting ? l.startWhoConsent : null,
            icon: x.isSelf ? Icons.person_rounded : Icons.child_care_rounded,
            selected: state.learnerId == x.id && state.history.isNotEmpty,
            onTap: () => cubit.pickLearner(x.id),
          ),
          const Gap(Space.m),
        ],
      ],
    );
  }
}

/// The subject: those with a teacher taking new students; one the learner
/// studies already says so in the API's words, with the way to it.
class SubjectStep extends StatelessWidget {
  const SubjectStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = context.watch<StartCubit>().state;
    final cubit = context.read<StartCubit>();
    final learner = state.learner;
    final subjects = state.options?.subjects ?? const <StartSubject>[];
    final self = learner == null || learner.isSelf;
    return StepBody(
      title: self ? l.startSubjectTitleSelf : l.startSubjectTitle(givenName(learner.name)),
      lead: Lead(l.startSubjectLead),
      children: [
        const StepNote(),
        if (subjects.isEmpty) Note(l.startSubjectNone, key: const Key('start-no-subjects'), icon: Icons.mail_outline_rounded),
        for (final s in subjects) ...[
          if (s.canStart)
            ChoiceCard(
              key: Key('subject-${s.id}'),
              title: s.name,
              icon: Icons.menu_book_rounded,
              selected: state.subjectId == s.id && state.step != StartStep.subject,
              onTap: () => cubit.pickSubject(s.id),
            )
          else
            _TakenSubject(subject: s),
          const Gap(Space.m),
        ],
        if (state.busy) const Center(child: CircularProgressIndicator.adaptive()),
      ],
    );
  }
}

/// A subject the learner already studies (or whose course waits for payment): why not, and the way to it.
class _TakenSubject extends StatelessWidget {
  const _TakenSubject({required this.subject});

  final StartSubject subject;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final s = subject;
    final (label, tone) = switch (s.courseStatus) {
      'pending_payment' => (l.startCourseWaiting, Tone.attention),
      'paused' => (l.startCoursePaused, Tone.neutral),
      _ => (l.startCourseActive, Tone.brand),
    };
    return Container(
      key: Key('subject-taken-${s.id}'),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: c.page,
        borderRadius: BorderRadius.circular(context.shape.card),
        border: Border.all(color: c.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const IconBadge(Icons.menu_book_rounded, tone: Tone.neutral, size: 40),
              const Gap(Space.m),
              Expanded(child: Text(s.name, style: context.type.cardTitle)),
              StatusChip(label, tone: tone),
            ],
          ),
          if (s.whyNot != null) ...[const Gap(Space.s), Text(s.whyNot!, style: context.type.caption.copyWith(height: 1.6))],
          const Gap(Space.xs),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(
              style: TextButton.styleFrom(foregroundColor: c.brandDeep, padding: EdgeInsets.zero, visualDensity: VisualDensity.compact),
              onPressed: () => context.go(s.waitingPayment ? '/family/money' : '/family/lessons'),
              icon: Icon(s.waitingPayment ? Icons.account_balance_wallet_outlined : Icons.calendar_month_outlined, size: 18),
              label: Text(s.waitingPayment ? l.trialOpenMoney : l.startOpenLessons),
            ),
          ),
        ],
      ),
    );
  }
}

/// The plans priced for the family: one to one and group never alike, the
/// recommended one marked, the first invoice with the offer struck through.
class PlanStep extends StatelessWidget {
  const PlanStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = context.watch<StartCubit>().state;
    final cubit = context.read<StartCubit>();
    final options = state.options;
    final plans = options?.plans ?? const <StartPlan>[];
    final region = options?.region;
    final ends = options?.offerEndsAt;
    return StepBody(
      title: l.startPlanTitle,
      lead: Lead(region == null ? l.startPlanLeadAny : l.startPlanLead(region)),
      bottom: plans.isEmpty
          ? null
          : StepButton(
              key: const Key('plan-next'),
              label: l.startContinue,
              icon: Icons.arrow_forward_rounded,
              onPressed: state.plan == null ? null : cubit.planNext,
            ),
      children: [
        const StepNote(),
        if (options != null && options.offerOpen) ...[
          Note(
            l.startOfferUntil(options.offerPercentText, ends == null ? '' : ends.label),
            key: const Key('start-offer'),
            tone: Tone.attention,
            icon: Icons.local_offer_rounded,
          ),
          const Gap(Space.l),
        ],
        if (plans.isEmpty) Note(l.startPlanNone, key: const Key('start-no-plans'), icon: Icons.mail_outline_rounded),
        for (final p in plans) ...[
          PlanChoice(
            plan: p,
            selected: state.planId == p.id,
            recommended: state.recommendedPlanId == p.id,
            onTap: () => cubit.pickPlan(p.id),
          ),
          const Gap(Space.m),
        ],
      ],
    );
  }
}

/// The teacher: the trial's first with a badge, each with their card's photo and words.
class TeacherStep extends StatelessWidget {
  const TeacherStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = context.watch<StartCubit>().state;
    final cubit = context.read<StartCubit>();
    final teachers = state.options?.privateTeachers ?? const <StartTeacher>[];
    final gone = state.options?.trialTeacherAvailable == false;
    return StepBody(
      title: l.startTeacherTitle,
      lead: Lead(l.startTeacherLead(state.subject?.name ?? '')),
      bottom: teachers.isEmpty
          ? null
          : StepButton(
              key: const Key('teacher-next'),
              label: l.startContinue,
              icon: Icons.arrow_forward_rounded,
              onPressed: state.teacherId == null ? null : cubit.teacherNext,
            ),
      children: [
        const StepNote(),
        if (gone) ...[Note(l.startTrialTeacherGone, key: const Key('trial-teacher-gone'), tone: Tone.attention), const Gap(Space.l)],
        if (teachers.isEmpty)
          Note(
            l.startTeacherNone,
            key: const Key('start-no-teachers'),
            icon: Icons.mail_outline_rounded,
            action: OutlinedButton(onPressed: cubit.back, child: Text(l.startOtherPlan)),
          ),
        for (final t in teachers) ...[
          _TeacherCard(teacher: t, selected: state.teacherId == t.id, onTap: () => cubit.pickTeacher(t.id)),
          const Gap(Space.m),
        ],
      ],
    );
  }
}

class _TeacherCard extends StatelessWidget {
  const _TeacherCard({required this.teacher, required this.selected, required this.onTap});

  final StartTeacher teacher;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final t = teacher;
    return Semantics(
      button: true,
      selected: selected,
      label: joinSpoken(context, [t.name, if (t.trialTeacher) l.startTrialTeacher, ?t.bio]),
      excludeSemantics: true,
      child: PressScale(
        onTap: onTap,
        child: AnimatedContainer(
          key: Key('teacher-${t.id}'),
          duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: selected ? c.brandSoft : c.card,
            borderRadius: BorderRadius.circular(context.shape.card),
            border: Border.all(color: selected ? c.brand : c.line, width: selected ? 2 : 1),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TeacherFace(name: t.name, photoUrl: t.photoUrl),
              const Gap(Space.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(t.name, style: context.type.cardTitle),
                    if (t.trialTeacher) ...[
                      const Gap(Space.xs),
                      StatusChip(
                        l.startTrialTeacher,
                        tone: Tone.reward,
                        icon: Icons.verified_rounded,
                        key: const Key('trial-teacher-badge'),
                      ),
                    ],
                    if (t.bio != null) ...[
                      const Gap(Space.xs),
                      Text(t.bio!, maxLines: 3, overflow: TextOverflow.ellipsis, style: context.type.caption),
                    ],
                  ],
                ),
              ),
              const Gap(Space.s),
              Icon(
                selected ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                color: selected ? c.brand : c.faint,
                size: 26,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// The open groups of a group plan: the teacher, the set times, the seats as
/// dots, forming (how many more it needs) or under way (its next lesson).
class GroupStep extends StatelessWidget {
  const GroupStep({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final state = context.watch<StartCubit>().state;
    final cubit = context.read<StartCubit>();
    final slots = state.slots;
    final groups = slots?.groups ?? const <OpenGroup>[];
    final learner = state.learner;
    return StepBody(
      title: l.startGroupTitle,
      lead: Lead(l.startGroupLead(learner == null || learner.isSelf ? l.startWhoSelf : givenName(learner.name))),
      bottom: groups.isEmpty
          ? null
          : StepButton(
              key: const Key('group-next'),
              label: l.startContinue,
              icon: Icons.arrow_forward_rounded,
              onPressed: state.group == null ? null : cubit.groupNext,
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
        else if (groups.isEmpty)
          Note(l.startGroupNone, key: const Key('start-no-groups'), icon: Icons.groups_outlined)
        else
          for (final g in groups) ...[
            _GroupCard(group: g, selected: state.groupId == g.id, onTap: () => cubit.pickGroup(g.id)),
            const Gap(Space.m),
          ],
      ],
    );
  }
}

class _GroupCard extends StatelessWidget {
  const _GroupCard({required this.group, required this.selected, required this.onTap});

  final OpenGroup group;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final g = group;
    return Semantics(
      button: true,
      selected: selected,
      label: joinSpoken(context, [
        g.name,
        l.startGroupWith(g.teacher),
        ...g.times.map((t) => t.label),
        l.groupSeatsSpoken(g.taken, g.capacity),
        g.forming ? l.startGroupToStart(g.toStart) : l.startGroupRunning,
      ]),
      excludeSemantics: true,
      child: PressScale(
        onTap: onTap,
        child: AnimatedContainer(
          key: Key('group-${g.id}'),
          duration: context.calm ? Duration.zero : const Duration(milliseconds: 220),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: selected ? c.infoSoft : c.card,
            borderRadius: BorderRadius.circular(context.shape.card),
            border: Border.all(color: selected ? c.info : c.line, width: selected ? 2 : 1),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const IconBadge(Icons.groups_rounded, tone: Tone.info, size: 40),
                  const Gap(Space.m),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(g.name, style: context.type.cardTitle),
                        Text(l.startGroupWith(g.teacher), style: context.type.caption),
                      ],
                    ),
                  ),
                  Icon(
                    selected ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                    color: selected ? c.info : c.faint,
                    size: 26,
                  ),
                ],
              ),
              const Gap(Space.m),
              Wrap(spacing: 6, runSpacing: 6, children: [for (final t in g.times) StatusChip(t.label, icon: Icons.event_repeat_rounded)]),
              const Gap(Space.m),
              Row(
                children: [
                  GroupPill(taken: g.taken, capacity: g.capacity),
                  const Gap(Space.s),
                  Expanded(
                    child: Text(
                      l.startGroupFree(g.free),
                      style: context.type.caption.copyWith(color: c.infoInk, fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
              const Gap(Space.s),
              if (g.forming)
                StatusChip(
                  '${l.startGroupForming} · ${l.startGroupToStart(g.toStart)}',
                  tone: Tone.attention,
                  icon: Icons.hourglass_top_rounded,
                )
              else ...[
                StatusChip(l.startGroupRunning, tone: Tone.brand, icon: Icons.play_circle_outline_rounded),
                if (g.next != null) ...[const Gap(Space.xs), Text(l.startGroupNext(g.next!.when(context)), style: context.type.caption)],
                if (g.held > 0) ...[const Gap(Space.xs), Text(l.startGroupHeld(g.held), style: context.type.caption)],
              ],
            ],
          ),
        ),
      ),
    );
  }
}
