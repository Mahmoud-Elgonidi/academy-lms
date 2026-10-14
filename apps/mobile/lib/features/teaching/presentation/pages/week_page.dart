import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../schedule/domain/lesson.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';
import '../widgets/teacher_header.dart';
import '../widgets/teacher_lessons.dart';
import '../widgets/waiting.dart';

/// The teacher's week, Saturday to Friday as the academy's calendar runs:
/// how many lessons and hours, the weeks before and after, the seven days as
/// a strip (each with its count; today picked first), and the lessons from
/// the day picked to the week's end, a card a day.
class WeekPage extends StatefulWidget {
  const WeekPage({super.key});

  @override
  State<WeekPage> createState() => _WeekPageState();
}

class _WeekPageState extends State<WeekPage> {
  int _offset = 0;

  /// Today on the teacher's calendar, as the API first answered.
  DateTime? _today;

  /// The day the list starts from (null: today in this week, else its Saturday).
  DateTime? _picked;

  /// The Saturday on or before [day].
  static DateTime _saturday(DateTime day) => DateTime(day.year, day.month, day.day - (day.weekday + 1) % 7);

  Future<TeacherLessons> _load(TeachingRepository repo) async {
    final today = _today;
    if (today != null) return repo.lessons(from: _saturday(today).add(Duration(days: 7 * _offset)));
    // The first answer says which day today is on the teacher's clock; the week is the one around it.
    final first = await repo.lessons();
    final start = _saturday(first.from);
    if (mounted) setState(() => _today = first.from);
    return start == first.from ? first : repo.lessons(from: start);
  }

  void _move(int by) => setState(() {
    _offset += by;
    _picked = null;
  });

  @override
  Widget build(BuildContext context) => BlocProvider(
    key: ValueKey(_offset),
    create: (c) => LoadCubit<TeacherLessons>(
      () => _load(c.read<TeachingRepository>()),
      refreshOn: c.read<Live>().on({Live.lessons}),
      refreshAt: (w) => w.lessons.expand((l) => l.clockMoments),
    )..load(),
    child: Builder(
      builder: (context) {
        final l = context.l10n;
        final week = context.select<LoadCubit<TeacherLessons>, TeacherLessons?>((c) => c.state.data);
        final today = _today;
        final picked =
            _picked ?? (week != null && today != null && !today.isBefore(week.from) && !today.isAfter(week.to) ? today : week?.from);
        return TeacherTab(
          header: TeacherHeader(
            title: l.navWeek,
            eyebrow: week == null ? null : l.lessonsHours(l.lessonsCount(week.count), hoursAmount(context, week.minutes / 60)),
            bottom: Column(
              children: [
                _WeekBar(week: week, offset: _offset, onMove: today == null ? null : _move),
                if (week != null && picked != null) ...[
                  const Gap(Space.m),
                  _Strip(week: week, today: today, picked: picked, onPick: (d) => setState(() => _picked = d)),
                ],
              ],
            ),
          ),
          body: LoadView<TeacherLessons>(
            skeleton: const SkeletonList(count: 3),
            builder: (context, week) => _Days(week: week, from: picked ?? week.from, today: today),
          ),
        );
      },
    ),
  );
}

/// The week's dates between the buttons to the weeks before and after.
class _WeekBar extends StatelessWidget {
  const _WeekBar({required this.week, required this.offset, required this.onMove});

  final TeacherLessons? week;
  final int offset;
  final void Function(int by)? onMove;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final locale = context.locale;
    final w = week;
    final which = switch (offset) {
      0 => l.thisWeek,
      -1 => l.prevWeek,
      1 => l.nextWeek,
      _ => null,
    };
    return Row(
      children: [
        Tooltip(
          message: l.prevWeek,
          excludeFromSemantics: true,
          child: HeaderButton(icon: Icons.chevron_left_rounded, label: l.prevWeek, onPressed: onMove == null ? null : () => onMove!(-1)),
        ),
        Expanded(
          child: Column(
            children: [
              if (w != null)
                Text(
                  '${DateFormat('EEEE d', locale).format(w.from)} – ${DateFormat('EEEE d MMMM', locale).format(w.to)}',
                  textAlign: TextAlign.center,
                  style: context.type.body.copyWith(fontWeight: FontWeight.w700),
                ),
              if (which != null) Text(which, style: context.type.caption),
            ],
          ),
        ),
        Tooltip(
          message: l.nextWeek,
          excludeFromSemantics: true,
          child: HeaderButton(icon: Icons.chevron_right_rounded, label: l.nextWeek, onPressed: onMove == null ? null : () => onMove!(1)),
        ),
      ],
    );
  }
}

/// The seven days, each with its date and how many lessons (— for none);
/// the one picked filled with the academy's colour.
class _Strip extends StatelessWidget {
  const _Strip({required this.week, required this.today, required this.picked, required this.onPick});

  final TeacherLessons week;
  final DateTime? today;
  final DateTime picked;
  final ValueChanged<DateTime> onPick;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final names = l.weekdaysShort.split(RegExp('[،,]'));
    final counts = <String, int>{};
    for (final x in week.lessons.where((x) => !x.cancelled)) {
      counts.update(x.startsAt.localDate, (n) => n + 1, ifAbsent: () => 1);
    }
    return Row(
      children: [
        for (var d = 0; d < 7; d++)
          ...() {
            final day = week.from.add(Duration(days: d));
            final key = DateFormat('yyyy-MM-dd').format(day);
            final n = counts[key] ?? 0;
            final on = DateUtils.isSameDay(day, picked);
            final isToday = today != null && DateUtils.isSameDay(day, today);
            final ink = on ? Colors.white : (n == 0 ? c.faint : c.ink);
            return [
              if (d > 0) const Gap(Space.xs),
              Expanded(
                child: Semantics(
                  selected: on,
                  button: true,
                  label: '${DateFormat('EEEE d MMMM', context.locale).format(day)}، ${l.lessonsCount(n)}',
                  excludeSemantics: true,
                  child: Material(
                    key: Key('day-$key'),
                    color: on ? c.brand : (n == 0 ? c.page : c.card),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(context.shape.field),
                      side: BorderSide(color: on ? c.brand : (isToday ? c.brand.withValues(alpha: 0.6) : c.line)),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: () => onPick(day),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: Space.s),
                        child: Column(
                          children: [
                            FittedBox(
                              child: Text(
                                names[(day.weekday + 1) % 7],
                                style: context.type.label.copyWith(color: on ? Colors.white.withValues(alpha: 0.9) : c.muted),
                              ),
                            ),
                            Text('${day.day}', style: context.type.number.copyWith(fontSize: 18, color: ink)),
                            Text(n == 0 ? '—' : '$n', style: context.type.label.copyWith(color: on ? Colors.white : c.muted)),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ];
          }(),
      ],
    );
  }
}

class _Days extends StatelessWidget {
  const _Days({required this.week, required this.from, required this.today});

  final TeacherLessons week;
  final DateTime from;
  final DateTime? today;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final locale = context.locale;
    final reports = context.watch<LoadCubit<TeacherTodo>>().state.data?.openReports ?? const <String, String>{};
    final byDay = <String, List<Lesson>>{};
    for (final lesson in week.lessons) {
      byDay.putIfAbsent(lesson.startsAt.localDate, () => []).add(lesson);
    }
    Future<void> reload() async {
      await Future.wait([context.read<LoadCubit<TeacherLessons>>().load(), reloadWaiting(context)]);
    }

    final days = [
      for (var d = 0; d < 7; d++)
        if (week.from.add(Duration(days: d)) case final day when !day.isBefore(from))
          if (byDay[DateFormat('yyyy-MM-dd').format(day)] case final lessons?) (day, lessons),
    ];
    return ListView(
      padding: pagePadding.copyWith(top: Space.s),
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        if (week.lessons.isEmpty)
          EmptyState(icon: Icons.event_available_outlined, title: l.noLessonsWeek)
        else if (days.isEmpty)
          Note(l.noLessonsLater, icon: Icons.event_available_outlined),
        for (final (day, lessons) in days) ...[
          SectionHeader(
            today != null && DateUtils.isSameDay(day, today)
                ? l.dayToday(DateFormat('EEEE d', locale).format(day))
                : DateFormat('EEEE d', locale).format(day),
          ),
          TeacherDayCard(lessons: lessons, openReports: reports, reload: reload),
        ],
      ]),
    );
  }
}
