import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/modules_of.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/done_moment.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../../start/data/start_repository.dart';
import '../../../start/presentation/start_page.dart';
import '../../data/trial_reports_repository.dart';
import '../../domain/trial_report.dart';
import 'offer_countdown.dart';
import 'trials_page.dart';

/// A trial as its page shows it: the report when it is ready, else the trial
/// itself (missed, cancelled, the report on its way), and the learner it
/// belongs to (a notification may open a child the tabs do not show).
class TrialView {
  const TrialView({this.report, this.trial, this.learnerId});

  final TrialReport? report;
  final FamilyTrial? trial;
  final String? learnerId;

  /// The report, and the trial in its learner's list: the learners on this
  /// account are asked in turn ([learners]: the one on screen first; null
  /// is the student themself). A report not ready is not an error here.
  static Future<TrialView> load(TrialReportsRepository repo, String id, List<String?> learners) async {
    Future<TrialReport?> report() async {
      try {
        return await repo.report(id);
      } on ApiProblem catch (p) {
        if (p.isNotFound) return null;
        rethrow;
      }
    }

    final found = report();
    FamilyTrial? trial;
    String? learnerId;
    for (final learner in learners) {
      try {
        trial = (await repo.trials(learnerId: learner)).trials.where((t) => t.id == id).firstOrNull;
      } on ApiProblem catch (p) {
        if (p.isOffline) rethrow;
      }
      if (trial != null) {
        learnerId = learner;
        break;
      }
    }
    return TrialView(report: await found, trial: trial, learnerId: learnerId ?? learners.firstOrNull);
  }
}

/// A free trial's report for the family (docs/architecture/02, section 27,
/// J2): the learner and subject, when, the teacher with their card's photo,
/// the level found, strengths and what to work on, the teacher's words as a
/// quote; then (the classic look alone) the plan the teacher recommends in
/// plain words with today's price, the offer with its time running down
/// (the plain price once it ends), the way to start, and "not the right
/// teacher?" for the free second trial. A child's look reads the teacher's
/// part in simple words, without prices, the offer or booking.
class TrialReportPage extends StatelessWidget {
  const TrialReportPage({required this.id, required this.base, super.key});

  final String id;
  final String base;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final learners = context.read<LearnerCubit>();
    final family = learners.scope == LearnerScope.family;
    final ids = family ? <String?>{?learners.state.selected?.id, ...learners.state.learners.map((x) => x.id)}.toList() : <String?>[null];
    return Scaffold(
      appBar: AppBar(title: Text(context.isKids ? l.trialReportKidsTitle : l.trialReportTitle)),
      body: BlocProvider(
        create: (c) => LoadCubit<TrialView>(
          () => TrialView.load(c.read<TrialReportsRepository>(), id, ids),
          refreshOn: c.read<Live>().on({Live.lessons, Live.inbox, Live.payments}),
          refreshAt: (v) => v.report?.clockMoments ?? const [],
        )..load(),
        child: BlocBuilder<LoadCubit<TrialView>, LoadState<TrialView>>(
          builder: (context, state) {
            final view = state.data;
            if (view == null) {
              if (state.problem != null) return ErrorState.of(state.problem!, onRetry: () => context.read<LoadCubit<TrialView>>().load());
              return const _Skeleton();
            }
            final report = view.report;
            final Widget body = report != null
                ? TrialReportView(report: report, base: base, learnerId: view.learnerId)
                : _NotReady(trial: view.trial, base: base);
            return RefreshIndicator.adaptive(onRefresh: () => context.read<LoadCubit<TrialView>>().load(), child: body);
          },
        ),
      ),
    );
  }
}

/// No report to read: the trial as it stands (missed, cancelled, still being written), or a calm word.
class _NotReady extends StatelessWidget {
  const _NotReady({required this.trial, required this.base});

  final FamilyTrial? trial;
  final String base;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final t = trial;
    final all = SecondaryAction(l.trialReportAllTrials, icon: Icons.list_alt_rounded, onPressed: () => context.go(base));
    return ListView(
      key: const Key('trial-not-ready'),
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        if (t != null) ...[
          TrialCard(trial: t, base: base, classic: !context.isKids && base.startsWith('/family'), openReport: false),
          const Gap(Space.xl),
          all,
        ] else
          EmptyState(icon: Icons.edit_note_rounded, title: l.trialReportNotReadyTitle, body: l.trialReportNotReadyBody, action: all),
      ]),
    );
  }
}

/// The report itself.
class TrialReportView extends StatefulWidget {
  const TrialReportView({required this.report, required this.base, this.learnerId, this.clock = DateTime.now, super.key});

  final TrialReport report;
  final String base;
  final String? learnerId;
  final DateTime Function() clock;

  @override
  State<TrialReportView> createState() => _TrialReportViewState();
}

class _TrialReportViewState extends State<TrialReportView> {
  /// The countdown reached the end before the reload said so.
  bool _ended = false;

  @override
  void didUpdateWidget(TrialReportView old) {
    super.didUpdateWidget(old);
    if (old.report.offer?.endsAt != widget.report.offer?.endsAt) _ended = false;
  }

  void _offerEnded() {
    setState(() => _ended = true);
    context.read<LoadCubit<TrialView>?>()?.load();
  }

  /// «ابدأ بهذه الباقة»: the start flow with the plan and the trial's teacher chosen; the report reads again on the way back.
  Future<void> _start() async {
    final start = widget.report.start;
    await context.push(
      StartPage.location(
        learner: start?.learnerId ?? widget.learnerId,
        subject: start?.subjectId,
        plan: start?.planId ?? widget.report.recommendation.plan?.id,
        teacher: start?.teacherId,
      ),
    );
    if (mounted) await context.read<LoadCubit<TrialView>?>()?.load();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final r = widget.report;
    final kids = r.kids;
    final next = r.next;
    final sections = <Widget>[
      _Hero(report: r),
      const Gap(Space.l),
      _Level(level: r.level, kids: kids),
      if (r.strengths != null) ...[
        const Gap(Space.m),
        _TextCard(
          key: const Key('report-strengths'),
          title: kids ? l.trialReportStrengthsKids : l.trialReportStrengths,
          text: r.strengths!,
          icon: Icons.thumb_up_alt_outlined,
          tone: Tone.brand,
        ),
      ],
      if (r.toWorkOn != null) ...[
        const Gap(Space.m),
        _TextCard(
          key: const Key('report-to-work-on'),
          title: kids ? l.trialReportToWorkOnKids : l.trialReportToWorkOn,
          text: r.toWorkOn!,
          icon: Icons.flag_outlined,
          tone: Tone.attention,
        ),
      ],
      if (r.note != null) ...[
        const Gap(Space.m),
        _Quote(title: kids ? l.trialReportNoteKids : l.trialReportNote, note: r.note!, by: r.card?.name ?? r.teacher),
      ],
      if (r.classic) ...[
        SectionHeader(l.trialPlanTitle),
        _PlanCard(report: r, open: r.openAt(widget.clock()) && !_ended, onStart: _start, onEnded: _offerEnded),
        if (next != null) ...[const Gap(Space.l), _NextCard(report: r, next: next, base: widget.base)],
      ] else if (r.started) ...[
        const Gap(Space.l),
        Note(kids ? l.trialStartedKids(r.subject) : l.trialStarted(r.subject), tone: Tone.brand, icon: Icons.celebration_outlined),
      ],
    ];
    return ListView(
      key: const Key('trial-report'),
      padding: pagePadding.copyWith(bottom: Space.xxl),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [for (final (i, w) in sections.indexed) w is Gap || w is SizedBox ? w : w.appear(context, i ~/ 2)],
    );
  }
}

/// The trial at a glance, on the academy's soft colour: whose, which subject, when, and the teacher with their photo.
class _Hero extends StatelessWidget {
  const _Hero({required this.report});

  final TrialReport report;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final r = report;
    final card = r.card;
    final name = card?.name.isNotEmpty ?? false ? card!.name : r.teacher;
    return Container(
      key: const Key('report-hero'),
      padding: const EdgeInsets.all(Space.l + 2),
      decoration: BoxDecoration(
        color: c.brandSoft,
        borderRadius: BorderRadius.circular(context.shape.card + 6),
        border: Border.all(color: c.brand.withValues(alpha: 0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (r.second) ...[StatusChip(l.trialReportSecond, tone: Tone.info, icon: Icons.swap_horiz_rounded), const Gap(Space.s)],
          Semantics(
            header: true,
            child: Text(l.trialReportFor(r.subject, r.learner), style: context.type.display.copyWith(color: c.brandDeep)),
          ),
          if (r.heldAt != null) ...[
            const Gap(Space.xs),
            Row(
              children: [
                Icon(Icons.event_available_rounded, size: 18, color: c.brandDeep),
                const Gap(6),
                Expanded(
                  child: Text(r.heldAt!.label, style: context.type.caption.copyWith(color: c.ink)),
                ),
              ],
            ),
          ],
          const Gap(Space.l),
          Container(
            padding: const EdgeInsets.all(Space.m),
            decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(context.shape.card)),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _Photo(url: card?.photoUrl, name: name),
                const Gap(Space.m),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l.trialReportTeacher, style: context.type.label.copyWith(color: c.muted)),
                      Text(name, key: const Key('report-teacher'), style: context.type.cardTitle),
                      if (card?.bio != null) ...[
                        const Gap(2),
                        Text(card!.bio!, maxLines: 3, overflow: TextOverflow.ellipsis, style: context.type.caption),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The teacher's photo from their card, else their initial.
class _Photo extends StatelessWidget {
  const _Photo({required this.url, required this.name});

  final String? url;
  final String name;

  @override
  Widget build(BuildContext context) {
    const size = 56.0;
    // "أ. منى": the initial of the name, not of the title.
    final initial = InitialAvatar(name.contains('. ') ? name.split('. ').last : name, size: size);
    if (url == null) return initial;
    return ClipRRect(
      borderRadius: BorderRadius.circular(context.isKids ? size / 2 : size * 0.32),
      child: Image.network(
        url!,
        key: const Key('report-photo'),
        width: size,
        height: size,
        fit: BoxFit.cover,
        semanticLabel: name,
        errorBuilder: (_, _, _) => initial,
      ),
    );
  }
}

/// The level the teacher found, as a medal: their own words, big.
class _Level extends StatelessWidget {
  const _Level({required this.level, required this.kids});

  final String level;
  final bool kids;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return Semantics(
      container: true,
      child: LmsCard(
        key: const Key('report-level'),
        child: Row(
          children: [
            Container(
              width: 60,
              height: 60,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(colors: [c.brand, c.brandDeep], begin: Alignment.topLeft, end: Alignment.bottomRight),
                boxShadow: [BoxShadow(color: c.brand.withValues(alpha: 0.25), blurRadius: 12, offset: const Offset(0, 4))],
              ),
              child: Icon(kids ? Icons.eco_rounded : Icons.stairs_rounded, color: c.onBrand, size: 30),
            ),
            const Gap(Space.l),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(kids ? l.trialReportLevelKids : l.trialReportLevel, style: context.type.caption),
                  const Gap(2),
                  // A level in a word or two stands big; a sentence reads as a card's title.
                  Text(level, style: (level.length > 36 ? context.type.cardTitle : context.type.title).copyWith(color: c.brandText)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Strengths, or what to work on: a title with its icon, then the teacher's words (a line each when they wrote lines).
class _TextCard extends StatelessWidget {
  const _TextCard({required this.title, required this.text, required this.icon, required this.tone, super.key});

  final String title;
  final String text;
  final IconData icon;
  final Tone tone;

  @override
  Widget build(BuildContext context) {
    final lines = text.split('\n').map((s) => s.trim()).where((s) => s.isNotEmpty).toList();
    final (_, ink) = tone.colours(context);
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              IconBadge(icon, tone: tone, size: 36),
              const Gap(Space.m),
              Expanded(
                child: Semantics(header: true, child: Text(title, style: context.type.cardTitle)),
              ),
            ],
          ),
          const Gap(Space.m),
          if (lines.length < 2)
            Text(text, style: context.type.body.copyWith(height: 1.75))
          else
            for (final line in lines)
              Padding(
                padding: const EdgeInsets.only(bottom: Space.xs),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 9),
                      child: Container(
                        width: 6,
                        height: 6,
                        decoration: BoxDecoration(color: ink, shape: BoxShape.circle),
                      ),
                    ),
                    const Gap(Space.s),
                    Expanded(child: Text(line, style: context.type.body.copyWith(height: 1.75))),
                  ],
                ),
              ),
        ],
      ),
    );
  }
}

/// The teacher's words to the family, as a quote with their name under it.
class _Quote extends StatelessWidget {
  const _Quote({required this.title, required this.note, required this.by});

  final String title;
  final String note;
  final String by;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      key: const Key('report-note'),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: c.card,
        borderRadius: BorderRadius.circular(context.shape.card),
        border: Border.all(color: c.line),
      ),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ColoredBox(color: c.brand, child: const SizedBox(width: 4)),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(Space.l, Space.m, Space.l, Space.l),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.format_quote_rounded, color: c.brand, size: 30),
                        const Gap(Space.xs),
                        Semantics(
                          header: true,
                          child: Text(title, style: context.type.label.copyWith(color: c.muted)),
                        ),
                      ],
                    ),
                    const Gap(Space.xs),
                    Text(note, style: context.type.body.copyWith(fontSize: 16.5, height: 1.85, color: c.ink)),
                    const Gap(Space.s),
                    Text('— $by', style: context.type.caption.copyWith(fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// What the teacher recommends in plain words, today's price, the offer
/// while it is open (its time left, the price after it and the saving), and
/// the way to start; or the course already begun.
class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.report, required this.open, required this.onStart, required this.onEnded});

  final TrialReport report;
  final bool open;
  final VoidCallback onStart;
  final VoidCallback onEnded;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final help = context.hasModule(Feature.support);
    final r = report.recommendation;
    final plan = r.plan;
    final perWeek = r.perWeek ?? plan?.sessionsPerWeek;
    final ended = !open && report.offerState == OfferState.open || report.offerState == OfferState.ended;
    final parts = <Widget>[
      if (plan != null) ...[
        Text(plan.name, key: const Key('report-plan'), style: context.type.cardTitle.copyWith(fontSize: 18)),
        const Gap(Space.s),
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: [
            if (perWeek != null && perWeek > 0)
              StatusChip(l.trialPlanPerWeek(perWeek), tone: Tone.brand, icon: Icons.calendar_view_week_rounded),
            if (plan.sessionMinutes > 0) StatusChip(l.trialPlanLength(l.minutes(plan.sessionMinutes)), icon: Icons.timer_outlined),
            StatusChip(
              plan.group ? l.trialPlanGroup : l.trialPlanPrivate,
              tone: plan.group ? Tone.info : Tone.neutral,
              icon: plan.group ? Icons.groups_outlined : Icons.person_outline_rounded,
            ),
          ],
        ),
        if (plan.sessionsPerBlock > 0 && plan.validityDays > 0) ...[
          const Gap(Space.s),
          Text(l.trialPlanBlock(l.lessonsCount(plan.sessionsPerBlock), l.trialDaysCount(plan.validityDays)), style: context.type.caption),
        ],
      ] else if (r.withdrawn)
        Note(l.trialPlanWithdrawn, key: const Key('report-withdrawn'), tone: Tone.attention, icon: Icons.info_outline_rounded)
      else if (perWeek != null)
        Text(l.trialPlanOnlyPerWeek(l.trialPlanPerWeek(perWeek)), style: context.type.body),
      if (report.started)
        ..._started(context)
      else ...[
        const Gap(Space.l),
        AnimatedSwitcher(
          duration: context.calm ? Duration.zero : const Duration(milliseconds: 420),
          child: open
              ? _Offer(key: const ValueKey('open'), report: report, onEnded: onEnded)
              : _Price(key: const ValueKey('price'), report: report, ended: ended),
        ),
        const Gap(Space.l),
        if (report.start case final start? when !start.available) ...[
          Note(start.whyNot ?? l.startBlockedTitle, key: const Key('report-start-why'), icon: Icons.info_outline_rounded),
          if (start.reason == 'self_serve_off' && help) ...[
            const Gap(Space.s),
            SecondaryAction(
              l.startOffHelp,
              key: const Key('report-start-help'),
              icon: Icons.forum_outlined,
              onPressed: () => context.go('/family/me/help'),
            ),
          ],
        ] else
          SizedBox(
            height: 54,
            child: PrimaryAction(
              plan != null ? l.trialStartPlan : l.trialStartAsk,
              key: const Key('report-start'),
              icon: Icons.rocket_launch_outlined,
              onPressed: onStart,
            ),
          ),
      ],
    ];
    return LmsCard(
      key: const Key('report-plan-card'),
      padding: const EdgeInsets.all(Space.l + 2),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: parts),
    );
  }

  List<Widget> _started(BuildContext context) {
    final l = context.l10n;
    final status = report.courseStatus;
    return [
      const Gap(Space.l),
      Note(
        switch (status) {
          'pending_payment' => l.trialCourseWaiting,
          'paused' => l.trialCoursePaused,
          'active' => l.trialCourseActive,
          _ => l.trialStarted(report.subject),
        },
        key: const Key('report-started'),
        tone: Tone.brand,
        icon: Icons.celebration_outlined,
      ),
      if (status == 'pending_payment') ...[
        const Gap(Space.m),
        SizedBox(
          height: 52,
          child: PrimaryAction(l.trialOpenMoney, icon: Icons.account_balance_wallet_outlined, onPressed: () => context.go('/family/money')),
        ),
        // A course the family started here and has not paid: it may be called off, after asking.
        if (report.start case final start? when start.ownPending) ...[
          const Gap(Space.s),
          SecondaryAction(
            l.startCancelPending,
            key: const Key('report-cancel'),
            icon: Icons.event_busy_outlined,
            onPressed: () => _cancel(context, start.enrollmentId!),
          ),
        ],
      ],
    ];
  }

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
    try {
      final message = await context.read<StartRepository>().cancel(enrollmentId);
      if (!context.mounted) return;
      DoneMoment.show(context, title: message);
      await context.read<LoadCubit<TrialView>?>()?.load();
    } on ApiProblem catch (p) {
      if (context.mounted) showSnack(context, context.problemText(p));
    }
  }
}

/// The open offer: what it takes off and until when, the time left running down, the price after it and the saving.
class _Offer extends StatelessWidget {
  const _Offer({required this.report, required this.onEnded, super.key});

  final TrialReport report;
  final VoidCallback onEnded;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final offer = report.offer!;
    final was = report.recommendation.price;
    final ends = offer.endsAt;
    return Container(
      key: const Key('report-offer'),
      padding: const EdgeInsets.all(Space.l),
      decoration: BoxDecoration(
        color: c.attentionSoft,
        borderRadius: BorderRadius.circular(context.shape.card),
        border: Border.all(color: c.attention.withValues(alpha: 0.45)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.local_offer_rounded, color: c.attentionInk),
              const Gap(Space.s),
              Expanded(
                child: Text(
                  l.trialOfferTitle(offer.percentText),
                  key: const Key('report-offer-title'),
                  style: context.type.title.copyWith(color: c.attentionInk),
                ),
              ),
            ],
          ),
          if (ends != null) ...[
            const Gap(Space.xs),
            Text(l.trialOfferLead(ends.label), style: context.type.caption.copyWith(color: c.ink)),
            const Gap(Space.m),
            Text(l.trialOfferLeft, style: context.type.label.copyWith(color: c.attentionInk)),
            const Gap(Space.xs),
            OfferCountdown(endsAt: ends.utc, onEnd: onEnded),
          ],
          const Gap(Space.l),
          if (offer.price != null) ...[
            Text(l.trialOfferWith, style: context.type.label.copyWith(color: c.muted)),
            Wrap(
              crossAxisAlignment: WrapCrossAlignment.center,
              spacing: Space.s,
              children: [
                Text(offer.price!.text, key: const Key('report-offer-price'), style: context.type.number.copyWith(fontSize: 30)),
                if (was != null && was.minor != offer.price!.minor)
                  Text(
                    l.trialOfferInstead(was.text),
                    style: context.type.caption.copyWith(decoration: TextDecoration.lineThrough, decorationColor: c.muted),
                  ),
              ],
            ),
            if (offer.saving != null && offer.saving!.minor > 0) ...[
              const Gap(Space.s),
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: StatusChip(l.trialOfferSaving(offer.saving!.text), tone: Tone.brand, icon: Icons.savings_outlined),
              ),
            ],
          ] else
            Text(l.trialOfferNoPrice, style: context.type.body),
          if (offer.familyDiscountBigger) ...[
            const Gap(Space.m),
            Note(l.trialOfferFamilyBigger, key: const Key('report-family-bigger'), icon: Icons.family_restroom_rounded),
          ],
        ],
      ),
    );
  }
}

/// The plan's price today in the family's region, and what became of the offer (ended, used).
class _Price extends StatelessWidget {
  const _Price({required this.report, required this.ended, super.key});

  final TrialReport report;
  final bool ended;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final r = report.recommendation;
    final price = r.price;
    final block = r.plan?.sessionsPerBlock ?? 0;
    return Column(
      key: const Key('report-price'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (ended) ...[
          Note(
            price != null ? l.trialOfferEnded : l.trialOfferEndedNoPrice,
            key: const Key('report-offer-ended'),
            tone: Tone.neutral,
            icon: Icons.timer_off_outlined,
          ),
          const Gap(Space.m),
        ],
        if (report.offerState == OfferState.used) ...[
          Note(l.trialOfferUsed, key: const Key('report-offer-used'), tone: Tone.brand, icon: Icons.check_circle_outline_rounded),
          const Gap(Space.m),
        ],
        if (price != null) ...[
          Text(l.trialPrice, style: context.type.label.copyWith(color: context.colors.muted)),
          Wrap(
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: Space.s,
            children: [
              Text(price.text, key: const Key('report-plain-price'), style: context.type.number.copyWith(fontSize: 28)),
              if (block > 0) Text(l.trialPriceFor(l.lessonsCount(block)), style: context.type.caption),
            ],
          ),
          if (r.region != null && r.region!.isNotEmpty) Text(l.trialPriceRegion(r.region!), style: context.type.caption),
        ] else if (r.plan != null)
          Text(l.trialNoPrice, key: const Key('report-no-price'), style: context.type.caption),
      ],
    );
  }
}

/// "Not the right teacher?": the free second trial with another teacher while it may be booked, else why not, calmly.
/// After a trial that did not take place, the same card books another time.
class _NextCard extends StatelessWidget {
  const _NextCard({required this.report, required this.next, required this.base});

  final TrialReport report;
  final NextTrial next;
  final String base;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final until = next.until;
    return LmsCard(
      key: const Key('report-next'),
      padding: const EdgeInsets.all(Space.l + 2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              IconBadge(next.second ? Icons.swap_horiz_rounded : Icons.event_repeat_rounded, tone: Tone.info, size: 40),
              const Gap(Space.m),
              Expanded(
                child: Semantics(
                  header: true,
                  child: Text(next.second ? l.trialSecondTitle : l.trialRebookTitle, style: context.type.cardTitle),
                ),
              ),
            ],
          ),
          const Gap(Space.m),
          if (next.available) ...[
            Text(
              next.second ? (until != null ? l.trialSecondBody(until.label) : l.trialSecondBodyNoDate) : l.trialRebookBody,
              style: context.type.body.copyWith(height: 1.7),
            ),
            const Gap(Space.m),
            SizedBox(
              height: 52,
              child: SecondaryAction(
                next.second ? l.trialSecondPick : l.trialBookAgain,
                key: const Key('report-next-book'),
                icon: Icons.event_available_outlined,
                onPressed: () => context.go(nextTrialLocation(base, report.id, second: next.second, teacher: report.teacher)),
              ),
            ),
          ] else if (next.whyNot != null)
            Note(next.whyNot!, key: const Key('report-next-why'), icon: Icons.chat_bubble_outline_rounded),
        ],
      ),
    );
  }
}

/// Shaped like the report on its way.
class _Skeleton extends StatelessWidget {
  const _Skeleton();

  @override
  Widget build(BuildContext context) => ListView(
    padding: pagePadding,
    physics: const NeverScrollableScrollPhysics(),
    children: [
      Skeleton(height: 200, radius: context.shape.card + 6),
      const Gap(Space.l),
      const Skeleton(height: 92, radius: 16),
      const Gap(Space.m),
      const Skeleton(height: 120, radius: 16),
      const Gap(Space.m),
      const Skeleton(height: 120, radius: 16),
    ],
  );
}
