import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

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
import '../bloc/learner_cubit.dart';
import '../widgets/learner_switcher.dart';
import '../widgets/next_lesson_card.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';

/// The guardian's (and an adult student's) home: the next lesson with its
/// join button, what is left and what is owed, the latest report, and the
/// homework due.
class FamilyHomePage extends StatelessWidget {
  const FamilyHomePage({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<SessionCubit>().state;
    final account = s.current;
    final learners = context.watch<LearnerCubit>().state;
    final learnerId = context.learnerId();
    final first = (account?.userName ?? '').split(' ').first;
    final evening = DateTime.now().hour >= 12;
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 6),
              child: Row(
                children: [
                  InitialAvatar(account?.academyName(context.locale) ?? '', size: 40),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(account?.academyName(context.locale) ?? '', style: context.text.bodySmall),
                        Text(
                          evening ? context.l10n.greetingEvening(first) : context.l10n.greetingMorning(first),
                          style: context.text.titleLarge,
                        ),
                      ],
                    ),
                  ),
                  const NotificationBell(),
                ],
              ),
            ),
            const LearnerSwitcher(),
            Expanded(
              child: !learners.loaded
                  ? const Center(child: CircularProgressIndicator.adaptive())
                  : BlocProvider(
                      key: ValueKey(learnerId),
                      create: (c) => LoadCubit<FamilyHome>(() => c.read<HomeLoader>().familyHome(learnerId))..load(),
                      child: LoadView<FamilyHome>(
                        builder: (context, home) => _Home(home: home, learnerId: learnerId, name: learners.selected?.firstName ?? first),
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Home extends StatelessWidget {
  const _Home({required this.home, required this.learnerId, required this.name});

  final FamilyHome home;
  final String? learnerId;
  final String name;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final left = home.balance.fold<int>(0, (n, b) => n + b.sessions);
    final due = home.invoices?.payable.firstOrNull;
    final report = home.report;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        NextLessonCard(lesson: home.next, onOpened: () => context.read<LoadCubit<FamilyHome>>().load()),
        Align(
          alignment: AlignmentDirectional.centerEnd,
          child: TextButton(onPressed: () => context.go('/family/lessons'), child: Text(l.allLessons)),
        ),
        for (final n in home.lessons.notices) ...[Note(n, icon: Icons.event_note_rounded), const SizedBox(height: 10)],
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: _Tile(
                label: l.balance,
                value: l.lessonsCount(left),
                caption: home.balance.map((b) => b.subject).join('، '),
                tone: left <= 1 ? Tone.attention : Tone.brand,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _Tile(
                label: l.owed,
                value: due == null ? l.nothingOwed : (home.invoices?.owed ?? due.outstanding),
                caption: due?.dueAt == null ? null : l.dueBy(due!.dueAt!.day(context.locale)),
                tone: due == null ? Tone.neutral : Tone.attention,
                action: due == null ? null : l.payNow,
                onTap: due == null ? null : () => context.go('/family/money'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        FamilyChallengesCard(learnerId: learnerId, name: name),
        if (report != null) ...[
          const SizedBox(height: 16),
          LmsCard(
            onTap: home.reportCourse == null
                ? null
                : () =>
                      context.push('/reports/${home.reportCourse!.enrollmentId}?title=${Uri.encodeComponent(home.reportCourse!.subject)}'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${l.lastReport} · ${report.lessonAt?.relativeDay(context) ?? ''}',
                        style: context.text.labelMedium?.copyWith(fontSize: 13),
                      ),
                    ),
                    Text(l.readReport, style: context.text.titleSmall?.copyWith(color: c.brandDeep)),
                  ],
                ),
                if (report.items.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      for (final i in report.items.take(4))
                        Pill(
                          '${i.label}: ${i.title}',
                          tone: i.outcome == 'mastered' ? Tone.brand : (i.outcome == 'needs_review' ? Tone.attention : Tone.neutral),
                        ),
                    ],
                  ),
                ],
                if ((report.notesForStudent ?? '').isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Text('"${report.notesForStudent}"', style: context.text.bodyMedium?.copyWith(height: 1.7)),
                ],
                if (report.teacher.isNotEmpty) ...[const SizedBox(height: 6), Text(report.teacher, style: context.text.bodySmall)],
              ],
            ),
          ),
        ],
        if (home.homework != null) ...[
          const SizedBox(height: 12),
          LmsCard(
            child: Row(
              children: [
                Icon(Icons.assignment_outlined, color: c.attentionInk),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l.homeworkLine(home.homework!.title), style: context.text.titleSmall),
                      if (home.homework!.dueAt != null)
                        Text(l.dueBy(home.homework!.dueAt!.day(context.locale)), style: context.text.bodySmall),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ]),
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.label, required this.value, required this.tone, this.caption, this.action, this.onTap});

  final String label;
  final String value;
  final String? caption;
  final Tone tone;
  final String? action;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final (bg, ink) = tone.colours(context);
    return LmsCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: context.text.labelMedium?.copyWith(fontSize: 13)),
          const SizedBox(height: 6),
          Text(value, style: context.text.titleLarge?.copyWith(color: tone == Tone.neutral ? null : ink)),
          if (caption != null && caption!.isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(caption!, style: context.text.bodySmall, maxLines: 2, overflow: TextOverflow.ellipsis),
          ],
          if (action != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
              child: Text(
                action!,
                style: TextStyle(color: ink, fontWeight: FontWeight.w700, fontSize: 12.5),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
