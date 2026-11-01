import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../../mushaf/presentation/widgets/mushaf_entry.dart';
import '../data/quran_repository.dart';
import '../domain/quran.dart';
import 'widgets/quran_ui.dart';

/// A Quran lesson for its teacher, opened from the lesson's sheet: the plan (where the student is, how much is
/// behind them, a pause or an end), today's portion as cards (the new part, the near and the far review), the
/// lesson's mushaf, and for each student (a group's chosen one at a time) each part recited with its mistakes by
/// kind and a grade, saved once. A student's own pace is changed from here; a group's is the office's.
class QuranLessonPage extends StatelessWidget {
  const QuranLessonPage({required this.lessonId, super.key});

  final String lessonId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) =>
        LoadCubit<LessonPortion>(() => c.read<QuranRepository>().portion(lessonId), refreshOn: c.read<Live>().on({Live.lessons}))..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.quranLessonTitle)),
      body: LoadView<LessonPortion>(
        skeleton: const SkeletonList(count: 3),
        builder: (context, portion) => _Lesson(lessonId: lessonId, portion: portion),
      ),
    ),
  );
}

class _Lesson extends StatefulWidget {
  const _Lesson({required this.lessonId, required this.portion});

  final String lessonId;
  final LessonPortion portion;

  @override
  State<_Lesson> createState() => _LessonState();
}

class _LessonState extends State<_Lesson> {
  /// What is typed, by student and part, kept while switching between a group's students.
  final _scores = <String, Map<String, PartScore>>{};
  final _notes = <String, TextEditingController>{};
  String? _student;
  bool _busy = false;
  String? _error;

  LessonPortion get _p => widget.portion;

  String? get _studentId => _student ?? _p.students.firstOrNull?.id;

  Map<String, PartScore> _scoresOf(String student) => _scores.putIfAbsent(student, () {
    final parts = _p.plan?.portion?.parts ?? const <PortionPart>[];
    return {for (final part in parts) part.key: PartScore()};
  });

  TextEditingController _note(String student, String part) => _notes.putIfAbsent('$student/$part', TextEditingController.new);

  @override
  void dispose() {
    for (final c in _notes.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit(String student) async {
    final l = context.l10n;
    final scores = _scoresOf(student);
    for (final e in scores.entries) {
      e.value.note = _note(student, e.key).text;
    }
    final recited = {
      for (final e in scores.entries)
        if (e.value.recited) e.key: e.value,
    };
    if (recited.isEmpty) return setState(() => _error = l.quranChooseParts);
    if (recited.values.any((s) => s.grade != null && s.note.trim().isEmpty)) return setState(() => _error = l.quranNoteNeeded);
    setState(() {
      _busy = true;
      _error = null;
    });
    final cubit = context.read<LoadCubit<LessonPortion>>();
    try {
      final record = await context.read<QuranRepository>().record(
        widget.lessonId,
        parts: {for (final e in recited.entries) e.key: e.value.toJson()},
        studentId: student,
        group: _p.group,
      );
      if (!mounted) return;
      showSnack(context, record.advanced ? l.quranRecordedAdvanced : l.quranRecordedStays);
      _scores.remove(student);
      await cubit.load();
    } on ApiProblem catch (e) {
      if (mounted) setState(() => _error = e.message ?? l.problem);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final plan = _p.plan;
    if (plan == null) {
      return ListView(
        padding: pagePadding,
        physics: const AlwaysScrollableScrollPhysics(),
        children: [EmptyState(icon: Icons.menu_book_outlined, title: l.quranLessonNoPlan)],
      );
    }
    final student = _studentId;
    final done = student == null ? null : _p.resultOf(student);
    final parts = plan.portion?.parts ?? const <PortionPart>[];
    final children = <Widget>[
      _PlanHeader(plan: plan),
      MushafEntry(
        lessonId: widget.lessonId,
        builder: (context, label, open) => SecondaryAction(label, icon: Icons.menu_book_rounded, onPressed: open),
      ),
      if (_p.group && _p.students.length > 1) ...[
        SectionHeader(l.quranStudent),
        Wrap(
          spacing: Space.s,
          runSpacing: Space.xs,
          children: [
            for (final s in _p.students)
              ChoiceChip(
                key: Key('student-${s.id}'),
                label: Text(s.name),
                avatar: _p.resultOf(s.id) != null ? Icon(Icons.check_rounded, color: context.colors.brandDeep, size: 18) : null,
                selected: s.id == student,
                onSelected: (_) => setState(() {
                  _student = s.id;
                  _error = null;
                }),
              ),
          ],
        ),
      ],
      if (plan.portion?.lessonTitle case final lesson?) Text(lesson, style: context.type.cardTitle),
      if (done != null) ...[
        Note(l.quranAlreadyRecorded, tone: Tone.brand, icon: Icons.check_circle_outline_rounded),
        LmsCard(
          key: const Key('quran-result'),
          child: ResultView(result: done),
        ),
      ] else if (student != null) ...[
        if (parts.isNotEmpty) Note(l.quranMistakesHint, icon: Icons.info_outline_rounded),
        for (final part in parts)
          _PartCard(
            key: Key('part-${part.key}'),
            part: part,
            score: _scoresOf(student)[part.key]!,
            mistakeTypes: _p.mistakeTypes,
            grades: _p.grades,
            note: _note(student, part.key),
            onChanged: () => setState(() => _error = null),
          ),
        if (_error != null)
          Text(
            _error!,
            key: const Key('quran-error'),
            style: context.type.body.copyWith(color: context.colors.dangerInk),
          ),
        if (parts.isNotEmpty)
          PrimaryAction(
            l.quranSubmit,
            key: const Key('quran-submit'),
            icon: Icons.check_rounded,
            busy: _busy,
            onPressed: _busy ? null : () => _submit(student),
          ),
      ],
    ];
    return quranList(context, children);
  }
}

/// The plan in short: the program, where the student is, how much is behind them, the pace, and a pause or an end.
class _PlanHeader extends StatelessWidget {
  const _PlanHeader({required this.plan});

  final QuranPlan plan;

  String _n(num v) => v == v.roundToDouble() ? '${v.toInt()}' : '$v';

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = plan;
    return LmsCard(
      key: const Key('quran-plan'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(p.programName, style: context.type.cardTitle),
          if (p.trackLabel.isNotEmpty) Text(p.trackLabel, style: context.type.caption),
          const Gap(Space.s),
          if (p.finished)
            Text(l.quranFinished, style: context.type.body)
          else if (p.position != null)
            Text(l.quranAt(p.position!), style: context.type.body),
          const Gap(Space.s),
          ProgressBar(p.progress, height: 6),
          const Gap(Space.xs),
          Text(l.quranProgress(p.percent), style: context.type.caption),
          const Gap(Space.xs),
          Text(
            l.quranPaceLine(_n(p.newAmount), p.unitLabel, '${p.nearPortions}', '${p.farPages}'),
            key: const Key('quran-pace'),
            style: context.type.caption,
          ),
          if (p.frozenLabel != null) ...[
            const Gap(Space.s),
            Note(p.frozenLabel!, tone: Tone.attention, icon: Icons.pause_circle_outline_rounded),
          ],
          if (!p.isGroup && p.enrollmentId != null)
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: TextButton.icon(
                key: const Key('quran-pace-open'),
                onPressed: () => _editPace(context, p),
                icon: const Icon(Icons.tune_rounded),
                label: Text(l.quranPace),
              ),
            ),
        ],
      ),
    );
  }
}

/// The student's own pace: the new amount a lesson in the program's unit, the near review in portions and the far
/// review in pages; or back to the program's.
Future<void> _editPace(BuildContext context, QuranPlan plan) async {
  final cubit = context.read<LoadCubit<LessonPortion>>();
  final saved = await showActionSheet<bool>(
    context,
    title: context.l10n.quranPace,
    builder: (sheet) => _PaceForm(plan: plan, repository: context.read<QuranRepository>()),
  );
  if (saved == true && context.mounted) {
    showSnack(context, context.l10n.quranPaceSaved);
    await cubit.load();
  }
}

class _PaceForm extends StatefulWidget {
  const _PaceForm({required this.plan, required this.repository});

  final QuranPlan plan;
  final QuranRepository repository;

  @override
  State<_PaceForm> createState() => _PaceFormState();
}

class _PaceFormState extends State<_PaceForm> {
  late final _new = TextEditingController(text: _n(widget.plan.newAmount));
  late final _near = TextEditingController(text: '${widget.plan.nearPortions}');
  late final _far = TextEditingController(text: '${widget.plan.farPages}');
  bool _busy = false;
  String? _error;

  static String _n(num v) => v == v.roundToDouble() ? '${v.toInt()}' : '$v';

  @override
  void initState() {
    super.initState();
    // The latest pace (another screen may have changed it).
    widget.repository.studentPlan(widget.plan.enrollmentId!).then((s) {
      final p = s.plan;
      if (!mounted || p == null || _busy) return;
      _new.text = _n(p.newAmount);
      _near.text = '${p.nearPortions}';
      _far.text = '${p.farPages}';
    }, onError: (Object _) {});
  }

  @override
  void dispose() {
    _new.dispose();
    _near.dispose();
    _far.dispose();
    super.dispose();
  }

  Future<void> _save({bool program = false}) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.repository.setPace(
        widget.plan.enrollmentId!,
        newAmount: program ? null : num.tryParse(_new.text.trim()),
        nearPortions: program ? null : int.tryParse(_near.text.trim()),
        farPages: program ? null : int.tryParse(_far.text.trim()),
      );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiProblem catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = e.message ?? context.l10n.problem;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    InputDecoration field(String label) => InputDecoration(labelText: label);
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(Space.xl - 4, 0, Space.xl - 4, Space.xl + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            key: const Key('pace-new'),
            controller: _new,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: field(l.quranPaceNew(widget.plan.unitLabel)),
          ),
          const Gap(Space.m),
          TextField(key: const Key('pace-near'), controller: _near, keyboardType: TextInputType.number, decoration: field(l.quranPaceNear)),
          const Gap(Space.m),
          TextField(key: const Key('pace-far'), controller: _far, keyboardType: TextInputType.number, decoration: field(l.quranPaceFar)),
          if (_error != null) ...[const Gap(Space.s), Text(_error!, style: context.type.body.copyWith(color: context.colors.dangerInk))],
          const Gap(Space.l),
          PrimaryAction(l.quranPaceSave, key: const Key('pace-save'), busy: _busy, onPressed: _busy ? null : _save),
          const Gap(Space.s),
          TextButton(onPressed: _busy ? null : () => _save(program: true), child: Text(l.quranPaceProgram)),
        ],
      ),
    );
  }
}

/// A part of today's portion: whether the student recited it, then its mistakes by kind and the grade (with a note
/// when it is the teacher's own).
class _PartCard extends StatelessWidget {
  const _PartCard({
    required this.part,
    required this.score,
    required this.mistakeTypes,
    required this.grades,
    required this.note,
    required this.onChanged,
    super.key,
  });

  final PortionPart part;
  final PartScore score;
  final List<QuranChoice> mistakeTypes;
  final List<QuranChoice> grades;
  final TextEditingController note;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          PortionPartView(part: part),
          SwitchListTile.adaptive(
            key: Key('recited-${part.key}'),
            contentPadding: EdgeInsets.zero,
            title: Text(l.quranPartRecited, style: context.type.body),
            value: score.recited,
            onChanged: (v) {
              score.recited = v;
              onChanged();
            },
          ),
          if (score.recited) ...[
            Text(l.quranMistakes, style: context.type.label),
            for (final t in mistakeTypes)
              MistakeCounter(
                key: Key('${part.key}-${t.key}'),
                kind: '${part.key}-${t.key}',
                label: t.label,
                count: score.mistakes[t.key] ?? 0,
                onChange: (by) {
                  score.add(t.key, by);
                  onChanged();
                },
              ),
            const Gap(Space.s),
            Text(l.quranGrade, style: context.type.label),
            const Gap(Space.xs),
            GradePicker(
              keyPrefix: 'grade-${part.key}',
              grades: grades,
              value: score.grade,
              onChanged: (g) {
                score.grade = g;
                onChanged();
              },
            ),
            if (score.grade != null) ...[
              const Gap(Space.s),
              TextField(
                key: Key('note-${part.key}'),
                controller: note,
                maxLength: 500,
                minLines: 1,
                maxLines: 3,
                decoration: InputDecoration(labelText: l.quranNote, hintText: l.quranNoteHint),
              ),
            ],
          ],
        ],
      ),
    );
  }
}
