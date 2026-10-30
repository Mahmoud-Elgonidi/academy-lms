import 'dart:math' as math;

import 'package:flutter/foundation.dart' show listEquals;
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart' show DateFormat;

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/teaching_repository.dart';
import '../../domain/shifts.dart';
import 'shift_editor_page.dart';

/// The days of the week in the order people here count them: from
/// Saturday in Arabic, from Monday in English (ISO numbers).
List<int> weekOrder(String locale) => locale == 'ar' ? const [6, 7, 1, 2, 3, 4, 5] : const [1, 2, 3, 4, 5, 6, 7];

String dayName(int weekday, String locale) => DateFormat('EEEE', locale).format(DateTime(2024, 1, weekday));

String calendarDay(String ymd, String locale) => ymd.isEmpty ? '' : DateFormat('EEEE d MMMM', locale).format(DateTime.parse(ymd));

/// "7 November": a calendar day without its weekday, for short labels.
String shortDay(String ymd, String locale) => ymd.isEmpty ? '' : DateFormat('d MMMM', locale).format(DateTime.parse(ymd));

/// "My hours": one thing that matters now on top (the open proposal, an
/// approved schedule that starts later, or a recent refusal), then one week
/// card (now and, when there is one, the coming week behind a switch), one
/// way to propose a change, and the history folded away.
class ShiftsPage extends StatelessWidget {
  const ShiftsPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<MyShifts>(c.read<TeachingRepository>().shifts, refreshOn: c.read<Live>().on({Live.lessons}))..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.myHours)),
      body: LoadView<MyShifts>(builder: (context, mine) => _Body(mine: mine)),
    ),
  );
}

class _Body extends StatelessWidget {
  const _Body({required this.mine});

  final MyShifts mine;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final hero = HoursHero.shows(mine);
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        Text(l.hoursOnClock(zoneName(mine.timezone, context.locale)), style: context.text.bodySmall),
        const SizedBox(height: 10),
        if (hero) HoursHero(mine: mine),
        if (hero) const SizedBox(height: 16),
        WeekCard(mine: mine),
        if (mine.proposal == null) const SizedBox(height: 12),
        if (mine.proposal == null) _ProposeChange(mine: mine),
        if (mine.history.isNotEmpty) const SizedBox(height: 20),
        if (mine.history.isNotEmpty) HistoryList(history: mine.history),
      ]),
    );
  }
}

/// Opens the editor from the proposal, else from the latest agreed week.
Future<void> editShifts(BuildContext context, MyShifts mine) async {
  final p = mine.proposal;
  final upcomingDay = mine.upcoming?.startsOn.localDate;
  final from = upcomingDay != null && upcomingDay.compareTo(mine.earliestAdding) > 0 ? upcomingDay : mine.earliestAdding;
  final cubit = context.read<LoadCubit<MyShifts>>();
  final saved = await context.push<bool>(
    '/teach/hours/edit',
    extra: ShiftDraft(
      blocks: [...(p?.week ?? mine.latest).blocks],
      effectiveFrom: p?.effectiveFrom ?? from,
      note: p?.note,
      minHours: p?.minWeeklyMinutes == null ? null : p!.minWeeklyMinutes! / 60,
      maxHours: p?.maxWeeklyMinutes == null ? null : p!.maxWeeklyMinutes! / 60,
      earliestAdding: mine.earliestAdding,
      earliestRemoving: mine.earliestRemoving,
      current: mine.latest,
    ),
  );
  if (saved == true) await cubit.load();
}

/// The one card on top, by what matters most: the open proposal, else an
/// approved schedule that starts later, else a proposal refused in the last
/// two weeks; else nothing, and the week comes first.
class HoursHero extends StatelessWidget {
  const HoursHero({required this.mine, super.key});

  final MyShifts mine;

  static bool shows(MyShifts mine) => mine.proposal != null || mine.upcoming != null || recentRefusal(mine) != null;

  /// The latest decision when it is a refusal from the last two weeks.
  static ShiftDecision? recentRefusal(MyShifts mine) {
    final d = mine.history.isEmpty ? null : mine.history.first;
    if (d == null || d.status != 'rejected' || d.decidedAt == null) return null;
    return d.decidedAt!.fromNow() > const Duration(days: -14) ? d : null;
  }

  @override
  Widget build(BuildContext context) {
    final p = mine.proposal;
    if (p != null) return ProposalCard(mine: mine, proposal: p);
    final upcoming = mine.upcoming;
    if (upcoming != null) return _UpcomingHero(upcoming: upcoming, current: mine.current);
    final refusal = recentRefusal(mine);
    if (refusal != null) return _RefusedHero(decision: refusal);
    return const SizedBox.shrink();
  }
}

/// The hero's frame: the tone's soft colour, an icon and one sentence, then what follows.
class _HeroShell extends StatelessWidget {
  const _HeroShell({required this.tone, required this.icon, required this.title, required this.children, this.waiting = false, super.key});

  final Tone tone;
  final IconData icon;
  final String title;
  final List<Widget> children;

  /// Something is on its way: the icon breathes softly.
  final bool waiting;

  @override
  Widget build(BuildContext context) {
    final (bg, ink) = tone.colours(context);
    final badge = Container(
      width: 40,
      height: 40,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: context.colors.card, shape: BoxShape.circle),
      child: Icon(icon, color: ink, size: 22),
    );
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: context.shape.cardRadius,
        border: Border.all(color: ink.withValues(alpha: .18)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              if (waiting) badge.pulse(context, to: 1.08) else badge,
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  title,
                  style: context.text.titleMedium?.copyWith(color: ink, fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
          ...children,
        ],
      ),
    );
  }
}

/// An approved schedule that starts later: the day, how far off it is, and the hours counting to the new number.
class _UpcomingHero extends StatelessWidget {
  const _UpcomingHero({required this.upcoming, required this.current});

  final UpcomingShifts upcoming;
  final ShiftWeek current;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final before = current.weeklyMinutes / 60;
    final after = upcoming.week.weeklyMinutes / 60;
    final days = math.max(0, (upcoming.startsOn.fromNow().inMinutes / (24 * 60)).ceil());
    final big = context.text.titleLarge?.copyWith(color: c.brandDeep, fontWeight: FontWeight.w700);
    return _HeroShell(
      key: const Key('shifts-upcoming'),
      tone: Tone.brand,
      icon: Icons.event_available_rounded,
      title: l.upcomingHeroTitle(upcoming.startsOn.label),
      children: [
        Padding(
          padding: const EdgeInsetsDirectional.only(start: 52),
          child: Text(l.startsInDays(days), style: context.text.bodyMedium?.copyWith(color: c.brandDeep)),
        ),
        const SizedBox(height: 14),
        if (before == after)
          Text(l.hoursSame(hoursAmount(context, after)), style: context.text.titleSmall?.copyWith(color: c.brandDeep))
        else
          Wrap(
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 8,
            runSpacing: 4,
            children: [
              if (before > 0) ...[
                Text(hoursAmount(context, before), style: context.text.titleSmall?.copyWith(color: c.muted)),
                Icon(Icons.arrow_forward_rounded, size: 18, color: c.muted),
              ],
              CountUp(after, from: before, decimals: 1, format: (v) => l.weeklyHours(hoursAmount(context, v)), style: big),
            ],
          ),
        const SizedBox(height: 6),
        Text(current.blocks.isEmpty ? l.upcomingApproved : l.upcomingNote, style: context.text.bodySmall),
      ],
    );
  }
}

/// The last proposal was refused lately: say so, and why.
class _RefusedHero extends StatelessWidget {
  const _RefusedHero({required this.decision});

  final ShiftDecision decision;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final reason = decision.reason ?? '';
    return _HeroShell(
      key: const Key('shifts-refused'),
      tone: Tone.danger,
      icon: Icons.event_busy_rounded,
      title: l.rejectedHeroTitle,
      children: [
        const SizedBox(height: 10),
        Text(l.stageRejected, style: context.text.bodyMedium),
        if (reason.isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(
            l.reasonLine(reason),
            style: context.text.bodyMedium?.copyWith(color: context.colors.dangerInk, fontWeight: FontWeight.w600),
          ),
        ],
      ],
    );
  }
}

/// The open proposal (or draft) as the hero: its step, what it means, the
/// days it changes, the one thing to do now, and its conversation.
class ProposalCard extends StatefulWidget {
  const ProposalCard({required this.mine, required this.proposal, super.key});

  final MyShifts mine;
  final ShiftProposal proposal;

  @override
  State<ProposalCard> createState() => _ProposalCardState();
}

class _ProposalCardState extends State<ProposalCard> {
  final _acceptKey = GlobalKey();
  bool _busy = false;

  Future<void> _run(Future<void> Function(TeachingRepository repo) action, String done, {bool celebrate = false}) async {
    setState(() => _busy = true);
    final repo = context.read<TeachingRepository>();
    final cubit = context.read<LoadCubit<MyShifts>>();
    try {
      await action(repo);
      if (!mounted) return;
      if (celebrate) {
        Juice.reward();
        Celebration.show(context, from: Celebration.centreOf(_acceptKey.currentContext ?? context));
      }
      showSnack(context, done);
      await cubit.load();
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _withdraw() async {
    final l = context.l10n;
    final sure = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(l.withdrawConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.back)),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.withdraw)),
        ],
      ),
    );
    if (sure == true && mounted) await _run((r) => r.withdrawShifts(), l.withdrawn);
  }

  Future<void> _edit() => editShifts(context, widget.mine);

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final p = widget.proposal;
    final stage = ShiftStage.of(p.status);
    final (title, meaning, tone, icon) = switch (stage) {
      ShiftStage.draft => (l.heroDraftTitle, l.stageDraft, Tone.brand, Icons.edit_note_rounded),
      ShiftStage.review => (l.heroReviewTitle, l.stageReview, Tone.info, Icons.hourglass_top_rounded),
      ShiftStage.changesRequested => (l.heroChangesTitle, l.stageChanges, Tone.attention, Icons.rate_review_outlined),
      ShiftStage.countered => (l.heroCounterTitle, l.academyCounter, Tone.attention, Icons.compare_arrows_rounded),
      // An open proposal is never decided; these stay for completeness.
      ShiftStage.approved || ShiftStage.rejected => (p.statusLabel, '', Tone.neutral, Icons.info_outline_rounded),
    };
    final base = p.countered ? (p.previousWeek ?? widget.mine.latest) : widget.mine.latest;
    final quiet = TextButton.styleFrom(visualDensity: VisualDensity.compact);
    final withdraw = TextButton(
      style: quiet.copyWith(foregroundColor: WidgetStatePropertyAll(c.dangerInk)),
      onPressed: _busy ? null : _withdraw,
      child: Text(l.withdrawProposal),
    );
    return _HeroShell(
      key: const Key('shifts-proposal'),
      tone: tone,
      icon: icon,
      title: title,
      waiting: stage == ShiftStage.review,
      children: [
        const SizedBox(height: 16),
        ShiftStepper(stage: stage),
        const SizedBox(height: 14),
        if (meaning.isNotEmpty) Text(meaning, style: context.text.bodyMedium),
        const SizedBox(height: 12),
        Row(
          children: [
            Icon(Icons.event_rounded, size: 18, color: c.muted),
            const SizedBox(width: 6),
            Expanded(child: Text(l.startsOn(calendarDay(p.effectiveFrom, context.locale)), style: context.text.bodyMedium)),
            Text(l.weeklyHours(hoursAmount(context, p.week.weeklyMinutes / 60)), style: context.text.bodySmall),
          ],
        ),
        const SizedBox(height: 8),
        LmsCard(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
          child: WeekView(week: p.week, compareTo: base.blocks.isEmpty ? null : base, changedOnly: true),
        ),
        if ((p.note ?? '').isNotEmpty) ...[const SizedBox(height: 8), Text(p.note!, style: context.text.bodyMedium)],
        const SizedBox(height: 14),
        switch (stage) {
          ShiftStage.draft => FilledButton.icon(
            onPressed: _busy ? null : _edit,
            icon: const Icon(Icons.send_rounded),
            label: Text(l.continueAndSend),
          ),
          ShiftStage.countered => FilledButton.icon(
            key: _acceptKey,
            onPressed: _busy ? null : () => _run((r) => r.acceptShifts(), l.proposalAccepted, celebrate: true),
            icon: const Icon(Icons.check_rounded),
            label: Text(l.acceptCounter),
          ),
          ShiftStage.changesRequested => FilledButton.icon(
            onPressed: _busy ? null : _edit,
            icon: const Icon(Icons.edit_calendar_rounded),
            label: Text(l.editAndSend),
          ),
          _ => const SizedBox.shrink(),
        },
        if (stage != ShiftStage.draft)
          Wrap(
            alignment: WrapAlignment.center,
            children: [
              if (stage == ShiftStage.countered) TextButton(style: quiet, onPressed: _busy ? null : _edit, child: Text(l.editAndSend)),
              withdraw,
            ],
          ),
        if (stage != ShiftStage.draft) ...[const SizedBox(height: 6), ConversationRow(proposal: p)],
      ],
    );
  }
}

/// The week in force and, when one is approved to start later, that week
/// behind a switch. Never "0 hours" over a schedule that is coming.
class WeekCard extends StatefulWidget {
  const WeekCard({required this.mine, super.key});

  final MyShifts mine;

  @override
  State<WeekCard> createState() => _WeekCardState();
}

class _WeekCardState extends State<WeekCard> {
  bool _next = false;

  Widget _header(BuildContext context, String title, ShiftWeek week) => Padding(
    padding: const EdgeInsetsDirectional.only(start: 14, end: 14, top: 12, bottom: 2),
    child: Row(
      children: [
        Expanded(child: Text(title, style: context.text.titleSmall)),
        Pill(context.l10n.weeklyHours(hoursAmount(context, week.weeklyMinutes / 60)), tone: Tone.brand, icon: Icons.schedule_rounded),
      ],
    ),
  );

  Widget _week(ShiftWeek week, {ShiftWeek? compareTo}) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 14),
    child: WeekView(week: week, compareTo: compareTo),
  );

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final current = widget.mine.current;
    final upcoming = widget.mine.upcoming;
    final List<Widget> body;
    if (upcoming == null) {
      body = [
        _header(context, l.currentWeek, current),
        if (current.blocks.isEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 16),
            child: Text(l.noHoursYet, style: context.text.bodyMedium?.copyWith(color: c.muted)),
          )
        else
          _week(current),
      ];
    } else {
      final from = shortDay(upcoming.startsOn.localDate, context.locale);
      final next = Column(
        key: const ValueKey('next'),
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _header(context, l.startsOn(upcoming.startsOn.label), upcoming.week),
          _week(upcoming.week, compareTo: current.blocks.isEmpty ? null : current),
        ],
      );
      if (current.blocks.isEmpty) {
        body = [
          Padding(
            padding: const EdgeInsetsDirectional.only(start: 14, end: 14, top: 12),
            child: Row(
              children: [
                Icon(Icons.hourglass_empty_rounded, size: 16, color: c.faint),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(l.noHoursBefore(from), style: context.text.bodySmall?.copyWith(color: c.muted)),
                ),
              ],
            ),
          ),
          next,
        ];
      } else {
        final now = Column(
          key: const ValueKey('now'),
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [_header(context, l.currentWeek, current), _week(current)],
        );
        final rtl = Directionality.of(context) == TextDirection.rtl;
        final calm = context.calm;
        final shown = AnimatedSwitcher(
          duration: calm ? Duration.zero : const Duration(milliseconds: 320),
          switchInCurve: Curves.easeOutCubic,
          switchOutCurve: Curves.easeInCubic,
          layoutBuilder: (top, below) => Stack(alignment: Alignment.topCenter, children: [...below, ?top]),
          transitionBuilder: (child, animation) {
            // The coming week enters from the end side, now from the start.
            final fromEnd = child.key == const ValueKey('next');
            final dx = (fromEnd ? 0.06 : -0.06) * (rtl ? -1 : 1);
            return FadeTransition(
              opacity: animation,
              child: SlideTransition(
                position: Tween(begin: Offset(dx, 0), end: Offset.zero).animate(animation),
                child: child,
              ),
            );
          },
          child: _next ? next : now,
        );
        body = [
          Padding(
            padding: const EdgeInsetsDirectional.only(start: 12, end: 12, top: 12),
            child: _WeekSwitch(labels: (l.weekNow, l.weekFrom(from)), next: _next, onChanged: (v) => setState(() => _next = v)),
          ),
          if (calm)
            shown
          else
            AnimatedSize(
              duration: const Duration(milliseconds: 280),
              curve: Curves.easeOutCubic,
              alignment: Alignment.topCenter,
              child: shown,
            ),
        ];
      }
    }
    return LmsCard(
      key: const Key('shifts-week'),
      padding: const EdgeInsets.only(bottom: 4),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: body),
    );
  }
}

/// Two halves, now and the coming week, with a thumb that slides between them.
class _WeekSwitch extends StatelessWidget {
  const _WeekSwitch({required this.labels, required this.next, required this.onChanged});

  final (String, String) labels;
  final bool next;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final radius = BorderRadius.circular(math.min(context.shape.button, 22));
    final inner = BorderRadius.circular(math.max(0, math.min(context.shape.button, 22) - 3));
    final speed = context.calm ? Duration.zero : const Duration(milliseconds: 260);
    Widget half(String label, bool selected, bool value, Key key) => Expanded(
      child: Semantics(
        button: true,
        selected: selected,
        child: InkWell(
          key: key,
          borderRadius: inner,
          onTap: selected ? null : () => onChanged(value),
          child: Center(
            child: AnimatedDefaultTextStyle(
              duration: speed,
              style: (context.text.labelLarge ?? const TextStyle()).copyWith(
                color: selected ? c.brandDeep : c.muted,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              ),
              child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
            ),
          ),
        ),
      ),
    );
    return Container(
      height: 44,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(color: c.chip, borderRadius: radius),
      child: Stack(
        children: [
          AnimatedAlign(
            alignment: next ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
            duration: speed,
            curve: context.springCurve,
            child: FractionallySizedBox(
              widthFactor: .5,
              heightFactor: 1,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  color: c.card,
                  borderRadius: inner,
                  boxShadow: [BoxShadow(color: c.ink.withValues(alpha: .08), blurRadius: 6, offset: const Offset(0, 1))],
                ),
              ),
            ),
          ),
          Row(
            children: [
              half(labels.$1, !next, false, const Key('shifts-week-now')),
              half(labels.$2, next, true, const Key('shifts-week-next')),
            ],
          ),
        ],
      ),
    );
  }
}

/// A week as rows of days: the times written, and a small bar of the day
/// (06:00 to midnight) that fills. Against [compareTo], the days that are
/// new or changed are marked and the others stay calmer; [changedOnly]
/// keeps only those days (all working days when nothing differs).
class WeekView extends StatelessWidget {
  const WeekView({required this.week, this.compareTo, this.changedOnly = false, super.key});

  final ShiftWeek week;
  final ShiftWeek? compareTo;
  final bool changedOnly;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final before = compareTo;
    bool changed(int day) => before != null && !listEquals(week.on(day), before.on(day));
    final order = weekOrder(context.locale);
    var days = changedOnly ? order.where(changed).toList() : order;
    if (days.isEmpty) days = order.where((d) => week.on(d).isNotEmpty).toList();
    if (days.isEmpty) days = order;
    return Column(
      children: [
        for (final (i, day) in days.indexed) ...[
          if (i > 0) Divider(height: 1, color: c.line),
          _DayRow(
            day: day,
            spans: week.on(day),
            before: before?.on(day),
            mark: !changed(day) ? null : (before!.on(day).isEmpty ? context.l10n.dayNew : context.l10n.dayChanged),
            dim: before != null && !changed(day),
            index: i,
          ),
        ],
      ],
    );
  }
}

class _DayRow extends StatelessWidget {
  const _DayRow({required this.day, required this.spans, required this.index, this.before, this.mark, this.dim = false});

  final int day;
  final List<ShiftBlock> spans;

  /// The same day in the week compared against, to mark what was added and list what was removed.
  final List<ShiftBlock>? before;
  final String? mark;
  final bool dim;
  final int index;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final removed = [
      for (final b in before ?? const <ShiftBlock>[])
        if (!spans.contains(b)) b,
    ];
    final off = spans.isEmpty && removed.isEmpty;
    final range = context.text.labelLarge?.copyWith(fontFeatures: const [FontFeature.tabularFigures()]);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 88,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(dayName(day, context.locale), style: context.text.titleSmall?.copyWith(color: spans.isEmpty || dim ? c.muted : c.ink)),
                if (mark != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 3),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 6,
                          height: 6,
                          decoration: BoxDecoration(color: c.brand, shape: BoxShape.circle),
                        ),
                        const SizedBox(width: 4),
                        Text(
                          mark!,
                          style: context.text.labelSmall?.copyWith(color: c.brandDeep, fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Wrap(
                  spacing: 12,
                  runSpacing: 4,
                  children: [
                    if (off) Text(l.dayOff, style: context.text.bodySmall?.copyWith(color: c.faint)),
                    for (final b in spans)
                      Builder(
                        builder: (context) {
                          final label = wallRange(b.start, b.end, context.locale);
                          final added = before != null && !before!.contains(b);
                          return Text(
                            label,
                            semanticsLabel: added ? '${l.timeAdded}: $label' : null,
                            style: range?.copyWith(
                              color: added ? c.brandDeep : (dim ? c.muted : c.ink),
                              fontWeight: added ? FontWeight.w700 : FontWeight.w500,
                            ),
                          );
                        },
                      ),
                    for (final b in removed)
                      Text(
                        wallRange(b.start, b.end, context.locale),
                        semanticsLabel: '${l.timeRemoved}: ${wallRange(b.start, b.end, context.locale)}',
                        style: context.text.bodySmall?.copyWith(color: c.dangerInk, decoration: TextDecoration.lineThrough),
                      ),
                  ],
                ),
                if (spans.isNotEmpty) ...[const SizedBox(height: 7), DayBar(spans: spans, strong: !dim, index: index)],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// A day from 06:00 to midnight as a thin track, its working spans filling in from where they start.
class DayBar extends StatelessWidget {
  const DayBar({required this.spans, this.strong = true, this.index = 0, super.key});

  final List<ShiftBlock> spans;

  /// Calmer for a day that did not change.
  final bool strong;

  /// Its row: each row fills a little after the one above.
  final int index;

  static const _from = 6 * 60;
  static const _to = 24 * 60;

  static double _at(String hhmm) => ((ShiftBlock.minutes(hhmm) - _from) / (_to - _from)).clamp(0.0, 1.0);

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final fill = strong ? c.brand : c.brand.withValues(alpha: .4);
    Widget bar(double t) => LayoutBuilder(
      builder: (context, box) {
        final w = box.maxWidth;
        return SizedBox(
          height: 8,
          child: Stack(
            children: [
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(color: c.chip, borderRadius: BorderRadius.circular(4)),
                ),
              ),
              // Noon and six in the evening, faintly.
              for (final m in const ['12:00', '18:00'])
                PositionedDirectional(
                  start: w * _at(m),
                  top: 1,
                  bottom: 1,
                  width: 1,
                  child: ColoredBox(color: c.line),
                ),
              for (final b in spans)
                PositionedDirectional(
                  start: w * _at(b.start),
                  top: 0,
                  bottom: 0,
                  width: math.max(3.0, w * (_at(b.end) - _at(b.start))) * t,
                  child: DecoratedBox(
                    decoration: BoxDecoration(color: fill, borderRadius: BorderRadius.circular(4)),
                  ),
                ),
            ],
          ),
        );
      },
    );
    if (context.calm) return ExcludeSemantics(child: bar(1));
    final wait = 60 * index.clamp(0, 7);
    final total = 520 + wait;
    return ExcludeSemantics(
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: 1),
        duration: Duration(milliseconds: total),
        curve: Interval(wait / total, 1, curve: Curves.easeOutCubic),
        builder: (context, t, _) => bar(t),
      ),
    );
  }
}

/// No open proposal: one way to ask for other hours, built on the latest agreed week.
class _ProposeChange extends StatelessWidget {
  const _ProposeChange({required this.mine});

  final MyShifts mine;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final upcoming = mine.upcoming;
    if (mine.latest.blocks.isEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.newProposalHint, style: context.text.bodyMedium, textAlign: TextAlign.center),
          const SizedBox(height: 10),
          FilledButton.icon(
            key: const Key('shifts-propose'),
            onPressed: () => editShifts(context, mine),
            icon: const Icon(Icons.edit_calendar_rounded),
            label: Text(l.proposeHours),
          ),
        ],
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        OutlinedButton.icon(
          key: const Key('shifts-propose'),
          onPressed: () => editShifts(context, mine),
          icon: const Icon(Icons.edit_calendar_rounded),
          label: Text(l.proposeChange),
        ),
        if (upcoming != null) ...[
          const SizedBox(height: 6),
          Text(
            l.builtOnUpcoming(shortDay(upcoming.startsOn.localDate, context.locale)),
            textAlign: TextAlign.center,
            style: context.text.bodySmall,
          ),
        ],
      ],
    );
  }
}

/// The decisions so far, folded: what was asked, how it ended, and the academy's reason for a refusal.
class HistoryList extends StatefulWidget {
  const HistoryList({required this.history, super.key});

  final List<ShiftDecision> history;

  @override
  State<HistoryList> createState() => _HistoryListState();
}

class _HistoryListState extends State<HistoryList> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final calm = context.calm;
    final speed = calm ? Duration.zero : const Duration(milliseconds: 260);
    final rows = Column(
      children: [
        for (final (i, d) in widget.history.indexed) ...[
          Divider(height: 1, color: c.line),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.askedToStart(calendarDay(d.effectiveFrom, context.locale)), style: context.text.bodyMedium),
                const SizedBox(height: 4),
                Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Pill(
                      d.status == 'superseded' ? l.supersededLabel : d.statusLabel,
                      tone: switch (d.status) {
                        'rejected' => Tone.danger,
                        'approved' => Tone.brand,
                        _ => Tone.neutral,
                      },
                    ),
                    if (d.decidedAt != null) Text(d.decidedAt!.label, style: context.text.bodySmall),
                  ],
                ),
                if ((d.reason ?? '').isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(l.reasonLine(d.reason!), style: context.text.bodySmall?.copyWith(color: c.dangerInk)),
                ],
              ],
            ),
          ).appear(context, i),
        ],
      ],
    );
    return LmsCard(
      key: const Key('shifts-history'),
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Semantics(
            button: true,
            expanded: _open,
            child: InkWell(
              key: const Key('shifts-history-toggle'),
              onTap: () => setState(() => _open = !_open),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                child: Row(
                  children: [
                    Icon(Icons.history_rounded, color: c.muted, size: 20),
                    const SizedBox(width: 10),
                    Expanded(child: Text(l.historyCount(widget.history.length), style: context.text.titleSmall)),
                    AnimatedRotation(
                      turns: _open ? .5 : 0,
                      duration: speed,
                      child: Icon(Icons.expand_more_rounded, color: c.muted),
                    ),
                  ],
                ),
              ),
            ),
          ),
          AnimatedSize(
            duration: speed,
            curve: Curves.easeOutCubic,
            alignment: Alignment.topCenter,
            child: _open ? rows : const SizedBox(width: double.infinity),
          ),
        ],
      ),
    );
  }
}

/// Where a proposal is: four steps, the last one the academy's answer.
class ShiftStepper extends StatelessWidget {
  const ShiftStepper({required this.stage, super.key});

  final ShiftStage stage;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final answer = switch (stage) {
      ShiftStage.countered => l.stepCountered,
      ShiftStage.changesRequested => l.stepChanges,
      ShiftStage.approved => l.stepApproved,
      ShiftStage.rejected => l.stepRejected,
      _ => l.stepDecision,
    };
    final labels = [l.stepDraft, l.stepSent, l.stepReview, answer];
    final endColour = switch (stage) {
      ShiftStage.rejected => c.danger,
      ShiftStage.countered || ShiftStage.changesRequested => c.attention,
      _ => c.brand,
    };
    // Every step before the one it is on is done; an approval is done through the end.
    bool done(int i) => i < stage.step || stage == ShiftStage.approved;
    Color colour(int i) => i == 3 && stage.step == 3 ? endColour : (done(i) || i == stage.step ? c.brand : c.line);
    return Semantics(
      label: labels.take(stage.step + 1).join(' ← '),
      excludeSemantics: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (var i = 0; i < 4; i++)
            Expanded(
              // The steps take their colour one after another.
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(child: Container(height: 2, color: i == 0 ? Colors.transparent : (i <= stage.step ? c.brand : c.line))),
                      Container(
                        width: 26,
                        height: 26,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: done(i) || (i == 3 && stage.step == 3) ? colour(i) : c.card,
                          border: Border.all(color: colour(i), width: 2),
                        ),
                        child: done(i) || (i == 3 && stage.step == 3)
                            ? Icon(
                                stage == ShiftStage.rejected && i == 3
                                    ? Icons.close_rounded
                                    : (i == 3 && stage.step == 3 && stage != ShiftStage.approved
                                          ? Icons.priority_high_rounded
                                          : Icons.check_rounded),
                                size: 15,
                                color: Colors.white,
                              )
                            : Text('${i + 1}', style: context.text.labelSmall?.copyWith(color: i == stage.step ? c.brandDeep : c.muted)),
                      ),
                      Expanded(child: Container(height: 2, color: i == 3 ? Colors.transparent : (i < stage.step ? c.brand : c.line))),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    labels[i],
                    textAlign: TextAlign.center,
                    style: context.text.labelSmall?.copyWith(
                      color: i == stage.step ? c.ink : c.muted,
                      fontWeight: i == stage.step ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                ],
              ).appear(context, i),
            ),
        ],
      ),
    );
  }
}

/// The proposal's conversation, one row away: how many messages and the last one.
class ConversationRow extends StatelessWidget {
  const ConversationRow({required this.proposal, super.key});

  final ShiftProposal proposal;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final said = proposal.comments.where((m) => !m.system).toList();
    final last = said.isEmpty ? null : said.last;
    final unanswered = last != null && !last.mine;
    final cubit = context.read<LoadCubit<MyShifts>>();
    return LmsCard(
      key: const Key('shifts-conversation'),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      onTap: () async {
        await context.push('/teach/hours/chat');
        await cubit.load();
      },
      child: Row(
        children: [
          Badge(
            isLabelVisible: unanswered,
            backgroundColor: c.attention,
            smallSize: 9,
            child: Icon(Icons.forum_outlined, color: c.brandDeep),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.conversationCount(said.length), style: context.text.titleSmall),
                const SizedBox(height: 2),
                Text(
                  last == null ? l.conversationStart : '${last.mine ? l.messagesYou : l.ticketAcademy}: ${last.body}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.text.bodySmall,
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: c.muted, textDirection: Directionality.of(context)),
        ],
      ),
    );
  }
}
