import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../classroom/presentation/join.dart';
import '../../../schedule/domain/lesson.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/minute_tick.dart';

/// The next lesson, the one surface in the brand's colour: when, with
/// whom, how long until it starts, and its one action: the join button once
/// the room opens, before that the time it opens. The countdown is drawn
/// again each minute; the join state comes from the screen's reload at the
/// room's opening (`Lesson.clockMoments`).
class NextLessonCard extends StatelessWidget {
  const NextLessonCard({required this.lesson, super.key});

  final Lesson? lesson;

  @override
  Widget build(BuildContext context) => MinuteTick(builder: _card);

  Widget _card(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final lesson = this.lesson;
    final radius = BorderRadius.circular(context.shape.card + 6);
    if (lesson == null) {
      return Container(
        padding: const EdgeInsets.all(Space.l + 2),
        decoration: BoxDecoration(
          color: c.card,
          borderRadius: radius,
          border: Border.all(color: c.line),
        ),
        child: Row(
          children: [
            const IconBadge(Icons.event_available_rounded, tone: Tone.neutral, size: 40),
            const Gap(Space.m),
            Expanded(
              child: Text(l.noNextLesson, style: context.type.body.copyWith(color: c.muted)),
            ),
          ],
        ),
      );
    }
    final opens = lesson.joinOpensAt;
    final starts = lesson.startsAt.fromNow();
    final status = lesson.status == 'in_progress' && lesson.joinOpen
        ? l.lessonOn
        : lesson.joinOpen
        ? l.roomOpen
        : (!starts.isNegative && starts.inHours < 24 ? l.startsIn(waitText(context, starts)) : null);
    const ink = Colors.white;
    return Container(
      key: const Key('next-lesson'),
      padding: const EdgeInsets.fromLTRB(Space.l + 4, Space.l + 2, Space.l + 4, Space.l + 4),
      decoration: BoxDecoration(
        borderRadius: radius,
        gradient: LinearGradient(
          colors: [c.brand, Color.lerp(c.brand, Colors.black, 0.18)!],
          begin: AlignmentDirectional.topStart,
          end: AlignmentDirectional.bottomEnd,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // The room's state takes what is left of the line and wraps on a small phone or with large text.
          Row(
            children: [
              Text(
                l.nextLesson,
                style: TextStyle(color: ink.withValues(alpha: 0.88), fontWeight: FontWeight.w700, fontSize: 14),
              ),
              const Gap(Space.s),
              if (status != null)
                Expanded(
                  child: Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(color: ink.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(999)),
                      child: Text(
                        status,
                        style: const TextStyle(color: ink, fontSize: 12, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
                ),
            ],
          ),
          const Gap(Space.m),
          Text(
            lesson.startsAt.when(context),
            style: TextStyle(fontFamily: context.shape.headingFont, color: ink, fontSize: 26, fontWeight: FontWeight.w800, height: 1.25),
          ),
          const Gap(Space.xs),
          Text(
            '${l.lessonWith(lesson.subject, lesson.withName)} · ${l.minutes(lesson.minutes)}',
            style: TextStyle(color: ink.withValues(alpha: 0.9), fontSize: 15),
          ),
          if (lesson.unfunded) ...[
            const Gap(Space.s),
            Row(
              children: [
                Icon(Icons.error_outline_rounded, size: 16, color: ink.withValues(alpha: 0.95)),
                const Gap(Space.xs),
                Text(
                  l.unfunded,
                  style: TextStyle(color: ink.withValues(alpha: 0.95), fontSize: 13, fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ],
          const Gap(Space.l),
          SizedBox(
            width: double.infinity,
            height: 52,
            child: lesson.joinOpen || opens == null
                ? FilledButton.icon(
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: c.brandDeep,
                      disabledBackgroundColor: Colors.white.withValues(alpha: 0.25),
                      disabledForegroundColor: Colors.white.withValues(alpha: 0.85),
                    ),
                    onPressed: lesson.joinOpen ? () => joinLesson(context, lesson.id) : null,
                    icon: const Icon(Icons.videocam_rounded),
                    // Bold on the label itself: a button's textStyle would drop the look's font.
                    label: Text(l.joinLesson, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                  ).shimmerIf(context, lesson.joinOpen, color: c.brandSoft)
                : OutlinedButton.icon(
                    key: const Key('room-opens'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: ink,
                      disabledForegroundColor: ink,
                      side: BorderSide(color: ink.withValues(alpha: 0.55)),
                    ),
                    onPressed: null,
                    icon: const Icon(Icons.schedule_rounded),
                    label: Text(
                      l.roomOpensAt(opens.clock(context.locale)),
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
