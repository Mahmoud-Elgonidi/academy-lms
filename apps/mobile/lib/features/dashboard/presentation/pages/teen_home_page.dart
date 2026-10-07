import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../challenges/presentation/widgets/teasers.dart';
import '../../data/home_loader.dart';
import '../../domain/journey.dart';
import '../widgets/next_lesson_card.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';

/// A high-school student's plan: the goal, how far along, the finish date
/// at the current pace, the milestones, and what is next.
class TeenHomePage extends StatelessWidget {
  const TeenHomePage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<StudentHome>(c.read<HomeLoader>().studentHome)..load(),
    child: Scaffold(
      body: SafeArea(
        child: LoadView<StudentHome>(builder: (context, home) => _TeenHome(home: home)),
      ),
    ),
  );
}

class _TeenHome extends StatelessWidget {
  const _TeenHome({required this.home});

  final StudentHome home;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final name = context.select<SessionCubit, String>((s) => s.state.current?.userName ?? '');
    final course = home.journey.courses.firstOrNull;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(DateFormat('EEEE d MMMM', context.locale).format(DateTime.now()), style: context.text.bodySmall),
                  Text(l.planFor(name.split(' ').first), style: context.text.headlineSmall),
                ],
              ),
            ),
            const NotificationBell(),
            GestureDetector(onTap: () => context.push('/account'), child: InitialAvatar(name, size: 40)),
          ],
        ),
        const SizedBox(height: 14),
        if (course != null) ...[
          LmsCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(l.goal, style: context.text.labelMedium),
                          Text(course.program ?? course.subject, style: context.text.titleLarge),
                        ],
                      ),
                    ),
                    CountUp(
                      course.percent ?? 0,
                      suffix: '%',
                      style: context.text.headlineMedium?.copyWith(color: c.brand),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                ProgressBar((course.percent ?? 0) / 100, height: 6),
                const SizedBox(height: 14),
                if (course.finished)
                  Text(l.programDone, style: context.text.titleMedium?.copyWith(color: c.brandDeep))
                else
                  Row(
                    children: [
                      Expanded(
                        child: _Fact(label: l.finishOn, value: course.finishOn?.date(context.locale) ?? '—'),
                      ),
                      Expanded(
                        child: _Fact(label: l.pace, value: course.lessonsPerWeek == null ? '—' : l.perWeek(course.lessonsPerWeek!)),
                      ),
                    ],
                  ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          const ChallengesTeaser(route: '/teen/goals'),
          const SizedBox(height: 16),
          SectionLabel(l.milestones),
          LmsCard(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 8),
            child: Column(
              children: [for (final w in course.worlds) _Milestone(world: w, last: w == course.worlds.last)],
            ),
          ),
        ],
        const SizedBox(height: 16),
        NextLessonCard(lesson: home.next, onOpened: () => context.read<LoadCubit<StudentHome>>().load()),
        if (home.homework != null) ...[
          const SizedBox(height: 12),
          LmsCard(
            onTap: () => context.go('/teen/homework'),
            child: Row(
              children: [
                Icon(Icons.assignment_outlined, color: c.brand),
                const SizedBox(width: 12),
                Expanded(child: Text(l.homeworkLine(home.homework!.title), style: context.text.titleSmall)),
                if (home.homework!.dueAt != null) Text(home.homework!.dueAt!.relativeDay(context), style: context.text.bodySmall),
              ],
            ),
          ),
        ],
      ]),
    );
  }
}

class _Fact extends StatelessWidget {
  const _Fact({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: context.text.bodySmall),
      Text(value, style: context.text.titleSmall),
    ],
  );
}

class _Milestone extends StatelessWidget {
  const _Milestone({required this.world, required this.last});

  final World world;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final done = world.state == 'done';
    final here = world.state == 'here';
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 24,
            child: Column(
              children: [
                const SizedBox(height: 12),
                Container(
                  width: 16,
                  height: 16,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: done ? c.brand : (here ? c.card : c.chip),
                    border: Border.all(color: done || here ? c.brand : c.line, width: here ? 4 : 2),
                  ),
                  child: done ? const Icon(Icons.check, size: 10, color: Colors.white) : null,
                ),
                if (!last) Expanded(child: Container(width: 2, color: done ? c.brand : c.line)),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      world.title,
                      style: context.text.titleSmall?.copyWith(
                        color: done || here ? c.ink : c.muted,
                        fontWeight: here ? FontWeight.w700 : null,
                      ),
                    ),
                  ),
                  Text('${world.percent}%', style: context.text.bodySmall),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
