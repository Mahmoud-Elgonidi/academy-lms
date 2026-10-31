import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/lessons_repository.dart';
import '../../domain/lesson.dart';

Future<T?> _sheet<T>(BuildContext context, Widget child) => showActionSheet<T>(
  context,
  builder: (ctx) => RepositoryProvider.value(
    value: context.read<LessonsRepository>(),
    child: Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(ctx).bottom),
      child: child,
    ),
  ),
);

/// Asks first, telling what the academy's policy does with this
/// cancellation; returns true once cancelled. A group lesson is never
/// cancelled: "I won't attend" excuses the seat of [learnerId] (the learner
/// on screen) and the lesson goes on for the others; the sheet says so.
Future<bool> showCancelSheet(BuildContext context, Lesson lesson, {String? learner, String? learnerId}) async =>
    await _sheet<bool>(context, _CancelSheet(lesson, learner: learner, learnerId: lesson.isGroup ? learnerId : null)) ?? false;

/// Returns true once rated.
Future<bool> showRateSheet(BuildContext context, Lesson lesson) async => await _sheet<bool>(context, _RateSheet(lesson)) ?? false;

/// Disputes what [lesson] was recorded as (the family's or the teacher's
/// reasons, as the API offers them for this lesson); [send] posts it to the
/// side's own endpoint. Returns true once sent.
Future<bool> showDisputeSheet(
  BuildContext context,
  Lesson lesson, {
  required bool teacher,
  required Future<void> Function(String reason, String note) send,
  String? about,
}) async =>
    await showActionSheet<bool>(
      context,
      builder: (c) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(c).bottom),
        child: _DisputeSheet(lesson: lesson, teacher: teacher, send: send, about: about),
      ),
    ) ??
    false;

/// Turns down a substitute after asking; returns true once done.
Future<bool> refuseSubstitute(BuildContext context, Lesson lesson) async {
  final l = context.l10n;
  final sure = await confirmDestructive(
    context,
    title: l.refuseSubstituteTitle,
    body: l.refuseSubstituteBody,
    confirm: l.refuseSubstituteYes,
    keep: l.keepIt,
  );
  if (!sure || !context.mounted) return false;
  try {
    final days = await context.read<LessonsRepository>().refuseSubstitute(lesson.id);
    if (context.mounted) showSnack(context, l.refusedSubstitute(days));
    return true;
  } on ApiProblem catch (p) {
    if (context.mounted) showSnack(context, context.problemText(p));
    return false;
  }
}

class _SheetBody extends StatelessWidget {
  const _SheetBody({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.fromLTRB(Space.xl - 4, 0, Space.xl - 4, Space.xl),
    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: children),
  );
}

/// A sheet's top: what it is about in a quiet line, then its question.
class _SheetTitle extends StatelessWidget {
  const _SheetTitle({required this.line, required this.title, this.center = false});

  final String line;
  final String title;
  final bool center;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: center ? CrossAxisAlignment.center : CrossAxisAlignment.start,
    children: [
      Text(line, style: context.type.caption, textAlign: center ? TextAlign.center : null),
      Semantics(
        header: true,
        child: Text(title, style: context.type.title, textAlign: center ? TextAlign.center : null),
      ),
    ],
  );
}

class _CancelSheet extends StatefulWidget {
  const _CancelSheet(this.lesson, {this.learner, this.learnerId});

  final Lesson lesson;

  /// Whose seat a group lesson excuses (a guardian's learner on screen).
  final String? learnerId;

  /// The child it is for (a guardian's sheet), said under the question.
  final String? learner;

  @override
  State<_CancelSheet> createState() => _CancelSheetState();
}

class _CancelSheetState extends State<_CancelSheet> {
  late final Future<CancelPreview> _preview = context.read<LessonsRepository>().cancellation(widget.lesson.id, learnerId: widget.learnerId);
  final _note = TextEditingController();
  String? _reason;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _cancel() async {
    if (_reason == null) {
      setState(() => _error = context.l10n.pickReason);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<LessonsRepository>().cancel(widget.lesson.id, reason: _reason!, note: _note.text, learnerId: widget.learnerId);
      if (mounted) Navigator.pop(context, true);
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
    final lesson = widget.lesson;
    final locale = context.locale;
    return FutureBuilder<CancelPreview>(
      future: _preview,
      builder: (context, snap) {
        if (snap.hasError) {
          return _SheetBody(
            children: [
              Note(context.problemText(snap.error!), tone: Tone.danger, icon: Icons.error_outline_rounded),
              const SizedBox(height: 12),
              OutlinedButton(onPressed: () => Navigator.pop(context, false), child: Text(l.close)),
            ],
          );
        }
        final preview = snap.data;
        if (preview == null) return const SizedBox(height: 200, child: Center(child: CircularProgressIndicator.adaptive()));
        final c = context.colors;
        // A group lesson goes on for the others: only the learner's own seat is excused.
        final excuse = lesson.isGroup || preview.excuseOnly;
        return _SheetBody(
          children: [
            _SheetTitle(
              line: [lesson.withName.isEmpty ? lesson.subject : l.lessonWith(lesson.subject, lesson.withName), ?widget.learner].join(' · '),
              title: excuse ? l.skipTitle(lesson.startsAt.relativeDay(context)) : l.cancelTitle(lesson.startsAt.relativeDay(context)),
            ),
            const Gap(Space.xs),
            Text(
              '${lesson.startsAt.day(locale)} · ${lesson.startsAt.clock(locale)}–${lesson.endsAt.clock(locale)}',
              style: context.type.caption,
            ),
            if (excuse) ...[
              const Gap(Space.m),
              Note(l.skipGoesOn, key: const Key('cancel-group'), tone: Tone.info, icon: Icons.groups_rounded),
            ],
            const Gap(Space.l),
            // What the academy's policy does with this cancellation, before anything is sent.
            Container(
              key: const Key('cancel-outcome'),
              padding: const EdgeInsets.all(Space.m + 2),
              decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(context.shape.card - 2)),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const IconBadge(Icons.policy_outlined, size: 36),
                  const Gap(Space.m),
                  Expanded(
                    child: Text(preview.outcome, style: context.type.body.copyWith(color: c.brandDeep, height: 1.6)),
                  ),
                ],
              ),
            ),
            const Gap(Space.l),
            Text(l.reason, style: context.type.cardTitle),
            const Gap(Space.s),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final r in preview.reasons)
                  ChoiceChip(
                    label: Text(r.label),
                    selected: _reason == r.value,
                    onSelected: (_) => setState(() {
                      _reason = r.value;
                      _error = null;
                    }),
                  ),
              ],
            ),
            const Gap(Space.l),
            TextField(
              controller: _note,
              maxLines: 2,
              maxLength: 500,
              decoration: InputDecoration(labelText: '${l.noteForAcademy} ${l.optional}'),
            ),
            if (_error != null) ...[Note(_error!, tone: Tone.danger, icon: Icons.error_outline_rounded), const Gap(Space.m)],
            PrimaryAction(excuse ? l.skipYes : l.yesCancel, danger: !excuse, busy: _busy, onPressed: _cancel),
            const Gap(Space.s),
            SecondaryAction(l.keepIt, onPressed: _busy ? null : () => Navigator.pop(context, false)),
          ],
        );
      },
    );
  }
}

class _RateSheet extends StatefulWidget {
  const _RateSheet(this.lesson);

  final Lesson lesson;

  @override
  State<_RateSheet> createState() => _RateSheetState();
}

class _RateSheetState extends State<_RateSheet> {
  late final Future<Rating> _rating = context.read<LessonsRepository>().rating(widget.lesson.id).then((r) {
    _stars = r.stars ?? 0;
    _tags.addAll(r.tags);
    _comment.text = r.comment ?? '';
    return r;
  });
  final _comment = TextEditingController();
  final _tags = <String>{};
  int _stars = 0;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_stars == 0) return;
    setState(() => _busy = true);
    try {
      await context.read<LessonsRepository>().rate(widget.lesson.id, stars: _stars, tags: _tags.toList(), comment: _comment.text);
      if (mounted) Navigator.pop(context, true);
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
    return FutureBuilder<Rating>(
      future: _rating,
      builder: (context, snap) {
        final rating = snap.data;
        if (snap.hasError) {
          return _SheetBody(children: [Note(context.problemText(snap.error!), tone: Tone.danger)]);
        }
        if (rating == null) return const SizedBox(height: 200, child: Center(child: CircularProgressIndicator.adaptive()));
        if (!rating.canRate) {
          return _SheetBody(
            children: [
              Note(rating.why ?? l.problem, tone: Tone.info),
              const SizedBox(height: 12),
              OutlinedButton(onPressed: () => Navigator.pop(context, false), child: Text(l.close)),
            ],
          );
        }
        final choices = _stars >= 4 ? rating.good : (_stars > 0 ? rating.bad : const <({String value, String label})>[]);
        final lesson = widget.lesson;
        return _SheetBody(
          children: [
            _SheetTitle(
              center: true,
              line: [
                lesson.withName.isEmpty ? lesson.subject : l.lessonWith(lesson.subject, lesson.withName),
                lesson.startsAt.relativeDay(context),
              ].join(' · '),
              title: l.rateTitle,
            ),
            const Gap(Space.m),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var n = 1; n <= 5; n++)
                  IconButton(
                    tooltip: l.starsOf(n),
                    iconSize: 42,
                    onPressed: () => setState(() {
                      _stars = n;
                      _tags.clear();
                    }),
                    icon: Icon(n <= _stars ? Icons.star_rounded : Icons.star_outline_rounded, color: n <= _stars ? c.reward : c.faint),
                  ),
              ],
            ),
            SizedBox(
              height: 22,
              child: _stars == 0
                  ? null
                  : Text(
                      [l.stars1, l.stars2, l.stars3, l.stars4, l.stars5][_stars - 1],
                      textAlign: TextAlign.center,
                      style: context.type.body.copyWith(fontWeight: FontWeight.w700),
                    ),
            ),
            if (choices.isNotEmpty) ...[
              const Gap(Space.m),
              Text('${_stars >= 4 ? l.rateGood : l.rateBad} ${l.optional}', style: context.type.cardTitle),
              const Gap(Space.s),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final t in choices)
                    FilterChip(
                      label: Text(t.label),
                      selected: _tags.contains(t.value),
                      showCheckmark: false,
                      onSelected: (on) => setState(() => on ? _tags.add(t.value) : _tags.remove(t.value)),
                    ),
                ],
              ),
            ],
            const Gap(Space.l),
            TextField(
              controller: _comment,
              maxLines: 2,
              maxLength: 500,
              decoration: InputDecoration(labelText: '${l.rateComment} ${l.optional}'),
            ),
            Row(
              children: [
                Icon(Icons.lock_outline_rounded, size: 16, color: c.muted),
                const Gap(Space.xs + 2),
                Expanded(child: Text(l.rateAnonymous, style: context.type.caption)),
              ],
            ),
            if (_error != null) ...[const Gap(Space.m), Note(_error!, tone: Tone.danger)],
            const Gap(Space.l),
            PrimaryAction(l.sendRating, busy: _busy, onPressed: _stars == 0 ? null : _send),
            TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l.notNow)),
          ],
        );
      },
    );
  }
}

class _DisputeSheet extends StatefulWidget {
  const _DisputeSheet({required this.lesson, required this.teacher, required this.send, this.about});

  final Lesson lesson;
  final bool teacher;
  final Future<void> Function(String reason, String note) send;

  /// Whose seat it is about, in a group lesson (the student's name).
  final String? about;

  @override
  State<_DisputeSheet> createState() => _DisputeSheetState();
}

class _DisputeSheetState extends State<_DisputeSheet> {
  final _note = TextEditingController();
  String? _reason;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_reason == null || _note.text.trim().isEmpty) {
      setState(() => _error = context.l10n.disputeFillIn);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.send(_reason!, _note.text);
      if (mounted) Navigator.pop(context, true);
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
    final lesson = widget.lesson;
    final locale = context.locale;
    return _SheetBody(
      children: [
        _SheetTitle(
          line: [
            '${lesson.startsAt.day(locale)} · ${lesson.startsAt.clock(locale)}–${lesson.endsAt.clock(locale)} · ${lesson.subject}',
            if (widget.about case final who?) l.disputeAbout(who),
          ].join(' · '),
          title: l.disputeTitle,
        ),
        const Gap(Space.l),
        Note(
          widget.teacher ? l.disputeIntroTeacher(lesson.statusLabel) : l.disputeIntroFamily(lesson.statusLabel),
          tone: Tone.brand,
          icon: Icons.gavel_rounded,
        ),
        const Gap(Space.l),
        Text(l.disputeWhat, style: context.type.cardTitle),
        const Gap(Space.s),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final r in lesson.disputeReasons)
              ChoiceChip(
                label: Text(r.label),
                selected: _reason == r.key,
                onSelected: (_) => setState(() {
                  _reason = r.key;
                  _error = null;
                }),
              ),
          ],
        ),
        const Gap(Space.l),
        TextField(
          controller: _note,
          maxLines: 3,
          maxLength: 1000,
          decoration: InputDecoration(labelText: l.disputeNote, helperText: l.disputeNoteHint),
        ),
        if (_error != null) ...[Note(_error!, tone: Tone.danger, icon: Icons.error_outline_rounded), const Gap(Space.m)],
        PrimaryAction(l.disputeSend, busy: _busy, onPressed: _send),
        const Gap(Space.s),
        SecondaryAction(l.close, onPressed: _busy ? null : () => Navigator.pop(context, false)),
      ],
    );
  }
}
