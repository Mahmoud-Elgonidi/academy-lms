import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/done_moment.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../../shared/widgets/star_picker.dart';
import '../../../homework/data/homework_repository.dart';
import '../../../homework/presentation/widgets/submission_view.dart';
import '../../../homework/presentation/widgets/voice_recorder.dart';
import '../../../roadmap/domain/report_answers.dart';
import '../../data/report_drafts.dart';
import '../../data/teaching_repository.dart';
import '../../domain/reports.dart';
import '../../domain/voice_draft.dart';
import '../../../../shared/widgets/latin_digits.dart';

/// Writing a report from the phone, one card at a time: what was worked on
/// (what happened with each lesson of today's plan, a score, a note), how
/// it went (focus and overall), the subject's own questions, the words for
/// the family and for the academy, and homework. An absence asks only for
/// the note. When the academy turned it on, a voice note fills what is still
/// empty (marked suggested until sent). Sending and keeping a draft on this
/// phone stay at the foot. Pops with true once sent.
class TeacherReportPage extends StatelessWidget {
  const TeacherReportPage({required this.reportId, super.key});

  final String reportId;

  @override
  Widget build(BuildContext context) {
    final account = context.read<SessionCubit>().state.current?.id ?? '';
    return BlocProvider(
      create: (c) => LoadCubit<(ReportForm, ReportDraft?)>(() async {
        final form = await c.read<TeachingRepository>().report(reportId);
        final draft = form.summary.open ? await ReportDrafts().of(account, reportId) : null;
        return (form, draft);
      })..load(),
      child: BlocBuilder<LoadCubit<(ReportForm, ReportDraft?)>, LoadState<(ReportForm, ReportDraft?)>>(
        builder: (context, state) {
          final l = context.l10n;
          final s = state.data?.$1.summary;
          return Scaffold(
            appBar: AppBar(
              title: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(s?.absence ?? false ? l.reportAbsence : l.reportTitle),
                  if (s != null)
                    Text([s.student, if (s.lessonAt != null) s.lessonAt!.when(context)].join(' · '), style: context.type.caption),
                ],
              ),
            ),
            body: LoadView<(ReportForm, ReportDraft?)>(
              refreshable: false,
              builder: (context, x) => _Editor(
                key: ValueKey(x.$2?.savedAt),
                form: x.$1,
                draft: x.$2,
                account: account,
                onDiscard: () async {
                  await ReportDrafts().remove(account, reportId);
                  if (context.mounted) await context.read<LoadCubit<(ReportForm, ReportDraft?)>>().load();
                },
              ),
            ),
          );
        },
      ),
    );
  }
}

class _Editor extends StatefulWidget {
  const _Editor({required this.form, required this.account, required this.onDiscard, this.draft, super.key});

  final ReportForm form;

  /// What was kept on this phone; it stands in for the form's own values.
  final ReportDraft? draft;
  final String account;
  final VoidCallback onDiscard;

  @override
  State<_Editor> createState() => _EditorState();
}

class _EditorState extends State<_Editor> {
  late final ReportForm f = widget.form;
  late final Map<String, dynamic>? d = widget.draft?.form;
  late final List<ReportItem> _items = d == null
      ? [...f.items]
      : [for (final i in d!['items'] as List<dynamic>? ?? const []) ReportItem.fromJson(i as Map<String, dynamic>)];
  late int? _engagement = d == null ? f.engagement : (d!['engagement'] as num?)?.toInt();
  late int? _overall = d == null ? f.overall : (d!['overall'] as num?)?.toInt();
  late final _forFamily = TextEditingController(text: d == null ? f.notesForStudent : d!['notes_for_student'] as String?);
  late final _internal = TextEditingController(text: d == null ? f.notesInternal : d!['notes_internal'] as String?);
  late final _absence = TextEditingController(text: d == null ? f.absenceNote : d!['absence_note'] as String?);
  late bool _give = d == null ? f.homework != null : d!['give'] == true;
  late final _hwTitle = TextEditingController(text: d == null ? f.homework?.title : d!['hw_title'] as String?);
  late final _hwDetails = TextEditingController(text: d == null ? f.homework?.instructions : d!['hw_details'] as String?);
  late String _accepts = (d == null ? f.homework?.accepts : d!['accepts'] as String?) ?? 'any';
  late int _dueDays = (d?['due_days'] as num?)?.toInt() ?? 3;
  late final Map<String, Object?> _answers = d == null
      ? {for (final a in f.answers) a.key: a.value}
      : {...?(d!['answers'] as Map<String, dynamic>?)};
  late final Map<String, TextEditingController> _typed = {
    for (final q in f.template?.questions ?? const <ReportQuestion>[])
      if (q.type == 'number' || q.type == 'text') q.key: TextEditingController(text: _answers[q.key]?.toString()),
  };
  final _notes = <String>{};
  bool _busy = false;

  /// The voice note's draft: what it filled shows as suggested (or check this) until sent.
  VoiceDraft? _heard;
  Map<String, DraftMark> _marks = const {};
  bool _drafting = false;
  String? _draftError;

  /// Bumped when a draft comes in, so the lessons' note fields show what it wrote.
  int _round = 0;
  String? _error;
  Map<String, List<String>> _fieldErrors = const {};

  bool get _absent => f.summary.absence;

  /// Handed in or marked: the homework stays as it is.
  bool get _homeworkLocked => f.homework != null && (f.homework!.status != 'assigned' || f.homework!.submissions.isNotEmpty);

  @override
  void dispose() {
    for (final c in [_forFamily, _internal, _absence, _hwTitle, _hwDetails, ..._typed.values]) {
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
    final template = f.template;
    final answers = {..._answers, for (final e in _typed.entries) e.key: e.value.text};
    final missing = _absent || template == null ? const <ReportQuestion>[] : template.missing(answers);
    if (missing.isNotEmpty) {
      setState(() => _error = l.answerRequired(missing.first.label));
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
            if (template != null) 'answers': template.payload(answers),
          };
    try {
      await context.read<TeachingRepository>().submitReport(f.summary.id, payload);
      await ReportDrafts().remove(widget.account, f.summary.id);
      if (!mounted) return;
      DoneMoment.show(context, title: l.reportSent);
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

  /// The form as it stands, as the draft endpoint reads it (a homework already handed in counts as set).
  Map<String, dynamic> _current() {
    final template = f.template;
    final answers = {..._answers, for (final e in _typed.entries) e.key: e.value.text};
    if (_absent) return {'absence_note': _absence.text, 'notes_internal': _internal.text};
    return {
      'engagement': _engagement,
      'overall': _overall,
      'notes_for_student': _forFamily.text,
      'notes_internal': _internal.text,
      'items': [for (final i in _items) i.toJson()],
      if (template != null) 'answers': template.payload(answers),
      'homework': _homeworkLocked
          ? {'title': f.homework!.title}
          : (_give && _hwTitle.text.trim().isNotEmpty
                ? {'title': _hwTitle.text, 'instructions': _hwDetails.text, 'accepts': _accepts, 'due_days': _dueDays}
                : null),
    };
  }

  Future<void> _draftFrom(HandInFile? note) async {
    if (note == null) return;
    final repository = context.read<TeachingRepository>();
    setState(() {
      _drafting = true;
      _draftError = null;
    });
    try {
      final draft = await repository.draftReport(f.summary.id, note: note, current: _current());
      if (mounted) setState(() => _take(draft));
    } on ApiProblem catch (p) {
      if (mounted) setState(() => _draftError = context.problemText(p));
    } finally {
      if (mounted) setState(() => _drafting = false);
    }
  }

  /// The draft into the form: only what it filled, so whatever the teacher set stays.
  void _take(VoiceDraft d) {
    _engagement = d.stars('engagement') ?? _engagement;
    _overall = d.stars('overall') ?? _overall;
    if (d.text('notes_for_student') case final t?) _forFamily.text = t;
    if (d.text('notes_internal') case final t?) _internal.text = t;
    if (d.text('absence_note') case final t?) _absence.text = t;
    final items = d.items(_items);
    _items
      ..clear()
      ..addAll(items);
    for (final MapEntry(:key, :value) in d.answers.entries) {
      if (_typed[key] case final typed?) {
        typed.text = '$value';
      } else {
        _answers[key] = value;
      }
    }
    if (d.homework case final h? when !_homeworkLocked) {
      _give = true;
      _hwTitle.text = h.title;
      _hwDetails.text = h.instructions ?? '';
      _accepts = h.accepts;
      _dueDays = h.dueDays;
    }
    _heard = d;
    _marks = d.marks;
    _error = null;
    _round++;
  }

  /// The fields as the teacher reads them: the form's own labels, a lesson by its title, a question by its words.
  List<String> _names(List<String> fields) {
    final l = context.l10n;
    final questions = {for (final q in f.template?.questions ?? const <ReportQuestion>[]) q.key: q.label};
    final lessons = {for (final o in f.lessonOptions) o.id: o.label.split(' › ').last, for (final i in _items) i.nodeId: i.title};
    return {
      for (final field in fields)
        switch (field.split('.')) {
          ['items', final id, ...] => lessons[id] ?? l.workedOn,
          ['answers', final key, ...] => questions[key] ?? l.questionsTitle,
          ['items'] => l.workedOn,
          ['engagement'] => l.engagement,
          ['overall'] => l.overall,
          ['notes_for_student'] => l.notesForFamily,
          ['notes_internal'] => l.notesInternal,
          ['absence_note'] => l.absenceNote,
          ['homework', ...] => l.homeworkField,
          _ => field,
        },
    }.toList();
  }

  Widget _voiceCard() {
    final l = context.l10n;
    final d = _heard;
    final and = Localizations.localeOf(context).languageCode == 'ar' ? '، ' : ', ';
    return _Section(
      title: l.voiceTitle,
      children: [
        Text(l.voiceHint, style: context.type.caption),
        const Gap(Space.m),
        if (_drafting)
          Row(
            children: [
              const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2)),
              const Gap(Space.m),
              Expanded(child: Text(l.voiceWorking, style: context.type.body)),
            ],
          )
        else
          VoiceRecorder(
            key: ValueKey(_round),
            limit: const Duration(minutes: 2),
            label: d == null ? l.voiceRecord : l.recordAgain,
            onRecorded: _draftFrom,
          ),
        const Gap(Space.s),
        Text('${l.voiceLimit} · ${l.voiceKept}', style: context.type.caption),
        if (_draftError != null) ...[const Gap(Space.s), Note(_draftError!, tone: Tone.danger, icon: Icons.error_outline_rounded)],
        if (d != null) ...[
          const Gap(Space.s),
          Note(
            l.voiceDone(d.filled.length),
            tone: d.filled.isEmpty ? Tone.attention : Tone.brand,
            icon: d.filled.isEmpty ? Icons.info_outline_rounded : Icons.check_circle_outline_rounded,
          ),
          if (d.unsure.isNotEmpty) ...[
            const Gap(Space.m),
            Text(l.voiceUnsure, style: context.type.label),
            const Gap(Space.xs),
            Wrap(spacing: 6, runSpacing: 6, children: [for (final n in _names(d.unsure)) Pill(n, tone: Tone.attention)]),
          ],
          if (d.left.isNotEmpty) ...[const Gap(Space.s), Text(l.voiceLeft(_names(d.left).join(and)), style: context.type.caption)],
          ExpansionTile(
            tilePadding: EdgeInsets.zero,
            childrenPadding: const EdgeInsets.only(bottom: Space.s),
            expandedAlignment: AlignmentDirectional.centerStart,
            title: Text(l.voiceHeard, style: context.type.label),
            children: [Text(d.heard, style: context.type.body)],
          ),
        ],
      ],
    );
  }

  /// A field the draft filled, marked above it.
  Widget _markOf(String field) => switch (_marks[field]) {
    final m? => Align(alignment: AlignmentDirectional.centerEnd, child: _Mark(m)),
    null => const SizedBox.shrink(),
  };

  /// Keeps the form as it stands on this phone, and goes back to the list.
  Future<void> _keep() async {
    final l = context.l10n;
    await ReportDrafts().save(widget.account, f.summary.id, {
      'items': [for (final i in _items) i.toDraft()],
      'engagement': _engagement,
      'overall': _overall,
      'notes_for_student': _forFamily.text,
      'notes_internal': _internal.text,
      'absence_note': _absence.text,
      'give': _give,
      'hw_title': _hwTitle.text,
      'hw_details': _hwDetails.text,
      'accepts': _accepts,
      'due_days': _dueDays,
      'answers': {..._answers, for (final e in _typed.entries) e.key: e.value.text},
    });
    if (!mounted) return;
    showSnack(context, l.reportDraftSaved);
    Navigator.of(context).pop(false);
  }

  String? _err(String field) => _fieldErrors[field]?.first;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final s = f.summary;
    final locked = f.locked;
    final draft = widget.draft;
    final list = ListView(
      padding: pagePadding,
      children: staggered(context, [
        Wrap(
          spacing: 8,
          runSpacing: 6,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            StatusChip(s.statusLabel, tone: s.status == 'overdue' ? Tone.danger : (s.open ? Tone.attention : Tone.brand)),
            if (s.open && s.dueAt != null) Text(l.reportDueBy(s.dueAt!.when(context)), style: context.type.caption),
          ],
        ),
        if (draft != null) ...[
          const Gap(Space.m),
          Note(
            l.draftFrom(waitText(context, DateTime.now().toUtc().difference(draft.savedAt))),
            icon: Icons.edit_note_rounded,
            action: TextButton(onPressed: widget.onDiscard, child: Text(l.discardDraft)),
          ),
        ],
        if (locked) ...[
          const Gap(Space.m),
          Note(
            [l.reportLocked, if ((f.reviewNote ?? '').isNotEmpty) f.reviewNote!].join('\n'),
            tone: Tone.brand,
            icon: Icons.lock_outline_rounded,
          ),
        ],
        if (f.voiceDraft && !locked) _voiceCard(),
        if (_absent) ...[
          const Gap(Space.m),
          _Section(
            title: l.absenceNote,
            children: [
              Text(l.absenceHint, style: context.type.caption),
              const Gap(Space.m),
              _markOf('absence_note'),
              TextField(
                controller: _absence,
                enabled: !locked,
                minLines: 3,
                maxLines: 8,
                maxLength: 1000,
                decoration: InputDecoration(labelText: l.absenceNote, errorText: _err('absence_note')),
              ),
            ],
          ),
        ] else ...[
          const Gap(Space.m),
          _Section(
            title: l.workedOn,
            children: [
              if (_items.isNotEmpty) Text(l.planHint, style: context.type.caption),
              for (final (i, item) in _items.indexed) ...[if (i > 0) const Divider(height: Space.xl), _itemCard(item, i, locked)],
              if (!locked && _items.length < 10 && f.lessonOptions.length > _items.length)
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: TextButton.icon(onPressed: _addLesson, icon: const Icon(Icons.add_rounded), label: Text(l.addLesson)),
                ),
              if (_err('items') != null) Text(_err('items')!, style: TextStyle(color: c.dangerInk)),
            ],
          ),
          _Section(
            title: l.howItWent,
            children: [
              Row(
                children: [
                  Text(l.engagement, style: context.type.label),
                  if (_marks['engagement'] case final m?) ...[const Gap(Space.s), _Mark(m)],
                ],
              ),
              IgnorePointer(
                ignoring: locked,
                child: StarPicker(value: _engagement, label: l.engagement, onChanged: (v) => setState(() => _engagement = v)),
              ),
              const Gap(Space.s),
              Row(
                children: [
                  Text(l.overall, style: context.type.label),
                  if (_marks['overall'] case final m?) ...[const Gap(Space.s), _Mark(m)],
                ],
              ),
              IgnorePointer(
                ignoring: locked,
                child: StarPicker(value: _overall, label: l.overall, onChanged: (v) => setState(() => _overall = v)),
              ),
            ],
          ),
          if (f.template case final t? when t.questions.isNotEmpty) _questionsCard(t, locked),
        ],
        _Section(
          title: l.talkSection,
          children: [
            if (!_absent) _markOf('notes_for_student'),
            if (!_absent)
              TextField(
                controller: _forFamily,
                enabled: !locked,
                minLines: 2,
                maxLines: 6,
                maxLength: 3000,
                decoration: InputDecoration(labelText: l.notesForFamily, errorText: _err('notes_for_student')),
              ),
            _markOf('notes_internal'),
            TextField(
              controller: _internal,
              enabled: !locked,
              minLines: 2,
              maxLines: 6,
              maxLength: 3000,
              decoration: InputDecoration(labelText: l.notesInternal, helperText: l.notesInternalHint, errorText: _err('notes_internal')),
            ),
          ],
        ),
        if (!_absent) _homeworkCard(locked),
        if (_error != null) ...[const Gap(Space.m), Note(_error!, tone: Tone.danger, icon: Icons.error_outline_rounded)],
      ]),
    );
    if (locked) return list;
    return Column(
      children: [
        Expanded(child: list),
        // Sending (and keeping a draft, while it is still to write) stays at the foot, above the keyboard.
        DecoratedBox(
          decoration: BoxDecoration(
            color: c.card,
            border: Border(top: BorderSide(color: c.line)),
          ),
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(Space.l, Space.m, Space.l, Space.m),
              child: Row(
                children: [
                  if (s.open) ...[Expanded(child: SecondaryAction(l.keepDraft, onPressed: _busy ? null : _keep)), const Gap(Space.m)],
                  Expanded(
                    flex: 2,
                    child: PrimaryAction(
                      s.open ? l.sendReport : l.updateReport,
                      icon: Icons.send_rounded,
                      busy: _busy,
                      onPressed: _busy ? null : _send,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _itemCard(ReportItem item, int index, bool locked) {
    final l = context.l10n;
    final c = context.colors;
    final noteOpen = _notes.contains(item.nodeId) || (item.note ?? '').isNotEmpty;
    return KeyedSubtree(
      key: ValueKey('${item.nodeId}#$_round'),
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
              if (_marks['items.${item.nodeId}'] case final m?) ...[const Gap(Space.xs), _Mark(m)],
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

  /// The subject's own questions, each by the kind of answer it takes.
  Widget _questionsCard(ReportTemplate t, bool locked) {
    final l = context.l10n;
    final c = context.colors;
    Widget title(ReportQuestion q) => Wrap(
      spacing: 6,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Text(q.label, style: context.text.titleSmall),
        if (!q.required) Text(l.optional, style: context.text.bodySmall),
        if (!q.family) Pill(l.academyOnly, tone: Tone.neutral),
        if (_marks['answers.${q.key}'] case final m?) _Mark(m),
      ],
    );
    Widget chips(ReportQuestion q, List<(Object, String)> options) => Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        for (final (value, label) in options)
          ChoiceChip(
            label: Text(label),
            selected: _answers[q.key] == value,
            onSelected: locked
                ? null
                : (on) {
                    Juice.tap();
                    setState(() => _answers[q.key] = on || q.required ? value : null);
                  },
          ),
      ],
    );
    return _Section(
      title: l.questionsTitle,
      children: [
        Text(l.questionsHint, style: context.text.bodySmall),
        for (final q in t.questions) ...[
          const SizedBox(height: 14),
          if (q.type == 'number' || q.type == 'text') ...[
            _markOf('answers.${q.key}'),
            TextField(
              controller: _typed[q.key],
              enabled: !locked,
              keyboardType: q.type == 'number' ? const TextInputType.numberWithOptions(decimal: true) : TextInputType.text,
              inputFormatters: q.type == 'number' ? const [LatinDigits(decimal: true)] : null,
              maxLength: q.type == 'text' ? 500 : null,
              decoration: InputDecoration(
                labelText: q.required ? q.label : '${q.label} ${l.optional}',
                suffixText: q.unit,
                helperText: q.family ? null : l.academyOnly,
                errorText: _err('answers.${q.key}'),
              ),
            ),
          ] else ...[
            title(q),
            const SizedBox(height: 6),
            switch (q.type) {
              'rating' => IgnorePointer(
                ignoring: locked,
                child: StarPicker(
                  value: (_answers[q.key] as num?)?.toInt(),
                  label: q.label,
                  clearable: !q.required,
                  onChanged: (v) => setState(() => _answers[q.key] = v),
                ),
              ),
              'yes_no' => chips(q, [(true, l.answerYes), (false, l.answerNo)]),
              _ => chips(q, [for (final o in q.options) (o.key, o.label)]),
            },
            if (_err('answers.${q.key}') case final e?) Text(e, style: TextStyle(color: c.dangerInk)),
          ],
        ],
      ],
    );
  }

  Widget _homeworkCard(bool locked) {
    final l = context.l10n;
    final hw = f.homework;
    if (_homeworkLocked) {
      return _Section(
        title: l.homeworkField,
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
      );
    }
    return _Section(
      title: l.homeworkField,
      children: [
        _markOf('homework'),
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
    );
  }
}

/// Filled by the voice note's draft: suggested, or worth a second look.
class _Mark extends StatelessWidget {
  const _Mark(this.mark);

  final DraftMark mark;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final check = mark == DraftMark.check;
    return Pill(check ? l.checkThis : l.suggested, tone: check ? Tone.attention : Tone.brand, icon: Icons.mic_rounded);
  }
}

/// One part of the report on its own card, under its title.
class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: Space.m),
    child: LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(header: true, child: Text(title, style: context.type.cardTitle)),
          const Gap(Space.s),
          ...children,
        ],
      ),
    ),
  );
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
