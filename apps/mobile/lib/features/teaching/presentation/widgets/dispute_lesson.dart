import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../schedule/domain/lesson.dart';
import '../../../schedule/presentation/widgets/lesson_sheets.dart';
import '../../data/teaching_repository.dart';

/// The teacher's "Dispute" on one of their lessons, while the API offers it
/// (within 48 hours of a lesson recorded as held or missed); [reload] runs
/// once it is sent, so the card shows the lesson under review.
VoidCallback? teacherDispute(BuildContext context, Lesson lesson, Future<void> Function() reload) {
  if (!lesson.canDispute) return null;
  return () async {
    final repo = context.read<TeachingRepository>();
    final sent = await showDisputeSheet(
      context,
      lesson,
      teacher: true,
      send: (reason, note) => repo.dispute(lesson.id, reason: reason, note: note),
    );
    if (sent && context.mounted) {
      showSnack(context, context.l10n.disputeSent);
      await reload();
    }
  };
}
