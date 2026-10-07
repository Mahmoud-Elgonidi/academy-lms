import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/stage.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/homework_repository.dart';
import '../../domain/homework.dart';

/// The student's homework: open ones first, each handed in from here when
/// it takes text; marked ones show the teacher's grade and words.
class HomeworkPage extends StatelessWidget {
  const HomeworkPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final kids = context.shape.stage == Stage.kids;
    return BlocProvider(
      create: (c) => LoadCubit<List<Homework>>(() => c.read<HomeworkRepository>().list())..load(),
      child: Scaffold(
        appBar: AppBar(title: Text(kids ? l.navMyHomework : l.homeworkTitle)),
        body: LoadView<List<Homework>>(
          builder: (context, list) => ListView(
            padding: pagePadding,
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              if (kids) ...[Note(l.kidsHomeworkStar, tone: Tone.reward, icon: Icons.star_rounded), const SizedBox(height: 12)],
              if (list.isEmpty) EmptyNote(l.noHomework, icon: Icons.assignment_turned_in_outlined),
              for (final h in list) ...[_HomeworkCard(homework: h), const SizedBox(height: 12)],
            ],
          ),
        ),
      ),
    );
  }
}

class _HomeworkCard extends StatefulWidget {
  const _HomeworkCard({required this.homework});

  final Homework homework;

  @override
  State<_HomeworkCard> createState() => _HomeworkCardState();
}

class _HomeworkCardState extends State<_HomeworkCard> {
  final _answer = TextEditingController();
  bool _writing = false;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _answer.dispose();
    super.dispose();
  }

  Future<void> _handIn() async {
    if (_answer.text.trim().isEmpty) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<HomeworkRepository>().handIn(widget.homework.id, _answer.text.trim());
      if (!mounted) return;
      showSnack(context, context.l10n.handedIn);
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
    final c = context.colors;
    final h = widget.homework;
    final tone = switch (h.status) {
      'reviewed' => Tone.brand,
      'submitted' => Tone.info,
      'missed' => Tone.danger,
      _ => Tone.attention,
    };
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: Text(h.title, style: context.text.titleMedium)),
              const SizedBox(width: 8),
              Pill(h.statusLabel, tone: tone),
            ],
          ),
          const SizedBox(height: 4),
          Text([h.teacher, if (h.dueAt != null) l.dueBy(h.dueAt!.when(context))].join(' · '), style: context.text.bodySmall),
          if ((h.instructions ?? '').isNotEmpty) ...[const SizedBox(height: 8), Text(h.instructions!, style: context.text.bodyMedium)],
          for (final s in h.submissions) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: c.chip, borderRadius: BorderRadius.circular(context.shape.card - 6)),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.yourSubmission, style: context.text.labelMedium),
                  if ((s.body ?? '').isNotEmpty) Text(s.body!, style: context.text.bodyMedium),
                  if (s.file != null)
                    Row(
                      children: [
                        const Icon(Icons.attach_file_rounded, size: 16),
                        Flexible(child: Text(s.file!)),
                      ],
                    ),
                ],
              ),
            ),
          ],
          if (h.grade != null || (h.feedback ?? '').isNotEmpty) ...[
            const SizedBox(height: 10),
            Note(
              [if (h.grade != null) l.grade(h.grade!), if ((h.feedback ?? '').isNotEmpty) h.feedback!].join(' · '),
              tone: Tone.brand,
              icon: Icons.rate_review_outlined,
            ),
          ],
          if (h.open) ...[
            const SizedBox(height: 12),
            if (!h.takesText)
              Note(l.handInOnWeb, tone: Tone.info, icon: Icons.mic_none_rounded)
            else if (!_writing)
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () => setState(() => _writing = true),
                  icon: const Icon(Icons.edit_rounded),
                  label: Text(l.handIn),
                ),
              )
            else ...[
              TextField(
                controller: _answer,
                autofocus: true,
                minLines: 3,
                maxLines: 8,
                maxLength: 5000,
                decoration: InputDecoration(labelText: l.yourAnswer, errorText: _error, errorMaxLines: 3),
              ),
              SizedBox(
                width: double.infinity,
                child: FilledButton(onPressed: _busy ? null : _handIn, child: Text(l.send)),
              ),
            ],
          ],
        ],
      ),
    );
  }
}
