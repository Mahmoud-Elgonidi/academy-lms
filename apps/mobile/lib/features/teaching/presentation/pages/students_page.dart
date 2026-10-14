import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../messages/data/messages_repository.dart';
import '../../../roadmap/presentation/widgets/material_chips.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';
import '../widgets/teacher_header.dart';
import '../widgets/waiting.dart';

/// The teacher's students: how many and in how many subjects, a search by
/// name and the subjects to narrow by, then one row each (the subject and
/// where they are, what waits on the teacher, a paused course); a row opens
/// the student's sheet.
class StudentsPage extends StatelessWidget {
  const StudentsPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) =>
        LoadCubit<List<TeacherStudent>>(c.read<TeachingRepository>().students, refreshOn: c.read<Live>().on({Live.lessons}))..load(),
    child: Builder(
      builder: (context) {
        final l = context.l10n;
        final list = context.select<LoadCubit<List<TeacherStudent>>, List<TeacherStudent>?>((c) => c.state.data);
        final subjects = {for (final s in list ?? const <TeacherStudent>[]) s.subject}.length;
        return TeacherTab(
          header: TeacherHeader(
            title: l.navStudents,
            eyebrow: list == null || list.isEmpty ? null : l.studentsIn(l.studentsCount(list.length), l.subjectsCount(subjects)),
          ),
          body: LoadView<List<TeacherStudent>>(
            skeleton: const SkeletonList(count: 4),
            builder: (context, students) => _Students(students: students),
          ),
        );
      },
    ),
  );
}

class _Students extends StatefulWidget {
  const _Students({required this.students});

  final List<TeacherStudent> students;

  @override
  State<_Students> createState() => _StudentsState();
}

class _StudentsState extends State<_Students> {
  String _query = '';
  String? _subject;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final all = widget.students;
    final todo = context.watch<LoadCubit<TeacherTodo>>().state.data;
    final bySubject = <String, int>{};
    for (final s in all) {
      bySubject.update(s.subject, (n) => n + 1, ifAbsent: () => 1);
    }
    final shown = [
      for (final s in all)
        if ((_subject == null || s.subject == _subject) && (_query.isEmpty || s.student.contains(_query))) s,
    ];
    if (all.isEmpty) {
      return ListView(
        padding: pagePadding,
        physics: const AlwaysScrollableScrollPhysics(),
        children: [EmptyState(icon: Icons.groups_outlined, title: l.noStudents)],
      );
    }
    return ListView(
      padding: pagePadding.copyWith(top: Space.m),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        TextField(
          key: const Key('student-search'),
          decoration: InputDecoration(hintText: l.searchByName, prefixIcon: const Icon(Icons.search_rounded)),
          onChanged: (v) => setState(() => _query = v.trim()),
        ),
        if (bySubject.length > 1) ...[
          const Gap(Space.m),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (final (i, (key, label)) in [
                  (null, l.allSubjects),
                  for (final MapEntry(:key, :value) in bySubject.entries) (key, '$key · $value'),
                ].indexed) ...[
                  if (i > 0) const Gap(Space.s),
                  ChoiceChip(
                    key: Key('subject-${key ?? 'all'}'),
                    label: Text(label),
                    selected: _subject == key,
                    showCheckmark: false,
                    onSelected: (_) => setState(() => _subject = key),
                  ),
                ],
              ],
            ),
          ),
        ],
        const Gap(Space.m),
        if (shown.isEmpty)
          Note(l.noStudentsFound, icon: Icons.search_off_rounded)
        else
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: staggered(context, [
                for (final (i, s) in shown.indexed) ...[
                  if (i > 0) const Divider(height: 1, indent: Space.l, endIndent: Space.l),
                  _StudentRow(student: s, report: todo?.reportsByCourse[s.enrollmentId]),
                ],
              ]),
            ),
          ),
      ],
    );
  }
}

/// One student: soft initials, the name, the subject and where they are,
/// then what waits on the teacher (a report, homework to mark) and a paused
/// course. It opens the student's sheet.
class _StudentRow extends StatelessWidget {
  const _StudentRow({required this.student, required this.report});

  final TeacherStudent student;

  /// The report still to write in this course.
  final String? report;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final s = student;
    final chips = [
      if (report != null) StatusChip(l.reportWaitingChip, tone: Tone.attention),
      if (s.toMark > 0) StatusChip(l.toMark(s.toMark), tone: Tone.info),
      if (s.paused) StatusChip(l.paused),
    ];
    return InkWell(
      key: Key('student-${s.enrollmentId}'),
      onTap: PressScale.withPress(context, () => _showStudent(context, s, report)),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m),
        child: Row(
          children: [
            Opacity(opacity: s.paused ? 0.55 : 1, child: SoftAvatar(s.student)),
            const Gap(Space.m),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(s.student, style: context.type.cardTitle, maxLines: 1, overflow: TextOverflow.ellipsis),
                  Text([s.subject, ?s.here].join(' · '), style: context.type.caption, maxLines: 1, overflow: TextOverflow.ellipsis),
                  if (chips.isNotEmpty) ...[const Gap(Space.xs + 2), Wrap(spacing: 6, runSpacing: 4, children: chips)],
                ],
              ),
            ),
            const Gap(Space.xs),
            Icon(Icons.chevron_right_rounded, color: c.faint),
          ],
        ),
      ),
    );
  }
}

/// A student's sheet: how far through the course, where they are now, the
/// last report, the lesson's materials, and what the teacher can do from
/// here (write the report waiting, mark their homework, send a message).
Future<void> _showStudent(BuildContext context, TeacherStudent s, String? report) => showActionSheet<void>(
  context,
  builder: (sheet) {
    final l = context.l10n;
    final c = context.colors;
    VoidCallback after(VoidCallback then) => () {
      Navigator.of(sheet).pop();
      then();
    };
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(Space.xl - 4, 0, Space.xl - 4, Space.xl),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              SoftAvatar(s.student, size: 52),
              const Gap(Space.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Semantics(header: true, child: Text(s.student, style: context.type.title)),
                    Text(s.subject, style: context.type.caption),
                  ],
                ),
              ),
              if (s.paused) StatusChip(l.paused),
            ],
          ),
          if (s.percent != null) ...[
            const Gap(Space.l),
            Text(
              l.ofCourse(s.percent!),
              style: context.type.body.copyWith(fontWeight: FontWeight.w700, color: c.brandDeep),
            ),
            const Gap(Space.s),
            ProgressBar(s.percent! / 100, height: 8),
          ],
          if (s.here != null) ...[const Gap(Space.m), Text(l.hereNow(s.here!), style: context.type.body)],
          if (s.lastReportAt != null) ...[
            const Gap(Space.xs),
            Text(l.lastReportAt(s.lastReportAt!.day(context.locale)), style: context.type.caption),
          ],
          if (s.materials.isNotEmpty) ...[const Gap(Space.m), MaterialChips(items: s.materials)],
          if (report != null) ...[
            const Gap(Space.l),
            PrimaryAction(l.writeTheReport, icon: Icons.edit_note_rounded, onPressed: after(() => openReport(context, report))),
          ],
          if (s.toMark > 0) ...[
            const Gap(Space.s),
            SecondaryAction(
              '${l.markHomework} (${s.toMark})',
              icon: Icons.edit_outlined,
              onPressed: after(() async {
                await context.push('/teach/marking');
                if (context.mounted) await reloadWaiting(context);
              }),
            ),
          ],
          if (s.studentId != null) ...[
            const Gap(Space.s),
            SecondaryAction(
              l.messageStudent,
              icon: Icons.chat_bubble_outline_rounded,
              onPressed: after(() => _message(context, s.studentId!)),
            ),
          ],
        ],
      ),
    );
  },
);

/// Opens (or starts) the conversation with the student and their guardians.
Future<void> _message(BuildContext context, String studentId) async {
  try {
    final talk = await context.read<MessagesRepository>().open(studentId);
    // Conversations live under More, where a message about it opens too.
    if (context.mounted) context.go('/teach/more/messages/${talk.id}');
  } on ApiProblem catch (p) {
    if (context.mounted) showSnack(context, context.problemText(p));
  }
}
