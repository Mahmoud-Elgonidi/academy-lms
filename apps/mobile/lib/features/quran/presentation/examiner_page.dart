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

/// The examiner's queue, from a teacher's More: the exams booked with them (soonest first), each opening to record
/// its result (mistakes by kind, a grade, words to the student, and for a fail the retake day), and the last results
/// they recorded.
class QuranExaminerPage extends StatelessWidget {
  const QuranExaminerPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.quranExaminerTitle)),
      body: BlocProvider(
        create: (c) => LoadCubit<ExaminerBoard>(c.read<QuranRepository>().examiner, refreshOn: c.read<Live>().on({Live.lessons}))..load(),
        child: LoadView<ExaminerBoard>(
          skeleton: const SkeletonList(count: 3),
          builder: (context, board) => quranList(context, [
            SectionHeader(l.quranExaminerBooked),
            if (board.booked.isEmpty) EmptyNote(l.quranExaminerNone, icon: Icons.event_available_outlined),
            for (final e in board.booked) _ExamRow(exam: e, onTap: () => _record(context, e, board)),
            if (board.recent.isNotEmpty) ...[SectionHeader(l.quranExaminerRecent), for (final e in board.recent) _ExamRow(exam: e)],
          ]),
        ),
      ),
    );
  }

  Future<void> _record(BuildContext context, QuranExam exam, ExaminerBoard board) async {
    final cubit = context.read<LoadCubit<ExaminerBoard>>();
    final saved = await showActionSheet<QuranExam>(
      context,
      title: context.l10n.quranExamRecord,
      builder: (_) => _ResultForm(exam: exam, board: board, repository: context.read<QuranRepository>()),
    );
    if (saved == null || !context.mounted) return;
    showSnack(context, context.l10n.quranExamSaved(saved.statusLabel));
    await cubit.load();
  }
}

class _ExamRow extends StatelessWidget {
  const _ExamRow({required this.exam, this.onTap});

  final QuranExam exam;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final r = exam.result;
    return LmsCard(
      key: Key('exam-${exam.id}'),
      onTap: onTap,
      child: Row(
        children: [
          IconBadge(Icons.fact_check_outlined, tone: exam.passed ? Tone.brand : (exam.failed ? Tone.attention : Tone.info)),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(exam.studentName ?? '', style: context.type.cardTitle),
                Text([exam.stopLabel, exam.programName].where((s) => s.isNotEmpty).join(' · '), style: context.type.body),
                if (exam.startsAt != null) Text(exam.startsAt!.label, style: context.type.caption),
              ],
            ),
          ),
          const Gap(Space.s),
          if (r != null)
            GradeView(grade: r.grade, label: r.gradeLabel)
          else if (onTap != null)
            Icon(Icons.chevron_right_rounded, color: context.colors.faint),
        ],
      ),
    );
  }
}

class _ResultForm extends StatefulWidget {
  const _ResultForm({required this.exam, required this.board, required this.repository});

  final QuranExam exam;
  final ExaminerBoard board;
  final QuranRepository repository;

  @override
  State<_ResultForm> createState() => _ResultFormState();
}

class _ResultFormState extends State<_ResultForm> {
  final _mistakes = <String, int>{};
  bool _counted = false;
  String? _grade;
  final _note = TextEditingController();
  DateTime? _retake;
  bool _busy = false;
  ApiProblem? _problem;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  static String _ymd(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _pickRetake() async {
    final today = DateUtils.dateOnly(DateTime.now());
    final picked = await showDatePicker(
      context: context,
      initialDate: _retake ?? today.add(const Duration(days: 7)),
      firstDate: today.add(const Duration(days: 1)),
      lastDate: today.add(const Duration(days: 180)),
    );
    if (picked != null) setState(() => _retake = picked);
  }

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _problem = null;
    });
    try {
      final exam = await widget.repository.recordExam(
        widget.exam.id,
        mistakes: _counted ? Map.of(_mistakes) : null,
        grade: _grade,
        note: _note.text.trim().isEmpty ? null : _note.text.trim(),
        retakeOn: _retake == null ? null : _ymd(_retake!),
      );
      if (mounted) Navigator.of(context).pop(exam);
    } on ApiProblem catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _problem = e;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final e = widget.exam;
    final general = _problem == null || _problem!.field('note') != null || _problem!.field('retake_on') != null ? null : _problem!.message;
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(Space.xl - 4, 0, Space.xl - 4, Space.xl + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text([e.studentName ?? '', e.stopLabel].where((s) => s.isNotEmpty).join(' · '), style: context.type.cardTitle),
          if (e.sessionId != null)
            MushafEntry(
              lessonId: e.sessionId!,
              builder: (context, label, open) => Padding(
                padding: const EdgeInsets.only(top: Space.s),
                child: SecondaryAction(label, icon: Icons.menu_book_rounded, onPressed: open),
              ),
            ),
          const Gap(Space.m),
          Text(l.quranMistakes, style: context.type.label),
          Text(l.quranMistakesHint, style: context.type.caption),
          for (final t in widget.board.mistakeTypes)
            MistakeCounter(
              kind: t.key,
              label: t.label,
              count: _mistakes[t.key] ?? 0,
              onChange: (by) => setState(() {
                _counted = true;
                _mistakes[t.key] = ((_mistakes[t.key] ?? 0) + by).clamp(0, 500);
              }),
            ),
          const Gap(Space.s),
          Text(l.quranGrade, style: context.type.label),
          const Gap(Space.xs),
          GradePicker(grades: widget.board.grades, value: _grade, onChanged: (g) => setState(() => _grade = g)),
          const Gap(Space.m),
          Note(l.quranExamFailHint, tone: Tone.info),
          const Gap(Space.s),
          TextField(
            key: const Key('exam-note'),
            controller: _note,
            maxLength: 1000,
            minLines: 2,
            maxLines: 4,
            decoration: InputDecoration(labelText: l.quranExamNote, errorText: _problem?.field('note')),
          ),
          ListTile(
            key: const Key('exam-retake'),
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.event_outlined, color: c.muted),
            title: Text(l.quranExamRetake, style: context.type.body),
            subtitle: Text(
              _problem?.field('retake_on') ?? (_retake == null ? l.quranExamRetakePick : quranDay(_ymd(_retake!), context.locale)),
              style: context.type.caption.copyWith(color: _problem?.field('retake_on') != null ? c.dangerInk : null),
            ),
            onTap: _pickRetake,
          ),
          if (general != null) ...[
            const Gap(Space.s),
            Text(
              general,
              key: const Key('exam-error'),
              style: context.type.body.copyWith(color: c.dangerInk),
            ),
          ],
          const Gap(Space.l),
          PrimaryAction(
            l.quranExamRecord,
            key: const Key('exam-save'),
            icon: Icons.check_rounded,
            busy: _busy,
            onPressed: _busy ? null : _save,
          ),
        ],
      ),
    );
  }
}
