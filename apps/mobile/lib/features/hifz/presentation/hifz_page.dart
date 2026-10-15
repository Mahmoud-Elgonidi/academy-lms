import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../core/time/formats.dart';
import '../../../shared/motion/done_moment.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../data/hifz_repository.dart';
import '../domain/hifz.dart';
import 'widgets/hifz_map_view.dart';
import 'widgets/page_sheet.dart';

/// A learner's hifz map, opened from their journey (the student's own, in
/// their look's words, or a guardian's child's): every page of the mushaf
/// by part in its strength's colour with the key and the counts, then
/// today's review, each page opening to be recited (checked word by word
/// where the academy has it on) or shown and graded, and the page recited
/// last with its check. It follows the hifz signal, so a check that comes
/// back shows by itself; finishing the day's review is celebrated in the
/// look's own way.
class HifzPage extends StatelessWidget {
  const HifzPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final learners = context.watch<LearnerCubit>().state;
    final family = context.read<LearnerCubit>().scope == LearnerScope.family;
    final learner = learners.selected;
    final learnerId = context.learnerId();
    final title = family && learner != null && !learner.isSelf
        ? l.hifzTitleOf(learner.firstName)
        : (context.isKids ? l.hifzTitleKids : l.hifzTitle);
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: family && !learners.loaded
          ? const Center(child: CircularProgressIndicator.adaptive())
          : BlocProvider(
              key: ValueKey(learnerId),
              create: (c) => LoadCubit<HifzBoard>(
                () => c.read<HifzRepository>().board(learnerId: learnerId),
                refreshOn: c.read<Live>().on({Live.hifz}),
              )..load(),
              child: _Board(learnerId: learnerId),
            ),
    );
  }
}

class _Board extends StatelessWidget {
  const _Board({required this.learnerId});

  final String? learnerId;

  @override
  Widget build(BuildContext context) => BlocListener<LoadCubit<HifzBoard>, LoadState<HifzBoard>>(
    // The last page of today's review just went: the look's own celebration.
    listenWhen: (before, now) => (before.data?.wird.pages.isNotEmpty ?? false) && (now.data?.wird.finished ?? false),
    listener: (context, _) => DoneMoment.show(context, title: context.isKids ? context.l10n.hifzWirdDoneKids : context.l10n.hifzWirdDone),
    child: LoadView<HifzBoard>(
      skeleton: const SkeletonList(count: 3),
      builder: (context, board) => _BoardView(board: board, learnerId: learnerId),
    ),
  );
}

class _BoardView extends StatelessWidget {
  const _BoardView({required this.board, required this.learnerId});

  final HifzBoard board;
  final String? learnerId;

  Future<void> _open(BuildContext context, {required int page, required String title, String? recitationId}) async {
    final l = context.l10n;
    final cubit = context.read<LoadCubit<HifzBoard>>();
    final graded = await openHifzPage(
      context,
      page: page,
      title: title,
      checks: board.checks,
      strengths: board.map.strengths,
      learnerId: learnerId,
      recitationId: recitationId,
    );
    if (graded == null || !context.mounted) return;
    await cubit.load();
    if (!context.mounted) return;
    // The day's last page is celebrated; any other says when it comes back.
    final due = graded.dueAt;
    if (!(cubit.state.data?.wird.finished ?? false) && due != null) showSnack(context, l.hifzNextReview(due.day(context.locale)));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final b = board;
    final wird = b.wird;
    if (!b.map.available) {
      return ListView(
        padding: pagePadding,
        physics: const AlwaysScrollableScrollPhysics(),
        children: [EmptyState(icon: Icons.auto_stories_outlined, title: l.hifzNoText)],
      );
    }
    final latest = b.latest;
    final children = <Widget>[
      HifzMapCard(map: b.map, kids: context.isKids),
      SectionHeader(l.hifzToday, note: wird.daily > 0 ? l.hifzTodayProgress(wird.doneToday, wird.daily) : null),
      if (wird.pages.isNotEmpty)
        LmsCard(
          key: const Key('hifz-wird'),
          padding: EdgeInsets.zero,
          child: Column(
            children: [
              for (final (i, p) in wird.pages.indexed) ...[
                if (i > 0) const Divider(height: 1, indent: Space.l, endIndent: Space.l),
                _WirdRow(
                  page: p,
                  map: b.map,
                  checks: b.checks,
                  onTap: () => _open(
                    context,
                    page: p.page,
                    title: pageTitle(context, page: p.page, title: p.title, surahs: p.surahs),
                  ),
                ),
              ],
            ],
          ),
        )
      else if (wird.finished)
        Note(l.hifzTodayDone, tone: Tone.brand, icon: Icons.check_circle_outline_rounded)
      else
        EmptyNote(l.hifzTodayNone, icon: Icons.event_available_outlined),
      if (latest != null) ...[
        SectionHeader(l.hifzLatest),
        _Latest(
          recitation: latest,
          onTap: () => _open(
            context,
            page: latest.page,
            title: pageTitle(context, page: latest.page),
            recitationId: latest.id,
          ),
        ),
      ],
    ];
    return ListView(
      padding: pagePadding.copyWith(top: Space.m),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        for (final (i, w) in children.indexed)
          Padding(
            padding: const EdgeInsets.only(bottom: Space.m),
            child: w.appear(context, i),
          ),
      ],
    );
  }
}

/// A page of today's review: its number in its strength's colour, its name, its part and strength, how late it is,
/// and the microphone where it is recited to be checked.
class _WirdRow extends StatelessWidget {
  const _WirdRow({required this.page, required this.map, required this.checks, required this.onTap});

  final WirdPage page;
  final HifzMap map;
  final bool checks;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final (bg, ink) = strengthTone(page.strength).colours(context);
    return InkWell(
      key: Key('wird-${page.page}'),
      onTap: PressScale.withPress(context, onTap),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              alignment: Alignment.center,
              decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(context.shape.field)),
              child: Text(
                '${page.page}',
                style: context.type.label.copyWith(color: ink, fontSize: 14, fontWeight: FontWeight.w800),
              ),
            ),
            const Gap(Space.m),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    pageTitle(context, page: page.page, title: page.title, surahs: page.surahs),
                    style: context.type.cardTitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text('${l.hifzJuz(page.juz)} · ${map.label(page.strength)}', style: context.type.caption),
                  if (page.overdueDays > 0) ...[
                    const Gap(Space.xs),
                    StatusChip(l.hifzOverdue(page.overdueDays), tone: Tone.attention, icon: Icons.schedule_rounded),
                  ],
                ],
              ),
            ),
            const Gap(Space.xs),
            Icon(checks ? Icons.mic_none_rounded : Icons.chevron_right_rounded, color: c.faint),
          ],
        ),
      ),
    );
  }
}

/// The page recited last (within a day): listening while it is heard, then how it went and the grade it counted as;
/// it opens on its check.
class _Latest extends StatelessWidget {
  const _Latest({required this.recitation, required this.onTap});

  final HifzRecitation recitation;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final r = recitation;
    final check = r.check;
    final line = r.pending
        ? l.hifzListening
        : (check.failed ? check.failure ?? check.statusLabel : check.summaryLabel ?? check.statusLabel);
    return LmsCard(
      key: const Key('hifz-latest'),
      onTap: onTap,
      child: Row(
        children: [
          IconBadge(Icons.graphic_eq_rounded, tone: check.failed ? Tone.danger : (r.pending ? Tone.info : Tone.brand)),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(pageTitle(context, page: r.page), style: context.type.cardTitle),
                Text(line, style: context.type.caption, maxLines: 2, overflow: TextOverflow.ellipsis),
              ],
            ),
          ),
          const Gap(Space.s),
          if (r.gradeLabel != null)
            StatusChip(r.gradeLabel!, tone: gradeTone(r.grade))
          else if (r.pending)
            SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: context.colors.info)),
        ],
      ),
    );
  }
}
