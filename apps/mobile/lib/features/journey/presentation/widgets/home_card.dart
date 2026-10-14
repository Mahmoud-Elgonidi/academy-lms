import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/journey.dart';

/// The way into a learner's journey from the family's home: "Salma's
/// journey", the level she is on and how far, her stars and what is open.
class JourneyHomeCard extends StatelessWidget {
  const JourneyHomeCard({super.key, required this.name, required this.to, this.map, this.self = false});

  final String name;

  /// The journey tab.
  final String to;

  /// The journey, when it could be read (the card still leads there without it).
  final JourneyMap? map;

  /// The person's own journey (an adult student).
  final bool self;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final m = map;
    final course = m?.courses.firstOrNull;
    final here = course?.here;
    final open = m?.missions.where((x) => !x.done).length ?? 0;
    final line = [
      if (here != null) '${here.title} · ${here.percent}%',
      if (m != null && m.rewards) l.journeyStarsCount(m.stars.total),
      if (open > 0) l.journeyOpenMissions(open),
    ].join(' · ');
    return LmsCard(
      key: const Key('journey-card'),
      onTap: () => context.go(to),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(context.shape.card - 4)),
            alignment: Alignment.center,
            child: Icon(Icons.map_rounded, color: c.brandDeep),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(self ? l.navJourney : l.journeyTitle(name), style: context.text.titleMedium),
                if (line.isNotEmpty) Text(line, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.text.bodySmall),
                if (here != null) ...[const SizedBox(height: 6), ProgressBar(here.percent / 100, height: 6)],
              ],
            ),
          ),
          const SizedBox(width: 8),
          Icon(Icons.chevron_right_rounded, color: c.faint),
        ],
      ),
    );
  }
}
