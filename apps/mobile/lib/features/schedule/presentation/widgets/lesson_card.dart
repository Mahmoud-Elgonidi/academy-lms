import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../classroom/presentation/join.dart';
import '../../domain/lesson.dart';

/// One lesson in a list: its time on the viewer's clock, whom it is with,
/// its state, and what can be done now (join, cancel, turn down a
/// substitute, rate).
class LessonCard extends StatelessWidget {
  const LessonCard({required this.lesson, this.onCancel, this.onRefuse, this.onRate, this.highlight = false, super.key});

  final Lesson lesson;
  final VoidCallback? onCancel;
  final VoidCallback? onRefuse;
  final VoidCallback? onRate;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final l = context.l10n;
    final locale = context.locale;
    final l0 = lesson;
    final tone = switch (l0.status) {
      'in_progress' => Tone.brand,
      'completed' => Tone.brand,
      'cancelled' => Tone.neutral,
      'student_absent' || 'teacher_absent' => Tone.attention,
      'needs_review' => Tone.info,
      _ => l0.unfunded ? Tone.attention : Tone.neutral,
    };
    return LmsCard(
      color: highlight ? c.brandSoft : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: 84,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l0.startsAt.clock(locale),
                      maxLines: 1,
                      softWrap: false,
                      overflow: TextOverflow.visible,
                      style: context.text.titleMedium?.copyWith(
                        fontSize: 18,
                        fontFamily: context.shape.headingFont,
                        decoration: l0.cancelled ? TextDecoration.lineThrough : null,
                        color: l0.cancelled ? c.muted : null,
                      ),
                    ),
                    Text(l.minutes(l0.minutes), style: context.text.bodySmall),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l0.subject, style: context.text.titleMedium),
                    if (l0.withName.isNotEmpty) Text(l0.withName, style: context.text.bodyMedium?.copyWith(color: c.muted)),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      children: [
                        Pill(l0.unfunded && l0.ahead ? l.unfunded : l0.statusLabel, tone: tone),
                        if (l0.typeLabel != null) Pill(l0.typeLabel!, tone: Tone.info),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (l0.substituteFor != null) ...[
            const SizedBox(height: 10),
            Note(l.substituteFor(l0.substituteFor!), tone: Tone.attention, icon: Icons.swap_horiz_rounded),
          ],
          if (l0.cancelledBecause != null) ...[const SizedBox(height: 10), Text(l0.cancelledBecause!, style: context.text.bodySmall)],
          if (l0.joinOpen) ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: () => joinLesson(context, l0.id),
                icon: const Icon(Icons.videocam_rounded),
                label: Text(l.joinLesson),
              ),
            ),
          ] else if (l0.joinOpensAt != null && l0.startsAt.fromNow().inHours < 12) ...[
            const SizedBox(height: 8),
            Text(l.joinOpensAt(l0.joinOpensAt!.clock(locale)), style: context.text.bodySmall),
          ],
          if (onCancel != null || onRefuse != null || onRate != null) ...[
            const SizedBox(height: 6),
            Wrap(
              spacing: 4,
              children: [
                if (onRate != null)
                  TextButton.icon(onPressed: onRate, icon: const Icon(Icons.star_outline_rounded), label: Text(l.rateLesson)),
                if (onRefuse != null) TextButton(onPressed: onRefuse, child: Text(l.refuseSubstitute)),
                if (onCancel != null)
                  TextButton(
                    style: TextButton.styleFrom(foregroundColor: c.dangerInk),
                    onPressed: onCancel,
                    child: Text(l.cancelLesson),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
