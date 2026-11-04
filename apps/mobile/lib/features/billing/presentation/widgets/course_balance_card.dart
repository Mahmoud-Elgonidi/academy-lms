import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/course_days.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/course_state.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/billing_repository.dart';
import '../../domain/billing.dart';
import '../../domain/cards.dart';
import '../../domain/course_balance.dart';

/// A course on the family's balance with what its own balance says, or null when it could not be read.
typedef CourseLine = ({Balance course, CourseBalance? balance});

/// Each course's balance card, for the courses a learner's balance lists
/// ([courses], from `GET family/balance`): read again on the payments and
/// lessons signals, a card each, in order. A course whose balance could not
/// be read still shows its lessons left.
class CourseBalances extends StatelessWidget {
  const CourseBalances({required this.courses, this.open = true, super.key});

  final List<Balance> courses;

  /// Each card opens the course's balance page.
  final bool open;

  @override
  Widget build(BuildContext context) {
    final ids = courses.map((c) => c.enrollmentId).where((id) => id.isNotEmpty).toList();
    if (ids.isEmpty) return const SizedBox.shrink();
    return BlocProvider(
      key: ValueKey(ids.join(',')),
      create: (c) {
        final repo = c.read<BillingRepository>();
        Future<CourseLine> one(Balance course) async {
          try {
            return (course: course, balance: await repo.courseBalance(course.enrollmentId));
          } on ApiProblem {
            return (course: course, balance: null);
          }
        }

        return LoadCubit<List<CourseLine>>(
          () => Future.wait([for (final course in courses.where((x) => x.enrollmentId.isNotEmpty)) one(course)]),
          refreshOn: c.read<Live>().on({Live.payments, Live.lessons}),
        )..load();
      },
      child: BlocBuilder<LoadCubit<List<CourseLine>>, LoadState<List<CourseLine>>>(
        builder: (context, state) {
          final lines = state.data;
          if (lines == null) {
            return Column(
              children: [
                for (final _ in ids) ...[Skeleton(height: 150, radius: context.shape.card), const Gap(Space.m)],
              ],
            );
          }
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final (i, line) in lines.indexed) ...[
                if (i > 0) const Gap(Space.m),
                CourseBalanceCard(
                  key: Key('balance-${line.course.enrollmentId}'),
                  subject: line.course.shared ? context.l10n.sharedPackage(line.course.subject) : line.course.subject,
                  balance: line.balance,
                  fallback: line.course,
                  onTap: open && line.balance != null && line.balance!.classic
                      ? () => context.go('/family/money/courses/${line.course.enrollmentId}')
                      : null,
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

/// One course's balance, said plainly: the lessons left on a calm bar (with
/// the mark where the next invoice goes out), the plan in words, a warning
/// when unbooked lessons expire within a week, a paused or ending course,
/// the next package's invoice waiting with the way to pay it, or when the
/// next invoice comes and about how much. The kids' look shows the lessons
/// left alone, in simple words; middle school and teens no money (the API
/// leaves it out for them).
class CourseBalanceCard extends StatelessWidget {
  const CourseBalanceCard({required this.subject, this.balance, this.fallback, this.onTap, this.planName = false, super.key});

  final String subject;

  /// The plan's own name under the subject (its page); the card elsewhere says its shape in words alone.
  final bool planName;
  final CourseBalance? balance;

  /// What the learner's balance said, shown alone when [balance] could not be read.
  final Balance? fallback;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final b = balance;
    if (b == null) return _Fallback(subject: subject, course: fallback);
    if (b.kids) return _KidsBalance(subject: subject, balance: b);
    final l = context.l10n;
    final c = context.colors;
    final plan = b.plan;
    final left = b.lessonsLeft;
    final card = LmsCard(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(Space.l, Space.l, Space.l, Space.m + 2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(subject, style: context.type.cardTitle, maxLines: 2, overflow: TextOverflow.ellipsis),
                    if (planName && plan != null && plan.name.isNotEmpty) Text(plan.name, style: context.type.caption),
                  ],
                ),
              ),
              if (b.days.stand != CourseStand.on) ...[const Gap(Space.s), CourseStatePill(b.days)],
              if (onTap != null) ...[const Gap(Space.xs), Icon(Icons.chevron_right_rounded, color: c.faint, semanticLabel: l.balanceOpen)],
            ],
          ),
          const Gap(Space.m),
          if (left != null) ...[
            Wrap(
              crossAxisAlignment: WrapCrossAlignment.end,
              spacing: Space.s,
              children: [
                Text(
                  left == 0 ? l.balanceNone : l.lessonsCount(left),
                  key: const Key('balance-left'),
                  style: context.type.number.copyWith(fontSize: 26, color: b.low ? c.attentionInk : c.ink),
                ),
                if (left > 0)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 3),
                    child: Text(
                      (plan?.perBlock ?? 0) > 0 ? l.balanceLeftOf(l.lessonsCount(plan!.perBlock)) : l.balanceLeftPlain,
                      style: context.type.caption,
                    ),
                  ),
              ],
            ),
            if ((b.makeupLessons ?? 0) > 0) Text(l.balanceMakeup(b.makeupLessons!), style: context.type.caption),
            if (b.share case final share?) ...[
              const Gap(Space.m),
              BalanceBar(
                share: share,
                mark: b.classic ? b.mark : null,
                low: b.low,
                label: l.balanceBarLabel(l.lessonsCount(left), l.lessonsCount(plan!.perBlock)),
              ),
            ],
          ] else if (b.lessonsText != null)
            Text(b.lessonsText!, style: context.type.cardTitle),
          if (plan != null) ...[const Gap(Space.m), _PlanShape(plan: plan)],
          if (b.expiry case final expiry? when expiry.text.isNotEmpty) ...[
            const Gap(Space.m),
            expiry.soon
                ? Note(expiry.text, key: const Key('balance-expiry-soon'), tone: Tone.attention, icon: Icons.timer_outlined)
                : _Line(key: const Key('balance-expiry'), icon: Icons.event_available_outlined, text: expiry.text),
          ],
          if (b.renewalNote case final note?) ...[
            const Gap(Space.m),
            Note(note, key: const Key('balance-renewal-note'), tone: Tone.neutral, icon: Icons.pause_circle_outline_rounded),
          ],
          if (b.waiting case final waiting?) ...[const Gap(Space.m), _Waiting(invoice: waiting)],
          if (b.next case final next? when b.waiting == null) ...[const Gap(Space.m), _Next(next: next)],
          if (b.autoRenew case final auto? when b.classic) ...[const Gap(Space.m), _AutoRenewLine(auto: auto)],
        ],
      ),
    );
    // Read out as something that opens, as it looks.
    return onTap == null ? card : Semantics(button: true, child: card);
  }
}

/// The lessons left on a calm bar: full is a whole package, and a small mark
/// stands where the next invoice goes out.
class BalanceBar extends StatelessWidget {
  const BalanceBar({required this.share, required this.label, this.mark, this.low = false, super.key});

  final double share;
  final double? mark;
  final bool low;
  final String label;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    final mark = this.mark;
    return Semantics(
      label: label,
      excludeSemantics: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 18,
            child: Stack(
              alignment: Alignment.center,
              children: [
                ProgressBar(share, height: 10, colour: low ? c.attention : c.brand),
                if (mark != null)
                  Align(
                    key: const Key('balance-mark'),
                    alignment: AlignmentDirectional(-1 + 2 * mark, 0),
                    child: Container(
                      width: 3,
                      height: 18,
                      decoration: BoxDecoration(
                        color: c.ink,
                        borderRadius: BorderRadius.circular(2),
                        border: Border.all(color: c.card, width: 0.5),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          if (mark != null) ...[
            const Gap(Space.xs),
            Row(
              children: [
                Container(
                  width: 3,
                  height: 11,
                  decoration: BoxDecoration(color: c.ink, borderRadius: BorderRadius.circular(2)),
                ),
                const Gap(Space.s),
                Expanded(child: Text(l.balanceMarkHint, style: context.type.label)),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// The plan in plain words: lessons a week, a lesson's length, group or shared.
class _PlanShape extends StatelessWidget {
  const _PlanShape({required this.plan});

  final BalancePlan plan;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Wrap(
      key: const Key('balance-plan'),
      spacing: 6,
      runSpacing: 6,
      children: [
        if (plan.perWeek > 0) Pill(l.balancePerWeek(plan.perWeek), icon: Icons.event_repeat_rounded),
        if (plan.minutes > 0) Pill(l.balancePerLesson(l.minutes(plan.minutes)), icon: Icons.timer_outlined),
        if (plan.group) Pill(l.balanceGroup, tone: Tone.info, icon: Icons.groups_rounded),
        if (plan.shared) Pill(l.balanceShared, tone: Tone.info, icon: Icons.all_inclusive_rounded),
      ],
    );
  }
}

/// The next package's invoice, waiting: which, what is left, by when, and Pay (its payer only).
class _Waiting extends StatelessWidget {
  const _Waiting({required this.invoice});

  final WaitingInvoice invoice;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final id = invoice.id;
    return Container(
      key: const Key('balance-waiting'),
      width: double.infinity,
      padding: const EdgeInsets.all(Space.m + 2),
      decoration: BoxDecoration(
        color: c.attentionSoft,
        borderRadius: BorderRadius.circular(context.shape.field),
        border: Border.all(color: c.attention.withValues(alpha: 0.4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.receipt_long_rounded, size: 18, color: c.attentionInk),
              const Gap(Space.s),
              Expanded(
                child: Text(
                  l.balanceWaitingTitle,
                  style: context.type.body.copyWith(fontWeight: FontWeight.w700, color: c.attentionInk),
                ),
              ),
            ],
          ),
          const Gap(Space.xs),
          Text(l.balanceWaitingLine(invoice.number, invoice.outstanding), style: context.type.body),
          if (invoice.dueAt case final due?)
            Text(l.payDueOn('${due.day(context.locale)} · ${due.clock(context.locale)}'), style: context.type.caption),
          const Gap(Space.m),
          if (id != null)
            SizedBox(
              width: double.infinity,
              height: 48,
              child: FilledButton.icon(
                key: const Key('balance-pay'),
                style: FilledButton.styleFrom(backgroundColor: c.attentionInk, foregroundColor: Colors.white),
                onPressed: PressScale.withPress(context, () => context.push('/family/pay/$id')),
                icon: const Icon(Icons.lock_outline_rounded, size: 18),
                label: Text(l.payAmount(invoice.outstanding), style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
            )
          else
            Text(l.balanceNotPayer, key: const Key('balance-not-payer'), style: context.type.caption),
        ],
      ),
    );
  }
}

/// The next invoice still to come: about how much, when (after how many lessons, around which day), and that it is an estimate.
class _Next extends StatelessWidget {
  const _Next({required this.next});

  final NextInvoice next;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final day = next.on?.day(context.locale);
    final when = next.now
        ? l.balanceNextNow
        : day != null
        ? l.balanceNextWhen(next.atLessonsLeft, day)
        : next.text;
    return Container(
      key: const Key('balance-next'),
      width: double.infinity,
      padding: const EdgeInsets.all(Space.m + 2),
      decoration: BoxDecoration(color: c.page, borderRadius: BorderRadius.circular(context.shape.field)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.receipt_outlined, size: 18, color: c.muted),
              const Gap(Space.s),
              Expanded(child: Text(l.balanceNextTitle, style: context.type.label)),
              if (next.amount.isNotEmpty)
                Text(
                  l.balanceNextAbout(next.amount),
                  key: const Key('balance-next-amount'),
                  style: context.type.cardTitle.copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
                ),
            ],
          ),
          const Gap(Space.s),
          Text(when, style: context.type.body.copyWith(height: 1.6)),
          if (!next.now && next.lessonsUntil > 0) ...[
            const Gap(Space.s),
            Pill(l.balanceNextAfter(next.lessonsUntil), tone: Tone.info, icon: Icons.hourglass_bottom_rounded),
          ],
          const Gap(Space.s),
          Text(l.balanceEstimate, style: context.type.caption),
        ],
      ),
    );
  }
}

/// Automatic renewal on this course (J5b, its payer only): on or off in the API's sentence, the waiting invoice's
/// charge (how much, the moment as the API wrote it), a card that expires soon, and the way to the cards screen.
class _AutoRenewLine extends StatelessWidget {
  const _AutoRenewLine({required this.auto});

  final CourseAutoRenew auto;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final next = auto.next;
    return Container(
      key: const Key('balance-auto'),
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(Space.m + 2, Space.m, Space.m + 2, Space.xs),
      decoration: BoxDecoration(color: auto.on ? c.brandSoft : c.page, borderRadius: BorderRadius.circular(context.shape.field)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(auto.on ? Icons.autorenew_rounded : Icons.credit_card_outlined, size: 18, color: auto.on ? c.brandDeep : c.muted),
              const Gap(Space.s),
              Expanded(child: Text(l.cardsAutoTitle, style: context.type.label)),
              StatusChip(
                auto.on ? l.balanceAutoOn : l.balanceAutoOff,
                key: const Key('balance-auto-state'),
                tone: auto.on ? Tone.brand : Tone.neutral,
              ),
            ],
          ),
          if (auto.text.isNotEmpty) ...[
            const Gap(Space.s),
            Text(auto.text, key: const Key('balance-auto-text'), style: context.type.body.copyWith(height: 1.6)),
          ],
          if (next case final charge? when auto.on) ...[
            const Gap(Space.s),
            Row(
              key: const Key('balance-auto-next'),
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Icon(
                    Icons.event_repeat_rounded,
                    size: 16,
                    color: charge.status == ChargeStatus.retry ? c.attentionInk : c.brandDeep,
                  ),
                ),
                const Gap(Space.s),
                Expanded(
                  child: Text(
                    l.balanceAutoNext(charge.amount, charge.at?.label ?? ''),
                    style: context.type.body.copyWith(fontWeight: FontWeight.w700, color: c.brandDeep),
                  ),
                ),
              ],
            ),
          ],
          if (auto.card?.expiresSoon ?? false) ...[
            const Gap(Space.s),
            Note(l.cardsExpiresSoon, key: const Key('balance-auto-expiring'), tone: Tone.attention, icon: Icons.event_busy_outlined),
          ],
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(
              key: const Key('balance-auto-manage'),
              style: TextButton.styleFrom(padding: EdgeInsets.zero, visualDensity: VisualDensity.compact, foregroundColor: c.brandDeep),
              onPressed: () => context.go('/family/money/cards'),
              icon: const Icon(Icons.credit_card_rounded, size: 18),
              label: Text(l.balanceAutoManage),
            ),
          ),
        ],
      ),
    );
  }
}

/// The kids' look: the lessons left as stars, in simple words; no plan, no money.
class _KidsBalance extends StatelessWidget {
  const _KidsBalance({required this.subject, required this.balance});

  final String subject;
  final CourseBalance balance;

  static const _most = 12;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final left = balance.lessonsLeft ?? 0;
    return LmsCard(
      key: const Key('balance-kids'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(subject, style: context.type.cardTitle),
          const Gap(Space.s),
          Text(
            l.balanceKidsLeft(left),
            key: const Key('balance-kids-left'),
            style: context.type.title.copyWith(color: c.brandDeep),
          ),
          if (left > 0) ...[
            const Gap(Space.m),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (var i = 0; i < left.clamp(0, _most); i++) Icon(Icons.star_rounded, color: c.reward, size: 28).appear(context, i),
                if (left > _most) Text('+${left - _most}', style: context.type.cardTitle.copyWith(color: c.muted)),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// A course whose balance could not be read: its lessons left from the learner's balance, and that the rest did not load.
class _Fallback extends StatelessWidget {
  const _Fallback({required this.subject, this.course});

  final String subject;
  final Balance? course;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(subject, style: context.type.cardTitle)),
              if (course case final b?) StatusChip(l.lessonsCount(b.sessions), tone: Tone.brand),
            ],
          ),
          const Gap(Space.xs),
          Text(l.balanceFailed, style: context.type.caption),
        ],
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.icon, required this.text, super.key});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Icon(icon, size: 16, color: context.colors.muted),
      ),
      const Gap(Space.s),
      Expanded(child: Text(text, style: context.type.caption)),
    ],
  );
}

/// A student's own courses' balances (their More): the lessons left in their
/// look's words, under [title]; nothing at all while there is none, or when
/// it cannot be read (More stands without it).
class StudentBalances extends StatelessWidget {
  const StudentBalances({this.title, super.key});

  final String? title;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) =>
        LoadCubit<List<Balance>>(c.read<BillingRepository>().balance, refreshOn: c.read<Live>().on({Live.payments, Live.lessons}))..load(),
    child: BlocBuilder<LoadCubit<List<Balance>>, LoadState<List<Balance>>>(
      builder: (context, state) {
        final courses = state.data ?? const <Balance>[];
        if (courses.isEmpty) return const SizedBox.shrink();
        return Column(
          key: const Key('student-balances'),
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (title != null) SectionHeader(title!),
            CourseBalances(courses: courses, open: false),
            const Gap(Space.m),
          ],
        );
      },
    ),
  );
}
