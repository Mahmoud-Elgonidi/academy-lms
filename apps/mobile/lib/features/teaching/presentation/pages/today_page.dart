import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../dashboard/presentation/widgets/family_header.dart' show AcademyLogo;
import '../../../dashboard/presentation/widgets/next_lesson_card.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';
import '../widgets/teacher_header.dart';
import '../widgets/teacher_lessons.dart';
import '../widgets/waiting.dart';

/// The teacher's day on their own clock: the academy and the day above, how
/// many lessons, the next one as the one surface in the academy's colour,
/// what waits (reports to write, homework to mark; each opens its place),
/// then the rest of the day and what is done (Write on a lesson whose report
/// is still to write).
class TodayPage extends StatelessWidget {
  const TodayPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<TeacherLessons>(
      () => c.read<TeachingRepository>().lessons(days: 1),
      refreshOn: c.read<Live>().on({Live.lessons}),
      refreshAt: (d) => d.lessons.expand((l) => l.clockMoments),
    )..load(),
    child: const _Today(),
  );
}

class _Today extends StatelessWidget {
  const _Today();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = context.watch<SessionCubit>().state;
    final zone = s.me?.displayTimezone ?? s.timezone;
    final day = context.select<LoadCubit<TeacherLessons>, TeacherLessons?>((c) => c.state.data);
    final account = s.current;
    return TeacherTab(
      header: TeacherHeader(
        leading: account == null ? null : AcademyLogo(account: account),
        eyebrow: day == null
            ? null
            : l.todayHeader(DateFormat('EEEE d MMMM', context.locale).format(day.from), zoneName(zone, context.locale)),
        title: day == null ? l.navToday : l.todayCount(day.count),
      ),
      body: LoadView<TeacherLessons>(
        skeleton: const SkeletonList(count: 3),
        builder: (context, t) => _Day(day: t),
      ),
    );
  }
}

class _Day extends StatelessWidget {
  const _Day({required this.day});

  final TeacherLessons day;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final todo = context.watch<LoadCubit<TeacherTodo>>().state.data;
    final lessons = day.lessons;
    final next = lessons.where((x) => x.upcoming).firstOrNull;
    final later = lessons.where((x) => x.upcoming && x.id != next?.id).toList();
    final done = lessons.where((x) => !x.upcoming).toList();
    final reports = todo?.openReports ?? const <String, String>{};
    Future<void> reload() async {
      await Future.wait([context.read<LoadCubit<TeacherLessons>>().load(), reloadWaiting(context)]);
    }

    return ListView(
      padding: pagePadding.copyWith(top: Space.m),
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        if (next != null)
          NextLessonCard(lesson: next)
        else if (lessons.isEmpty)
          EmptyState(
            icon: Icons.wb_sunny_outlined,
            title: l.restDay,
            action: OutlinedButton(onPressed: () => context.go('/teach/week'), child: Text(l.openWeek)),
          ),
        if (todo != null) ...[const Gap(Space.m), _Waiting(todo: todo)],
        if (later.isNotEmpty) ...[SectionHeader(l.laterToday), TeacherDayCard(lessons: later, openReports: reports, reload: reload)],
        if (done.isNotEmpty) ...[SectionHeader(l.doneToday), TeacherDayCard(lessons: done, openReports: reports, reload: reload)],
      ]),
    );
  }
}

/// Reports to write and homework to mark, side by side; each opens its
/// place while there is any.
class _Waiting extends StatelessWidget {
  const _Waiting({required this.todo});

  final TeacherTodo todo;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final first = todo.firstReport;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: StatTile(
              key: const Key('today-reports'),
              icon: Icons.description_outlined,
              tone: todo.reports > 0 ? Tone.attention : Tone.brand,
              label: l.reportsOpen,
              value: Text('${todo.reports}'),
              caption: todo.reports == 0 ? l.allWritten : (first == null ? null : l.oldestFor(first.student)),
              onTap: todo.reports == 0 ? null : () => context.go('/teach/reports'),
            ),
          ),
          const Gap(Space.m),
          Expanded(
            child: StatTile(
              key: const Key('today-marking'),
              icon: Icons.edit_outlined,
              tone: todo.homework > 0 ? Tone.info : Tone.brand,
              label: l.markingTile,
              value: Text('${todo.homework}'),
              caption: todo.homework == 0 ? l.nothingToMark : null,
              onTap: todo.homework == 0
                  ? null
                  : () async {
                      await context.push('/teach/marking');
                      if (context.mounted) await reloadWaiting(context);
                    },
            ),
          ),
        ],
      ),
    );
  }
}
