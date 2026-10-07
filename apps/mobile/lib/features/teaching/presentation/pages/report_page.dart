import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../../shared/widgets/star_picker.dart';
import '../../../homework/presentation/widgets/submission_view.dart';
import '../../data/teaching_repository.dart';
import '../../domain/reports.dart';

/// Writing a report from the phone: today's plan (what happened with each
/// lesson, a score, a note), focus and overall, words for the family and
/// for the academy, and homework. An absence asks only for the note.
/// Pops with true once sent.
class TeacherReportPage extends StatelessWidget {
  const TeacherReportPage({required this.reportId, super.key});

  final String reportId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<ReportForm>(() => c.read<TeachingRepository>().report(reportId))..load(),
    child: BlocBuilder<LoadCubit<ReportForm>, LoadState<ReportForm>>(
      builder: (context, state) => Scaffold(
        appBar: AppBar(title: Text(state.data?.summary.student ?? context.l10n.navReports)),
        body: LoadView<ReportForm>(refreshable: false, builder: (context, form) => _Editor(form: form)),
      ),
    ),
  );
}

class _Editor extends StatefulWidget {
  const _Editor({required this.form});

  final ReportForm form;

  @override
  State<_Editor> createState() => _EditorState();
}

class _EditorState extends State<_Editor> {
  late final ReportForm f = widget.form;
  late final List<ReportItem> _items = [...f.items];
  late int? _engagement = f.engagement;
  late int? _overall = f.overall;
  late final _forFamily = TextEditingController(text: f.notesForStudent);
  late final _internal = TextEditingController(text: f.notesInternal);
  late final _absence = TextEditingController(text: f.absenceNote);
  late bool _give = f.homework != null;
  late final _hwTitle = TextEditingController(text: f.homework?.title);
  late final _hwDetails = TextEditingController(text: f.homework?.instructions);
  late String _accepts = f.homework?.accepts ?? 'any';
  int _dueDays = 3;
  final _notes = <String>{};
  final _sendKey = GlobalKey();
  bool _busy = false;
  String? _error;
  Map<String, List<String>> _fieldErrors = const {};

  bool get _absent => f.summary.absence;

  /// Handed in or marked: the homework stays as it is.
  bool get _homeworkLocked => f.homework != null && (f.homework!.status != 'assigned' || f.homework!.submissions.isNotEmpty);

  @override
  void dispose() {
    for (final c in [_forFamily, _internal, _absence, _hwTitle, _hwDetails]) {
      c.dispose();
    }
    super.dispose();
  }

  String _outcomeLabel(String o) {
    final l = context.l10n;
    return switch (o) {
      'introduced' => l.outcomeIntroduced,
      'practiced' => l.outcomePracticed,
      'mastered' => l.outcomeMastered,
      _ => l.outcomeNeedsReview,
    };
  }

  Future<void> _addLesson() async {
    final chosen = _items.map((i) => i.nodeId).toSet();
    final picked = await showModalBottomSheet<({String id, String label})>(
      context: context,
      useRootNavigator: true,
      isScrollControlled: true,
      builder: (c) => _LessonPicker(options: f.lessonOptions.where((o) => !chosen.contains(o.id)).toList()),
    );
    if (picked == null) return;
    final parts = picked.label.split(' › ');
    setState(
      () => _items.add(
        ReportItem(nodeId: picked.id, title: parts.last, path: parts.take(parts.length - 1).join(' › '), outcome: 'practiced'),
      ),
    );
  }

  Future<void> _send() async {
    final l = context.l10n;
    if (!_absent && (_engagement == null || _overall == null)) {
      setState(() => _error = l.pickRatings);
      return;
    }
    if (!_absent && _items.any((i) => i.outcome == null)) {
      setState(() => _error = l.pickOutcomes);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors = const {};
    });
    final homework = _homeworkLocked || _absent
        ? null
        : (_give && _hwTitle.text.trim().isNotEmpty
              ? {'title': _hwTitle.text.trim(), 'instructions': _hwDetails.text.trim(), 'accepts': _accepts, 'due_days': _dueDays}
              : {'title': ''});
    final payload = _absent
        ? {'absence_note': _absence.text.trim(), 'notes_internal': _internal.text.trim()}
        : {
            'engagement': _engagement,
            'overall': _overall,
            'notes_for_student': _forFamily.text.trim(),
            'notes_internal': _internal.text.trim(),
            'items': [for (final i in _items) i.toJson()],
            'homework': ?homework,
          };
    try {
      await context.read<TeachingRepository>().submitReport(f.summary.id, payload);
      if (!mounted) return;
      Juice.reward();
      Celebration.show(context, from: Celebration.centreOf(_sendKey.currentContext ?? context), count: 50);
      showSnack(context, l.reportSent);
      Navigator.of(context).pop(true);
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = context.problemText(p);
          _fieldErrors = p.errors;
        });
      }
    }
  }

  String? _err(String field) => _fieldErrors[field]?.first;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final s = f.summary;
    final locked = f.locked;
    return ListView(
      padding: pagePadding,
      children: staggered(context, [
        Wrap(
          spacing: 8,
          runSpacing: 6,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Pill(s.absence ? l.reportAbsence : l.reportLesson, tone: Tone.info),
            Pill(s.statusLabel, tone: s.status == 'overdue' ? Tone.danger : (s.open ? Tone.attention : Tone.brand)),
            if (s.lessonAt != null) Text(s.lessonAt!.when(context), style: context.text.bodySmall),
          ],
        ),
        if (s.open && s.dueAt != null) ...[
          const SizedBox(height: 4),
          Text(l.reportDueBy(s.dueAt!.when(context)), style: context.text.bodySmall),
        ],
        if (locked) ...[
          const SizedBox(height: 12),
          Note(
            [l.reportLocked, if ((f.reviewNote ?? '').isNotEmpty) f.reviewNote!].join('\n'),
            tone: Tone.brand,
            icon: Icons.lock_outline_rounded,
          ),
        ],
        const SizedBox(height: 14),
        if (_absent) ...[
          Note(l.absenceHint, icon: Icons.event_busy_outlined),
          const SizedBox(height: 12),
          TextField(
            controller: _absence,
            enabled: !locked,
            minLines: 3,
            maxLines: 8,
            maxLength: 1000,
            decoration: InputDecoration(labelText: l.absenceNote, errorText: _err('absence_note')),
          ),
        ] else ...[
          SectionLabel(l.todaysPlan),
          if (_items.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(l.planHint, style: context.text.bodySmall),
            ),
          for (final (i, item) in _items.indexed) ...[_itemCard(item, i, locked), const SizedBox(height: 10)],
          if (!locked && _items.length < 10 && f.lessonOptions.length > _items.length)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(onPressed: _addLesson, icon: const Icon(Icons.add_rounded), label: Text(l.addLesson)),
            ),
          if (_err('items') != null) Text(_err('items')!, style: TextStyle(color: c.dangerInk)),
          const SizedBox(height: 10),
          LmsCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.engagement, style: context.text.titleSmall),
                IgnorePointer(
                  ignoring: locked,
                  child: StarPicker(value: _engagement, label: l.engagement, onChanged: (v) => setState(() => _engagement = v)),
                ),
                const SizedBox(height: 10),
                Text(l.overall, style: context.text.titleSmall),
                IgnorePointer(
                  ignoring: locked,
                  child: StarPicker(value: _overall, label: l.overall, onChanged: (v) => setState(() => _overall = v)),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _forFamily,
            enabled: !locked,
            minLines: 2,
            maxLines: 6,
            maxLength: 3000,
            decoration: InputDecoration(labelText: l.notesForFamily, errorText: _err('notes_for_student')),
          ),
        ],
        const SizedBox(height: 6),
        TextField(
          controller: _internal,
          enabled: !locked,
          minLines: 2,
          maxLines: 6,
          maxLength: 3000,
          decoration: InputDecoration(labelText: l.notesInternal, helperText: l.notesInternalHint, errorText: _err('notes_internal')),
        ),
        if (!_absent) ...[const SizedBox(height: 12), _homeworkCard(locked)],
        if (_error != null) ...[const SizedBox(height: 12), Note(_error!, tone: Tone.danger, icon: Icons.error_outline_rounded)],
        if (!locked) ...[
          const SizedBox(height: 16),
          SizedBox(
            key: _sendKey,
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: _busy ? null : _send,
              icon: _busy
                  ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2.4))
                  : const Icon(Icons.send_rounded),
              label: Text(s.open ? l.sendReport : l.updateReport),
            ),
          ),
        ],
      ]),
    );
  }

  Widget _itemCard(ReportItem item, int index, bool locked) {
    final l = context.l10n;
    final c = context.colors;
    final noteOpen = _notes.contains(item.nodeId) || (item.note ?? '').isNotEmpty;
    return LmsCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.title, style: context.text.titleSmall),
                    if (item.path.isNotEmpty) Text(item.path, style: context.text.bodySmall),
                  ],
                ),
              ),
              if (item.reason != null)
                Pill(item.reason == 'review' ? l.reasonReview : l.reasonNext, tone: item.reason == 'review' ? Tone.attention : Tone.info),
              if (!locked)
                IconButton(
                  tooltip: l.remove,
                  onPressed: () => setState(() => _items.removeAt(index)),
                  icon: Icon(Icons.close_rounded, color: c.muted),
                ),
            ],
          ),
          for (final o in item.objectives) Text('• $o', style: context.text.bodySmall),
          const SizedBox(height: 8),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              for (final o in const ['introduced', 'practiced', 'mastered', 'needs_review'])
                ChoiceChip(
                  label: Text(_outcomeLabel(o)),
                  selected: item.outcome == o,
                  onSelected: locked
                      ? null
                      : (_) {
                          Juice.tap();
                          setState(() => item.outcome = o);
                        },
                ),
            ],
          ),
          const SizedBox(height: 6),
          Wrap(
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 6,
            children: [
              Text(l.itemScore, style: context.text.bodySmall),
              IgnorePointer(
                ignoring: locked,
                child: StarPicker(
                  value: item.score,
                  size: 24,
                  clearable: true,
                  label: l.itemScore,
                  onChanged: (v) => setState(() => item.score = v),
                ),
              ),
              if (!noteOpen && !locked) TextButton(onPressed: () => setState(() => _notes.add(item.nodeId)), child: Text(l.addNote)),
            ],
          ),
          if (noteOpen)
            Padding(
              padding: const EdgeInsetsDirectional.only(end: 6),
              child: TextFormField(
                initialValue: item.note,
                enabled: !locked,
                maxLength: 500,
                decoration: InputDecoration(labelText: l.itemNote, isDense: true),
                onChanged: (v) => item.note = v,
              ),
            ),
        ],
      ),
    );
  }

  Widget _homeworkCard(bool locked) {
    final l = context.l10n;
    final hw = f.homework;
    if (_homeworkLocked) {
      return LmsCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text(hw!.title, style: context.text.titleSmall)),
                Pill(hw.statusLabel, tone: Tone.info),
              ],
            ),
            const SizedBox(height: 6),
            Text(l.homeworkLocked, style: context.text.bodySmall),
            for (final s in hw.submissions) ...[const SizedBox(height: 8), SubmissionView(submission: s, title: hw.student)],
          ],
        ),
      );
    }
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            title: Text(l.giveHomework, style: context.text.titleSmall),
            value: _give,
            onChanged: locked ? null : (v) => setState(() => _give = v),
          ),
          if (_give) ...[
            TextField(
              controller: _hwTitle,
              enabled: !locked,
              maxLength: 200,
              decoration: InputDecoration(labelText: l.homeworkField, errorText: _err('homework.title')),
            ),
            TextField(
              controller: _hwDetails,
              enabled: !locked,
              minLines: 2,
              maxLines: 5,
              maxLength: 3000,
              decoration: InputDecoration(labelText: '${l.homeworkDetails} ${l.optional}'),
            ),
            Text(l.acceptsLabel, style: context.text.labelMedium),
            const SizedBox(height: 6),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final (value, label) in [
                  ('any', l.acceptsAny),
                  ('text', l.acceptsText),
                  ('audio', l.acceptsAudio),
                  ('file', l.acceptsFile),
                ])
                  ChoiceChip(
                    label: Text(label),
                    selected: _accepts == value,
                    onSelected: locked ? null : (_) => setState(() => _accepts = value),
                  ),
              ],
            ),
            if (hw == null) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  IconButton.outlined(
                    onPressed: _dueDays > 1 ? () => setState(() => _dueDays--) : null,
                    icon: const Icon(Icons.remove_rounded),
                  ),
                  const SizedBox(width: 10),
                  Text(l.dueIn(_dueDays), style: context.text.titleSmall),
                  const SizedBox(width: 10),
                  IconButton.outlined(
                    onPressed: _dueDays < 30 ? () => setState(() => _dueDays++) : null,
                    icon: const Icon(Icons.add_rounded),
                  ),
                ],
              ),
            ] else if (hw.dueAt != null) ...[
              const SizedBox(height: 8),
              Text(l.dueBy(hw.dueAt!.when(context)), style: context.text.bodySmall),
            ],
          ],
        ],
      ),
    );
  }
}

/// The lessons of the course to add one more to the report, with a search.
class _LessonPicker extends StatefulWidget {
  const _LessonPicker({required this.options});

  final List<({String id, String label})> options;

  @override
  State<_LessonPicker> createState() => _LessonPickerState();
}

class _LessonPickerState extends State<_LessonPicker> {
  String _q = '';

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final shown = widget.options.where((o) => _q.isEmpty || o.label.contains(_q)).toList();
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * 0.7,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: TextField(
                  decoration: InputDecoration(labelText: l.searchLessons, prefixIcon: const Icon(Icons.search_rounded)),
                  onChanged: (v) => setState(() => _q = v.trim()),
                ),
              ),
              Expanded(
                child: ListView(
                  children: [for (final o in shown) ListTile(title: Text(o.label), onTap: () => Navigator.of(context).pop(o))],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
