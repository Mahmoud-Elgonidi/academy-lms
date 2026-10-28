import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/teaching_repository.dart';
import '../../domain/performance.dart';

/// "My performance" (as /teach's): the teacher's own numbers over the last
/// 90 days as the academy sees them, by group, what counted against them, and
/// families' ratings without names. Lessons as the API wrote them.
class PerformancePage extends StatelessWidget {
  const PerformancePage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocProvider(
      create: (c) => LoadCubit<Performance>(c.read<TeachingRepository>().performance, refreshOn: c.read<Live>().on({Live.lessons}))..load(),
      child: Scaffold(
        appBar: AppBar(title: Text(l.performanceTitle)),
        body: LoadView<Performance>(builder: (context, p) => _Numbers(performance: p)),
      ),
    );
  }
}

String _group(AppLocalizations l, String group) => switch (group) {
  'commitment' => l.performanceGroupCommitment,
  'quality' => l.performanceGroupQuality,
  'professionalism' => l.performanceGroupProfessionalism,
  'business' => l.performanceGroupBusiness,
  _ => group,
};

class _Numbers extends StatelessWidget {
  const _Numbers({required this.performance});

  final Performance performance;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = performance;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        Text(l.performanceLead(p.days), style: context.type.caption),
        for (final group in Performance.groups)
          if (p.kpis.where((k) => k.group == group).toList() case final kpis when kpis.isNotEmpty) ...[
            SectionHeader(_group(l, group)),
            for (final k in kpis) ...[_KpiTile(kpi: k), const Gap(Space.s)],
          ],
        SectionHeader(l.performanceCounted),
        if (p.events.isEmpty)
          EmptyNote(l.performanceNoEvents, icon: Icons.verified_outlined)
        else
          LmsCard(
            padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.xs),
            child: Column(
              children: [
                for (final (i, e) in p.events.indexed) ...[if (i > 0) const Divider(height: 1), _EventRow(event: e)],
              ],
            ),
          ),
        SectionHeader(l.performanceRatings, note: p.average == null ? null : l.performanceAverage(p.average!, p.count)),
        if (p.ratings.isEmpty)
          EmptyNote(l.performanceNoRatings, icon: Icons.star_outline_rounded)
        else
          for (final r in p.ratings) ...[_RatingCard(rating: r), const Gap(Space.s)],
      ]),
    );
  }
}

class _KpiTile extends StatelessWidget {
  const _KpiTile({required this.kpi});

  final Kpi kpi;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final k = kpi;
    final under = [?k.target, ?k.hint].where((x) => x.isNotEmpty).join(' · ');
    return LmsCard(
      key: Key('kpi-${k.key}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: Text(k.label, style: context.type.label.copyWith(fontSize: 13))),
              const Gap(Space.s),
              Pill(
                switch (k.state) {
                  'good' => l.performanceGood,
                  'bad' => l.performanceBad,
                  _ => l.performanceNone,
                },
                tone: switch (k.state) {
                  'good' => Tone.brand,
                  'bad' => Tone.danger,
                  _ => Tone.neutral,
                },
              ),
            ],
          ),
          const Gap(Space.xs),
          Text(
            k.value,
            textDirection: TextDirection.ltr,
            style: context.type.number.copyWith(color: k.state == 'bad' ? c.dangerInk : null),
          ),
          if (under.isNotEmpty) ...[const Gap(Space.xs), Text(under, style: context.type.caption)],
        ],
      ),
    );
  }
}

class _EventRow extends StatelessWidget {
  const _EventRow({required this.event});

  final KpiEvent event;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final e = event;
    final excluded = e.excluded != null;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Space.m),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: Space.s,
            runSpacing: Space.xs,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(
                e.text,
                style: context.type.cardTitle.copyWith(
                  fontSize: 14.5,
                  color: excluded ? c.muted : null,
                  decoration: excluded ? TextDecoration.lineThrough : null,
                ),
              ),
              if (excluded) Pill(l.performanceExcluded),
            ],
          ),
          if (e.lesson.isNotEmpty) Text(e.lesson, style: context.type.caption),
          if (e.comment != null && e.comment!.isNotEmpty) ...[const Gap(Space.xs), Text('“${e.comment}”', style: context.type.body)],
          if (e.excluded != null && e.excluded!.isNotEmpty) ...[const Gap(Space.xs), Text(e.excluded!, style: context.type.caption)],
          if (e.note != null && e.note!.isNotEmpty) Text(e.note!, style: context.type.caption),
        ],
      ),
    );
  }
}

/// A family's rating, never who gave it.
class _RatingCard extends StatelessWidget {
  const _RatingCard({required this.rating});

  final LessonRatingLine rating;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final r = rating;
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(
            label: l.performanceStars(r.stars),
            excludeSemantics: true,
            child: Row(
              children: [
                for (var n = 1; n <= 5; n++)
                  Icon(
                    n <= r.stars ? Icons.star_rounded : Icons.star_outline_rounded,
                    size: 20,
                    color: n <= r.stars ? c.attention : c.faint,
                  ),
              ],
            ),
          ),
          if (r.lesson.isNotEmpty) ...[const Gap(Space.xs), Text(r.lesson, style: context.type.caption)],
          if (r.tags.isNotEmpty) ...[
            const Gap(Space.s),
            Wrap(spacing: 6, runSpacing: 6, children: [for (final t in r.tags) Pill(t)]),
          ],
          if (r.comment != null && r.comment!.isNotEmpty) ...[const Gap(Space.s), Text('“${r.comment}”', style: context.type.body)],
        ],
      ),
    );
  }
}
