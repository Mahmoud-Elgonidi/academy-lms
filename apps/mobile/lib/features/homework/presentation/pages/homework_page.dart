import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/router/areas.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';
import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/stage.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/motion/done_moment.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/homework_repository.dart';
import '../widgets/submission_view.dart';
import '../widgets/voice_recorder.dart';
import '../../domain/homework.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../core/realtime/live.dart';
import '../../../games/presentation/play/play_page.dart';
import '../../../games/presentation/game_words.dart';
import '../../../journey/data/journey_repository.dart';
import '../../../journey/presentation/journey_ui.dart';

/// The student's homework, each handed in from here in words, a voice
/// recording made in the app, or a picture or PDF, or by playing its round
/// in the games (practice homework, with how it went); marked ones show the
/// teacher's grade and words. A child's list is as it was; the others pick
/// between waiting, handed in and marked.
class HomeworkPage extends StatelessWidget {
  const HomeworkPage({super.key});

  @override
  Widget build(BuildContext context) {
    final kids = context.shape.stage == Stage.kids;
    return BlocProvider(
      create: (c) =>
          LoadCubit<List<Homework>>(() => c.read<HomeworkRepository>().list(), refreshOn: c.read<Live>().on({Live.homework, Live.practice}))
            ..load(),
      child: kids ? const _KidsHomework() : _Homework(tab: Area.of(context.watch<SessionCubit>().state) == Area.teen),
    );
  }
}

/// A child's homework, as it has been: the star note, then every card.
class _KidsHomework extends StatelessWidget {
  const _KidsHomework();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.navMyHomework)),
      body: LoadView<List<Homework>>(
        builder: (context, list) => ListView(
          padding: pagePadding,
          physics: const AlwaysScrollableScrollPhysics(),
          children: staggered(context, [
            ...[Note(l.kidsHomeworkStar, tone: Tone.reward, icon: Icons.star_rounded), const SizedBox(height: 12)],
            if (list.isEmpty) EmptyNote(l.noHomework, icon: Icons.assignment_turned_in_outlined),
            for (final h in list) ...[_HomeworkCard(homework: h), const SizedBox(height: 12)],
          ]),
        ),
      ),
    );
  }
}

enum _Show { open, sent, marked }

/// Homework for everyone else: waiting on you, handed in, marked (each with
/// its count), the cards of the one picked, and under what waits the last
/// one marked. Secondary school's tab has the header; elsewhere it is a
/// page over the tabs.
class _Homework extends StatefulWidget {
  const _Homework({required this.tab});

  final bool tab;

  @override
  State<_Homework> createState() => _HomeworkState();
}

class _HomeworkState extends State<_Homework> {
  _Show _show = _Show.open;

  static _Show _of(Homework h) => h.open ? _Show.open : (h.status == 'reviewed' ? _Show.marked : _Show.sent);

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final list = context.select<LoadCubit<List<Homework>>, List<Homework>?>((c) => c.state.data);
    final waiting = list?.where((h) => h.open).length ?? 0;
    final body = LoadView<List<Homework>>(
      skeleton: const SkeletonList(count: 3),
      builder: (context, list) {
        final count = {for (final s in _Show.values) s: list.where((h) => _of(h) == s).length};
        final shown = [
          for (final h in list)
            if (_of(h) == _show) h,
        ];
        final last = _show == _Show.open ? list.where((h) => h.status == 'reviewed').firstOrNull : null;
        String label(_Show s, String name) => count[s]! > 0 ? '$name · ${count[s]}' : name;
        return ListView(
          padding: pagePadding.copyWith(top: Space.m),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            SegmentedButton<_Show>(
              key: const Key('homework-show'),
              showSelectedIcon: false,
              segments: [
                ButtonSegment(value: _Show.open, label: Text(label(_Show.open, l.homeworkWaiting))),
                ButtonSegment(value: _Show.sent, label: Text(label(_Show.sent, l.homeworkSent))),
                ButtonSegment(value: _Show.marked, label: Text(label(_Show.marked, l.homeworkMarked))),
              ],
              selected: {_show},
              onSelectionChanged: (s) => setState(() => _show = s.single),
            ),
            const Gap(Space.l),
            ...staggered(context, [
              if (shown.isEmpty)
                EmptyState(
                  icon: Icons.assignment_turned_in_outlined,
                  title: switch (_show) {
                    _Show.open => l.homeworkNoneWaiting,
                    _Show.sent => l.homeworkNoneSent,
                    _Show.marked => l.homeworkNoneMarked,
                  },
                ),
              for (final h in shown) ...[_HomeworkCard(key: ValueKey(h.id), homework: h), const Gap(Space.m)],
              if (last != null) ...[
                SectionHeader(l.homeworkLastMarked),
                _MarkedRow(homework: last, onTap: () => setState(() => _show = _Show.marked)),
              ],
            ]),
          ],
        );
      },
    );
    if (!widget.tab) {
      return Scaffold(
        appBar: AppBar(title: Text(l.homeworkTitle)),
        body: body,
      );
    }
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(18, Space.m, 18, Space.xs),
              child: AppHeader(
                eyebrow: waiting == 0 ? null : l.homeworkWaitingOnYou(waiting),
                title: l.homeworkTitle,
                actions: const [NotificationBell(framed: true)],
              ),
            ),
            Expanded(child: body),
          ],
        ),
      ),
    );
  }
}

/// The last homework marked: its grade, what the teacher said and who; it
/// opens the marked ones.
class _MarkedRow extends StatelessWidget {
  const _MarkedRow({required this.homework, required this.onTap});

  final Homework homework;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final h = homework;
    return LmsCard(
      key: const Key('homework-last-marked'),
      onTap: onTap,
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(context.shape.card.clamp(8, 12))),
            child: h.grade == null
                ? Icon(Icons.check_rounded, color: c.brandDeep)
                : Text(
                    '${h.grade}/5',
                    textDirection: TextDirection.ltr,
                    style: context.type.label.copyWith(color: c.brandDeep, fontWeight: FontWeight.w800),
                  ),
          ),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(h.title, style: context.type.cardTitle, maxLines: 1, overflow: TextOverflow.ellipsis),
                Text(
                  [if ((h.feedback ?? '').isNotEmpty) '«${h.feedback}»', h.teacher].join(' · '),
                  style: context.type.caption,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: c.faint),
        ],
      ),
    );
  }
}

class _HomeworkCard extends StatefulWidget {
  const _HomeworkCard({required this.homework, super.key});

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
      if (kids) {
        Juice.reward();
        Celebration.show(context, burst: Burst.stars, from: Celebration.centreOf(_sendKey.currentContext ?? context));
        showSnack(context, l.handedIn);
      } else {
        DoneMoment.show(context, title: l.handedIn);
      }
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

  /// Its practice round: the game the teacher chose, else the best one on its lesson (from the journey).
  Future<void> _play() async {
    if (_busy) return;
    setState(() => _busy = true);
    final l = context.l10n;
    final h = widget.homework;
    final list = context.read<LoadCubit<List<Homework>>>();
    try {
      final map = await context.read<JourneyRepository>().map();
      if (!mounted) return;
      final mission = map.mission('homework:${h.id}');
      final game = mission == null ? null : map.gameFor(mission);
      final allowance = map.games?.allowance;
      if (game == null || map.plain) {
        showSnack(context, l.homeworkNoGame);
      } else if (allowance != null && !allowance.may) {
        showSnack(context, allowance.why(l, kids: context.shape.stage == Stage.kids));
      } else {
        await openRound(context, PlayRequest(gameId: game, homeworkId: h.id, look: map.roundLook));
        unawaited(list.load());
      }
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// The card for middle school, secondary school and adults: when it is
  /// due (or how it stands) and from whom, the title, what to do, then one
  /// way to hand it in (with a recording or a file a tap away) or its round.
  Widget _card(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final h = widget.homework;
    final chip = switch (h.status) {
      'reviewed' => StatusChip(h.grade == null ? h.statusLabel : l.grade(h.grade!), tone: Tone.brand),
      'submitted' => StatusChip(h.statusLabel, tone: Tone.info),
      'missed' => StatusChip(h.statusLabel, tone: Tone.danger),
      _ when h.dueAt != null => StatusChip(
        l.dueBy('${h.dueAt!.shortDay(context)} ${h.dueAt!.clock(context.locale)}'),
        tone: Tone.attention,
      ),
      _ => StatusChip(h.statusLabel, tone: Tone.attention),
    };
    final practice = h.practice;
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              chip,
              const Gap(Space.s),
              Expanded(
                child: Text(
                  [h.teacher, if (h.byPlaying) l.homeworkPracticeTitle].join(' · '),
                  style: context.type.caption,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const Gap(Space.s),
          Text(h.title, style: context.type.cardTitle.copyWith(fontSize: 17)),
          if ((h.instructions ?? '').isNotEmpty) ...[const Gap(Space.xs), Text(h.instructions!, style: context.type.body)],
          for (final s in h.submissions) ...[const Gap(Space.s), SubmissionView(submission: s)],
          if ((h.feedback ?? '').isNotEmpty) ...[const Gap(Space.s), Note(h.feedback!, tone: Tone.brand, icon: Icons.rate_review_outlined)],
          if (h.byPlaying || practice != null) ...[
            const Gap(Space.s),
            Text(
              practice != null && practice.done && practice.label.isNotEmpty
                  ? practice.result(l, kids: false)
                  : (h.byPlaying ? l.homeworkByPlaying : l.homeworkPracticeNotYet),
              style: context.type.caption.copyWith(color: practice?.done ?? false ? c.brandDeep : null),
            ),
            if (h.open) ...[
              const Gap(Space.m),
              SecondaryAction(
                practice?.done ?? false ? l.gamesAgain : l.homeworkStartRound,
                icon: Icons.play_arrow_rounded,
                onPressed: _busy ? null : _play,
              ),
            ],
          ],
          if (h.open && !h.byPlaying) ...[
            const Gap(Space.m),
            if (!_writing)
              Row(
                children: [
                  Expanded(
                    child: PrimaryAction(l.homeworkHandIn, key: Key('hand-in-${h.id}'), onPressed: () => setState(() => _writing = true)),
                  ),
                  if (h.takesAudio) ...[
                    const Gap(Space.s),
                    IconButton.outlined(
                      tooltip: l.recordVoice,
                      onPressed: () => setState(() => _writing = true),
                      icon: const Icon(Icons.mic_none_rounded),
                    ),
                  ],
                  if (h.takesFile) ...[
                    const Gap(Space.s),
                    IconButton.outlined(
                      tooltip: l.attachPicture,
                      onPressed: () async {
                        await _pick();
                        if (mounted && _picked != null) setState(() => _writing = true);
                      },
                      icon: const Icon(Icons.upload_rounded),
                    ),
                  ],
                ],
              )
            else
              ..._form(context, kids: false),
          ],
        ],
      ),
    );
  }

  /// The hand-in form once opened: a recording, a file, words, then send.
  List<Widget> _form(BuildContext context, {required bool kids}) {
    final l = context.l10n;
    final h = widget.homework;
    return [
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
    ];
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final h = widget.homework;
    final kids = context.shape.stage == Stage.kids;
    if (!kids) return _card(context);
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
          if (h.practice != null || h.byPlaying) ...[
            const SizedBox(height: 10),
            _Practice(practice: h.practice, open: h.open, byPlaying: h.byPlaying, busy: _busy, onPlay: _play),
          ],
          if (h.open && !h.byPlaying) ...[
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
            else
              ..._form(context, kids: kids),
          ],
        ],
      ),
    );
  }
}

/// A homework's practice in the games: how its best round went (or not
/// yet), and "play" while it is open.
class _Practice extends StatelessWidget {
  const _Practice({required this.practice, required this.open, required this.byPlaying, required this.busy, required this.onPlay});

  final HomeworkPractice? practice;
  final bool open;

  /// Handed in by playing only.
  final bool byPlaying;
  final bool busy;
  final VoidCallback onPlay;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final done = practice?.done ?? false;
    return Container(
      key: const Key('homework-practice'),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: done ? c.brandSoft : c.infoSoft, borderRadius: BorderRadius.circular(context.shape.card - 4)),
      child: Row(
        children: [
          Icon(done ? Icons.check_circle_rounded : Icons.sports_esports_outlined, color: done ? c.brandDeep : c.infoInk),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.homeworkPracticeTitle, style: context.text.titleSmall),
                Text(
                  done && (practice?.label.isNotEmpty ?? false)
                      ? practice!.label
                      : (byPlaying ? l.homeworkByPlaying : l.homeworkPracticeNotYet),
                  style: context.text.bodySmall,
                ),
              ],
            ),
          ),
          if (open) ...[
            const SizedBox(width: 8),
            FilledButton.icon(
              style: FilledButton.styleFrom(minimumSize: const Size(64, 40), padding: const EdgeInsets.symmetric(horizontal: 14)),
              onPressed: busy ? null : onPlay,
              icon: const Icon(Icons.play_arrow_rounded),
              label: Text(done ? l.gamesAgain : l.gamesPlay),
            ),
          ],
        ],
      ),
    );
  }
}
