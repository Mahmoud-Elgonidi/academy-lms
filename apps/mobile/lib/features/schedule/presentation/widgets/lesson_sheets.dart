import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/lessons_repository.dart';
import '../../domain/lesson.dart';

Future<T?> _sheet<T>(BuildContext context, Widget child) => showModalBottomSheet<T>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  useRootNavigator: true,
  builder: (_) => RepositoryProvider.value(
    value: context.read<LessonsRepository>(),
    child: Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: child,
    ),
  ),
);

/// Asks first, telling what the academy's policy does with this
/// cancellation; returns true once cancelled.
Future<bool> showCancelSheet(BuildContext context, Lesson lesson) async => await _sheet<bool>(context, _CancelSheet(lesson)) ?? false;

/// Returns true once rated.
Future<bool> showRateSheet(BuildContext context, Lesson lesson) async => await _sheet<bool>(context, _RateSheet(lesson)) ?? false;

/// Turns down a substitute after asking; returns true once done.
Future<bool> refuseSubstitute(BuildContext context, Lesson lesson) async {
  final l = context.l10n;
  final sure = await showDialog<bool>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(l.refuseSubstituteTitle),
      content: Text(l.refuseSubstituteBody),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.keepIt)),
        FilledButton(
          style: FilledButton.styleFrom(minimumSize: const Size(64, 44)),
          onPressed: () => Navigator.pop(c, true),
          child: Text(l.refuseSubstituteYes),
        ),
      ],
    ),
  );
  if (sure != true || !context.mounted) return false;
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
    padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: children),
  );
}

class _CancelSheet extends StatefulWidget {
  const _CancelSheet(this.lesson);

  final Lesson lesson;

  @override
  State<_CancelSheet> createState() => _CancelSheetState();
}

class _CancelSheetState extends State<_CancelSheet> {
  late final Future<CancelPreview> _preview = context.read<LessonsRepository>().cancellation(widget.lesson.id);
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
      await context.read<LessonsRepository>().cancel(widget.lesson.id, reason: _reason!, note: _note.text);
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
        return _SheetBody(
          children: [
            Text(l.cancelTitle(lesson.startsAt.relativeDay(context)), style: context.text.headlineSmall),
            const SizedBox(height: 6),
            Text(
              '${lesson.startsAt.day(locale)} · ${lesson.startsAt.clock(locale)}–${lesson.endsAt.clock(locale)} · ${lesson.subject}',
              style: context.text.bodyMedium?.copyWith(color: context.colors.muted),
            ),
            const SizedBox(height: 14),
            Note(preview.outcome, tone: Tone.brand, icon: Icons.policy_outlined),
            const SizedBox(height: 18),
            Text(l.reason, style: context.text.titleSmall),
            const SizedBox(height: 8),
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
            const SizedBox(height: 16),
            TextField(
              controller: _note,
              maxLines: 2,
              maxLength: 500,
              decoration: InputDecoration(labelText: '${l.noteForAcademy} ${l.optional}'),
            ),
            if (_error != null) ...[Note(_error!, tone: Tone.danger, icon: Icons.error_outline_rounded), const SizedBox(height: 12)],
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(onPressed: _busy ? null : () => Navigator.pop(context, false), child: Text(l.keepIt)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    style: FilledButton.styleFrom(backgroundColor: context.colors.danger),
                    onPressed: _busy ? null : _cancel,
                    child: Text(l.yesCancel),
                  ),
                ),
              ],
            ),
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
        return _SheetBody(
          children: [
            Text(l.rateTitle, style: context.text.headlineSmall, textAlign: TextAlign.center),
            const SizedBox(height: 4),
            Text(
              '${widget.lesson.withName} · ${widget.lesson.subject} · ${widget.lesson.startsAt.relativeDay(context)}',
              textAlign: TextAlign.center,
              style: context.text.bodySmall,
            ),
            const SizedBox(height: 14),
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
            if (choices.isNotEmpty) ...[
              const SizedBox(height: 10),
              Text('${_stars >= 4 ? l.rateGood : l.rateBad} ${l.optional}', style: context.text.titleSmall),
              const SizedBox(height: 8),
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
            const SizedBox(height: 16),
            TextField(
              controller: _comment,
              maxLines: 2,
              maxLength: 500,
              decoration: InputDecoration(labelText: '${l.rateComment} ${l.optional}'),
            ),
            Text(l.rateAnonymous, style: context.text.bodySmall),
            if (_error != null) ...[const SizedBox(height: 10), Note(_error!, tone: Tone.danger)],
            const SizedBox(height: 14),
            FilledButton(onPressed: _stars == 0 || _busy ? null : _send, child: Text(l.sendRating)),
            TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l.notNow)),
          ],
        );
      },
    );
  }
}
