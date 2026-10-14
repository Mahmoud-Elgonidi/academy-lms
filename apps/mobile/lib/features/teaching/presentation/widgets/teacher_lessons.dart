import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../schedule/domain/lesson.dart';
import '../../../schedule/presentation/widgets/lesson_row.dart';
import 'dispute_lesson.dart';
import 'waiting.dart';

/// A day's lessons on one card, each a row that opens its sheet; a lesson
/// whose report is still to write carries Write in place of the arrow.
class TeacherDayCard extends StatelessWidget {
  const TeacherDayCard({required this.lessons, required this.openReports, required this.reload, super.key});

  final List<Lesson> lessons;

  /// The report still to write, by lesson.
  final Map<String, String> openReports;

  /// Reads the lessons again (after a dispute or a report).
  final Future<void> Function() reload;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return LmsCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (final (i, x) in lessons.indexed) ...[
            if (i > 0) const Divider(height: 1, indent: Space.l, endIndent: Space.l),
            LessonRow(
              key: Key('lesson-${x.id}'),
              lesson: x,
              grouped: true,
              onTap: () => openTeacherLesson(context, x, reportId: openReports[x.id], reload: reload),
              trailing: switch (openReports[x.id]) {
                final id? when !x.ahead => OutlinedButton(
                  key: Key('write-${x.id}'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(64, 44),
                    foregroundColor: c.brandDeep,
                    side: BorderSide(color: c.brand),
                  ),
                  onPressed: () async {
                    if (await openReport(context, id)) await reload();
                  },
                  child: Text(l.writeReport, style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
                _ => null,
              },
            ),
          ],
        ],
      ),
    );
  }
}

/// A teacher's lesson in its own sheet: when on their clock, with whom, its
/// state, the room, and what they may do (write its report, dispute what it
/// was recorded as).
Future<void> openTeacherLesson(BuildContext context, Lesson lesson, {required Future<void> Function() reload, String? reportId}) {
  final zone = context.read<SessionCubit>().state;
  return showLessonSheet(
    context,
    lesson,
    zone: zone.me?.displayTimezone ?? zone.timezone,
    actions: LessonActions(
      dispute: teacherDispute(context, lesson, reload),
      report: reportId == null || lesson.ahead
          ? null
          : () async {
              if (await openReport(context, reportId)) await reload();
            },
    ),
  );
}
