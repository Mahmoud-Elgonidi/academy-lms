import 'package:flutter/material.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/lesson.dart';

/// Names said as a list in the reader's language: "سارة وعلي ومنى", "Sara, Ali and Mona".
String joinNames(BuildContext context, List<String> names) {
  if (names.length <= 1) return names.join();
  final and = context.l10n.legalAnd;
  final ar = Localizations.localeOf(context).languageCode == 'ar';
  final head = names.take(names.length - 1).join(ar ? ' $and' : ', ');
  return ar ? '$head $and${names.last}' : '$head $and ${names.last}';
}

/// What a lesson is called in a list: "Subject with Teacher"; a teacher's
/// group lesson is its subject alone (its students come on the line under it).
String lessonTitle(BuildContext context, Lesson lesson) {
  if (lesson.group?.forTeacher ?? false) return lesson.subject;
  return lesson.withName.isEmpty ? lesson.subject : context.l10n.lessonWith(lesson.subject, lesson.withName);
}

/// A group lesson's others as the viewer may see them: a family its own
/// seat («مقعدك 4 من 5», not in the kids' look) and its classmates' first
/// names (the kids' look, how many), the teacher its students; null for a
/// one-to-one lesson.
String? groupLine(BuildContext context, Lesson lesson) {
  final g = lesson.group;
  if (!lesson.isGroup || g == null) return null;
  final l = context.l10n;
  if (g.students case final students?) {
    return students.isEmpty ? l.groupStudentsCount(0) : joinNames(context, [for (final s in students) s.name]);
  }
  final names = g.classmates;
  final others = names != null && names.isNotEmpty
      ? l.groupWithNames(joinNames(context, names))
      : l.groupWithCount(g.classmatesCount ?? names?.length ?? 0);
  // The seat first, so a line cut short still says it.
  final seat = g.seatNumber;
  if (seat == null || seat <= 0 || g.capacity <= 0 || context.isKids) return others;
  return '${l.groupYourSeat(seat, g.capacity)} · $others';
}

/// The lesson's kind as a pill: a group lesson's seats always; «فردية» only
/// when [showPrivate] (group lessons stand beside it in the same list).
Widget? formatPill(Lesson lesson, {bool showPrivate = false, bool onBrand = false}) {
  final g = lesson.group;
  if (lesson.isGroup) return GroupPill(taken: g?.seatsTaken ?? 0, capacity: g?.capacity ?? 0, onBrand: onBrand);
  return showPrivate ? const PrivatePill() : null;
}

/// Whether a list mixes group and one-to-one lessons, so each says which it is.
bool mixesFormats(Iterable<Lesson> lessons) => lessons.any((x) => x.isGroup) && lessons.any((x) => !x.isGroup);
