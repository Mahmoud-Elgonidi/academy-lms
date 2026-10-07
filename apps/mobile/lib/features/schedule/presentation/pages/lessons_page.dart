import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/stage.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../data/lessons_repository.dart';
import '../../domain/lesson.dart';
import '../widgets/lesson_card.dart';
import '../widgets/lesson_sheets.dart';
import '../../../../shared/motion/motion.dart';

/// A learner's lessons, four weeks ahead or back, by day; on the viewer's
/// clock. Holidays and clock changes that touch them come first.
class LessonsPage extends StatefulWidget {
  const LessonsPage({super.key});

  @override
  State<LessonsPage> createState() => _LessonsPageState();
}

class _LessonsPageState extends State<LessonsPage> {
  bool _past = false;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final learners = context.watch<LearnerCubit>();
    final learner = learners.state.selected;
    final family = learners.scope == LearnerScope.family;
    final zone = context.select<SessionCubit, String?>((s) => s.state.me?.displayTimezone ?? s.state.timezone);
    final learnerId = context.learnerId();
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(family && learner != null ? l.lessonsOf(learner.firstName) : l.navMyLessons),
            if (zone != null) Text(l.onClock(zoneName(zone, context.locale)), style: context.text.bodySmall),
          ],
        ),
      ),
      body: Column(
        children: [
          if (family) const LearnerSwitcher(),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 8),
            child: SizedBox(
              width: double.infinity,
              child: SegmentedButton<bool>(
                showSelectedIcon: false,
                segments: [
                  ButtonSegment(value: false, label: Text(l.upcoming)),
                  ButtonSegment(value: true, label: Text(l.past)),
                ],
                selected: {_past},
                onSelectionChanged: (v) => setState(() => _past = v.first),
              ),
            ),
          ),
          Expanded(
            child: !learners.state.loaded
                ? const Center(child: CircularProgressIndicator.adaptive())
                : BlocProvider(
                    key: ValueKey('$learnerId:$_past'),
                    create: (c) =>
                        LoadCubit<LessonList>(() => c.read<LessonsRepository>().family(learnerId: learnerId, past: _past))..load(),
                    child: LoadView<LessonList>(
                      builder: (context, data) => _LessonDays(data: data, past: _past),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

class _LessonDays extends StatelessWidget {
  const _LessonDays({required this.data, required this.past});

  final LessonList data;
  final bool past;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final days = <String, List<Lesson>>{};
    for (final lesson in data.lessons) {
      days.putIfAbsent(lesson.startsAt.localDate, () => []).add(lesson);
    }
    Future<void> reload() => context.read<LoadCubit<LessonList>>().load();
    final nextId = past ? null : data.lessons.where((x) => x.upcoming).firstOrNull?.id;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        for (final n in data.notices) ...[Note(n, tone: Tone.info, icon: Icons.event_note_rounded), const SizedBox(height: 10)],
        if (data.lessons.isEmpty) EmptyNote(past ? l.noPast : l.noUpcoming),
        for (final entry in days.entries) ...[
          SectionLabel(
            entry.value.first.startsAt.relativeDay(context) == entry.value.first.startsAt.day(context.locale)
                ? entry.value.first.startsAt.day(context.locale)
                : '${entry.value.first.startsAt.relativeDay(context)} · ${entry.value.first.startsAt.day(context.locale)}',
          ),
          for (final lesson in entry.value) ...[
            LessonCard(
              lesson: lesson,
              highlight: lesson.id == nextId,
              onCancel: lesson.canCancel
                  ? () async {
                      if (await showCancelSheet(context, lesson) && context.mounted) {
                        showSnack(context, l.cancelled);
                        await reload();
                      }
                    }
                  : null,
              onRefuse: lesson.canRefuseSubstitute
                  ? () async {
                      if (await refuseSubstitute(context, lesson) && context.mounted) await reload();
                    }
                  : null,
              onRate: past && lesson.tookPlace && context.shape.stage != Stage.kids
                  ? () async {
                      if (await showRateSheet(context, lesson) && context.mounted) showSnack(context, l.rated);
                    }
                  : null,
            ),
            const SizedBox(height: 10),
          ],
        ],
      ]),
    );
  }
}
