import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../schedule/domain/lesson.dart';
import '../../../schedule/presentation/widgets/lesson_card.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../core/realtime/live.dart';

/// Seven days of lessons from today (or another week), day by day.
class WeekPage extends StatefulWidget {
  const WeekPage({super.key});

  @override
  State<WeekPage> createState() => _WeekPageState();
}

class _WeekPageState extends State<WeekPage> {
  int _offset = 0;

  /// Today on the teacher's calendar, as the API first answered.
  DateTime? _today;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final from = _today?.add(Duration(days: 7 * _offset));
    return Scaffold(
      appBar: AppBar(
        title: Text(l.navWeek),
        actions: [
          IconButton(
            tooltip: l.prevWeek,
            onPressed: _today == null ? null : () => setState(() => _offset--),
            icon: const Icon(Icons.chevron_left_rounded),
          ),
          IconButton(
            tooltip: l.nextWeek,
            onPressed: _today == null ? null : () => setState(() => _offset++),
            icon: const Icon(Icons.chevron_right_rounded),
          ),
        ],
      ),
      body: BlocProvider(
        key: ValueKey(_offset),
        create: (c) => LoadCubit<TeacherLessons>(() async {
          final week = await c.read<TeachingRepository>().lessons(from: from);
          if (_today == null && mounted) setState(() => _today = week.from);
          return week;
        }, refreshOn: c.read<Live>().on({Live.lessons}))..load(),
        child: LoadView<TeacherLessons>(builder: (context, week) => _Days(week: week)),
      ),
    );
  }
}

class _Days extends StatelessWidget {
  const _Days({required this.week});

  final TeacherLessons week;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final locale = context.locale;
    final byDay = <String, List<Lesson>>{};
    for (final lesson in week.lessons) {
      byDay.putIfAbsent(lesson.startsAt.localDate, () => []).add(lesson);
    }
    final range = '${DateFormat('d MMM', locale).format(week.from)} – ${DateFormat('d MMM', locale).format(week.to)}';
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        Text(range, style: Theme.of(context).textTheme.titleLarge),
        Text(
          l.lessonsHours(l.lessonsCount(week.count), hoursAmount(context, week.minutes / 60)),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 10),
        if (week.lessons.isEmpty) EmptyNote(l.noLessonsWeek),
        if (week.lessons.isNotEmpty)
          for (var d = 0; d < 7; d++)
            ...() {
              final day = week.from.add(Duration(days: d));
              final key = DateFormat('yyyy-MM-dd').format(day);
              final lessons = byDay[key] ?? const <Lesson>[];
              return [
                SectionLabel(DateFormat('EEEE d MMMM', locale).format(day)),
                if (lessons.isEmpty)
                  Padding(
                    padding: const EdgeInsetsDirectional.only(start: 4, bottom: 10),
                    child: Text(l.noLessonsDay, style: Theme.of(context).textTheme.bodySmall),
                  ),
                for (final lesson in lessons) ...[LessonCard(lesson: lesson), const SizedBox(height: 10)],
              ];
            }(),
      ]),
    );
  }
}
