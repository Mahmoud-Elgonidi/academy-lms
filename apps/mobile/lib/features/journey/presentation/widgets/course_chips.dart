import 'package:flutter/material.dart';

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
