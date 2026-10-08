import 'package:file_picker/file_picker.dart';
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
import '../widgets/submission_view.dart';
import '../widgets/voice_recorder.dart';
import '../../domain/homework.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../core/realtime/live.dart';

/// The student's homework: open ones first, each handed in from here in
/// words, a voice recording made in the app, or a picture or PDF; marked
/// ones show the teacher's grade and words.
class HomeworkPage extends StatelessWidget {
  const HomeworkPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final kids = context.shape.stage == Stage.kids;
    return BlocProvider(
      create: (c) =>
          LoadCubit<List<Homework>>(() => c.read<HomeworkRepository>().list(), refreshOn: c.read<Live>().on({Live.homework}))..load(),
      child: Scaffold(
        appBar: AppBar(title: Text(kids ? l.navMyHomework : l.homeworkTitle)),
        body: LoadView<List<Homework>>(
          builder: (context, list) => ListView(
            padding: pagePadding,
            physics: const AlwaysScrollableScrollPhysics(),
            children: staggered(context, [
              if (kids) ...[Note(l.kidsHomeworkStar, tone: Tone.reward, icon: Icons.star_rounded), const SizedBox(height: 12)],
              if (list.isEmpty) EmptyNote(l.noHomework, icon: Icons.assignment_turned_in_outlined),
              for (final h in list) ...[_HomeworkCard(homework: h), const SizedBox(height: 12)],
            ]),
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
  final _sendKey = GlobalKey();
  bool _writing = false;
  bool _busy = false;
  String? _error;
  HandInFile? _recording;
  HandInFile? _picked;

  static const _mimes = {
    'pdf': 'application/pdf',
    'jpg': 'image/jpeg',
    'jpeg': 'image/jpeg',
    'png': 'image/png',
    'webp': 'image/webp',
    'heic': 'image/heic',
  };

  @override
  void dispose() {
    _answer.dispose();
    super.dispose();
  }

  Future<void> _pick() async {
    final l = context.l10n;
    final picked = await FilePicker.pickFile(type: FileType.custom, allowedExtensions: _mimes.keys.toList());
    if (picked == null || !mounted) return;
    final bytes = await picked.xFile.readAsBytes();
    if (!mounted) return;
    if (bytes.length > 25 * 1024 * 1024) {
      showSnack(context, l.fileTooBig);
      return;
    }
    final ext = (picked.extension ?? '').toLowerCase();
    setState(() {
      _picked = HandInFile(bytes: bytes, name: picked.name, mime: _mimes[ext] ?? 'application/octet-stream');
      _recording = null;
    });
  }

  bool get _ready => _answer.text.trim().isNotEmpty || _recording != null || _picked != null;

  Future<void> _handIn() async {
    if (!_ready) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final l = context.l10n;
    final kids = context.shape.stage == Stage.kids;
    try {
      final text = _answer.text.trim();
      await context.read<HomeworkRepository>().handIn(widget.homework.id, body: text.isEmpty ? null : text, file: _recording ?? _picked);
      if (!mounted) return;
      Juice.reward();
      Celebration.show(context, burst: kids ? Burst.stars : Burst.confetti, from: Celebration.centreOf(_sendKey.currentContext ?? context));
      showSnack(context, l.handedIn);
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
    final kids = context.shape.stage == Stage.kids;
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
          for (final s in h.submissions) ...[const SizedBox(height: 10), SubmissionView(submission: s)],
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
            if (!_writing)
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () => setState(() => _writing = true),
                  icon: Icon(h.accepts == 'audio' ? Icons.mic_rounded : Icons.edit_rounded),
                  label: Text(l.handIn),
                ),
              )
            else ...[
              if (h.takesAudio && _picked == null) ...[
                VoiceRecorder(big: kids, onRecorded: (f) => setState(() => _recording = f)),
                const SizedBox(height: 10),
              ],
              if (h.takesFile && _recording == null) ...[
                if (_picked == null)
                  OutlinedButton.icon(onPressed: _pick, icon: const Icon(Icons.attach_file_rounded), label: Text(l.attachPicture))
                else
                  InputChip(
                    avatar: const Icon(Icons.insert_drive_file_outlined, size: 18),
                    label: Text(_picked!.name),
                    onDeleted: () => setState(() => _picked = null),
                    deleteButtonTooltipMessage: l.remove,
                  ),
                const SizedBox(height: 10),
              ],
              if (h.takesText)
                TextField(
                  controller: _answer,
                  autofocus: h.accepts == 'text',
                  minLines: 2,
                  maxLines: 8,
                  maxLength: 5000,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(labelText: h.accepts == 'text' ? l.yourAnswer : l.answerOptional),
                ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Note(_error!, tone: Tone.danger, icon: Icons.error_outline_rounded),
                ),
              SizedBox(
                key: _sendKey,
                width: double.infinity,
                child: FilledButton(onPressed: _busy || !_ready ? null : _handIn, child: Text(l.send)),
              ),
            ],
          ],
        ],
      ),
    );
  }
}
