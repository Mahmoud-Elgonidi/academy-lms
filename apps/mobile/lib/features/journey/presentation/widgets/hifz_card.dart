import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../games/presentation/art/art.dart';
import '../../../games/presentation/games_ui.dart';
import '../../domain/journey.dart';
import '../journey_ui.dart';

/// The way from the journey to the hifz map, once the learner has pages on
/// it: what is left of today's review, or that it is done. On the kids' sea
/// it is one of the islands' white buttons, in a child's words.
class HifzJourneyCard extends StatelessWidget {
  const HifzJourneyCard({required this.hifz, required this.place, this.kids = false, super.key});

  final JourneyHifz hifz;
  final JourneyPlace place;
  final bool kids;

  void _open(BuildContext context) => context.push('${place.base}/hifz');

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final left = hifz.due;
    if (kids) {
      final line = left > 0 || hifz.doneToday > 0 ? l.hifzCardKids(left) : l.hifzCardNone;
      return Button3d(
        key: const Key('hifz-card'),
        color: Colors.white,
        radius: 24,
        depth: 5,
        padding: const EdgeInsets.all(14),
        label: '${l.hifzTitleKids}. $line',
        onTap: () => _open(context),
        child: ExcludeSemantics(
          child: Row(
            children: [
              const Icon(Icons.auto_stories_rounded, color: gwInk, size: 34),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.hifzTitleKids, style: gwText(context, 18)),
                    Text(line, style: gwText(context, 14, weight: FontWeight.w700)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: gwInk),
            ],
          ),
        ),
      );
    }
    final line = left > 0 ? l.hifzCardLeft(left) : (hifz.doneToday > 0 ? l.hifzCardDone : l.hifzCardNone);
    return Semantics(
      button: true,
      label: '${l.hifzWird}: $line',
      excludeSemantics: true,
      child: LmsCard(
        key: const Key('hifz-card'),
        onTap: () => _open(context),
        child: Row(
          children: [
            IconBadge(Icons.auto_stories_rounded, tone: left > 0 ? Tone.attention : Tone.brand, size: 48),
            const Gap(Space.m),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.hifzWird, style: context.type.cardTitle),
                  const Gap(Space.xs),
                  Text(line, style: context.type.caption),
                ],
              ),
            ),
            const Gap(Space.s),
            Icon(Icons.chevron_right_rounded, color: c.faint),
          ],
        ),
      ),
    );
  }
}
