import 'dart:async';

import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../classroom/presentation/join.dart';
import '../../../schedule/domain/lesson.dart';

/// The next lesson in the brand's colour: when, with whom, how long until
/// the room opens, and the join button once it does. Ticks every half
/// minute so the countdown and the button stay true.
class NextLessonCard extends StatefulWidget {
  const NextLessonCard({required this.lesson, this.onOpened, super.key});

  final Lesson? lesson;

  /// The room just opened: reload to get the join state from the API.
  final VoidCallback? onOpened;

  @override
  State<NextLessonCard> createState() => _NextLessonCardState();
}

class _NextLessonCardState extends State<NextLessonCard> {
  Timer? _tick;

  @override
  void initState() {
    super.initState();
    _tick = Timer.periodic(const Duration(seconds: 30), (_) {
      final opens = widget.lesson?.joinOpensAt;
      if (opens != null && opens.fromNow().isNegative && !(widget.lesson?.joinOpen ?? true)) widget.onOpened?.call();
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _tick?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final lesson = widget.lesson;
    final kids = context.shape.stage.name == 'kids';
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
          Row(
            children: [
              Text(
                l.nextLesson,
                style: TextStyle(color: ink.withValues(alpha: 0.85), fontWeight: FontWeight.w600, fontSize: kids ? 16 : 13),
              ),
              const Spacer(),
              if (status != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                  decoration: BoxDecoration(color: ink.withValues(alpha: 0.16), borderRadius: BorderRadius.circular(999)),
                  child: Text(
                    status,
                    style: const TextStyle(color: ink, fontSize: 12, fontWeight: FontWeight.w700),
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
            ),
          ),
        ],
      ),
    );
  }
}
