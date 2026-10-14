import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../classroom/presentation/join.dart';
import '../../../schedule/domain/lesson.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/minute_tick.dart';

/// The next lesson in the brand's colour: when, with whom, how long until
/// the room opens, and the join button once it does. The countdown is
/// drawn again each minute; the join state comes from the screen's reload
/// at the room's opening (`Lesson.clockMoments`).
class NextLessonCard extends StatelessWidget {
  const NextLessonCard({required this.lesson, super.key});

  final Lesson? lesson;

  @override
  Widget build(BuildContext context) => MinuteTick(builder: _card);

  Widget _card(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final lesson = this.lesson;
    final kids = context.isKids;
    final radius = BorderRadius.circular(context.shape.card + 4);
    if (lesson == null) {
      return Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: c.card,
          borderRadius: radius,
          border: Border.all(color: c.line),
        ),
        child: Row(
          children: [
            Icon(Icons.event_available_rounded, color: c.muted),
            const SizedBox(width: 10),
            Expanded(
              child: Text(l.noNextLesson, style: context.text.bodyMedium?.copyWith(color: c.muted)),
            ),
          ],
        ),
      );
    }
    final opens = lesson.joinOpensAt;
    final status = lesson.status == 'in_progress' && lesson.joinOpen
        ? l.lessonOn
        : lesson.joinOpen
        ? l.roomOpen
        : (opens != null && opens.fromNow().inHours < 24 ? l.roomOpensIn(waitText(context, opens.fromNow())) : null);
    const ink = Colors.white;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: radius,
        gradient: LinearGradient(
          colors: [c.brand, Color.lerp(c.brand, Colors.black, 0.22)!],
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
                style: TextStyle(color: ink.withValues(alpha: 0.85), fontWeight: FontWeight.w600, fontSize: kids ? 16 : 13),
              ),
              const SizedBox(width: 8),
              if (status != null)
                Expanded(
                  child: Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                      decoration: BoxDecoration(color: ink.withValues(alpha: 0.16), borderRadius: BorderRadius.circular(12)),
                      child: Text(
                        status,
                        style: const TextStyle(color: ink, fontSize: 12, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            lesson.startsAt.when(context),
            style: TextStyle(fontFamily: context.shape.headingFont, color: ink, fontSize: kids ? 26 : 22, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          Text(
            '${l.lessonWith(lesson.subject, lesson.withName)} · ${l.minutes(lesson.minutes)}',
            style: TextStyle(color: ink.withValues(alpha: 0.9), fontSize: kids ? 16 : 14),
          ),
          if (lesson.unfunded) ...[
            const SizedBox(height: 6),
            Text(
              l.unfunded,
              style: TextStyle(color: ink.withValues(alpha: 0.9), fontSize: 12.5, fontWeight: FontWeight.w700),
            ),
          ],
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              style: FilledButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: c.brandDeep,
                disabledBackgroundColor: Colors.white.withValues(alpha: 0.25),
                disabledForegroundColor: Colors.white.withValues(alpha: 0.85),
              ),
              onPressed: lesson.joinOpen ? () => joinLesson(context, lesson.id) : null,
              icon: const Icon(Icons.videocam_rounded),
              label: Text(lesson.joinOpen || opens == null ? l.joinLesson : '${l.joinLesson} · ${opens.clock(context.locale)}'),
            ).shimmerIf(context, lesson.joinOpen, color: c.brandSoft),
          ),
        ],
      ),
    );
  }
}
