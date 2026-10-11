import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart' show DateFormat;

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/teaching_repository.dart';
import '../../domain/shifts.dart';
import 'shifts_page.dart';
import '../../../../shared/widgets/latin_digits.dart';

/// What the editor starts from: the open proposal, or the week in force.
class ShiftDraft {
  const ShiftDraft({
    required this.blocks,
    required this.effectiveFrom,
    required this.earliestAdding,
    required this.earliestRemoving,
    required this.current,
    this.note,
    this.minHours,
    this.maxHours,
  });

  final List<ShiftBlock> blocks;
  final String effectiveFrom;
  final String earliestAdding;
  final String earliestRemoving;
  final ShiftWeek current;
  final String? note;
  final double? minHours;
  final double? maxHours;
}

/// The teacher draws their week: spans per day on their own clock, the
/// start date, a note; then saves a draft or sends it to the academy. The
/// API checks it as the website does (overlaps, short spans, notice for
/// cutting hours, the contract). Pops with true once saved.
class ShiftEditorPage extends StatefulWidget {
  const ShiftEditorPage({required this.draft, super.key});

  final ShiftDraft draft;

  @override
  State<ShiftEditorPage> createState() => _ShiftEditorPageState();
}

class _ShiftEditorPageState extends State<ShiftEditorPage> {
  late final List<ShiftBlock> _blocks = [...widget.draft.blocks];
  late String _from = widget.draft.effectiveFrom;
  late final _note = TextEditingController(text: widget.draft.note);
  late final _min = TextEditingController(text: _hours(widget.draft.minHours));
  late final _max = TextEditingController(text: _hours(widget.draft.maxHours));
  bool _busy = false;
  String? _error;

  static String _hours(double? h) => h == null ? '' : (h == h.roundToDouble() ? '${h.round()}' : '$h');

  @override
  void dispose() {
    _note.dispose();
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  String _hhmm(TimeOfDay t) => '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  TimeOfDay _time(String hhmm) => TimeOfDay(hour: int.parse(hhmm.substring(0, 2)) % 24, minute: int.parse(hhmm.substring(3, 5)));

  Future<void> _addOrEdit(int weekday, [ShiftBlock? block]) async {
    final l = context.l10n;
    final start = await showTimePicker(
      context: context,
      initialTime: _time(block?.start ?? '17:00'),
      helpText: dayName(weekday, context.locale),
    );
    if (start == null || !mounted) return;
    final end = await showTimePicker(context: context, initialTime: _time(block?.end ?? '21:00'), helpText: l.untilMidnight);
    if (end == null || !mounted) return;
    final endText = end.hour == 0 && end.minute == 0 ? '24:00' : _hhmm(end);
    if (ShiftBlock.minutes(endText) <= ShiftBlock.minutes(_hhmm(start))) {
      showSnack(context, l.endAfterStart);
      return;
    }
    Juice.tap();
    setState(() {
      if (block != null) _blocks.remove(block);
      _blocks.add(ShiftBlock(weekday: weekday, start: _hhmm(start), end: endText));
    });
  }

  Future<void> _pickDate() async {
    final first = DateTime.parse(widget.draft.earliestAdding);
    final current = DateTime.tryParse(_from) ?? first;
    final picked = await showDatePicker(
      context: context,
      firstDate: first,
      lastDate: first.add(const Duration(days: 365)),
      initialDate: current.isBefore(first) ? first : current,
    );
    if (picked != null) setState(() => _from = DateFormat('yyyy-MM-dd').format(picked));
  }

  Future<void> _save({required bool submit}) async {
    final l = context.l10n;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<TeachingRepository>().saveShifts(
        blocks: _blocks,
        effectiveFrom: _from,
        note: _note.text.trim().isEmpty ? null : _note.text.trim(),
        minHours: double.tryParse(_min.text.trim()),
        maxHours: double.tryParse(_max.text.trim()),
        submit: submit,
      );
      if (!mounted) return;
      Juice.reward();
      showSnack(context, submit ? l.proposalSent : l.draftSaved);
      Navigator.of(context).pop(true);
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = [for (final m in p.errors.values) ...m].join('\n').ifEmpty(context.problemText(p));
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final week = ShiftWeek(timezone: widget.draft.current.timezone, blocks: _blocks);
    final cutting = week.weeklyMinutes < widget.draft.current.weeklyMinutes || widget.draft.current.blocks.any((b) => !_blocks.contains(b));
    return Scaffold(
      appBar: AppBar(title: Text(l.editHours)),
      body: ListView(
        padding: pagePadding,
        children: [
          Text(l.weeklyHours(hoursAmount(context, week.weeklyMinutes / 60)), style: context.text.titleMedium),
          const SizedBox(height: 10),
          LmsCard(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            child: Column(
              children: [
                for (final (i, day) in weekOrder(context.locale).indexed) ...[
                  if (i > 0) const Divider(height: 1),
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Row(
                      children: [
                        SizedBox(width: 84, child: Text(dayName(day, context.locale), style: context.text.titleSmall)),
                        Expanded(
                          child: Wrap(
                            spacing: 6,
                            runSpacing: 6,
                            children: [
                              for (final b in week.on(day))
                                InputChip(
                                  label: Text('${b.start}–${b.end}'),
                                  onPressed: () => _addOrEdit(day, b),
                                  onDeleted: () => setState(() => _blocks.remove(b)),
                                  deleteButtonTooltipMessage: l.remove,
                                ),
                            ],
                          ),
                        ),
                        IconButton(
                          tooltip: l.addTime,
                          onPressed: () => _addOrEdit(day),
                          icon: const Icon(Icons.add_circle_outline_rounded),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 14),
          LmsCard(
            onTap: _pickDate,
            child: Row(
              children: [
                const Icon(Icons.event_rounded),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l.effectiveFrom, style: context.text.labelMedium),
                      Text(calendarDay(_from, context.locale), style: context.text.titleSmall),
                    ],
                  ),
                ),
                const Icon(Icons.edit_calendar_outlined),
              ],
            ),
          ),
          if (cutting) ...[
            const SizedBox(height: 8),
            Note(l.reduceNotice(calendarDay(widget.draft.earliestRemoving, context.locale)), tone: Tone.attention),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _min,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: const [LatinDigits(decimal: true)],
                  decoration: InputDecoration(labelText: l.minHours),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  controller: _max,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: const [LatinDigits(decimal: true)],
                  decoration: InputDecoration(labelText: l.maxHours),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _note,
            minLines: 2,
            maxLines: 5,
            maxLength: 2000,
            decoration: InputDecoration(labelText: '${l.proposalNote} ${l.optional}'),
          ),
          if (_error != null) ...[const SizedBox(height: 8), Note(_error!, tone: Tone.danger, icon: Icons.error_outline_rounded)],
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: _busy ? null : () => _save(submit: true),
            icon: const Icon(Icons.send_rounded),
            label: Text(l.sendProposal),
          ),
          const SizedBox(height: 8),
          OutlinedButton(onPressed: _busy ? null : () => _save(submit: false), child: Text(l.saveDraft)),
        ],
      ),
    );
  }
}

extension on String {
  String ifEmpty(String other) => isEmpty ? other : this;
}
