import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../../core/l10n/l10n.dart';

/// A group lesson at a glance: a pill with people, «جماعية», and its seats
/// as dots (●●●○○ 3/5), in the info colours with an outline so it reads
/// apart from a lesson's state. [onBrand] draws it on the brand's colour
/// (the next lesson's card). Seats past ten show as the count alone.
class GroupPill extends StatelessWidget {
  const GroupPill({required this.taken, required this.capacity, this.onBrand = false, this.label, this.spoken, super.key});

  final int taken;
  final int capacity;
  final bool onBrand;

  /// In place of «جماعية» (a course reads «مجموعة», a plan «باقة جماعية»).
  final String? label;

  /// What a screen reader says in place of the lesson's words, with the seats.
  final String? spoken;

  /// More seats than this read as the count alone.
  static const maxDots = 10;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    final ink = onBrand ? Colors.white : c.infoInk;
    final bg = onBrand ? Colors.white.withValues(alpha: 0.18) : c.infoSoft;
    final border = onBrand ? Colors.white.withValues(alpha: 0.5) : c.info.withValues(alpha: 0.35);
    final seats = capacity > 0;
    return Semantics(
      container: true,
      label: seats ? (spoken ?? l.groupSeatsSpoken(taken, capacity)) : (label ?? l.formatGroup),
      child: ExcludeSemantics(
        child: Container(
          key: const Key('group-pill'),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: border),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.groups_rounded, size: 15, color: ink),
              const SizedBox(width: 4),
              Text(
                label ?? l.formatGroup,
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: ink),
              ),
              if (seats) ...[
                const SizedBox(width: 6),
                if (capacity <= maxDots) ...[SeatDots(taken: taken, capacity: capacity, color: ink), const SizedBox(width: 4)],
                Text(
                  l.groupSeats(taken, capacity),
                  textDirection: TextDirection.ltr,
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: ink),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// A one-to-one lesson, said plainly: only where group lessons stand beside it.
class PrivatePill extends StatelessWidget {
  const PrivatePill({super.key});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      key: const Key('private-pill'),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: c.line),
      ),
      child: Text(
        context.l10n.formatPrivate,
        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: c.muted),
      ),
    );
  }
}

/// Seats as dots: [taken] filled, the rest hollow, in the order they fill.
class SeatDots extends StatelessWidget {
  const SeatDots({required this.taken, required this.capacity, required this.color, this.size = 7, super.key});

  final int taken;
  final int capacity;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => Row(
    key: const Key('seat-dots'),
    mainAxisSize: MainAxisSize.min,
    children: [for (var i = 0; i < capacity; i++) SeatDot(taken: i < taken, color: color, size: size)],
  );
}

/// One seat: filled when taken, hollow when free.
class SeatDot extends StatelessWidget {
  const SeatDot({required this.taken, required this.color, this.size = 7, super.key});

  final bool taken;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    margin: const EdgeInsetsDirectional.only(end: 2),
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: taken ? color : null,
      border: Border.all(color: color, width: 1.2),
    ),
  );
}

/// A course that is a seat in a group (the journey, the balance): its pill
/// with the seats, the learner's own seat («مقعدك 4 من 5»), and while the
/// group forms, that its lessons wait for it. [plan] names it as a plan
/// («باقة جماعية») on the money screen.
class CourseGroupLine extends StatelessWidget {
  const CourseGroupLine({
    required this.taken,
    required this.capacity,
    required this.seat,
    this.forming = false,
    this.plan = false,
    super.key,
  });

  final int taken;
  final int capacity;

  /// The learner's seat by when they joined; 0 when not known.
  final int seat;
  final bool forming;
  final bool plan;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return Column(
      key: const Key('course-group'),
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 4,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            GroupPill(
              taken: taken,
              capacity: capacity,
              label: plan ? l.groupPlan : l.groupCourse,
              spoken: plan ? l.groupPlanSpoken(taken, capacity) : l.groupCourseSpoken(taken, capacity),
            ),
            if (seat > 0 && capacity > 0)
              Text(
                l.groupYourSeat(seat, capacity),
                key: const Key('course-seat'),
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.infoInk),
              ),
          ],
        ),
        if (forming) ...[const SizedBox(height: 4), Text(l.groupForming, style: TextStyle(fontSize: 12.5, color: c.muted))],
      ],
    );
  }
}
