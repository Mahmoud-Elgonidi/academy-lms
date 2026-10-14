import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/stage.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';
import '../../../../shared/motion/done_moment.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../../dashboard/presentation/widgets/family_header.dart';
import '../../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../data/lessons_repository.dart';
import '../../domain/lesson.dart';
import '../widgets/lesson_card.dart';
import '../widgets/lesson_row.dart';
import '../widgets/lesson_sheets.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../core/realtime/live.dart';

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
    // The kids' look keeps its own quiet loader.
    final skeleton = context.isKids ? null : const SkeletonList();
    final list = Expanded(
      child: !learners.state.loaded
          ? skeleton ?? const Center(child: CircularProgressIndicator.adaptive())
          : BlocProvider(
              key: ValueKey('$learnerId:$_past'),
              create: (c) => LoadCubit<LessonList>(
                () => c.read<LessonsRepository>().family(learnerId: learnerId, past: _past),
                refreshOn: c.read<Live>().on({Live.lessons}),
                refreshAt: (d) => d.clockMoments,
              )..load(),
              child: LoadView<LessonList>(
                skeleton: skeleton,
                builder: (context, data) =>
                    family ? _FamilyLessonDays(data: data, past: _past, zone: zone) : _LessonDays(data: data, past: _past),
              ),
            ),
    );
    final when = Padding(
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
    );
    final clock = zone == null ? null : l.onClock(zoneName(zone, context.locale));
    if (family) {
      // A guardian's tab: the shared header with the bell, the children to switch between.
      final many = learners.state.learners.length > 1;
      return Scaffold(
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              FamilyHeader(
                eyebrow: clock,
                title: learner == null || learner.isSelf ? l.navMyLessons : (many ? l.navLessons : l.lessonsOf(learner.firstName)),
              ),
              when,
              list,
            ],
          ),
        ),
      );
    }
    if (!context.isKids) {
      // Middle and secondary school's tab: the header every tab has, with the bell.
      return Scaffold(
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(18, Space.m, 18, Space.xs),
                child: AppHeader(eyebrow: clock, title: l.navMyLessons, actions: const [NotificationBell(framed: true)]),
              ),
              when,
              list,
            ],
          ),
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.navMyLessons),
            if (clock != null) Text(clock, style: context.text.bodySmall),
          ],
        ),
      ),
      body: Column(children: [when, list]),
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
    // A child in the kids' look only joins: cancelling, turning down a substitute, disputing and rating are the family's.
    final kids = context.shape.stage == Stage.kids;
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
              onCancel: lesson.canCancel && !kids
                  ? () async {
                      if (await showCancelSheet(context, lesson) && context.mounted) {
                        DoneMoment.show(context, title: l.cancelled);
                        await reload();
                      }
                    }
                  : null,
              onRefuse: lesson.canRefuseSubstitute && !kids
                  ? () async {
                      if (await refuseSubstitute(context, lesson) && context.mounted) await reload();
                    }
                  : null,
              onDispute: lesson.canDispute && !kids
                  ? () async {
                      final repo = context.read<LessonsRepository>();
                      final sent = await showDisputeSheet(
                        context,
                        lesson,
                        teacher: false,
                        send: (reason, note) => repo.dispute(lesson.id, reason: reason, note: note),
                      );
                      if (sent && context.mounted) {
                        showSnack(context, l.disputeSent);
                        await reload();
                      }
                    }
                  : null,
              onRate: past && lesson.tookPlace && !kids
                  ? () async {
                      if (await showRateSheet(context, lesson) && context.mounted) DoneMoment.show(context, title: l.rated);
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

/// A guardian's lessons by day: a row each (its time in a column, whom
/// with, its state); the row opens the lesson's sheet, where cancelling,
/// turning down a substitute, disputing and rating are.
class _FamilyLessonDays extends StatelessWidget {
  const _FamilyLessonDays({required this.data, required this.past, this.zone});

  final LessonList data;
  final bool past;
  final String? zone;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final days = <String, List<Lesson>>{};
    for (final lesson in data.lessons) {
      days.putIfAbsent(lesson.startsAt.localDate, () => []).add(lesson);
    }
    Future<void> reload() => context.read<LoadCubit<LessonList>>().load();
    final nextId = past ? null : data.lessons.where((x) => x.upcoming).firstOrNull?.id;
    final learner = context.read<LearnerCubit>().state.selected;
    final who = learner == null || learner.isSelf ? null : learner.firstName;

    LessonActions actionsFor(Lesson lesson) => LessonActions(
      cancel: lesson.canCancel
          ? () async {
              if (await showCancelSheet(context, lesson, learner: who) && context.mounted) {
                DoneMoment.show(context, title: l.cancelled);
                await reload();
              }
            }
          : null,
      refuse: lesson.canRefuseSubstitute
          ? () async {
              if (await refuseSubstitute(context, lesson) && context.mounted) await reload();
            }
          : null,
      dispute: lesson.canDispute
          ? () async {
              final repo = context.read<LessonsRepository>();
              final sent = await showDisputeSheet(
                context,
                lesson,
                teacher: false,
                send: (reason, note) => repo.dispute(lesson.id, reason: reason, note: note),
              );
              if (sent && context.mounted) {
                showSnack(context, l.disputeSent);
                await reload();
              }
            }
          : null,
      rate: past && lesson.tookPlace
          ? () async {
              if (await showRateSheet(context, lesson) && context.mounted) DoneMoment.show(context, title: l.rated);
            }
          : null,
    );

    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        for (final n in data.notices) ...[Note(n, tone: Tone.info, icon: Icons.event_note_rounded), const Gap(Space.m)],
        if (data.lessons.isEmpty) EmptyState(icon: Icons.event_available_outlined, title: past ? l.noPast : l.noUpcoming),
        for (final entry in days.entries) ...[
          SectionHeader(entry.value.first.startsAt.relativeDay(context)),
          for (final lesson in entry.value) ...[
            LessonRow(
              key: Key('lesson-${lesson.id}'),
              lesson: lesson,
              highlight: lesson.id == nextId,
              onTap: () => showLessonSheet(context, lesson, actions: actionsFor(lesson), learner: who, zone: zone),
            ),
            const Gap(Space.m),
          ],
        ],
      ]),
    );
  }
}
