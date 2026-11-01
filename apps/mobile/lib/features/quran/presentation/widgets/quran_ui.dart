import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../hifz/domain/hifz.dart';
import '../../domain/quran.dart';

/// A grade's tone: the best ones in the brand's colour, "repeat" in danger.
Tone gradeToneOf(String grade) => switch (grade) {
  'excellent' || 'very_good' => Tone.brand,
  'good' => Tone.info,
  'acceptable' => Tone.attention,
  'repeat' => Tone.danger,
  _ => Tone.neutral,
};

/// A day on the academy's calendar (`2099-10-20`) as people read it: "الثلاثاء 20 أكتوبر".
String quranDay(String ymd, String locale) {
  final day = DateTime.tryParse(ymd);
  return day == null ? ymd : DateFormat('EEEE d MMMM', locale).format(day);
}

/// A grade as the look reads it: five stars at most in the kids' look (its label for a screen reader), a chip
/// elsewhere.
class GradeView extends StatelessWidget {
  const GradeView({required this.grade, required this.label, super.key});

  final String grade;
  final String label;

  @override
  Widget build(BuildContext context) {
    if (!context.isKids) return StatusChip(label, tone: gradeToneOf(grade));
    final c = context.colors;
    final stars = gradeStars(grade);
    return Semantics(
      label: label,
      container: true,
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 0; i < gradeOrder.length; i++)
            Icon(i < stars ? Icons.star_rounded : Icons.star_outline_rounded, size: 22, color: i < stars ? c.reward : c.line),
        ],
      ),
    );
  }
}

/// How much of each of the thirty parts is memorized, from the hifz map: a bar of thirty in the brand's colour (part
/// one where reading starts), or in the kids' look thirty stars that light up, without counts.
class JuzBar extends StatelessWidget {
  const JuzBar({required this.map, super.key});

  final HifzMap map;

  static const _held = {Strength.strong, Strength.fading, Strength.weak};

  /// 0 to 1 for part [juz]: its memorized pages out of its pages.
  static double share(HifzJuz juz) => juz.pages.isEmpty ? 0 : juz.pages.where((p) => _held.contains(p.strength)).length / juz.pages.length;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final kids = context.isKids;
    final parts = {for (final j in map.juzs) j.juz: share(j)};
    String state(double v) => v >= 1 ? l.quranJuzDone : (v > 0 ? l.quranJuzSome : l.quranJuzNone);
    if (kids) {
      return Wrap(
        key: const Key('juz-bar'),
        spacing: 2,
        runSpacing: 2,
        children: [
          for (var n = 1; n <= 30; n++)
            Semantics(
              key: Key('juz-$n'),
              label: l.quranJuzState(n, state(parts[n] ?? 0)),
              container: true,
              excludeSemantics: true,
              child: Icon(
                (parts[n] ?? 0) >= 1 ? Icons.star_rounded : ((parts[n] ?? 0) > 0 ? Icons.star_half_rounded : Icons.star_outline_rounded),
                size: 26,
                color: (parts[n] ?? 0) > 0 ? c.reward : c.line,
              ),
            ),
        ],
      );
    }
    final calm = context.calm;
    return SizedBox(
      key: const Key('juz-bar'),
      height: 32,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (var n = 1; n <= 30; n++)
            Expanded(
              child: Semantics(
                key: Key('juz-$n'),
                label: l.quranJuzState(n, state(parts[n] ?? 0)),
                container: true,
                excludeSemantics: true,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 1),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(context.shape.field / 3),
                    child: ColoredBox(
                      color: c.chip,
                      child: Align(
                        alignment: Alignment.bottomCenter,
                        child: TweenAnimationBuilder<double>(
                          tween: Tween(begin: 0, end: parts[n] ?? 0),
                          duration: calm ? Duration.zero : Duration(milliseconds: 500 + n * 15),
                          curve: Curves.easeOutCubic,
                          builder: (context, v, _) => FractionallySizedBox(
                            heightFactor: v,
                            widthFactor: 1,
                            child: ColoredBox(color: v >= 1 ? c.brand : c.brand.withValues(alpha: 0.55)),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// A count of one kind of mistake with one less and one more.
class MistakeCounter extends StatelessWidget {
  const MistakeCounter({required this.kind, required this.label, required this.count, required this.onChange, super.key});

  final String kind;
  final String label;
  final int count;
  final ValueChanged<int> onChange;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return Row(
      children: [
        Expanded(child: Text(label, style: context.type.body)),
        IconButton(
          key: Key('less-$kind'),
          tooltip: l.quranMistakeLess(label),
          onPressed: count > 0 ? () => onChange(-1) : null,
          icon: const Icon(Icons.remove_circle_outline_rounded),
          color: c.muted,
        ),
        SizedBox(
          width: 32,
          child: Text(
            '$count',
            key: Key('count-$kind'),
            textAlign: TextAlign.center,
            style: context.type.number.copyWith(fontSize: 18, color: count > 0 ? c.dangerInk : c.ink),
          ),
        ),
        IconButton(
          key: Key('more-$kind'),
          tooltip: l.quranMistakeMore(label),
          onPressed: () => onChange(1),
          icon: const Icon(Icons.add_circle_outline_rounded),
          color: c.brandDeep,
        ),
      ],
    );
  }
}

/// The grade: from the mistakes (none chosen), or one of the academy's.
class GradePicker extends StatelessWidget {
  const GradePicker({required this.grades, required this.value, required this.onChanged, this.keyPrefix = 'grade', super.key});

  final List<QuranChoice> grades;
  final String? value;
  final ValueChanged<String?> onChanged;
  final String keyPrefix;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: Space.s,
    runSpacing: Space.xs,
    children: [
      ChoiceChip(
        key: Key('$keyPrefix-auto'),
        label: Text(context.l10n.quranGradeAuto),
        selected: value == null,
        onSelected: (_) => onChanged(null),
      ),
      for (final g in grades)
        ChoiceChip(key: Key('$keyPrefix-${g.key}'), label: Text(g.label), selected: value == g.key, onSelected: (_) => onChanged(g.key)),
    ],
  );
}

/// One recorded result: when, whether the plan moved on, and each part's grade and mistakes (with the teacher's note
/// where the reader may see it).
class ResultView extends StatelessWidget {
  const ResultView({required this.result, this.showMove = true, super.key});

  final LessonResult result;
  final bool showMove;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final kids = context.isKids;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: Text(result.recordedAt?.label ?? '', style: context.type.caption)),
            if (showMove && !kids && !result.frozen)
              StatusChip(result.advanced ? l.quranAdvanced : l.quranStays, tone: result.advanced ? Tone.brand : Tone.neutral),
          ],
        ),
        for (final p in result.parts) ...[
          const Gap(Space.xs),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(p.label, style: context.type.cardTitle),
                    if (p.titles.isNotEmpty) Text(p.titles, style: context.type.caption),
                    if (!kids && p.mistakesLine.isNotEmpty) Text(p.mistakesLine, style: context.type.caption),
                    if (p.note?.isNotEmpty ?? false) Text(p.note!, style: context.type.caption.copyWith(fontStyle: FontStyle.italic)),
                  ],
                ),
              ),
              const Gap(Space.s),
              GradeView(grade: p.grade, label: p.gradeLabel),
            ],
          ),
        ],
      ],
    );
  }
}

/// A portion's part: its name, its passages and pages.
class PortionPartView extends StatelessWidget {
  const PortionPartView({required this.part, this.trailing, super.key});

  final PortionPart part;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final (bg, ink) = switch (part.key) {
      'new' => Tone.brand,
      'near' => Tone.info,
      _ => Tone.attention,
    }.colours(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 40,
          height: 40,
          alignment: Alignment.center,
          decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(context.shape.field)),
          child: Icon(
            switch (part.key) {
              'new' => Icons.auto_stories_rounded,
              'near' => Icons.replay_rounded,
              _ => Icons.history_rounded,
            },
            color: ink,
            size: 22,
          ),
        ),
        const Gap(Space.m),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(part.label, style: context.type.cardTitle),
              if (part.titles.isNotEmpty) Text(part.titles, style: context.type.body),
              if (part.pages.isNotEmpty) Text(l.quranPages(part.pageSpan), style: context.type.caption),
            ],
          ),
        ),
        ?trailing,
      ],
    );
  }
}

/// A list's children coming in one by one, a gap under each.
Widget quranList(BuildContext context, List<Widget> children, {EdgeInsetsGeometry? padding}) => ListView(
  padding: padding ?? pagePadding.copyWith(top: Space.m),
  physics: const AlwaysScrollableScrollPhysics(),
  children: [
    for (final (i, w) in children.indexed)
      Padding(
        padding: const EdgeInsets.only(bottom: Space.m),
        child: w.appear(context, i),
      ),
  ],
);
