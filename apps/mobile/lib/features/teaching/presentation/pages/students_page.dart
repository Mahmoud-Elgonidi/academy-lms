import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';

/// The teacher's students: subject, how far along, the lesson now, homework to mark.
class StudentsPage extends StatelessWidget {
  const StudentsPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<List<TeacherStudent>>(c.read<TeachingRepository>().students)..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.studentsTitle)),
      body: LoadView<List<TeacherStudent>>(
        builder: (context, students) => ListView(
          padding: pagePadding,
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            if (students.isEmpty) EmptyNote(context.l10n.noStudents, icon: Icons.groups_outlined),
            for (final s in students) ...[_StudentCard(student: s), const SizedBox(height: 10)],
          ],
        ),
      ),
    ),
  );
}

class _StudentCard extends StatelessWidget {
  const _StudentCard({required this.student});

  final TeacherStudent student;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = student;
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              InitialAvatar(s.student, size: 40),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(s.student, style: context.text.titleMedium),
                    Text(s.subject, style: context.text.bodySmall),
                  ],
                ),
              ),
              if (s.percent != null) Text('${s.percent}%', style: context.text.titleMedium?.copyWith(color: context.colors.brand)),
            ],
          ),
          if (s.percent != null) ...[const SizedBox(height: 10), ProgressBar(s.percent! / 100, height: 6)],
          const SizedBox(height: 8),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              if (s.here != null) Pill(l.hereNow(s.here!), tone: Tone.brand),
              if (s.toMark > 0) Pill(l.toMark(s.toMark), tone: Tone.attention),
              if (s.paused) Pill(l.paused),
            ],
          ),
          if (s.lastReportAt != null) ...[
            const SizedBox(height: 6),
            Text(l.lastReportAt(s.lastReportAt!.day(context.locale)), style: context.text.bodySmall),
          ],
        ],
      ),
    );
  }
}
