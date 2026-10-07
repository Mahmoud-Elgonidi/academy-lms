import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../data/learning_repository.dart';
import '../../domain/learning.dart';
import '../../../../shared/motion/motion.dart';

/// Where each course stands for the learner: the program, how far, the
/// lesson now, the weeks left, open homework; a course opens its reports.
class ProgressPage extends StatelessWidget {
  const ProgressPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final learners = context.watch<LearnerCubit>().state;
    final learner = learners.selected;
    return Scaffold(
      appBar: AppBar(title: Text(learner == null ? l.navProgress : l.progressOf(learner.firstName))),
      body: Column(
        children: [
          const LearnerSwitcher(),
          Expanded(
            child: BlocProvider(
              create: (c) => LoadCubit<List<StudentCourses>>(c.read<LearningRepository>().students)..load(),
              child: LoadView<List<StudentCourses>>(
                builder: (context, students) {
                  final courses = students.where((s) => learner == null || s.id == learner.id).expand((s) => s.courses).toList();
                  return ListView(
                    padding: pagePadding,
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: staggered(context, [
                      if (courses.isEmpty) EmptyNote(l.noCourses, icon: Icons.menu_book_outlined),
                      for (final course in courses) ...[_CourseCard(course: course), const SizedBox(height: 12)],
                    ]),
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CourseCard extends StatelessWidget {
  const _CourseCard({required this.course});

  final Course course;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return LmsCard(
      onTap: () => context.push('/reports/${course.enrollmentId}?title=${Uri.encodeComponent(course.subject)}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(course.subject, style: context.text.titleMedium),
                    Text('${course.program ?? l.noProgram} · ${course.teacher}', style: context.text.bodySmall),
                  ],
                ),
              ),
              if (course.percent != null) Text('${course.percent}%', style: context.text.titleLarge?.copyWith(color: c.brand)),
            ],
          ),
          if (course.percent != null) ...[const SizedBox(height: 10), ProgressBar(course.percent! / 100)],
          const SizedBox(height: 10),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              if (course.current != null) Pill(l.hereNow(course.current!), tone: Tone.brand),
              if (course.etaWeeks != null) Pill(l.weeksLeft(course.etaWeeks!), tone: Tone.info),
              if (course.homeworkOpen > 0) Pill(l.homeworkOpen(course.homeworkOpen), tone: Tone.attention),
            ],
          ),
          const SizedBox(height: 6),
          Align(
            alignment: AlignmentDirectional.centerEnd,
            child: Text(l.reports, style: context.text.titleSmall?.copyWith(color: c.brandDeep)),
          ),
        ],
      ),
    );
  }
}
