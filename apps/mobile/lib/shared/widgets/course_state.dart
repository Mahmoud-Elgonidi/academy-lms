import 'package:flutter/material.dart';

import '../../core/l10n/l10n.dart';
import '../../core/time/course_days.dart';
import '../../core/time/formats.dart';
import 'lms.dart';

/// A course paused (until when), to be paused, ending or ended, in the look's
/// words (a child in the kids' look reads of a break); null when it simply goes on.
String? courseStateText(BuildContext context, CourseDays days, {bool kids = false}) {
  final l = context.l10n;
  final locale = context.locale;
  return switch (days.stand) {
    CourseStand.on => null,
    CourseStand.ended => l.courseEnded,
    CourseStand.ending => l.courseEnding(days.endsOn!.day(locale)),
    CourseStand.paused => kids ? l.coursePausedKids(days.pausedUntil!.day(locale)) : l.coursePaused(days.pausedUntil!.day(locale)),
    CourseStand.pauseAhead => l.coursePauseAhead(days.pausedFrom!.day(locale), days.pausedUntil!.day(locale)),
  };
}

/// The same as a small label beside the course; nothing when it simply goes on.
class CourseStatePill extends StatelessWidget {
  const CourseStatePill(this.days, {this.kids = false, super.key});

  final CourseDays days;
  final bool kids;

  @override
  Widget build(BuildContext context) {
    final text = courseStateText(context, days, kids: kids);
    if (text == null) return const SizedBox.shrink();
    return Pill(
      text,
      key: const Key('course-state'),
      icon: days.stand == CourseStand.ended ? Icons.flag_outlined : Icons.pause_circle_outline_rounded,
      tone: switch (days.stand) {
        CourseStand.ended => Tone.neutral,
        CourseStand.ending => Tone.attention,
        _ => Tone.info,
      },
    );
  }
}
