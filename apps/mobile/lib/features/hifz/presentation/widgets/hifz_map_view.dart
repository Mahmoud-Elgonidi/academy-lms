import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/hifz.dart';

/// A strength's colour on the map, from the academy's own colours.
Color strengthColour(BuildContext context, Strength s) {
  final c = context.colors;
  return switch (s) {
    Strength.strong => c.brand,
    Strength.fading => c.attention,
    Strength.weak => c.danger,
    Strength.learning => c.info,
    Strength.fresh => c.line,
  };
}

/// A strength as a chip's tone.
Tone strengthTone(Strength s) => switch (s) {
  Strength.strong => Tone.brand,
  Strength.fading => Tone.attention,
  Strength.weak => Tone.danger,
  Strength.learning => Tone.info,
  Strength.fresh => Tone.neutral,
};

/// The whole map on a card: how many pages are memorized (a bar that fills),
/// then one row per part, each page a small square in its strength's colour
/// (the first page on the reading side), and the colours' key with how many
/// pages each has. A screen reader hears each part as one line.
class HifzMapCard extends StatelessWidget {
  const HifzMapCard({required this.map, this.kids = false, super.key});

  final HifzMap map;

  /// The child's own words for how many pages they know.
  final bool kids;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final memorized = map.counts.memorized;
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            kids ? l.hifzMemorizedKids(memorized) : l.hifzMemorized(memorized, map.pagesTotal),
            key: const Key('hifz-memorized'),
            style: context.type.cardTitle,
          ),
          const Gap(Space.s),
          ProgressBar(map.pagesTotal == 0 ? 0 : memorized / map.pagesTotal),
          const Gap(Space.l),
          HifzMapView(map: map),
          const Gap(Space.m),
          HifzLegend(map: map),
        ],
      ),
    );
  }
}

/// One row per part (its number at the start), each page a square in its strength's colour.
class HifzMapView extends StatelessWidget {
  const HifzMapView({required this.map, super.key});

  final HifzMap map;

  static const _label = 26.0;
  static const _gap = 2.0;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final most = map.juzs.fold(1, (n, j) => math.max(n, j.pages.length));
    return LayoutBuilder(
      builder: (context, box) {
        final square = ((box.maxWidth - _label - Space.s - _gap * (most - 1)) / most).clamp(5.0, 16.0);
        return Column(
          key: const Key('hifz-map'),
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (final j in map.juzs)
              Padding(
                padding: const EdgeInsets.only(bottom: _gap * 2),
                child: Semantics(
                  container: true,
                  label: l.hifzJuzSummary(j.juz, _summary(j)),
                  excludeSemantics: true,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: _label,
                        child: Text(
                          '${j.juz}',
                          textAlign: TextAlign.center,
                          style: context.type.label.copyWith(fontSize: 11, height: square / 11 < 1 ? 1 : square / 11),
                        ),
                      ),
                      const Gap(Space.s),
                      Expanded(
                        child: Wrap(
                          spacing: _gap,
                          runSpacing: _gap,
                          children: [for (final p in j.pages) _Square(key: Key('page-${p.page}'), size: square, strength: p.strength)],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    );
  }

  /// "ثابت 12، لسه 8": each strength the part has, in the key's order.
  String _summary(HifzJuz j) {
    final tally = j.tally;
    return [
      for (final s in Strength.values)
        if ((tally[s] ?? 0) > 0) '${map.label(s)} ${tally[s]}',
    ].join('، ');
  }
}

class _Square extends StatelessWidget {
  const _Square({required this.size, required this.strength, super.key});

  final double size;
  final Strength strength;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(color: strengthColour(context, strength), borderRadius: BorderRadius.circular(size * 0.28)),
  );
}

/// The colours' key: each strength's name (the API's words) and how many pages have it.
class HifzLegend extends StatelessWidget {
  const HifzLegend({required this.map, super.key});

  final HifzMap map;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: Space.m,
    runSpacing: Space.s,
    children: [
      for (final s in Strength.values)
        Row(
          key: Key('legend-${s.key}'),
          mainAxisSize: MainAxisSize.min,
          children: [
            _Square(size: 12, strength: s),
            const Gap(6),
            Text('${map.label(s)} · ${map.counts.of(s, map.pagesTotal)}', style: context.type.caption),
          ],
        ),
    ],
  );
}
