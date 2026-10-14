import 'package:flutter/material.dart';

import '../../core/l10n/l10n.dart';
import '../../core/time/formats.dart';
import '../../core/time/local_time.dart';
import 'lms.dart';

/// A small rounded label: a status, a count.
class StatusChip extends StatelessWidget {
  const StatusChip(this.label, {this.tone = Tone.neutral, this.icon, super.key});

  final String label;
  final Tone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final (bg, ink) = tone.colours(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 14, color: ink), const SizedBox(width: 4)],
          Flexible(
            child: Text(
              label,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: ink),
            ),
          ),
        ],
      ),
    );
  }
}

/// The name most screens already use for a [StatusChip].
typedef Pill = StatusChip;

/// When something is, as the API wrote it: the hour, or the day and the
/// hour with [withDay] ("بكرة · 7:00 م"), never converted on the phone.
class TimeChip extends StatelessWidget {
  const TimeChip(this.time, {this.withDay = false, this.tone = Tone.neutral, super.key});

  final LocalTime time;
  final bool withDay;
  final Tone tone;

  @override
  Widget build(BuildContext context) =>
      StatusChip(withDay ? time.when(context) : time.clock(context.locale), tone: tone, icon: Icons.schedule_rounded);
}
