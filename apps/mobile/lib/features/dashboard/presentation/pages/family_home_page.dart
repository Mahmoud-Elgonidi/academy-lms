import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../consent/presentation/privacy_page.dart';
import '../../../homework/domain/homework.dart';
import '../../../journey/presentation/widgets/home_card.dart';
import '../../../roadmap/domain/learning.dart';
import '../../data/home_loader.dart';
import '../bloc/learner_cubit.dart';
import '../widgets/family_header.dart';
import '../widgets/learner_switcher.dart';
import '../widgets/next_lesson_card.dart';

/// The guardian's (and an adult student's) home, in the order that matters:
/// the next lesson (the one surface in the academy's colour, with its join
/// button), what is left and what is owed, the learner's journey, the
/// latest report, and the homework.
class FamilyHomePage extends StatelessWidget {
  const FamilyHomePage({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<SessionCubit>().state;
    final account = s.current;
    final learners = context.watch<LearnerCubit>().state;
    final learnerId = context.learnerId();
    final first = (account?.userName ?? '').split(' ').first;
    final academy = account?.academyName(context.locale) ?? '';
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            FamilyHeader(
              leading: account == null ? null : AcademyLogo(account: account),
              eyebrow: academy,
              title: context.l10n.greetingHello(first),
            ),
            Expanded(
              child: !learners.loaded
                  ? const _HomeSkeleton()
                  : BlocProvider(
                      key: ValueKey(learnerId),
                      create: (c) => LoadCubit<FamilyHome>(
                        () => c.read<HomeLoader>().familyHome(learnerId),
                        refreshOn: c.read<Live>().on({Live.lessons, Live.homework, Live.payments, Live.inbox, Live.practice}),
                        refreshAt: (d) => d.lessons.clockMoments,
                      )..load(),
                      child: LoadView<FamilyHome>(
                        skeleton: const _HomeSkeleton(),
                        builder: (context, home) => _Home(home: home, name: learners.selected?.firstName ?? first),
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
  const _Home({required this.home, required this.name});

  final FamilyHome home;
  final String name;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final report = home.report;
    final self = context.watch<LearnerCubit>().state.selected?.isSelf ?? false;
    return ListView(
      padding: pagePadding.copyWith(top: Space.m),
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        // A guardian's child waiting for their consent to sign in.
        if (context.read<SessionCubit>().state.current?.isGuardian ?? false) const ConsentNudge(),
        NextLessonCard(lesson: home.next),
        const Gap(Space.m),
        _Numbers(home: home, name: name, self: self),
        for (final n in home.lessons.notices) ...[const Gap(Space.m), Note(n, icon: Icons.event_note_rounded)],
        SectionHeader(self ? l.navJourney : l.journeyTitle(name), action: l.openJourney, onAction: () => context.go('/family/journey')),
        JourneyHomeCard(name: name, to: '/family/journey', map: home.journey, self: self),
        if (report != null) ...[
          SectionHeader(l.lastReport, action: l.allReports, onAction: () => context.go('/family/journey/courses')),
          _ReportCard(report: report, course: home.reportCourse),
        ],
        if (home.homework.isNotEmpty) ...[
          SectionHeader(l.homeworkTitle, action: l.allHomework, onAction: () => context.go('/family/journey/homework')),
          MenuGroup(
            children: [for (final h in home.homework) _HomeworkRow(homework: h, onTap: () => context.go('/family/journey/homework'))],
          ),
        ],
      ]),
    );
  }
}

/// What is left on the learner's balance, and what is owed with the way to pay it.
class _Numbers extends StatelessWidget {
  const _Numbers({required this.home, required this.name, required this.self});

  final FamilyHome home;
  final String name;
  final bool self;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final left = home.balance.fold<int>(0, (n, b) => n + b.sessions);
    final subjects = home.balance.map((b) => b.subject).where((s) => s.isNotEmpty).join('، ');
    final due = home.invoices?.payable.firstOrNull;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: StatTile(
              key: const Key('home-balance'),
              label: self ? l.balance : l.balanceOf(name),
              value: Text(
                l.lessonsCount(left),
                style: TextStyle(color: left <= 1 && home.balance.isNotEmpty ? context.colors.attentionInk : null),
              ),
              caption: subjects.isEmpty ? null : subjects,
            ),
          ),
          const Gap(Space.m),
          Expanded(
            child: due == null
                ? StatTile(
                    key: const Key('home-owed'),
                    label: l.owed,
                    value: Text(l.nothingOwed, style: context.type.cardTitle),
                    icon: Icons.check_rounded,
                  )
                : _OwedTile(amount: home.invoices?.owed ?? due.outstanding, dueBy: due.dueAt?.day(context.locale)),
          ),
        ],
      ),
    );
  }
}

/// Money owed: the amount on the attention colour, by when, and Pay.
class _OwedTile extends StatelessWidget {
  const _OwedTile({required this.amount, this.dueBy});

  final String amount;
  final String? dueBy;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    return Container(
      key: const Key('home-owed'),
      padding: const EdgeInsets.all(Space.m + 2),
      decoration: BoxDecoration(
        color: c.attentionSoft,
        borderRadius: context.shape.cardRadius,
        border: Border.all(color: c.attention.withValues(alpha: 0.45)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.owed, style: context.type.label.copyWith(color: c.attentionInk)),
          const Gap(Space.s),
          Text(amount, style: context.type.number.copyWith(color: c.ink)),
          if (dueBy != null) ...[const Gap(Space.xs), Text(l.dueBy(dueBy!), style: context.type.caption)],
          const Spacer(),
          const Gap(Space.s),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              key: const Key('home-pay'),
              style: FilledButton.styleFrom(
                backgroundColor: c.attentionInk,
                foregroundColor: Colors.white,
                minimumSize: const Size(64, 44),
              ),
              onPressed: PressScale.withPress(context, () => context.go('/family/money')),
              child: Text(l.payShort),
            ),
          ),
        ],
      ),
    );
  }
}

/// The latest report: when and by whom, the teacher's words, and what each item came to.
class _ReportCard extends StatelessWidget {
  const _ReportCard({required this.report, this.course});

  final Report report;
  final Course? course;

  @override
  Widget build(BuildContext context) {
    final course = this.course;
    final line = [?report.lessonAt?.relativeDay(context), if (report.teacher.isNotEmpty) report.teacher].join(' · ');
    return LmsCard(
      key: const Key('home-report'),
      onTap: course == null ? null : () => context.push('/reports/${course.enrollmentId}?title=${Uri.encodeComponent(course.subject)}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(line, style: context.type.caption)),
              if (course != null) Icon(Icons.chevron_right_rounded, color: context.colors.faint, size: 20),
            ],
          ),
          if ((report.notesForStudent ?? '').isNotEmpty) ...[
            const Gap(Space.s),
            Text(report.notesForStudent!, style: context.type.body.copyWith(height: 1.7)),
          ],
          if (report.items.isNotEmpty) ...[
            const Gap(Space.m),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final i in report.items.take(4))
                  StatusChip(
                    '${i.label}: ${i.title}',
                    tone: i.outcome == 'mastered' ? Tone.brand : (i.outcome == 'needs_review' ? Tone.attention : Tone.neutral),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// A piece of homework: due (with the day), or handed in (with how it went).
class _HomeworkRow extends StatelessWidget {
  const _HomeworkRow({required this.homework, required this.onTap});

  final Homework homework;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final h = homework;
    final l = context.l10n;
    final line = [
      if (h.statusLabel.isNotEmpty) h.statusLabel,
      if (h.open && h.dueAt != null) l.dueBy(h.dueAt!.day(context.locale)),
      if (!h.open && h.practice != null && h.practice!.label.isNotEmpty) h.practice!.label,
      if (!h.open && h.grade != null) l.grade(h.grade!),
    ].join(' · ');
    return MenuRow(
      key: Key('home-homework-${h.id}'),
      icon: h.open ? Icons.edit_outlined : Icons.check_rounded,
      tone: h.open ? Tone.attention : Tone.brand,
      label: h.title,
      subtitle: line.isEmpty ? null : line,
      onTap: onTap,
    );
  }
}

/// Shaped like the home on its way: the next lesson, two numbers, two cards.
class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    padding: pagePadding.copyWith(top: Space.m),
    physics: const NeverScrollableScrollPhysics(),
    children: [
      Skeleton(height: 190, radius: context.shape.card + 6),
      const Gap(Space.m),
      const Row(
        children: [
          Expanded(child: Skeleton(height: 120, radius: 16)),
          Gap(Space.m),
          Expanded(child: Skeleton(height: 120, radius: 16)),
        ],
      ),
      const Gap(Space.xl),
      const FractionallySizedBox(alignment: AlignmentDirectional.centerStart, widthFactor: 0.35, child: Skeleton(height: 16)),
      const Gap(Space.m),
      const Skeleton(height: 96, radius: 16),
    ],
  );
}
