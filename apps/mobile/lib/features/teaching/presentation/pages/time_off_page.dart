import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../core/realtime/live.dart';

/// Asking for time off on the teacher's own clock (whole days, or part of
/// one day), and the requests so far; a pending one can be withdrawn.
class TimeOffPage extends StatelessWidget {
  const TimeOffPage({super.key});

  @override
  Widget build(BuildContext context) {
    final zone = context.select<SessionCubit, String?>((s) => s.state.me?.displayTimezone ?? s.state.timezone);
    return BlocProvider(
      // A decision on leave moves lessons (the lessons signal); coming back to the app catches up too.
      create: (c) => LoadCubit<TimeOffList>(c.read<TeachingRepository>().timeOff, refreshOn: c.read<Live>().on({Live.lessons}))..load(),
      child: Scaffold(
        appBar: AppBar(
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(context.l10n.requestTimeOff),
              Text(context.l10n.timeOffLead(zoneName(zone, context.locale)), style: context.text.bodySmall),
            ],
          ),
        ),
        body: LoadView<TimeOffList>(
          builder: (context, list) => ListView(
            padding: pagePadding,
            physics: const AlwaysScrollableScrollPhysics(),
            children: staggered(context, [
              const _RequestForm(),
              const SizedBox(height: 20),
              SectionLabel(context.l10n.yourRequests),
              if (list.requests.isEmpty) Text(context.l10n.noTimeOff, style: context.text.bodySmall),
              for (final r in list.requests) ...[_RequestCard(request: r), const SizedBox(height: 10)],
              if (list.holidays.isNotEmpty) ...[
                const SizedBox(height: 10),
                SectionLabel(context.l10n.academyHolidays),
                for (final h in list.holidays) ...[
                  Note('${h.name} · ${h.period}', icon: Icons.celebration_outlined),
                  const SizedBox(height: 8),
                ],
              ],
            ]),
          ),
        ),
      ),
    );
  }
}

class _RequestForm extends StatefulWidget {
  const _RequestForm();

  @override
  State<_RequestForm> createState() => _RequestFormState();
}

class _RequestFormState extends State<_RequestForm> {
  static const _reasons = ['sick', 'vacation', 'personal', 'family', 'emergency', 'other'];

  final _note = TextEditingController();
  bool _partOfDay = false;
  DateTime? _first;
  DateTime? _last;
  TimeOfDay? _from;
  TimeOfDay? _to;
  String? _reason;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  String _reasonLabel(String r) {
    final l = context.l10n;
    return switch (r) {
      'sick' => l.reasonSick,
      'vacation' => l.reasonVacation,
      'personal' => l.reasonPersonal,
      'family' => l.reasonFamily,
      'emergency' => l.reasonEmergency,
      _ => l.reasonOther,
    };
  }

  Future<DateTime?> _pickDay(DateTime? initial) {
    final today = DateUtils.dateOnly(DateTime.now());
    return showDatePicker(
      context: context,
      initialDate: initial ?? today,
      firstDate: today,
      lastDate: today.add(const Duration(days: 365)),
    );
  }

  Future<TimeOfDay?> _pickTime(TimeOfDay? initial) =>
      showTimePicker(context: context, initialTime: initial ?? const TimeOfDay(hour: 17, minute: 0));

  static String _hhmm(TimeOfDay t) => '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> _send() async {
    final l = context.l10n;
    if (_first == null || _reason == null || (_partOfDay && (_from == null || _to == null))) {
      setState(() => _error = l.fillTimeOff);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<TeachingRepository>().requestTimeOff(
        firstDay: _first!,
        lastDay: _partOfDay ? null : (_last ?? _first),
        from: _partOfDay ? _hhmm(_from!) : null,
        to: _partOfDay ? _hhmm(_to!) : null,
        reason: _reason!,
        note: _note.text,
      );
      if (!mounted) return;
      showSnack(context, l.requestSent);
      setState(() {
        _busy = false;
        _first = _last = null;
        _from = _to = null;
        _reason = null;
        _note.clear();
      });
      await context.read<LoadCubit<TimeOffList>>().load();
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = context.problemText(p);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final day = DateFormat('EEEE d MMMM', context.locale);
    Widget field(String label, String? value, VoidCallback onTap) => InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(context.shape.field),
      child: InputDecorator(
        decoration: InputDecoration(labelText: label),
        child: Text(value ?? '—', style: context.text.bodyLarge),
      ),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SegmentedButton<bool>(
          showSelectedIcon: false,
          segments: [
            ButtonSegment(value: false, label: Text(l.wholeDays)),
            ButtonSegment(value: true, label: Text(l.partOfDay)),
          ],
          selected: {_partOfDay},
          onSelectionChanged: (v) => setState(() => _partOfDay = v.first),
        ),
        const SizedBox(height: 14),
        if (!_partOfDay)
          Row(
            children: [
              Expanded(
                child: field(l.fromDay, _first == null ? null : day.format(_first!), () async {
                  final d = await _pickDay(_first);
                  if (d != null) setState(() => _first = d);
                }),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: field(l.toDay, (_last ?? _first) == null ? null : day.format(_last ?? _first!), () async {
                  final d = await _pickDay(_last ?? _first);
                  if (d != null) setState(() => _last = d);
                }),
              ),
            ],
          )
        else ...[
          field(l.dayLabel, _first == null ? null : day.format(_first!), () async {
            final d = await _pickDay(_first);
            if (d != null) setState(() => _first = d);
          }),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: field(l.fromTime, _from?.format(context), () async {
                  final t = await _pickTime(_from);
                  if (t != null) setState(() => _from = t);
                }),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: field(l.toTime, _to?.format(context), () async {
                  final t = await _pickTime(_to ?? _from);
                  if (t != null) setState(() => _to = t);
                }),
              ),
            ],
          ),
        ],
        const SizedBox(height: 14),
        Text(l.reason, style: context.text.titleSmall),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final r in _reasons)
              ChoiceChip(label: Text(_reasonLabel(r)), selected: _reason == r, onSelected: (_) => setState(() => _reason = r)),
          ],
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _note,
          maxLines: 2,
          maxLength: 500,
          decoration: InputDecoration(labelText: '${l.noteForAcademy} ${l.optional}'),
        ),
        if (_error != null) ...[Note(_error!, tone: Tone.danger, icon: Icons.error_outline_rounded), const SizedBox(height: 10)],
        FilledButton(onPressed: _busy ? null : _send, child: Text(l.sendRequest)),
      ],
    );
  }
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({required this.request});

  final TimeOff request;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final r = request;
    final tone = switch (r.status) {
      'approved' => Tone.brand,
      'rejected' => Tone.danger,
      'withdrawn' => Tone.neutral,
      _ => Tone.attention,
    };
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(r.period, style: context.text.titleSmall)),
              Pill(r.statusLabel, tone: tone),
            ],
          ),
          Text([r.reasonLabel, if ((r.note ?? '').isNotEmpty) r.note!].join(' · '), style: context.text.bodySmall),
          if ((r.decisionNote ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            Text('"${r.decisionNote}"', style: context.text.bodyMedium),
          ],
          if (r.cover != null && r.cover!.total > 0) ...[
            const SizedBox(height: 6),
            Text(l.coverState(r.cover!.covered, r.cover!.total), style: context.text.bodySmall),
          ],
          if (r.canWithdraw)
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: TextButton(
                style: TextButton.styleFrom(foregroundColor: context.colors.dangerInk),
                onPressed: () async {
                  try {
                    await context.read<TeachingRepository>().withdrawTimeOff(r.id);
                    if (!context.mounted) return;
                    showSnack(context, l.withdrawn);
                    await context.read<LoadCubit<TimeOffList>>().load();
                  } on ApiProblem catch (p) {
                    if (context.mounted) showSnack(context, context.problemText(p));
                  }
                },
                child: Text(l.withdraw),
              ),
            ),
        ],
      ),
    );
  }
}
