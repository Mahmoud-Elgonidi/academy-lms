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

/// "My hours", in steps: the week in force, an approved one that starts
/// later, the proposal where it stands (one thing to do at each step), its
/// conversation behind one row, and the last decisions.
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
    final p = mine.proposal;
    final upcoming = mine.upcoming;
    final last = mine.history.isEmpty ? null : mine.history.first;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        Text(l.hoursOnClock(zoneName(mine.timezone, context.locale)), style: context.text.bodySmall),
        const SizedBox(height: 8),
        SectionLabel(
          l.currentWeek,
          trailing: Text(l.weeklyHours(hoursAmount(context, mine.current.weeklyMinutes / 60)), style: context.text.bodySmall),
        ),
        if (mine.current.blocks.isEmpty) Note(l.noHoursYet) else WeekView(week: mine.current),
        if (upcoming != null) ...[const SizedBox(height: 16), UpcomingCard(upcoming: upcoming, current: mine.current)],
        const SizedBox(height: 20),
        SectionLabel(l.yourProposal),
        if (p != null)
          ProposalCard(mine: mine, proposal: p)
        else
          NewProposalCard(mine: mine, last: last != null && _recent(last) ? last : null),
        if (p != null && p.status != 'draft') ...[const SizedBox(height: 10), ConversationRow(proposal: p)],
        if (mine.history.isNotEmpty) ...[const SizedBox(height: 20), SectionLabel(l.earlierProposals), HistoryList(history: mine.history)],
      ]),
    );
  }

  /// A decision from the last two weeks still shows as where the proposal ended.
  static bool _recent(ShiftDecision d) =>
      d.status != 'superseded' && d.decidedAt != null && d.decidedAt!.fromNow() > const Duration(days: -14);
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

/// A week as rows of days with their spans; [compareTo] marks what was
/// added (and lists what was removed) against another week.
class WeekView extends StatelessWidget {
  const WeekView({required this.week, this.compareTo, super.key});

  final ShiftWeek week;
  final ShiftWeek? compareTo;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final before = compareTo;
    return LmsCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      child: Column(
        children: [
          for (final (i, day) in weekOrder(context.locale).indexed) ...[
            if (i > 0) Divider(height: 1, color: c.line),
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 9),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 84,
                    child: Text(
                      dayName(day, context.locale),
                      style: context.text.titleSmall?.copyWith(color: week.on(day).isEmpty ? c.muted : c.ink),
                    ),
                  ),
                  Expanded(
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        if (week.on(day).isEmpty && (before?.on(day).isEmpty ?? true))
                          Text(l.dayOff, style: context.text.bodySmall?.copyWith(color: c.faint)),
                        for (final b in week.on(day))
                          _TimeChip(wallRange(b.start, b.end, context.locale), added: before != null && !before.blocks.contains(b)),
                        if (before != null)
                          for (final b in before.on(day).where((b) => !week.blocks.contains(b)))
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 3),
                              child: Text(
                                wallRange(b.start, b.end, context.locale),
                                semanticsLabel: '${l.timeRemoved}: ${wallRange(b.start, b.end, context.locale)}',
                                style: context.text.bodySmall?.copyWith(color: c.dangerInk, decoration: TextDecoration.lineThrough),
                              ),
                            ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _TimeChip extends StatelessWidget {
  const _TimeChip(this.label, {this.added = false});

  final String label;
  final bool added;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: added ? c.brandSoft : c.chip,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: added ? c.brand : c.chip),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (added) ...[Icon(Icons.add_rounded, size: 14, color: c.brandDeep), const SizedBox(width: 2)],
          Text(
            label,
            semanticsLabel: added ? '${context.l10n.timeAdded}: $label' : null,
            style: context.text.labelMedium?.copyWith(
              color: added ? c.brandDeep : c.ink,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
        ],
      ),
    );
  }
}

/// An approved schedule that starts later: when, how the hours change, and its week against today's.
class UpcomingCard extends StatelessWidget {
  const UpcomingCard({required this.upcoming, required this.current, super.key});

  final UpcomingShifts upcoming;
  final ShiftWeek current;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final before = current.weeklyMinutes;
    final after = upcoming.week.weeklyMinutes;
    return Container(
      key: const Key('shifts-upcoming'),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: c.brandSoft,
        borderRadius: BorderRadius.circular(context.shape.card),
        border: Border.all(color: c.brand.withValues(alpha: .45)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.event_available_rounded, color: c.brandDeep),
              const SizedBox(width: 8),
              Expanded(
                child: Text(l.upcomingTitle(upcoming.startsOn.label), style: context.text.titleMedium?.copyWith(color: c.brandDeep)),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(l.upcomingNote, style: context.text.bodySmall),
          const SizedBox(height: 8),
          Pill(
            before == after
                ? l.hoursSame(hoursAmount(context, after / 60))
                : l.hoursChange(hoursAmount(context, before / 60), hoursAmount(context, after / 60)),
            tone: Tone.brand,
            icon: Icons.schedule_rounded,
          ),
          const SizedBox(height: 10),
          WeekView(week: upcoming.week, compareTo: current.blocks.isEmpty ? null : current),
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
              ),
            ),
        ],
      ),
    );
  }
}

/// The open proposal (or draft): its step, what it means, its week, and the one thing to do now.
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
    final (hint, tone) = switch (stage) {
      ShiftStage.draft => (l.stageDraft, Tone.neutral),
      ShiftStage.review => (l.stageReview, Tone.info),
      ShiftStage.changesRequested => (l.stageChanges, Tone.attention),
      ShiftStage.countered => (l.academyCounter, Tone.attention),
      ShiftStage.approved => (l.stageApproved, Tone.brand),
      ShiftStage.rejected => (l.stageRejected, Tone.danger),
    };
    final secondary = TextButton.styleFrom(visualDensity: VisualDensity.compact);
    return LmsCard(
      key: const Key('shifts-proposal'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ShiftStepper(stage: stage),
          const SizedBox(height: 14),
          Note(hint, tone: tone, icon: stage == ShiftStage.countered ? Icons.compare_arrows_rounded : Icons.info_outline_rounded),
          const SizedBox(height: 12),
          Row(
            children: [
              Icon(Icons.event_rounded, size: 18, color: c.muted),
              const SizedBox(width: 6),
              Expanded(child: Text(l.startsOn(calendarDay(p.effectiveFrom, context.locale)), style: context.text.bodyMedium)),
              Text(l.weeklyHours(hoursAmount(context, p.week.weeklyMinutes / 60)), style: context.text.bodySmall),
            ],
          ),
          const SizedBox(height: 10),
          WeekView(week: p.week, compareTo: p.countered ? p.previousWeek : null),
          if ((p.note ?? '').isNotEmpty) ...[const SizedBox(height: 8), Text(p.note!, style: context.text.bodyMedium)],
          const SizedBox(height: 14),
          switch (stage) {
            ShiftStage.countered => FilledButton.icon(
              key: _acceptKey,
              onPressed: _busy ? null : () => _run((r) => r.acceptShifts(), l.proposalAccepted, celebrate: true),
              icon: const Icon(Icons.check_rounded),
              label: Text(l.acceptCounter),
            ),
            ShiftStage.review => OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: c.dangerInk,
                side: BorderSide(color: c.dangerInk.withValues(alpha: .5)),
              ),
              onPressed: _busy ? null : _withdraw,
              icon: const Icon(Icons.undo_rounded),
              label: Text(l.withdrawProposal),
            ),
            _ => FilledButton.icon(
              onPressed: _busy ? null : _edit,
              icon: const Icon(Icons.edit_calendar_rounded),
              label: Text(l.editAndSend),
            ),
          },
          if (stage == ShiftStage.countered || stage == ShiftStage.changesRequested)
            Wrap(
              alignment: WrapAlignment.center,
              children: [
                if (stage == ShiftStage.countered)
                  TextButton(style: secondary, onPressed: _busy ? null : _edit, child: Text(l.editAndSend)),
                TextButton(
                  style: secondary.copyWith(foregroundColor: WidgetStatePropertyAll(c.dangerInk)),
                  onPressed: _busy ? null : _withdraw,
                  child: Text(l.withdrawProposal),
                ),
              ],
            ),
        ],
      ),
    );
  }
}

/// No open proposal: where the last one ended (when recent), and starting a new one.
class NewProposalCard extends StatelessWidget {
  const NewProposalCard({required this.mine, this.last, super.key});

  final MyShifts mine;
  final ShiftDecision? last;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final d = last;
    final reason = d?.reason ?? '';
    return LmsCard(
      key: const Key('shifts-new'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (d != null) ...[
            ShiftStepper(stage: d.approved ? ShiftStage.approved : ShiftStage.rejected),
            const SizedBox(height: 14),
            Note(
              [d.approved ? l.stageApproved : l.stageRejected, if (reason.isNotEmpty) l.reasonLine(reason)].join('\n'),
              tone: d.approved ? Tone.brand : Tone.danger,
              icon: d.approved ? Icons.check_circle_outline_rounded : Icons.info_outline_rounded,
            ),
          ] else
            Text(l.newProposalHint, style: context.text.bodyMedium),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: () => editShifts(context, mine),
            icon: const Icon(Icons.edit_calendar_rounded),
            label: Text(l.proposeHours),
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

/// The last decisions: what was asked, how it ended, and the academy's reason for a refusal.
class HistoryList extends StatelessWidget {
  const HistoryList({required this.history, super.key});

  final List<ShiftDecision> history;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return LmsCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      child: Column(
        children: [
          for (final (i, d) in history.indexed) ...[
            if (i > 0) Divider(height: 1, color: c.line),
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Pill(d.statusLabel, tone: d.status == 'rejected' ? Tone.danger : (d.status == 'approved' ? Tone.brand : Tone.neutral)),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(l.askedToStart(calendarDay(d.effectiveFrom, context.locale)), style: context.text.bodyMedium),
                        if (d.decidedAt != null) Text(d.decidedAt!.label, style: context.text.bodySmall),
                        if ((d.reason ?? '').isNotEmpty)
                          Text(l.reasonLine(d.reason!), style: context.text.bodySmall?.copyWith(color: c.dangerInk)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
