import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/report_drafts.dart';
import '../../data/teaching_repository.dart';
import '../../domain/reports.dart';
import '../../domain/teaching.dart';
import '../widgets/teacher_header.dart';
import '../widgets/waiting.dart';

/// The Reports tab: homework waiting to be marked as one row on top, then
/// the reports still to write (overdue first, each a tap from writing), the
/// ones begun and kept as drafts on this phone, then those sent in the last
/// two weeks, which can still be corrected.
class TeacherReportsPage extends StatefulWidget {
  const TeacherReportsPage({super.key});

  @override
  State<TeacherReportsPage> createState() => _TeacherReportsPageState();
}

class _TeacherReportsPageState extends State<TeacherReportsPage> {
  Map<String, ReportDraft> _drafts = const {};

  @override
  void initState() {
    super.initState();
    unawaited(_readDrafts());
  }

  Future<void> _readDrafts() async {
    final account = context.read<SessionCubit>().state.current?.id;
    if (account == null) return;
    final drafts = await ReportDrafts().all(account);
    if (mounted) setState(() => _drafts = drafts);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final todo = context.watch<LoadCubit<TeacherTodo>>().state.data;
    final waiting = todo == null
        ? null
        : switch ((todo.reports, todo.homework)) {
            (0, 0) => l.waitingNothing,
            (final r, 0) => l.waitingFor(l.reportsCount(r)),
            (0, final h) => l.waitingFor(l.homeworkCount(h)),
            (final r, final h) => l.waitingFor(l.bothWaiting(l.reportsCount(r), l.homeworkCount(h))),
          };
    return BlocProvider(
      create: (c) =>
          LoadCubit<List<ReportSummary>>(c.read<TeachingRepository>().reports, refreshOn: c.read<Live>().on({Live.lessons}))..load(),
      child: TeacherTab(
        header: TeacherHeader(title: l.navReports, eyebrow: waiting),
        body: LoadView<List<ReportSummary>>(
          skeleton: const SkeletonList(count: 4),
          builder: (context, reports) => _Reports(reports: reports, toMark: todo?.homework, drafts: _drafts, onBack: _readDrafts),
        ),
      ),
    );
  }
}

class _Reports extends StatelessWidget {
  const _Reports({required this.reports, required this.toMark, required this.drafts, required this.onBack});

  final List<ReportSummary> reports;
  final int? toMark;
  final Map<String, ReportDraft> drafts;

  /// Reads the drafts again on coming back from a report.
  final Future<void> Function() onBack;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final open = reports.where((r) => r.open).toList()..sort((a, b) => (b.status == 'overdue' ? 1 : 0) - (a.status == 'overdue' ? 1 : 0));
    final begun = open.where((r) => drafts.containsKey(r.id)).toList();
    final toWrite = open.where((r) => !drafts.containsKey(r.id)).toList();
    final sent = reports.where((r) => !r.open).toList();
    Future<void> openHere(String id) => _openHere(context, id, onBack);
    return ListView(
      padding: pagePadding.copyWith(top: Space.m),
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        _MarkingRow(count: toMark),
        SectionHeader(l.reportsOpen),
        if (open.isEmpty)
          Note(l.noReportsWaiting, tone: Tone.brand, icon: Icons.task_alt_rounded)
        else if (toWrite.isNotEmpty)
          _Card(
            rows: [for (final (i, r) in toWrite.indexed) _OpenRow(report: r, first: i == 0, open: openHere)],
          ),
        if (begun.isNotEmpty) ...[
          SectionHeader(l.drafts),
          _Card(
            rows: [for (final r in begun) _DraftRow(report: r, draft: drafts[r.id]!, open: openHere)],
          ),
        ],
        if (sent.isNotEmpty) ...[
          SectionHeader(l.reportsSent),
          _Card(
            rows: [for (final r in sent) _SentRow(report: r, open: openHere)],
          ),
        ],
      ]),
    );
  }
}

/// Whose report a row is: the student's (an absence says so), or a group
/// lesson's as one row with how many students it has.
String _who(BuildContext context, ReportSummary r) {
  final l = context.l10n;
  if (r.isGroup) return '${l.groupLessonRow} · ${l.groupStudentsCount(r.students.length)}';
  return [if (r.absence) l.reportAbsence, r.student].join(' · ');
}

/// Opens a report from this list; once back, the drafts are read again, and
/// the list too when it was sent.
Future<void> _openHere(BuildContext context, String id, Future<void> Function() onBack) async {
  final list = context.read<LoadCubit<List<ReportSummary>>>();
  final sent = await openReport(context, id);
  await onBack();
  if (sent) await list.load();
}

class _Card extends StatelessWidget {
  const _Card({required this.rows});

  final List<Widget> rows;

  @override
  Widget build(BuildContext context) => LmsCard(
    padding: EdgeInsets.zero,
    child: Column(
      children: [
        for (final (i, row) in rows.indexed) ...[if (i > 0) const Divider(height: 1, indent: Space.l, endIndent: Space.l), row],
      ],
    ),
  );
}

/// Homework handed in and waiting for a mark: one row that opens marking.
class _MarkingRow extends StatelessWidget {
  const _MarkingRow({required this.count});

  /// Null while it is not known.
  final int? count;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final n = count ?? 0;
    final some = n > 0;
    return Material(
      key: const Key('open-marking'),
      color: some ? c.attentionSoft : c.card,
      shape: RoundedRectangleBorder(
        borderRadius: context.shape.cardRadius,
        side: BorderSide(color: some ? c.attention.withValues(alpha: 0.4) : c.line),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: PressScale.withPress(context, () async {
          await context.push('/teach/marking');
          if (context.mounted) await reloadWaiting(context);
        }),
        child: Padding(
          padding: const EdgeInsets.all(Space.l),
          child: Row(
            children: [
              IconBadge(Icons.edit_outlined, tone: some ? Tone.attention : Tone.neutral, size: 40),
              const Gap(Space.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.markingRow, style: context.type.cardTitle),
                    Text(
                      some ? l.homeworkCount(n) : l.markingNone,
                      style: context.type.caption.copyWith(color: some ? c.attentionInk : null),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: some ? c.attentionInk : c.faint),
            ],
          ),
        ),
      ),
    );
  }
}

/// A report still to write: whose, when the lesson was (and how late, in
/// red), and Write: filled on the first, outlined on the others.
class _OpenRow extends StatelessWidget {
  const _OpenRow({required this.report, required this.first, required this.open});

  final ReportSummary report;
  final bool first;
  final Future<void> Function(String id) open;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final r = report;
    final overdue = r.status == 'overdue';
    void go() => open(r.id);
    final label = Text(l.writeReport, style: const TextStyle(fontWeight: FontWeight.w700));
    const size = Size(72, 44);
    return InkWell(
      key: Key('report-${r.id}'),
      onTap: go,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(_who(context, r), style: context.type.cardTitle, maxLines: 1, overflow: TextOverflow.ellipsis),
                  Text.rich(
                    TextSpan(
                      children: [
                        if (r.lessonAt != null) TextSpan(text: r.lessonAt!.when(context)),
                        if (overdue)
                          TextSpan(
                            text: '${r.lessonAt != null ? ' · ' : ''}${r.statusLabel}',
                            style: TextStyle(color: c.dangerInk, fontWeight: FontWeight.w600),
                          ),
                      ],
                    ),
                    style: context.type.caption,
                  ),
                ],
              ),
            ),
            const Gap(Space.m),
            first
                ? FilledButton(
                    onPressed: go,
                    style: FilledButton.styleFrom(minimumSize: size),
                    child: label,
                  )
                : OutlinedButton(
                    onPressed: go,
                    style: OutlinedButton.styleFrom(
                      minimumSize: size,
                      foregroundColor: c.brandDeep,
                      side: BorderSide(color: c.brand),
                    ),
                    child: label,
                  ),
          ],
        ),
      ),
    );
  }
}

/// A report sent: a check, whose, the day, where it stands when the academy read it.
class _SentRow extends StatelessWidget {
  const _SentRow({required this.report, required this.open});

  final ReportSummary report;
  final Future<void> Function(String id) open;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final r = report;
    final day = (r.submittedAt ?? r.lessonAt)?.day(context.locale);
    return InkWell(
      key: Key('report-${r.id}'),
      onTap: () => open(r.id),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 64),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.s),
          child: Row(
            children: [
              const IconBadge(Icons.check_rounded, tone: Tone.brand, size: 36),
              const Gap(Space.m),
              Expanded(
                child: Text(
                  _who(context, r),
                  style: context.type.body.copyWith(fontWeight: FontWeight.w600),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (r.status == 'reviewed') ...[StatusChip(r.statusLabel, tone: Tone.brand), const Gap(Space.s)],
              if (day != null) Text(day, style: context.type.caption),
              const Gap(Space.xs),
              Icon(Icons.chevron_right_rounded, color: c.faint, size: 22),
            ],
          ),
        ),
      ),
    );
  }
}

/// A report begun and kept on this phone: whose, when the lesson was and
/// how long ago it was kept, and Continue.
class _DraftRow extends StatelessWidget {
  const _DraftRow({required this.report, required this.draft, required this.open});

  final ReportSummary report;
  final ReportDraft draft;
  final Future<void> Function(String id) open;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final r = report;
    final ago = l.savedAgo(waitText(context, DateTime.now().toUtc().difference(draft.savedAt)));
    return InkWell(
      key: Key('draft-${r.id}'),
      onTap: () => open(r.id),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(_who(context, r), style: context.type.cardTitle, maxLines: 1, overflow: TextOverflow.ellipsis),
                  Text([if (r.lessonAt != null) r.lessonAt!.when(context), ago].join(' · '), style: context.type.caption),
                ],
              ),
            ),
            const Gap(Space.s),
            Text(
              l.continueDraft,
              style: context.type.body.copyWith(color: c.brandDeep, fontWeight: FontWeight.w700),
            ),
            Icon(Icons.chevron_right_rounded, color: c.faint, size: 22),
          ],
        ),
      ),
    );
  }
}
