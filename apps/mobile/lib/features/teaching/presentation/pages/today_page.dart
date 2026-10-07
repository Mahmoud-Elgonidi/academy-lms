import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../dashboard/presentation/widgets/next_lesson_card.dart';
import '../../../schedule/presentation/widgets/lesson_card.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';
import '../../../../shared/motion/motion.dart';

class _Today {
  const _Today(this.lessons, this.todo);

  final TeacherLessons lessons;
  final TeacherTodo? todo;
}

/// The teacher's day on their own clock: how many lessons and hours, the
/// next one with its room, the rest of the day, and what waits (reports to
/// write, homework to mark).
class TodayPage extends StatelessWidget {
  const TodayPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) {
      final repo = c.read<TeachingRepository>();
      return LoadCubit<_Today>(() async {
        final lessons = repo.lessons(days: 1);
        TeacherTodo? todo;
        try {
          todo = await repo.todo();
        } on Exception {
          todo = null;
        }
        return _Today(await lessons, todo);
      })..load();
    },
    child: Scaffold(
      body: SafeArea(
        child: LoadView<_Today>(builder: (context, t) => _TodayList(today: t)),
      ),
    ),
  );
}

class _TodayList extends StatelessWidget {
  const _TodayList({required this.today});

  final _Today today;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final zone = context.select<SessionCubit, String?>((s) => s.state.me?.displayTimezone ?? s.state.timezone);
    final lessons = today.lessons.lessons;
    final next = lessons.where((x) => x.upcoming).firstOrNull;
    final todo = today.todo;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        const SizedBox(height: 6),
        Text(
          l.todayHeader(DateFormat('EEEE d MMMM', context.locale).format(today.lessons.from), zoneName(zone, context.locale)),
          style: context.text.bodySmall,
        ),
        const SizedBox(height: 2),
        Text(l.todayCount(today.lessons.count), style: context.text.headlineSmall),
        if (today.lessons.minutes > 0)
          Text(l.hours(hoursText(today.lessons.minutes)), style: context.text.bodyMedium?.copyWith(color: c.muted)),
        const SizedBox(height: 14),
        if (next != null) ...[
          NextLessonCard(lesson: next, onOpened: () => context.read<LoadCubit<_Today>>().load()),
          const SizedBox(height: 16),
        ],
        for (final lesson in lessons.where((x) => x.id != next?.id)) ...[LessonCard(lesson: lesson), const SizedBox(height: 10)],
        if (todo != null && (todo.reports > 0 || todo.homework > 0)) ...[
          const SizedBox(height: 6),
          Row(
            children: [
              if (todo.reports > 0)
                Expanded(
                  child: _Waiting(
                    count: todo.reports,
                    label: l.reportsWaiting(todo.reports),
                    caption: todo.firstReport == null
                        ? null
                        : '${todo.firstReport!.student}${todo.firstReport!.dueAt == null ? '' : ' · ${l.reportDueBy(todo.firstReport!.dueAt!.when(context))}'}',
                    tone: Tone.attention,
                  ),
                ),
              if (todo.reports > 0 && todo.homework > 0) const SizedBox(width: 10),
              if (todo.homework > 0)
                Expanded(
                  child: _Waiting(count: todo.homework, label: l.homeworkToMark(todo.homework), tone: Tone.info),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Text(l.writeOnWeb, style: context.text.bodySmall),
        ],
      ]),
    );
  }
}

class _Waiting extends StatelessWidget {
  const _Waiting({required this.count, required this.label, required this.tone, this.caption});

  final int count;
  final String label;
  final String? caption;
  final Tone tone;

  @override
  Widget build(BuildContext context) {
    final (bg, ink) = tone.colours(context);
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
            child: Text(
              '$count',
              style: TextStyle(color: ink, fontWeight: FontWeight.w800, fontSize: 16),
            ),
          ),
          const SizedBox(height: 8),
          Text(label, style: context.text.titleSmall),
          if (caption != null) Text(caption!, style: context.text.bodySmall),
        ],
      ),
    );
  }
}
