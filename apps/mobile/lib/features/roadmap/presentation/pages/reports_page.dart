import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/learning_repository.dart';
import '../../domain/learning.dart';

/// The reports teachers wrote for the family: one course's, or (a teen's
/// Reports tab) every course's, latest first.
class ReportsPage extends StatelessWidget {
  const ReportsPage({this.enrollmentId, this.title, super.key});

  final String? enrollmentId;
  final String? title;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocProvider(
      create: (c) {
        final repo = c.read<LearningRepository>();
        return LoadCubit<List<Report>>(() => enrollmentId == null ? repo.allReports() : repo.reports(enrollmentId!))..load();
      },
      child: Scaffold(
        appBar: AppBar(title: Text(title == null ? l.reports : '${l.reports} · $title')),
        body: LoadView<List<Report>>(
          builder: (context, reports) => ListView(
            padding: pagePadding,
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              if (reports.isEmpty) EmptyNote(l.noReports, icon: Icons.article_outlined),
              for (final r in reports) ...[ReportCard(report: r), const SizedBox(height: 12)],
            ],
          ),
        ),
      ),
    );
  }
}

class ReportCard extends StatelessWidget {
  const ReportCard({required this.report, super.key});

  final Report report;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    Widget dots(String label, int? value) => value == null
        ? const SizedBox.shrink()
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('$label ', style: context.text.bodySmall),
              for (var i = 1; i <= 5; i++)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 1.5),
                  child: Icon(Icons.circle, size: 9, color: i <= value ? c.brand : c.line),
                ),
            ],
          );
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(report.lessonAt == null ? l.lastReport : l.reportOf(report.lessonAt!.day(context.locale)), style: context.text.titleMedium),
          Text(report.teacher, style: context.text.bodySmall),
          if (report.items.isNotEmpty) ...[
            const SizedBox(height: 10),
            for (final i in report.items)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  children: [
                    Expanded(child: Text(i.title, style: context.text.bodyMedium)),
                    Pill(
                      i.label,
                      tone: i.outcome == 'mastered' ? Tone.brand : (i.outcome == 'needs_review' ? Tone.attention : Tone.neutral),
                    ),
                  ],
                ),
              ),
          ],
          if ((report.notesForStudent ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: c.chip, borderRadius: BorderRadius.circular(context.shape.card - 6)),
              child: Text(report.notesForStudent!, style: context.text.bodyMedium?.copyWith(height: 1.7)),
            ),
          ],
          if ((report.absenceNote ?? '').isNotEmpty) ...[const SizedBox(height: 8), Note(report.absenceNote!, tone: Tone.attention)],
          const SizedBox(height: 10),
          Wrap(spacing: 14, runSpacing: 6, children: [dots(l.engagement, report.engagement), dots(l.overall, report.overall)]),
        ],
      ),
    );
  }
}
