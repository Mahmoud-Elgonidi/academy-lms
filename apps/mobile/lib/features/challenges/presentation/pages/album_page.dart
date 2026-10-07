import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../domain/challenges.dart';
import '../widgets/art.dart';

/// The kids' sticker album: every sticker of the set, the ones won from
/// chests in colour, the rest waiting as question marks.
class AlbumPage extends StatelessWidget {
  const AlbumPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.album)),
      body: LoadView<ChallengeBoard>(
        builder: (context, board) {
          final items = board.stickers ?? const <Sticker>[];
          return ListView(
            padding: pagePadding,
            children: [
              LmsCard(
                color: context.colors.rewardSoft,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.albumLead, style: context.text.bodyLarge),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: ProgressBar(
                            items.isEmpty ? 0 : board.stickersEarned / items.length,
                            colour: context.colors.reward,
                            height: 12,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Text(l.badgesCount(board.stickersEarned, items.length), style: context.text.titleMedium),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: 4,
                mainAxisSpacing: 14,
                crossAxisSpacing: 8,
                childAspectRatio: 0.78,
                children: [for (final (i, s) in items.indexed) _Slot(sticker: s).appear(context, i ~/ 2)],
              ),
            ],
          );
        },
      ),
    );
  }
}

class _Slot extends StatefulWidget {
  const _Slot({required this.sticker});

  final Sticker sticker;

  @override
  State<_Slot> createState() => _SlotState();
}

class _SlotState extends State<_Slot> {
  int _taps = 0;

  @override
  Widget build(BuildContext context) {
    final s = widget.sticker;
    Widget art = StickerArt(s.key, earned: s.earned, size: 64);
    if (_taps > 0 && !context.calm) {
      art = art.animate(key: ValueKey(_taps)).scaleXY(begin: 1.3, end: 1, duration: context.springDuration, curve: context.springCurve);
    }
    return Semantics(
      label: s.earned ? s.name : null,
      child: Pressable(
        onTap: s.earned
            ? () {
                Juice.tap();
                setState(() => _taps++);
              }
            : null,
        child: Column(
          children: [
            art,
            const SizedBox(height: 4),
            Text(
              s.earned ? s.name : '',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: context.text.bodySmall?.copyWith(fontWeight: FontWeight.w700, color: context.colors.ink),
            ),
          ],
        ),
      ),
    );
  }
}
