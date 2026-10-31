import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../schedule/domain/lesson.dart';
import '../../../schedule/presentation/widgets/lesson_sheets.dart';
import '../../data/teaching_repository.dart';

/// The teacher's "Dispute" on one of their lessons, while the API offers it
/// (within 48 hours of a lesson recorded as held or missed); [reload] runs
/// once it is sent, so the card shows the lesson under review. A group
/// lesson's dispute is about one student's seat: the teacher picks whose
/// first, from the students seated in it, and it goes with `student_id`.
VoidCallback? teacherDispute(BuildContext context, Lesson lesson, Future<void> Function() reload) {
  if (!lesson.canDispute) return null;
  return () async {
    final repo = context.read<TeachingRepository>();
    ({String id, String name})? student;
    if (lesson.isGroup) {
      final students = lesson.group?.students ?? const [];
      student = students.length == 1 ? students.single : await pickDisputeStudent(context, students);
      if (student == null || !context.mounted) return;
    }
    final sent = await showDisputeSheet(
      context,
      lesson,
      teacher: true,
      about: student?.name,
      send: (reason, note) => repo.dispute(lesson.id, reason: reason, note: note, studentId: student?.id),
    );
    if (sent && context.mounted) {
      showSnack(context, context.l10n.disputeSent);
      await reload();
    }
  };
}

/// Whose seat a group lesson's dispute is about: a sheet listing the students
/// seated in it; null when closed without a choice.
Future<({String id, String name})?> pickDisputeStudent(BuildContext context, List<({String id, String name})> students) {
  final l = context.l10n;
  return showActionSheet<({String id, String name})>(
    context,
    title: l.disputeWhose,
    builder: (c) => SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(Space.l, 0, Space.l, Space.xl),
      child: Column(
        key: const Key('dispute-students'),
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(l.disputeWhoseHint, style: c.type.caption),
          const Gap(Space.s),
          for (final s in students)
            ListTile(
              key: Key('dispute-student-${s.id}'),
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.person_outline_rounded),
              title: Text(s.name),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => Navigator.pop(c, s),
            ),
        ],
      ),
    ),
  );
}
