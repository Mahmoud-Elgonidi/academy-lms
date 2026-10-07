import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../../shared/widgets/star_picker.dart';
import '../../../homework/domain/homework.dart';
import '../../../homework/presentation/widgets/submission_view.dart';
import '../../data/teaching_repository.dart';

/// Homework handed in, to mark: read the words, play the recording, see the
/// picture; give a grade and a word. The marked ones stay one tap away.
class MarkingPage extends StatefulWidget {
  const MarkingPage({super.key});

  @override
  State<MarkingPage> createState() => _MarkingPageState();
}

class _MarkingPageState extends State<MarkingPage> {
  String _status = 'submitted';

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.markTitle)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 4, 18, 8),
            child: SizedBox(
              width: double.infinity,
              child: SegmentedButton<String>(
                showSelectedIcon: false,
                segments: [
                  ButtonSegment(value: 'submitted', label: Text(l.markWaiting)),
                  ButtonSegment(value: 'reviewed', label: Text(l.markDone)),
                ],
                selected: {_status},
                onSelectionChanged: (v) => setState(() => _status = v.first),
              ),
            ),
          ),
          Expanded(
            child: BlocProvider(
              key: ValueKey(_status),
              create: (c) => LoadCubit<List<Homework>>(() => c.read<TeachingRepository>().homeworkToMark(status: _status))..load(),
              child: LoadView<List<Homework>>(
                builder: (context, list) => ListView(
                  padding: pagePadding,
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: staggered(context, [
                    if (list.isEmpty) EmptyNote(l.noHomeworkToMark, icon: Icons.task_alt_rounded),
                    for (final h in list) ...[_MarkCard(key: ValueKey(h.id), homework: h), const SizedBox(height: 12)],
                  ]),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MarkCard extends StatefulWidget {
  const _MarkCard({required this.homework, super.key});

  final Homework homework;

  @override
  State<_MarkCard> createState() => _MarkCardState();
}

class _MarkCardState extends State<_MarkCard> {
  late int? _grade = widget.homework.grade;
  late final _feedback = TextEditingController(text: widget.homework.feedback);
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _feedback.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final l = context.l10n;
    try {
      await context.read<TeachingRepository>().mark(widget.homework.id, grade: _grade, feedback: _feedback.text.trim());
      if (!mounted) return;
      Juice.reward();
      showSnack(context, l.marked);
      await context.read<LoadCubit<List<Homework>>>().load();
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = context.problemText(p);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final h = widget.homework;
    final marked = h.status == 'reviewed';
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(h.student, style: context.text.titleSmall)),
              Pill(h.statusLabel, tone: marked ? Tone.brand : Tone.info),
            ],
          ),
          const SizedBox(height: 2),
          Text(h.title, style: context.text.titleMedium),
          if (h.dueAt != null) Text(l.dueBy(h.dueAt!.when(context)), style: context.text.bodySmall),
          if ((h.instructions ?? '').isNotEmpty) ...[const SizedBox(height: 6), Text(h.instructions!, style: context.text.bodyMedium)],
          for (final s in h.submissions) ...[const SizedBox(height: 10), SubmissionView(submission: s, title: h.student)],
          const SizedBox(height: 12),
          if (marked)
            Note(
              [if (h.grade != null) l.grade(h.grade!), if ((h.feedback ?? '').isNotEmpty) h.feedback!].join(' · '),
              tone: Tone.brand,
              icon: Icons.rate_review_outlined,
            )
          else ...[
            Text(l.gradeLabel, style: context.text.labelMedium),
            StarPicker(value: _grade, clearable: true, label: l.gradeLabel, onChanged: (v) => setState(() => _grade = v)),
            const SizedBox(height: 8),
            TextField(
              controller: _feedback,
              minLines: 2,
              maxLines: 5,
              maxLength: 3000,
              decoration: InputDecoration(labelText: l.feedbackLabel, errorText: _error, errorMaxLines: 3),
            ),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(onPressed: _busy ? null : _save, icon: const Icon(Icons.check_rounded), label: Text(l.saveMark)),
            ),
          ],
        ],
      ),
    );
  }
}
