import 'package:flutter/material.dart';

import '../../../../shared/widgets/lms.dart';
import '../../domain/journey.dart';

/// The learner's courses, when more than one: each has its own levels.
class CourseChips extends StatelessWidget {
  const CourseChips({super.key, required this.courses, required this.selected, required this.onPick});

  final List<JourneyCourse> courses;
  final String? selected;
  final ValueChanged<String> onPick;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 44,
    child: ListView(
      scrollDirection: Axis.horizontal,
      children: [
        for (final c in courses)
          Padding(
            padding: const EdgeInsetsDirectional.only(end: 8),
            child: ChoiceChip(
              label: Text(c.subject),
              selected: c.enrollmentId == selected,
              showCheckmark: false,
              onSelected: (_) => onPick(c.enrollmentId),
            ),
          ),
      ],
    ),
  );
}

/// A group course's line under the chips: «مجموعة ●●●○○ 3/5 · مقعدك 4 من 5»
/// (and while it forms, that it does); null for a one-to-one course. Not in
/// the kids' look, which reads its classmates as a count on the lesson.
Widget? courseGroupLine(JourneyCourse? course) {
  final g = course?.group;
  if (g == null) return null;
  return Align(
    alignment: AlignmentDirectional.centerStart,
    child: CourseGroupLine(taken: g.seatsTaken, capacity: g.capacity, seat: g.seat, forming: g.forming),
  );
}
