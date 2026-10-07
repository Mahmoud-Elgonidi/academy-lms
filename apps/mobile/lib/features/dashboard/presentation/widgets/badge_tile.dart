import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../domain/journey.dart';

/// One badge: bright when earned, a faint outline with its hint when not.
class BadgeTile extends StatelessWidget {
  const BadgeTile({required this.badge, this.showHint = false, super.key});

  final Achievement badge;
  final bool showHint;

  static IconData icon(String key) => switch (key) {
    'first_star' || 'five_stars' => Icons.star_rounded,
    'streak_3' || 'streak_5' => Icons.local_fire_department_rounded,
    'homework_3' => Icons.assignment_turned_in_rounded,
    'halfway' => Icons.flag_rounded,
    'level_done' => Icons.workspace_premium_rounded,
    _ => Icons.emoji_events_rounded,
  };

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final on = badge.earned;
    return Semantics(
      label: '${badge.name}. ${badge.hint}',
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: on ? c.rewardSoft : c.chip,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: on ? c.reward : c.line, width: 2),
            ),
            child: Icon(icon(badge.key), color: on ? c.reward : c.faint, size: 30),
          ),
          const SizedBox(height: 6),
          Text(
            badge.name,
            textAlign: TextAlign.center,
            maxLines: 2,
            style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: on ? c.ink : c.muted),
          ),
          if (showHint) Text(badge.hint, textAlign: TextAlign.center, maxLines: 3, style: context.text.bodySmall?.copyWith(fontSize: 11.5)),
        ],
      ),
    );
  }
}
