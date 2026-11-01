import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../../../shared/widgets/open_link.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../hifz/data/hifz_repository.dart';
import '../../hifz/domain/hifz.dart';
import '../data/quran_repository.dart';
import '../domain/quran.dart';
import 'widgets/quran_ui.dart';

/// Everything of a learner's Quran on one screen: the thirty parts from the hifz map, today's review, each course's
/// plan with this week's portion and the last lessons' grades, the exams and the certificates.
class FamilyQuran {
  const FamilyQuran({required this.plans, this.hifz, this.exams = const [], this.certificates = const []});

  final QuranPlans plans;
  final HifzBoard? hifz;
  final List<QuranExam> exams;
  final List<QuranCertificate> certificates;

  /// The plans decide; the map, the exams and the certificates are shown when they come.
  static Future<FamilyQuran> load(QuranRepository quran, HifzRepository hifz, String? learnerId) async {
    final board = hifz.board(learnerId: learnerId).then<HifzBoard?>((b) => b).catchError((Object _) => null);
    final exams = quran.exams(learnerId: learnerId).catchError((Object _) => const <QuranExam>[]);
    final certificates = quran
        .certificates(learnerId: learnerId)
        .then((c) => c.shown ? c.list : const <QuranCertificate>[])
        .catchError((Object _) => const <QuranCertificate>[]);
    final plans = await quran.plans(learnerId: learnerId);
    return FamilyQuran(plans: plans, hifz: await board, exams: await exams, certificates: await certificates);
  }
}

/// A learner's Quran, opened from their journey (the student's own, in their look's words, or a guardian's child's):
/// the thirty parts and today's review leading to the hifz map and the mistakes notebook, each course's plan (where
/// the learner is, how much is behind them, this week's portion and the last grades; the kids' look in stars, no
/// counts), the exams, and the certificates opening their page. It follows the hifz and lessons signals.
class QuranPage extends StatelessWidget {
  const QuranPage({required this.base, super.key});

  /// The journey it is under ('/kids/journey'): the hifz map is beside it.
  final String base;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final learners = context.watch<LearnerCubit>().state;
    final family = context.read<LearnerCubit>().scope == LearnerScope.family;
    final learner = learners.selected;
    final learnerId = context.learnerId();
    final title = family && learner != null && !learner.isSelf
        ? l.quranTitleOf(learner.firstName)
        : (context.isKids ? l.quranTitleKids : l.quranTitle);
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: family && !learners.loaded
          ? const Center(child: CircularProgressIndicator.adaptive())
          : BlocProvider(
              key: ValueKey(learnerId),
              create: (c) => LoadCubit<FamilyQuran>(
                () => FamilyQuran.load(c.read<QuranRepository>(), c.read<HifzRepository>(), learnerId),
                refreshOn: c.read<Live>().on({Live.hifz, Live.lessons}),
              )..load(),
              child: LoadView<FamilyQuran>(
                skeleton: const SkeletonList(count: 3),
                builder: (context, q) => _View(quran: q, base: base),
              ),
            ),
    );
  }
}

class _View extends StatelessWidget {
  const _View({required this.quran, required this.base});

  final FamilyQuran quran;
  final String base;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final kids = context.isKids;
    final hifz = quran.hifz;
    final map = hifz?.map;
    void openHifz() => context.push('$base/hifz');
    final children = <Widget>[
      if (map != null && map.available)
        LmsCard(
          key: const Key('quran-parts'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(l.quranParts, style: context.type.cardTitle),
              const Gap(Space.xs),
              Text(kids ? l.quranPartsKids : l.hifzMemorized(map.counts.memorized, map.pagesTotal), style: context.type.caption),
              const Gap(Space.m),
              JuzBar(map: map),
            ],
          ),
        ),
      if (hifz != null && map != null && map.available) _Wird(wird: hifz.wird, onOpen: openHifz),
      if (map != null && map.available)
        MenuGroup(
          children: [
            MenuRow(
              key: const Key('quran-open-hifz'),
              icon: Icons.auto_stories_outlined,
              label: kids ? l.quranOpenHifzKids : l.quranOpenHifz,
              onTap: openHifz,
            ),
          ],
        ),
      if (quran.plans.courses.isEmpty) EmptyNote(l.quranNone, icon: Icons.menu_book_outlined),
      for (final course in quran.plans.courses) ...[
        SectionHeader(kids ? l.quranPlanHeaderKids : l.quranPlanHeader, note: quran.plans.courses.length > 1 ? course.subject : null),
        _PlanCard(course: course),
        if (course.plan.portion case final portion? when portion.parts.isNotEmpty) _WeekCard(portion: portion),
        if (course.recent.isNotEmpty) ...[
          SectionHeader(l.quranRecent),
          LmsCard(
            key: Key('quran-recent-${course.enrollmentId}'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final (i, r) in course.recent.take(5).indexed) ...[if (i > 0) const Divider(height: Space.l), ResultView(result: r)],
              ],
            ),
          ),
        ],
      ],
      if (quran.exams.isNotEmpty) ...[
        SectionHeader(kids ? l.quranExamsKids : l.quranExams),
        for (final e in quran.exams) _ExamCard(exam: e),
      ],
      if (quran.certificates.isNotEmpty) ...[
        SectionHeader(kids ? l.quranCertificatesKids : l.quranCertificates),
        for (final c in quran.certificates) _CertificateCard(certificate: c),
      ],
    ];
    return quranList(context, children);
  }
}

/// Today's review in short, leading to the hifz map: done, or what is left (the kids' look without counts).
class _Wird extends StatelessWidget {
  const _Wird({required this.wird, required this.onOpen});

  final Wird wird;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final kids = context.isKids;
    final left = wird.pages.length;
    final line = wird.finished
        ? (kids ? l.hifzWirdDoneKids : l.hifzTodayDone)
        : left == 0
        ? l.hifzTodayNone
        : (kids ? l.quranWirdKids : l.hifzCardLeft(left));
    return LmsCard(
      key: const Key('quran-wird'),
      onTap: onOpen,
      child: Row(
        children: [
          IconBadge(
            wird.finished ? Icons.check_circle_outline_rounded : Icons.menu_book_rounded,
            tone: left > 0 ? Tone.attention : Tone.brand,
            size: 44,
          ),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.hifzToday, style: context.type.cardTitle),
                Text(line, style: context.type.caption),
                if (!kids && wird.daily > 0) ...[
                  const Gap(Space.s),
                  ProgressBar(wird.daily == 0 ? 0 : wird.doneToday / wird.daily, height: 6),
                ],
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: context.colors.faint),
        ],
      ),
    );
  }
}

/// A course's plan: the program, where the learner is, how much is behind them, and a pause or an end.
class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.course});

  final QuranCourse course;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final kids = context.isKids;
    final p = course.plan;
    return LmsCard(
      key: Key('quran-plan-${course.enrollmentId}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const IconBadge(Icons.menu_book_rounded, size: 44),
              const Gap(Space.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(p.programName, style: context.type.cardTitle),
                    if (p.trackLabel.isNotEmpty) Text(p.trackLabel, style: context.type.caption),
                  ],
                ),
              ),
              if (p.frozenLabel != null) StatusChip(p.frozenLabel!, tone: Tone.attention, icon: Icons.pause_circle_outline_rounded),
            ],
          ),
          const Gap(Space.m),
          if (p.finished)
            Text(kids ? l.quranFinishedKids : l.quranFinished, style: context.type.body.copyWith(fontWeight: FontWeight.w700))
          else if (p.position != null)
            Text(kids ? l.quranAtKids(p.position!) : l.quranAt(p.position!), style: context.type.body),
          const Gap(Space.s),
          ProgressBar(p.progress, height: 8),
          if (!kids) ...[const Gap(Space.xs), Text(l.quranProgress(p.percent), style: context.type.caption)],
        ],
      ),
    );
  }
}

/// This week's portion: the new part, the near and the far review, and the exams it reaches.
class _WeekCard extends StatelessWidget {
  const _WeekCard({required this.portion});

  final Portion portion;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return LmsCard(
      key: const Key('quran-week'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(context.isKids ? l.quranWeekKids : l.quranWeek, style: context.type.cardTitle),
          if (portion.lessonTitle case final lesson?) Text(lesson, style: context.type.body),
          for (final part in portion.parts) ...[const Gap(Space.m), PortionPartView(part: part)],
          if (portion.stops.isNotEmpty) ...[
            const Gap(Space.m),
            Note(l.quranStops(portion.stops.join('، ')), tone: Tone.reward, icon: Icons.emoji_events_outlined),
          ],
        ],
      ),
    );
  }
}

class _ExamCard extends StatelessWidget {
  const _ExamCard({required this.exam});

  final QuranExam exam;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final kids = context.isKids;
    final r = exam.result;
    final locale = context.locale;
    final tone = exam.passed ? Tone.brand : (exam.failed ? Tone.attention : (exam.booked ? Tone.info : Tone.neutral));
    return LmsCard(
      key: Key('quran-exam-${exam.id}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              IconBadge(exam.passed ? Icons.emoji_events_rounded : Icons.fact_check_outlined, tone: tone, size: 40),
              const Gap(Space.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(exam.stopLabel, style: context.type.cardTitle),
                    Text(exam.programName, style: context.type.caption),
                  ],
                ),
              ),
              StatusChip(exam.statusLabel, tone: tone),
            ],
          ),
          if (exam.booked && exam.startsAt != null) ...[
            const Gap(Space.s),
            Text([exam.startsAt!.label, if (exam.examiner != null) l.quranExamWith(exam.examiner!)].join(' · '), style: context.type.body),
          ],
          if (exam.notBefore != null && !exam.booked && r == null) ...[
            const Gap(Space.xs),
            Text(l.quranExamNotBefore(quranDay(exam.notBefore!, locale)), style: context.type.caption),
          ],
          if (r != null) ...[
            const Gap(Space.s),
            Row(
              children: [
                GradeView(grade: r.grade, label: r.gradeLabel),
                const Gap(Space.s),
                if (!kids)
                  Expanded(
                    child: Text(
                      [
                        for (final m in r.mistakes)
                          if (m.count > 0) '${m.label} ${m.count}',
                      ].join(' · '),
                      style: context.type.caption,
                    ),
                  ),
              ],
            ),
            if (r.note?.isNotEmpty ?? false) ...[const Gap(Space.xs), Text(r.note!, style: context.type.body)],
            if (r.retakeOn != null) ...[
              const Gap(Space.xs),
              Text(l.quranExamRetakeOn(quranDay(r.retakeOn!, locale)), style: context.type.caption),
            ],
          ],
        ],
      ),
    );
  }
}

class _CertificateCard extends StatelessWidget {
  const _CertificateCard({required this.certificate});

  final QuranCertificate certificate;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = certificate;
    return Semantics(
      button: c.opens,
      label: c.opens ? l.quranCertificateOpen : null,
      child: LmsCard(
        key: Key('quran-certificate-${c.id}'),
        onTap: c.opens ? () => openLink(context, Uri.parse(c.url!)) : null,
        child: Row(
          children: [
            IconBadge(Icons.workspace_premium_rounded, tone: c.revoked ? Tone.neutral : Tone.reward, size: 44),
            const Gap(Space.m),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(c.title, style: context.type.cardTitle),
                  Text([c.portion, c.gradeLabel].where((s) => s.isNotEmpty).join(' · '), style: context.type.caption),
                  if (c.issuedAt != null) Text(c.issuedAt!.label, style: context.type.caption),
                  if (c.code != null) Text(l.quranCertificateCode(c.code!), style: context.type.caption),
                ],
              ),
            ),
            if (c.revoked)
              StatusChip(l.quranCertificateRevoked)
            else if (c.opens)
              Icon(Icons.open_in_new_rounded, color: context.colors.faint),
          ],
        ),
      ),
    );
  }
}
