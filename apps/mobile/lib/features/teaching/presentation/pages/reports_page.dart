import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/teaching_repository.dart';
import '../../domain/reports.dart';
import '../../../../core/realtime/live.dart';

/// The teacher's reports: the ones still to write first (overdue on top),
/// then those sent in the last two weeks, which can still be corrected.
class TeacherReportsPage extends StatelessWidget {
  const TeacherReportsPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) =>
        LoadCubit<List<ReportSummary>>(c.read<TeachingRepository>().reports, refreshOn: c.read<Live>().on({Live.lessons}))..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.navReports)),
      body: LoadView<List<ReportSummary>>(
        builder: (context, reports) {
          final l = context.l10n;
          final open = reports.where((r) => r.open).toList();
          final sent = reports.where((r) => !r.open).toList();
          return ListView(
            padding: pagePadding,
            physics: const AlwaysScrollableScrollPhysics(),
            children: staggered(context, [
              SectionLabel(l.reportsOpen),
              if (open.isEmpty) Note(l.noReportsWaiting, tone: Tone.brand, icon: Icons.task_alt_rounded),
              for (final r in open) ...[_ReportRow(report: r), const SizedBox(height: 10)],
              if (sent.isNotEmpty) ...[const SizedBox(height: 10), SectionLabel(l.reportsSent)],
              for (final r in sent) ...[_ReportRow(report: r), const SizedBox(height: 10)],
            ]),
          );
        },
      ),
    ),
  );
}

class _ReportRow extends StatelessWidget {
  const _ReportRow({required this.report});

  final ReportSummary report;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final r = report;
    final tone = switch (r.status) {
      'overdue' => Tone.danger,
      'due' => Tone.attention,
      'reviewed' => Tone.brand,
      _ => Tone.neutral,
    };
    return LmsCard(
      onTap: () async {
        final sent = await context.push<bool>('/teach/reports/${r.id}');
        if (sent == true && context.mounted) await context.read<LoadCubit<List<ReportSummary>>>().load();
      },
      child: Row(
        children: [
          Icon(r.absence ? Icons.event_busy_outlined : Icons.article_outlined, color: context.colors.brand),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(r.student, style: context.text.titleSmall),
                Text(
                  [r.absence ? l.reportAbsence : l.reportLesson, if (r.lessonAt != null) r.lessonAt!.when(context)].join(' · '),
                  style: context.text.bodySmall,
                ),
                if (r.open && r.dueAt != null) Text(l.reportDueBy(r.dueAt!.when(context)), style: context.text.bodySmall),
              ],
            ),
          ),
          Pill(r.statusLabel, tone: tone),
          const Icon(Icons.chevron_right_rounded),
        ],
      ),
    );
  }
}
