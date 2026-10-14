import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/router/areas.dart';
import '../../../core/auth/session_cubit.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../data/journey_repository.dart';
import '../domain/journey.dart';
import 'calm/calm_journey.dart';
import 'journey_ui.dart';
import 'middle/middle_journey.dart';
import 'widgets/course_chips.dart';
import 'widgets/station_sheet.dart';

/// Every level of the student's course, opened from the journey ("the whole
/// curriculum") or from More: middle school's paths of stops, the calm look's
/// accordion; a lesson opens its sheet.
class LevelsPage extends StatelessWidget {
  const LevelsPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<JourneyMap>(
      () => c.read<JourneyRepository>().map(),
      refreshOn: c.read<Live>().on({Live.practice, Live.homework, Live.lessons}),
    )..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.journeyLevels)),
      body: LoadView<JourneyMap>(builder: (context, map) => _Levels(map: map)),
    ),
  );
}

class _Levels extends StatefulWidget {
  const _Levels({required this.map});

  final JourneyMap map;

  @override
  State<_Levels> createState() => _LevelsState();
}

class _LevelsState extends State<_Levels> {
  String? _course;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final m = widget.map;
    final place = JourneyPlace.of(Area.of(context.watch<SessionCubit>().state));
    final course = m.courses.where((c) => c.enrollmentId == _course).firstOrNull ?? m.courses.firstOrNull;
    void open(JourneyIsland island, JourneyStation s) => showStationSheet(context, map: m, place: place, island: island, station: s);
    final middle = m.look == JourneyLook.middle;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        if (m.courses.length > 1) ...[
          CourseChips(courses: m.courses, selected: course?.enrollmentId, onPick: (id) => setState(() => _course = id)),
          const Gap(Space.m),
        ],
        if (course == null || !course.hasCurriculum || course.islands.isEmpty)
          Note(l.journeyNoCurriculum, icon: Icons.map_outlined)
        else if (middle)
          for (final island in course.islands)
            Padding(
              padding: const EdgeInsets.only(bottom: Space.m),
              child: MiddleLevel(course: course, island: island, onStation: (s) => open(island, s)),
            )
        else
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                for (final island in course.islands) CalmLevelTile(course: course, island: island, onStation: (s) => open(island, s)),
              ],
            ),
          ),
      ]),
    );
  }
}
