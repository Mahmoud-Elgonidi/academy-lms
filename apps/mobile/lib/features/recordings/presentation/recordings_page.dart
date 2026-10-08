import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../data/recordings_repository.dart';
import '../domain/recordings.dart';

/// Lesson recordings (the same as in /my, /teach and the portal): the consent
/// of the learner on screen, or the teacher's own, and the recordings to
/// watch when the academy shares them. A recording opens in the phone's
/// player through a signed address (the opening is logged). It follows
/// lessons and consents live.
class RecordingsPage extends StatelessWidget {
  const RecordingsPage({required this.teacher, super.key});

  /// The teacher's own page, else a family's (about the learner chosen).
  final bool teacher;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final learnerId = teacher ? null : context.learnerId();
    return Scaffold(
      appBar: AppBar(title: Text(teacher ? l.recordingsMine : l.recordingsTitle)),
      body: BlocProvider(
        key: ValueKey(learnerId),
        create: (c) => LoadCubit<RecordingsScreen>(
          () => c.read<RecordingsRepository>().screen(teacher: teacher, learnerId: learnerId),
          refreshOn: c.read<Live>().on({Live.lessons, Live.consents}),
        )..load(),
        child: LoadView<RecordingsScreen>(
          builder: (context, screen) {
            final consent = teacher
                ? screen.consents.people.where((p) => p.self).firstOrNull
                : screen.consents.people.where((p) => p.personId == learnerId).firstOrNull ??
                      screen.consents.people.where((p) => p.self).firstOrNull;
            return ListView(
              padding: pagePadding,
              physics: const AlwaysScrollableScrollPhysics(),
              children: staggered(context, [
                if (!teacher) const LearnerSwitcher(),
                if (screen.consents.off) ...[Note(l.recordingsOff, icon: Icons.videocam_off_outlined), const SizedBox(height: 14)],
                if (consent != null) ...[_ConsentCard(consent: consent), const SizedBox(height: 18)],
                if (screen.shelf.shared) ...[
                  Text(teacher ? l.recordingsTeacherList : l.recordingsOf(consent?.name ?? ''), style: context.text.titleMedium),
                  const SizedBox(height: 4),
                  Text(l.recordingLogged, style: context.text.bodySmall?.copyWith(color: context.colors.muted)),
                  const SizedBox(height: 10),
                  if (screen.shelf.items.isEmpty) EmptyNote(l.recordingsEmpty),
                  for (final r in screen.shelf.items) ...[_RecordingTile(recording: r, withStudent: teacher), const SizedBox(height: 10)],
                ] else if (teacher)
                  Note(l.recordingsNotShared, icon: Icons.info_outline_rounded),
              ]),
            );
          },
        ),
      ),
    );
  }
}

class _ConsentCard extends StatefulWidget {
  const _ConsentCard({required this.consent});

  final RecordingConsent consent;

  @override
  State<_ConsentCard> createState() => _ConsentCardState();
}

class _ConsentCardState extends State<_ConsentCard> {
  bool _ticked = false;
  bool _busy = false;

  Future<void> _run(Future<void> Function() step, String done) async {
    setState(() => _busy = true);
    try {
      await step();
      if (!mounted) return;
      showSnack(context, done);
      await context.read<LoadCubit<RecordingsScreen>>().load();
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _withdraw() async {
    final l = context.l10n;
    final sure = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        content: Text(l.recordingWithdrawConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: Text(l.back)),
          FilledButton(onPressed: () => Navigator.pop(d, true), child: Text(l.recordingWithdraw)),
        ],
      ),
    );
    if (sure != true || !mounted) return;
    await _run(() => context.read<RecordingsRepository>().withdraw(widget.consent.personId), l.recordingWithdrawn);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final consent = widget.consent;
    final repo = context.read<RecordingsRepository>();
    return LmsCard(
      color: !consent.agreed && consent.canChange ? c.attentionSoft : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(consent.text.title, style: context.text.titleMedium)),
              Pill(consent.agreed ? l.recordingAgreed : l.recordingNotAgreed, tone: consent.agreed ? Tone.brand : Tone.attention),
            ],
          ),
          const SizedBox(height: 8),
          if (consent.agreed) ...[
            if (consent.by != null) Text(consent.by!, style: context.text.bodyMedium?.copyWith(color: c.muted)),
            if (consent.canChange) ...[
              const SizedBox(height: 10),
              OutlinedButton(onPressed: _busy ? null : _withdraw, child: Text(l.recordingWithdraw)),
            ],
          ] else if (consent.canChange) ...[
            Text(consent.text.intro, style: context.text.bodyMedium),
            const SizedBox(height: 10),
            for (final part in consent.text.sections)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(part.title, style: context.text.titleSmall),
                    Text(part.body, style: context.text.bodySmall),
                  ],
                ),
              ),
            CheckboxListTile(
              key: Key('agree-recording-${consent.personId}'),
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              value: _ticked,
              onChanged: _busy ? null : (v) => setState(() => _ticked = v ?? false),
              title: Text(consent.text.agree, style: context.text.titleSmall),
            ),
            const SizedBox(height: 6),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: !_ticked || _busy
                    ? null
                    : () => _run(() => repo.agree(consent.personId, consent.text.version), l.recordingAgreedDone),
                child: Text(l.recordingAgree),
              ),
            ),
          ] else if (consent.decides != null)
            Text(consent.decides!, style: context.text.bodyMedium?.copyWith(color: c.muted)),
        ],
      ),
    );
  }
}

class _RecordingTile extends StatefulWidget {
  const _RecordingTile({required this.recording, required this.withStudent});

  final LessonRecording recording;
  final bool withStudent;

  @override
  State<_RecordingTile> createState() => _RecordingTileState();
}

class _RecordingTileState extends State<_RecordingTile> {
  bool _busy = false;

  Future<void> _watch() async {
    setState(() => _busy = true);
    try {
      final url = await context.read<RecordingsRepository>().watch(widget.recording.id);
      await launchUrl(Uri.parse(url), mode: LaunchMode.inAppBrowserView);
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final r = widget.recording;
    final details = [
      if (widget.withStudent && r.student != null) r.student!,
      r.teacher,
      r.audio ? l.recordingAudio : l.recordingVideo,
      r.length,
      if (r.expiresAt != null) l.recordingUntil(r.expiresAt!.label),
    ].join(' · ');
    return LmsCard(
      child: Row(
        children: [
          Icon(r.audio ? Icons.graphic_eq_rounded : Icons.videocam_outlined, color: context.colors.brand),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(r.at.label, style: context.text.titleSmall),
                Text(details, style: context.text.bodySmall?.copyWith(color: context.colors.muted)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          FilledButton.tonalIcon(
            key: Key('watch-${r.id}'),
            onPressed: _busy ? null : _watch,
            icon: const Icon(Icons.play_arrow_rounded),
            label: Text(l.recordingWatch),
          ),
        ],
      ),
    );
  }
}
