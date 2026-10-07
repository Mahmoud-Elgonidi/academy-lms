import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart' show DateFormat;

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
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

/// "My hours": the week in force on the teacher's clock, and a proposal for
/// a new one — sent, answered with a change by the academy (accept it or
/// edit), or talked over in the proposal's conversation.
class ShiftsPage extends StatelessWidget {
  const ShiftsPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<MyShifts>(c.read<TeachingRepository>().shifts)..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.myHours)),
      body: LoadView<MyShifts>(builder: (context, mine) => _Body(mine: mine)),
    ),
  );
}

class _Body extends StatelessWidget {
  const _Body({required this.mine});

  final MyShifts mine;

  Future<void> _edit(BuildContext context) async {
    final p = mine.proposal;
    final saved = await context.push<bool>(
      '/teach/hours/edit',
      extra: ShiftDraft(
        blocks: [...(p?.week ?? mine.current).blocks],
        effectiveFrom: p?.effectiveFrom ?? mine.earliestAdding,
        note: p?.note,
        minHours: p?.minWeeklyMinutes == null ? null : p!.minWeeklyMinutes! / 60,
        maxHours: p?.maxWeeklyMinutes == null ? null : p!.maxWeeklyMinutes! / 60,
        earliestAdding: mine.earliestAdding,
        earliestRemoving: mine.earliestRemoving,
        current: mine.current,
      ),
    );
    if (saved == true && context.mounted) await context.read<LoadCubit<MyShifts>>().load();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = mine.proposal;
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
        const SizedBox(height: 16),
        if (p == null)
          FilledButton.icon(onPressed: () => _edit(context), icon: const Icon(Icons.edit_calendar_rounded), label: Text(l.proposeHours))
        else
          _ProposalCard(proposal: p, onEdit: () => _edit(context)),
      ]),
    );
  }
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
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
      child: Column(
        children: [
          for (final (i, day) in weekOrder(context.locale).indexed) ...[
            if (i > 0) const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(width: 92, child: Text(dayName(day, context.locale), style: context.text.titleSmall)),
                  Expanded(
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        if (week.on(day).isEmpty && (before?.on(day).isEmpty ?? true)) Text(l.dayOff, style: context.text.bodySmall),
                        for (final b in week.on(day))
                          Pill(
                            '${b.start}–${b.end}',
                            tone: before != null && !before.blocks.contains(b) ? Tone.brand : Tone.neutral,
                            icon: before != null && !before.blocks.contains(b) ? Icons.add_rounded : null,
                          ),
                        if (before != null)
                          for (final b in before.on(day).where((b) => !week.blocks.contains(b)))
                            Text(
                              '${b.start}–${b.end}',
                              style: context.text.bodySmall?.copyWith(color: c.dangerInk, decoration: TextDecoration.lineThrough),
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

class _ProposalCard extends StatefulWidget {
  const _ProposalCard({required this.proposal, required this.onEdit});

  final ShiftProposal proposal;
  final VoidCallback onEdit;

  @override
  State<_ProposalCard> createState() => _ProposalCardState();
}

class _ProposalCardState extends State<_ProposalCard> {
  final _message = TextEditingController();
  final _acceptKey = GlobalKey();
  late List<ShiftComment> _comments = widget.proposal.comments;
  bool _busy = false;

  @override
  void didUpdateWidget(_ProposalCard old) {
    super.didUpdateWidget(old);
    _comments = widget.proposal.comments;
  }

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

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

  Future<void> _send() async {
    final body = _message.text.trim();
    if (body.isEmpty) return;
    setState(() => _busy = true);
    try {
      final p = await context.read<TeachingRepository>().commentOnShifts(body);
      if (!mounted) return;
      Juice.tap();
      _message.clear();
      setState(() => _comments = p.comments);
    } on ApiProblem catch (e) {
      if (mounted) showSnack(context, context.problemText(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final p = widget.proposal;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SectionLabel(
          l.yourProposal,
          trailing: Text(l.weeklyHours(hoursAmount(context, p.week.weeklyMinutes / 60)), style: context.text.bodySmall),
        ),
        Wrap(
          spacing: 8,
          runSpacing: 6,
          children: [
            Pill(p.statusLabel, tone: p.myTurn ? Tone.attention : Tone.info),
            Pill(p.myTurn ? l.waitingOnYou : l.waitingOnAcademy, tone: Tone.neutral, icon: Icons.hourglass_bottom_rounded),
            Pill(l.startsOn(calendarDay(p.effectiveFrom, context.locale)), tone: Tone.neutral, icon: Icons.event_rounded),
          ],
        ),
        const SizedBox(height: 10),
        if (p.countered) ...[Note(l.academyCounter, tone: Tone.attention, icon: Icons.compare_arrows_rounded), const SizedBox(height: 8)],
        WeekView(week: p.week, compareTo: p.countered ? p.previousWeek : null),
        if ((p.note ?? '').isNotEmpty) ...[const SizedBox(height: 8), Text(p.note!, style: context.text.bodyMedium)],
        const SizedBox(height: 12),
        if (p.countered)
          FilledButton.icon(
            key: _acceptKey,
            onPressed: _busy ? null : () => _run((r) => r.acceptShifts(), l.proposalAccepted, celebrate: true),
            icon: const Icon(Icons.check_rounded),
            label: Text(l.acceptCounter),
          ),
        if (p.status != 'submitted' || p.countered) ...[
          const SizedBox(height: 8),
          OutlinedButton.icon(onPressed: _busy ? null : widget.onEdit, icon: const Icon(Icons.edit_rounded), label: Text(l.editProposal)),
        ],
        if (p.status != 'draft')
          TextButton(
            style: TextButton.styleFrom(foregroundColor: c.dangerInk),
            onPressed: _busy ? null : _withdraw,
            child: Text(l.withdraw),
          ),
        if (p.status != 'draft') ...[
          const SizedBox(height: 8),
          SectionLabel(l.conversation),
          for (final m in _comments)
            Align(
              alignment: m.mine ? AlignmentDirectional.centerStart : AlignmentDirectional.centerEnd,
              child: Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                constraints: const BoxConstraints(maxWidth: 300),
                decoration: BoxDecoration(
                  color: m.mine ? c.brandSoft : c.card,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: c.line),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(m.body, style: context.text.bodyMedium),
                    if (m.at != null) Text(m.at!.when(context), style: context.text.bodySmall),
                  ],
                ),
              ),
            ).appear(context, 0),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _message,
                  minLines: 1,
                  maxLines: 4,
                  maxLength: 2000,
                  decoration: InputDecoration(hintText: l.writeMessage, counterText: ''),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filled(tooltip: l.send, onPressed: _busy ? null : _send, icon: const Icon(Icons.send_rounded)),
            ],
          ),
        ],
      ],
    );
  }
}
