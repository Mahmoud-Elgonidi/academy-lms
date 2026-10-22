import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../classroom/presentation/join.dart';
import '../../../mushaf/presentation/widgets/mushaf_entry.dart';
import '../../domain/lesson.dart';

/// What a lesson's own sheet can do now; each is null when it may not.
class LessonActions {
  const LessonActions({this.cancel, this.refuse, this.rate, this.dispute, this.report});

  final VoidCallback? cancel;
  final VoidCallback? refuse;
  final VoidCallback? rate;
  final VoidCallback? dispute;

  /// The teacher's report on it, still to write.
  final VoidCallback? report;
}

/// One lesson in a list: the time in its own column, whom it is with, its
/// state as a chip; a tap opens its sheet. A room open now joins from the
/// row itself.
class LessonRow extends StatelessWidget {
  const LessonRow({required this.lesson, required this.onTap, this.highlight = false, this.grouped = false, this.trailing, super.key});

  final Lesson lesson;
  final VoidCallback onTap;

  /// The next lesson in the list.
  final bool highlight;

  /// One of the rows on a day's card (a teacher's), rather than a card of its own.
  final bool grouped;

  /// What stands in place of the arrow (a teacher's Write).
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    final locale = context.locale;
    final x = lesson;
    final struck = x.cancelled;
    final row = InkWell(
      onTap: PressScale.withPress(context, onTap),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(Space.l, Space.m + 2, Space.m, Space.m + 2),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            IntrinsicHeight(
              child: Row(
                children: [
                  SizedBox(
                    width: 64,
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          x.startsAt.hourMinute(locale),
                          maxLines: 1,
                          style: context.type.number.copyWith(
                            fontSize: 20,
                            color: struck ? c.muted : c.ink,
                            decoration: struck ? TextDecoration.lineThrough : null,
                          ),
                        ),
                        // On a 24-hour clock there is no day's half: the minutes alone.
                        Text(
                          [x.startsAt.dayPeriod(locale), l.minutesShort(x.minutes)].where((p) => p.isNotEmpty).join(' · '),
                          style: context.type.caption,
                        ),
                      ],
                    ),
                  ),
                  VerticalDivider(width: Space.xl, thickness: 1, color: c.line),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          x.withName.isEmpty ? x.subject : l.lessonWith(x.subject, x.withName),
                          style: context.type.cardTitle.copyWith(color: struck ? c.muted : null),
                        ),
                        const Gap(Space.xs + 2),
                        Wrap(
                          spacing: 6,
                          runSpacing: 4,
                          children: [
                            StatusChip(lessonStateLabel(context, x), tone: lessonTone(x)),
                            if (x.substituteFor != null) StatusChip(l.substituteChip(x.substituteFor!), tone: Tone.info),
                            if (x.typeLabel != null) StatusChip(x.typeLabel!, tone: Tone.info),
                            if (x.dispute case final d?)
                              StatusChip(l.disputeState(d.statusLabel), tone: d.open ? Tone.attention : Tone.neutral),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const Gap(Space.xs),
                  trailing ?? Icon(Icons.chevron_right_rounded, color: c.faint),
                ],
              ),
            ),
            if (x.joinOpen) ...[
              const Gap(Space.m),
              FilledButton.icon(
                onPressed: () => joinLesson(context, x.id),
                icon: const Icon(Icons.videocam_rounded),
                label: Text(l.joinLesson),
              ).shimmerIf(context, true, color: c.brandSoft),
            ],
          ],
        ),
      ),
    );
    if (grouped) return row;
    return Material(
      color: c.card,
      shape: RoundedRectangleBorder(
        borderRadius: context.shape.cardRadius,
        side: BorderSide(color: highlight ? c.brand.withValues(alpha: 0.6) : c.line, width: highlight ? 1.5 : 1),
      ),
      clipBehavior: Clip.antiAlias,
      child: row,
    );
  }
}

/// A lesson's state in the API's words, or "awaiting payment" for one still ahead that nothing pays for yet.
String lessonStateLabel(BuildContext context, Lesson x) => x.unfunded && x.ahead ? context.l10n.unfunded : x.statusLabel;

Tone lessonTone(Lesson x) => switch (x.status) {
  'in_progress' || 'completed' => Tone.brand,
  'cancelled' => Tone.neutral,
  'student_absent' || 'teacher_absent' => Tone.attention,
  'needs_review' => Tone.info,
  _ => x.unfunded ? Tone.attention : Tone.neutral,
};

/// A lesson's own sheet: when and with whom on the viewer's clock, its state,
/// a substitute's note with turning it down, the room (join, or when it
/// opens), its shared mushaf in a Quran lesson, and what else may be done
/// with it; cancelling last, in red.
Future<void> showLessonSheet(BuildContext context, Lesson lesson, {required LessonActions actions, String? learner, String? zone}) =>
    showActionSheet<void>(
      context,
      builder: (ctx) => _LessonSheet(host: context, lesson: lesson, actions: actions, learner: learner, zone: zone),
    );

class _LessonSheet extends StatelessWidget {
  const _LessonSheet({required this.host, required this.lesson, required this.actions, this.learner, this.zone});

  /// The list the sheet opened from: joining goes on from there once the sheet is closed.
  final BuildContext host;
  final Lesson lesson;
  final LessonActions actions;
  final String? learner;
  final String? zone;

  /// Closes this sheet, then runs [then] (its own sheet or question opens over the list).
  VoidCallback _after(BuildContext context, VoidCallback then) => () {
    Navigator.of(context).pop();
    then();
  };

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final x = lesson;
    final locale = context.locale;
    final opens = x.joinOpensAt;
    Widget row(String label, String value) => Padding(
      padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 72, child: Text(label, style: context.type.caption)),
          Expanded(
            child: Text(value, style: context.type.body.copyWith(fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(Space.xl - 4, 0, Space.xl - 4, Space.xl),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text([x.subject, ?learner].join(' · '), style: context.type.caption),
          Semantics(
            header: true,
            child: Text('${x.startsAt.relativeDay(context)} · ${x.startsAt.clock(locale)}', style: context.type.title),
          ),
          const Gap(Space.l),
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                row(
                  l.lessonTime,
                  l
                      .timeRange(
                        x.startsAt.hourMinute(locale),
                        x.endsAt.clock(locale),
                        zone == null ? '' : l.onClock(zoneName(zone, locale)),
                      )
                      .trim(),
                ),
                if (x.withName.isNotEmpty) ...[const Divider(height: 1), row(l.lessonWho, x.withName)],
                const Divider(height: 1),
                row(l.lessonState, [lessonStateLabel(context, x), ?x.typeLabel].join(' · ')),
              ],
            ),
          ),
          if (x.cancelledBecause != null) ...[const Gap(Space.m), Text(x.cancelledBecause!, style: context.type.caption)],
          if (x.substituteFor != null) ...[
            const Gap(Space.m),
            Note(
              l.substituteFor(x.substituteFor!),
              icon: Icons.swap_horiz_rounded,
              action: actions.refuse == null
                  ? null
                  : OutlinedButton(
                      style: OutlinedButton.styleFrom(minimumSize: const Size(64, 44), backgroundColor: c.card),
                      onPressed: _after(context, actions.refuse!),
                      child: Text(l.refuseSubstitute),
                    ),
            ),
          ],
          if (x.dispute case final d?) ...[
            const Gap(Space.m),
            Note(
              [l.disputeState(d.statusLabel), if (d.answer != null) l.disputeAnswer(d.answer!)].join('\n'),
              tone: d.open ? Tone.attention : (d.status == 'upheld' ? Tone.brand : Tone.neutral),
              icon: Icons.gavel_rounded,
            ),
          ],
          if (x.joinOpen) ...[
            const Gap(Space.l),
            PrimaryAction(l.joinLesson, icon: Icons.videocam_rounded, onPressed: _after(context, () => joinLesson(host, x.id))),
          ] else if (x.upcoming && opens != null) ...[
            const Gap(Space.l),
            Container(
              key: const Key('sheet-room-opens'),
              padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m + 2),
              decoration: BoxDecoration(color: c.chip, borderRadius: BorderRadius.circular(context.shape.field)),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.schedule_rounded, size: 20, color: c.muted),
                  const Gap(Space.s),
                  Flexible(
                    child: Text(
                      l.roomOpensOn('${opens.relativeDay(context)} ${opens.clock(locale)}'),
                      style: context.type.body.copyWith(fontWeight: FontWeight.w700, color: c.muted),
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (!x.cancelled)
            MushafEntry(
              lessonId: x.id,
              onOpen: _after(context, () => openMushaf(host, x.id)),
              builder: (context, label, open) => Padding(
                padding: const EdgeInsets.only(top: Space.s),
                child: SecondaryAction(label, icon: Icons.menu_book_rounded, onPressed: open),
              ),
            ),
          if (actions.report != null) ...[
            const Gap(Space.l),
            PrimaryAction(l.writeTheReport, icon: Icons.edit_note_rounded, onPressed: _after(context, actions.report!)),
          ],
          if (actions.rate != null) ...[
            const Gap(Space.l),
            SecondaryAction(l.rateLesson, icon: Icons.star_outline_rounded, onPressed: _after(context, actions.rate!)),
          ],
          if (actions.dispute != null) ...[
            const Gap(Space.s),
            SecondaryAction(l.disputeLesson, icon: Icons.gavel_rounded, onPressed: _after(context, actions.dispute!)),
          ],
          if (actions.cancel != null) ...[
            const Gap(Space.s),
            TextButton(
              key: const Key('sheet-cancel'),
              style: TextButton.styleFrom(foregroundColor: c.dangerInk, minimumSize: const Size.fromHeight(48)),
              onPressed: _after(context, actions.cancel!),
              child: Text(l.cancelLesson, style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
          ],
        ],
      ),
    );
  }
}
