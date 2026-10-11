import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../domain/games.dart';
import '../art/art.dart';
import '../game_sound.dart';
import '../games_ui.dart';

const _starOn = '#FFD43B';
const _starOff = '#E9ECEF';

String _star(String fill) =>
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="$fill" stroke="#13294B" stroke-width="1.6" stroke-linejoin="round"/></svg>';

/// A round's result over the island in the kids' look: the stars one by one
/// (each with its sound), how many were right first time, the homework handed
/// in, and the egg filling.
class KidsResultCard extends StatelessWidget {
  const KidsResultCard({super.key, required this.result, required this.onAgain, required this.onLeave});

  final RoundResult result;
  final VoidCallback onAgain;
  final VoidCallback onLeave;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final calm = context.calm;
    final share = result.eggNeed == 0 ? 0.0 : (result.eggHave / result.eggNeed).clamp(0.0, 1.0);
    Widget star(int i) {
      final on = i < result.stars;
      final size = i == 1 ? 86.0 : 66.0;
      final Widget s = Padding(
        padding: EdgeInsets.only(bottom: i == 1 ? 14 : 0),
        child: SvgArt(_star(on ? _starOn : _starOff), width: size, height: size),
      );
      if (calm) return s;
      var a = s
          .animate(delay: Duration(milliseconds: 250 + i * 280))
          .scaleXY(begin: 0, end: 1, duration: 500.ms, curve: const Cubic(0.3, 1.8, 0.5, 1))
          .rotate(begin: -40 / 360, end: 0, duration: 500.ms)
          .fadeIn(duration: 200.ms);
      if (on) {
        a = a.callback(duration: 1.ms, callback: (_) => GameSound.play(i == 2 && result.stars == 3 ? GameEffect.win : GameEffect.star));
      }
      return a;
    }

    final card = Container(
      width: 360,
      padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: gwInk, width: 4),
        boxShadow: const [BoxShadow(color: gwInk, offset: Offset(0, 7))],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Transform.translate(
            offset: const Offset(0, -40),
            child: Semantics(
              label: l.gamesStars(result.stars),
              excludeSemantics: true,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [star(0), const SizedBox(width: 6), star(1), const SizedBox(width: 6), star(2)],
              ),
            ),
          ),
          Transform.translate(
            offset: const Offset(0, -30),
            child: Column(
              children: [
                Text(resultTitle(l, result.stars), textAlign: TextAlign.center, style: gwText(context, 28, height: 1.15)),
                const SizedBox(height: 2),
                Text(
                  l.gamesScore(result.correct, result.total),
                  textAlign: TextAlign.center,
                  style: gwText(context, 16, weight: FontWeight.w700, color: const Color(0xFF3B4F72)),
                ),
                if (result.homeworkTitle != null && result.handedIn) ...[
                  const SizedBox(height: 10),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFD3F9D8),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: gwInk, width: 2),
                    ),
                    child: Text('✅ ${l.gamesHomeworkDone(result.homeworkTitle!)}', style: gwText(context, 14.5)),
                  ),
                ],
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF4D6),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: gwInk, width: 2),
                  ),
                  child: Row(
                    children: [
                      SvgArt(eggSvg(), width: 22, height: 27),
                      const SizedBox(width: 8),
                      Flexible(flex: 3, child: Text(l.gamesEggCard(result.eggHave, result.eggNeed), style: gwText(context, 14))),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Semantics(
                          value: '${result.eggHave} / ${result.eggNeed}',
                          child: Container(
                            height: 12,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(999),
                              border: Border.all(color: gwInk, width: 2),
                            ),
                            clipBehavior: Clip.antiAlias,
                            child: Align(
                              alignment: AlignmentDirectional.centerStart,
                              child:
                                  FractionallySizedBox(
                                    widthFactor: share,
                                    child: Container(color: gwSun),
                                  ).animate().scaleX(
                                    begin: 0,
                                    end: 1,
                                    delay: 1.seconds,
                                    duration: 900.ms,
                                    curve: const Cubic(0.2, 0.8, 0.3, 1),
                                    alignment: AlignmentDirectional.centerStart.resolve(Directionality.of(context)),
                                  ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Button3d(
                  color: gwSun,
                  height: 54,
                  depth: 4,
                  onTap: onAgain,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.replay_rounded, color: gwInk, size: 22),
                      const SizedBox(width: 6),
                      Text(l.gamesAgain, style: gwText(context, 19)),
                    ],
                  ),
                ),
                const SizedBox(height: 10),
                Button3d(
                  height: 54,
                  depth: 4,
                  onTap: () {
                    GameSound.play(GameEffect.back);
                    onLeave();
                  },
                  child: Text(l.gamesToIsland, style: gwText(context, 19)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
    return ColoredBox(
      color: gwInk.withValues(alpha: 0.72),
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 60, 20, 20),
            child: calm
                ? card
                : card.animate().scaleXY(begin: 0.4, end: 1, duration: 450.ms, curve: const Cubic(0.3, 1.6, 0.55, 1)).fadeIn(),
          ),
        ),
      ),
    );
  }
}

/// The colours of the older looks' dark games screen (middle school's own, the teens' and adults').
class HubPalette {
  const HubPalette._(this.bg, this.card, this.line, this.muted, this.accent, this.ink, this.alt);

  static const middle = HubPalette._(
    Color(0xFF1B1446),
    Color(0xFF241A5C),
    Color(0xFF4A3FA0),
    Color(0xFFB9B2F0),
    Color(0xFFA9E34B),
    Color(0xFF0B0726),
    Color(0xFF63E6BE),
  );
  static const teen = HubPalette._(
    Color(0xFF0B1222),
    Color(0xFF111A2E),
    Color(0xFF263248),
    Color(0xFF94A3B8),
    Color(0xFFC6F432),
    Color(0xFF0B1222),
    Color(0xFF8B7CF6),
  );

  static HubPalette of(String look) => look == 'middle' ? middle : teen;

  final Color bg;
  final Color card;
  final Color line;
  final Color muted;
  final Color accent;
  final Color ink;
  final Color alt;
}

/// A round's result in the older looks: your time against your best, the
/// stars, a creature hatched, the homework handed in, the next egg.
class HubResultCard extends StatelessWidget {
  const HubResultCard({super.key, required this.result, required this.look, required this.onAgain, required this.onLeave, this.bestBefore});

  final RoundResult result;
  final String look;
  final int? bestBefore;
  final VoidCallback onAgain;
  final VoidCallback onLeave;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = HubPalette.of(look);
    final race = look == 'middle';
    final kicker = bestBefore == null
        ? l.gamesFirst
        : (result.seconds ?? 1 << 30) < bestBefore!
        ? l.gamesBeat
        : l.gamesSoClose;
    final calm = context.calm;
    Widget star(int i) {
      final Widget s = Icon(Icons.star_rounded, size: 42, color: i < result.stars ? const Color(0xFFFCC419) : const Color(0xFFDEE2E6));
      if (calm) return s;
      var a = s
          .animate(delay: Duration(milliseconds: 200 + i * 250))
          .scaleXY(begin: 0, end: 1, duration: 500.ms, curve: const Cubic(0.3, 1.8, 0.5, 1));
      if (i < result.stars) a = a.callback(duration: 1.ms, callback: (_) => GameSound.play(GameEffect.star));
      return a;
    }

    const sub = Color(0xFF5C4A99);
    final creature = result.creature;
    final card = Container(
      width: 380,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20)),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (race)
            Text(
              kicker,
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: sub, fontFamily: context.shape.headingFont),
            ),
          const SizedBox(height: 2),
          Text(
            race ? l.gamesOver(result.correct, result.total, secondsLabel(l, result.seconds)) : l.gamesScore(result.correct, result.total),
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, height: 1.2, color: p.ink, fontFamily: context.shape.headingFont),
          ),
          const SizedBox(height: 6),
          Semantics(
            label: l.gamesStars(result.stars),
            excludeSemantics: true,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [star(0), const SizedBox(width: 4), star(1), const SizedBox(width: 4), star(2)],
            ),
          ),
          if (creature != null) ...[
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CreatureArt(creature.look, size: 40, motion: true),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    l.gamesHatched(creature.name),
                    style: TextStyle(fontWeight: FontWeight.w700, color: p.ink),
                  ),
                ),
              ],
            ),
          ],
          if (result.homeworkTitle != null && result.handedIn) ...[
            const SizedBox(height: 6),
            Text(
              '✅ ${l.gamesHomeworkDone(result.homeworkTitle!)}',
              textAlign: TextAlign.center,
              style: TextStyle(fontWeight: FontWeight.w600, color: p.ink),
            ),
          ],
          const SizedBox(height: 4),
          Text(l.gamesEgg(result.eggHave, result.eggNeed), style: const TextStyle(fontSize: 12.5, color: sub)),
          const SizedBox(height: 12),
          HubGoButton(label: race ? l.gamesAgain : l.gamesReviewAgain, palette: p, look: look, onTap: onAgain),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: OutlinedButton(
              style: OutlinedButton.styleFrom(
                foregroundColor: p.ink,
                side: BorderSide(color: p.ink.withValues(alpha: 0.25), width: 2),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              onPressed: onLeave,
              child: Text(l.gamesClose, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
    return ColoredBox(
      color: p.bg.withValues(alpha: 0.86),
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: calm
                ? card
                : card.animate().scaleXY(begin: 0.6, end: 1, duration: 350.ms, curve: const Cubic(0.3, 1.6, 0.55, 1)).fadeIn(),
          ),
        ),
      ),
    );
  }
}

/// The older looks' main button: chunky for middle school, flat for teens and adults.
class HubGoButton extends StatelessWidget {
  const HubGoButton({super.key, required this.label, required this.palette, required this.look, required this.onTap});

  final String label;
  final HubPalette palette;
  final String look;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = palette;
    final style = TextStyle(
      fontSize: look == 'middle' ? 21 : 17,
      fontWeight: FontWeight.w800,
      color: p.ink,
      fontFamily: context.shape.headingFont,
    );
    if (look == 'middle') {
      return Button3d(
        color: p.accent,
        ink: p.ink,
        radius: 16,
        depth: 5,
        border: 2,
        height: 58,
        width: double.infinity,
        onTap: onTap,
        enabled: onTap != null,
        child: Text(label, style: style),
      );
    }
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: FilledButton(
        style: FilledButton.styleFrom(
          backgroundColor: p.accent,
          foregroundColor: p.ink,
          disabledBackgroundColor: p.accent.withValues(alpha: 0.5),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
        onPressed: onTap,
        child: Text(label, style: style),
      ),
    );
  }
}
