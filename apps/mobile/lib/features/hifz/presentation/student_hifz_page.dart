import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/done_moment.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../data/hifz_repository.dart';
import '../domain/hifz.dart';
import 'widgets/hifz_map_view.dart';
import 'widgets/page_sheet.dart';

/// A student's hifz map for their teacher, opened from the student's sheet:
/// the same map (to read), the counts, the last two weeks' reviews and
/// where each came from, saying what the student already knows (whole
/// parts or surahs, and how well) and taking pages off that were marked by
/// mistake. Reports and checked homework move it, so it follows those.
class StudentHifzPage extends StatelessWidget {
  const StudentHifzPage({required this.enrollmentId, super.key});

  final String enrollmentId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<StudentHifz>(
      () => c.read<HifzRepository>().student(enrollmentId),
      refreshOn: c.read<Live>().on({Live.lessons, Live.homework}),
    )..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.hifzTitle)),
      body: LoadView<StudentHifz>(
        skeleton: const SkeletonList(count: 3),
        builder: (context, hifz) => _StudentHifz(hifz: hifz),
      ),
    ),
  );
}

class _StudentHifz extends StatelessWidget {
  const _StudentHifz({required this.hifz});

  final StudentHifz hifz;

  Future<void> _mark(BuildContext context) async {
    final l = context.l10n;
    final repo = context.read<HifzRepository>();
    final cubit = context.read<LoadCubit<StudentHifz>>();
    final choice = await _choose(context, hifz, mark: true);
    if (choice == null || !context.mounted) return;
    try {
      final n = await repo.markMemorized(hifz.enrollmentId, juzs: choice.juzs, surahs: choice.surahs, strength: choice.strength!);
      if (!context.mounted) return;
      DoneMoment.show(context, title: l.hifzMarked(n));
      await cubit.load();
    } on ApiProblem catch (p) {
      if (context.mounted) showSnack(context, context.problemText(p));
    }
  }

  Future<void> _clear(BuildContext context) async {
    final l = context.l10n;
    final repo = context.read<HifzRepository>();
    final cubit = context.read<LoadCubit<StudentHifz>>();
    final choice = await _choose(context, hifz, mark: false);
    if (choice == null || !context.mounted) return;
    final sure = await confirmDestructive(
      context,
      title: l.hifzClearConfirm,
      body: '${l.hifzChosen(hifz.chosen(juzs: choice.juzs, surahs: choice.surahs).length)}. ${l.hifzClearHint}',
      confirm: l.hifzClear,
    );
    if (!sure || !context.mounted) return;
    try {
      final n = await repo.clear(hifz.enrollmentId, juzs: choice.juzs, surahs: choice.surahs);
      if (!context.mounted) return;
      showSnack(context, l.hifzCleared(n));
      await cubit.load();
    } on ApiProblem catch (p) {
      if (context.mounted) showSnack(context, context.problemText(p));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final h = hifz;
    final children = <Widget>[
      Row(
        children: [
          SoftAvatar(h.studentName, size: 44),
          const Gap(Space.m),
          Expanded(
            child: Semantics(header: true, child: Text(h.studentName, style: context.type.title)),
          ),
        ],
      ),
      if (!h.map.available)
        EmptyState(icon: Icons.auto_stories_outlined, title: l.hifzNoText)
      else ...[
        HifzMapCard(map: h.map),
        PrimaryAction(l.hifzTell, key: const Key('hifz-tell'), icon: Icons.playlist_add_check_rounded, onPressed: () => _mark(context)),
        SecondaryAction(l.hifzClear, key: const Key('hifz-clear'), icon: Icons.layers_clear_outlined, onPressed: () => _clear(context)),
        SectionHeader(l.hifzRecent),
        if (h.recent.isEmpty)
          EmptyNote(l.hifzRecentNone, icon: Icons.history_rounded)
        else
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                for (final (i, r) in h.recent.take(20).indexed) ...[
                  if (i > 0) const Divider(height: 1, indent: Space.l, endIndent: Space.l),
                  _ReviewRow(review: r),
                ],
              ],
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

/// A review: the page, where it came from and when, and the grade it counted as.
class _ReviewRow extends StatelessWidget {
  const _ReviewRow({required this.review});

  final HifzReview review;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final r = review;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.hifzPageNumber(r.page), style: context.type.cardTitle),
                Text([r.sourceLabel, ?r.at?.label].where((s) => s.isNotEmpty).join(' · '), style: context.type.caption),
              ],
            ),
          ),
          const Gap(Space.s),
          StatusChip(r.gradeLabel, tone: gradeTone(r.grade)),
        ],
      ),
    );
  }
}

typedef _Choice = ({Set<int> juzs, Set<int> surahs, String? strength});

/// Chooses parts and surahs (and, to mark them, how well they are known); null when the teacher left.
Future<_Choice?> _choose(BuildContext context, StudentHifz hifz, {required bool mark}) => showActionSheet<_Choice>(
  context,
  title: mark ? context.l10n.hifzTell : context.l10n.hifzClear,
  builder: (_) => _Chooser(hifz: hifz, mark: mark),
);

class _Chooser extends StatefulWidget {
  const _Chooser({required this.hifz, required this.mark});

  final StudentHifz hifz;
  final bool mark;

  @override
  State<_Chooser> createState() => _ChooserState();
}

class _ChooserState extends State<_Chooser> {
  final _juzs = <int>{};
  final _surahs = <int>{};
  late String _strength = widget.hifz.marks.containsKey('good') ? 'good' : (widget.hifz.marks.keys.firstOrNull ?? 'good');
  String _q = '';

  /// The surahs the search finds by Arabic or English name, or by number (a few at a time).
  List<SurahPages> get _found {
    final q = _q.toLowerCase();
    if (q.isEmpty) return const [];
    return [
      for (final s in widget.hifz.surahs)
        if (s.surah.nameAr.contains(q) || s.surah.nameEn.toLowerCase().contains(q) || '${s.surah.number}' == q) s,
    ].take(8).toList();
  }

  void _toggle(Set<int> set, int n) => setState(() => set.contains(n) ? set.remove(n) : set.add(n));

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final h = widget.hifz;
    final pages = h.chosen(juzs: _juzs, surahs: _surahs).length;
    final names = {for (final s in h.surahs) s.surah.number: s.surah.label};
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(Space.xl - 4, 0, Space.xl - 4, Space.xl + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.hifzParts, style: context.type.section),
          const Gap(Space.s),
          Wrap(
            spacing: Space.xs,
            runSpacing: Space.xs,
            children: [
              for (final j in h.map.juzs)
                FilterChip(
                  key: Key('juz-${j.juz}'),
                  label: Text('${j.juz}'),
                  tooltip: l.hifzJuz(j.juz),
                  selected: _juzs.contains(j.juz),
                  showCheckmark: false,
                  onSelected: (_) => _toggle(_juzs, j.juz),
                ),
            ],
          ),
          const Gap(Space.l),
          Text(l.hifzSurahs, style: context.type.section),
          const Gap(Space.s),
          TextField(
            key: const Key('surah-search'),
            decoration: InputDecoration(labelText: l.reciteSearch, hintText: l.hifzSurahHint, prefixIcon: const Icon(Icons.search_rounded)),
            onChanged: (v) => setState(() => _q = v.trim()),
          ),
          if (_surahs.isNotEmpty) ...[
            const Gap(Space.s),
            Wrap(
              spacing: Space.xs,
              runSpacing: Space.xs,
              children: [
                for (final n in _surahs.toList()..sort())
                  InputChip(key: Key('surah-chip-$n'), label: Text(names[n] ?? '$n'), onDeleted: () => _toggle(_surahs, n)),
              ],
            ),
          ],
          for (final s in _found)
            CheckboxListTile(
              key: Key('surah-${s.surah.number}'),
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(s.surah.label),
              value: _surahs.contains(s.surah.number),
              onChanged: (_) => _toggle(_surahs, s.surah.number),
            ),
          if (widget.mark && h.marks.isNotEmpty) ...[
            const Gap(Space.l),
            Text(l.hifzHowWell, style: context.type.section),
            const Gap(Space.s),
            Wrap(
              spacing: Space.s,
              runSpacing: Space.xs,
              children: [
                for (final MapEntry(:key, :value) in h.marks.entries)
                  ChoiceChip(
                    key: Key('mark-$key'),
                    label: Text(value),
                    selected: _strength == key,
                    showCheckmark: false,
                    onSelected: (_) => setState(() => _strength = key),
                  ),
              ],
            ),
          ],
          const Gap(Space.l),
          Text(l.hifzChosen(pages), key: const Key('hifz-chosen'), style: context.type.label),
          const Gap(Space.s),
          PrimaryAction(
            widget.mark ? l.hifzSave : l.hifzClear,
            key: const Key('hifz-choose'),
            danger: !widget.mark,
            onPressed: pages == 0
                ? null
                : () =>
                      Navigator.of(context)
                          .pop<_Choice>((juzs: {..._juzs}, surahs: {..._surahs}, strength: widget.mark ? _strength : null)),
          ),
        ],
      ),
    );
  }
}
