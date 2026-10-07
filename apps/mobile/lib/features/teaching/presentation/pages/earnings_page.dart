import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';

/// The teacher's pay for a month (the academy's clock): the total so far,
/// lessons and hours, bonuses and deductions, and each lesson's line.
class EarningsPage extends StatefulWidget {
  const EarningsPage({super.key});

  @override
  State<EarningsPage> createState() => _EarningsPageState();
}

class _EarningsPageState extends State<EarningsPage> {
  /// "YYYY-MM"; null is this month.
  String? _month;

  /// This month, as the API first answered.
  String? _current;

  static String _shift(String month, int by) {
    final y = int.parse(month.substring(0, 4));
    final m = int.parse(month.substring(5, 7)) + by;
    final d = DateTime(y, m);
    return '${d.year}-${d.month.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final shown = _month ?? _current;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.navEarnings),
        actions: [
          IconButton(
            tooltip: l.prevMonth,
            onPressed: shown == null ? null : () => setState(() => _month = _shift(shown, -1)),
            icon: const Icon(Icons.chevron_left_rounded),
          ),
          IconButton(
            tooltip: l.nextMonth,
            onPressed: shown == null || shown == _current ? null : () => setState(() => _month = _shift(shown, 1)),
            icon: const Icon(Icons.chevron_right_rounded),
          ),
        ],
      ),
      body: BlocProvider(
        key: ValueKey(_month),
        create: (c) => LoadCubit<Earnings>(() async {
          final e = await c.read<TeachingRepository>().earnings(month: _month);
          if (_current == null && mounted) setState(() => _current = e.month);
          return e;
        })..load(),
        child: LoadView<Earnings>(builder: (context, e) => _Month(earnings: e)),
      ),
    );
  }
}

class _Month extends StatelessWidget {
  const _Month({required this.earnings});

  final Earnings earnings;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final e = earnings;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        Text(e.label, style: context.text.titleLarge),
        const SizedBox(height: 10),
        for (final t in e.totals) ...[
          LmsCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(l.soFar, style: context.text.bodySmall)),
                    Pill(e.closed ? l.monthClosed : l.monthOpen, tone: e.closed ? Tone.neutral : Tone.info),
                  ],
                ),
                const SizedBox(height: 6),
                Text(t.total, style: context.text.headlineMedium),
                Text(l.lessonsHours(t.lessons, t.hours), style: context.text.bodyMedium?.copyWith(color: c.muted)),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _Box(label: l.lessonPay, value: t.lessonPay),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _Box(label: l.bonuses, value: t.bonuses, tone: Tone.brand),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _Box(label: l.deductions, value: t.deductions),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
        ],
        if (e.unpriced > 0) ...[Note(l.unpricedNote(e.unpriced), tone: Tone.attention), const SizedBox(height: 12)],
        if (e.lines.isEmpty && e.adjustments.isEmpty) EmptyNote(l.noEarnings, icon: Icons.account_balance_wallet_outlined),
        if (e.lines.isNotEmpty)
          LmsCard(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Column(
              children: [
                for (final (i, line) in e.lines.indexed) ...[
                  if (i > 0) const Divider(height: 1),
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${line.student} · ${line.subject}', style: context.text.titleSmall),
                              Text([line.when, line.share].where((x) => x.isNotEmpty).join(' · '), style: context.text.bodySmall),
                              if (line.outcome.isNotEmpty || line.corrected)
                                Padding(
                                  padding: const EdgeInsets.only(top: 4),
                                  child: Wrap(
                                    spacing: 6,
                                    children: [
                                      if (line.outcome.isNotEmpty) Pill(line.outcome),
                                      if (line.corrected) Pill(l.corrected, tone: Tone.info),
                                    ],
                                  ),
                                ),
                              for (final n in line.notes) Text(n, style: context.text.bodySmall),
                            ],
                          ),
                        ),
                        Text(line.amount, style: context.text.titleSmall?.copyWith(color: line.negative ? c.dangerInk : null)),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        if (e.adjustments.isNotEmpty) ...[
          const SizedBox(height: 12),
          SectionLabel(l.adjustments),
          LmsCard(
            child: Column(
              children: [
                for (final a in e.adjustments)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${a.kind} · ${a.at}', style: context.text.titleSmall),
                              if (a.note.isNotEmpty) Text(a.note, style: context.text.bodySmall),
                            ],
                          ),
                        ),
                        Text(a.amount, style: context.text.titleSmall?.copyWith(color: a.negative ? c.dangerInk : c.brandDeep)),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _Box extends StatelessWidget {
  const _Box({required this.label, required this.value, this.tone = Tone.neutral});

  final String label;
  final String value;
  final Tone tone;

  @override
  Widget build(BuildContext context) {
    final (bg, ink) = tone.colours(context);
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(fontSize: 12, color: ink)),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: AlignmentDirectional.centerStart,
            child: Text(
              value,
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: tone == Tone.neutral ? context.colors.ink : ink),
            ),
          ),
        ],
      ),
    );
  }
}
