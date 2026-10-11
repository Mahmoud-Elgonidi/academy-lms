import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../app/router/areas.dart';
import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/state/load_cubit.dart';
import '../../../core/time/formats.dart';
import '../../../shared/motion/celebration.dart';
import '../../../shared/motion/juice.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../challenges/presentation/widgets/art.dart';
import '../../classroom/presentation/join.dart';
import '../../games/data/games_repository.dart';
import '../../games/presentation/game_sound.dart';
import '../../games/presentation/play/hatch_view.dart';
import '../../games/presentation/play/play_page.dart';
import '../data/journey_repository.dart';
import '../domain/journey.dart';

/// Where the journey lives in a look, and where its links go.
class JourneyPlace {
  const JourneyPlace({required this.base, required this.homework});

  /// The journey tab ('/kids/journey'); the album is under it.
  final String base;

  /// Where the student hands in homework in this look.
  final String homework;

  String get album => '$base/album';

  static JourneyPlace of(Area area) => switch (area) {
    Area.kids => const JourneyPlace(base: '/kids/journey', homework: '/kids/more/homework'),
    Area.middle => const JourneyPlace(base: '/middle/journey', homework: '/middle/more/homework'),
    Area.teen => const JourneyPlace(base: '/teen/journey', homework: '/teen/homework'),
    _ => const JourneyPlace(base: '/family/journey', homework: '/family/journey/homework'),
  };
}

/// Which of the journey's looks a map is shown in.
enum JourneyLook { kids, middle, calm, guardian }

extension JourneyLookOf on JourneyMap {
  JourneyLook get look {
    if (guardian) return JourneyLook.guardian;
    if (calm) return JourneyLook.calm;
    return switch (stage) {
      'kids' => JourneyLook.kids,
      'middle' => JourneyLook.middle,
      _ => JourneyLook.calm,
    };
  }

  /// How a round's page and result are dressed: the kids' islands, middle school's race, or the older students' look.
  String get roundLook => switch (look) {
    JourneyLook.kids => 'kids',
    JourneyLook.middle => 'middle',
    JourneyLook.guardian => 'classic',
    JourneyLook.calm => stage == 'teen' ? 'teen' : 'classic',
  };
}

/// A station's state, in words.
String stationStateLabel(AppLocalizations l, JourneyStation s) => switch (s.state) {
  'here' => l.journeyStationHere,
  'todo' => l.journeyStationTodo,
  _ when s.review => l.journeyStationReview,
  _ => l.journeyStationDone,
};

/// What a mission's time says: when the lesson is, or when the homework is due.
String? missionWhen(BuildContext context, Mission m) {
  final l = context.l10n;
  return switch (m.kind) {
    'lesson' when m.startsAt != null => m.startsAt!.when(context),
    'homework' when m.dueAt != null => l.dueBy(m.dueAt!.when(context)),
    _ => null,
  };
}

/// "The room opens in 3 hours" for a lesson not open yet (shown as the API wrote it, on the build).
String? lessonOpens(BuildContext context, Mission m) {
  final opens = m.joinOpensAt;
  if (m.kind != 'lesson' || m.joinOpen || opens == null) return null;
  final wait = opens.fromNow();
  if (wait.isNegative || wait.inHours >= 24) return null;
  return context.l10n.roomOpensIn(waitText(context, wait));
}

/// Stars in a row ([n] lit of [of]), drawn in the reward colour.
class StarDots extends StatelessWidget {
  const StarDots(this.n, {super.key, this.of = 4, this.size = 18, this.on, this.off});

  final int n;
  final int of;
  final double size;
  final Color? on;
  final Color? off;

  @override
  Widget build(BuildContext context) => Semantics(
    label: context.l10n.gamesStars(n),
    excludeSemantics: true,
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < of; i++)
          Icon(Icons.star_rounded, size: size, color: i < n ? (on ?? context.colors.reward) : (off ?? context.colors.line)),
      ],
    ),
  );
}

/// The journey's doings, the same in every look: play a station or a
/// homework's practice, join a lesson, hand in, open the chest, hatch the
/// egg. Each reads the journey again after.
class JourneyActions {
  JourneyActions(this.context, this.map, this.place, {this.say, this.go});

  final BuildContext context;
  final JourneyMap map;
  final JourneyPlace place;

  /// How a refusal is said on this screen (a toast over the islands); a snack bar otherwise.
  final void Function(String text)? say;

  /// How this screen goes somewhere after a round (an island over the map closes first); the router otherwise.
  final void Function(String location)? go;

  LoadCubit<JourneyMap> get _cubit => context.read<LoadCubit<JourneyMap>>();

  void _tell(String text) {
    if (say != null) {
      say!(text);
    } else {
      showSnack(context, text);
    }
  }

  /// Whether a round may start now; says why not.
  bool _mayPlay() {
    final games = map.games;
    if (games == null || map.plain) return false;
    if (!games.allowance.may) {
      GameSound.play(GameEffect.locked);
      _tell(games.allowance.reasonLabel ?? '');
      return false;
    }
    return true;
  }

  Future<void> _round(PlayRequest request) async {
    final cubit = _cubit;
    final router = GoRouter.of(context);
    final outcome = await openRound(context, request);
    unawaited(cubit.load());
    final named = outcome?.named;
    if (named == null) return;
    final to = '${place.album}?new=$named';
    if (go != null) {
      go!(to);
    } else {
      router.go(to);
    }
  }

  /// A round of [game] on [station]: its own practice items.
  Future<void> playStation(JourneyStation station, StationGame game, {String? sky}) async {
    if (!station.playable) {
      _tell(context.l10n.journeyStationTodoNote);
      return;
    }
    if (!_mayPlay()) return;
    await _round(
      PlayRequest(
        gameId: game.id,
        station: station.id,
        look: map.roundLook,
        learnerId: map.guardian ? map.learnerId : null,
        guardian: map.guardian,
        sky: sky,
        bestBefore: station.race?.bestSeconds,
      ),
    );
  }

  /// The round that hands a practice homework in: the teacher's game, else the best one on its lesson.
  Future<void> playHomework(Mission homework, {String? sky}) async {
    final game = map.gameFor(homework);
    if (game == null) {
      _tell(context.l10n.homeworkNoGame);
      return;
    }
    if (!_mayPlay()) return;
    await _round(PlayRequest(gameId: game, homeworkId: homework.homeworkId, look: map.roundLook, sky: sky));
  }

  /// Into the lesson's room (LiveKit in the app, or the outside link).
  Future<void> join(Mission lesson) async {
    final id = lesson.lessonId;
    if (id == null) return;
    await joinLesson(context, id);
    unawaited(_cubit.load());
  }

  /// To the homework page, where it is handed in.
  void handIn() => context.go(place.homework);

  /// Opens the week's chest: the lid, the burst, what was inside.
  Future<ChestOpened?> openChest({Offset? from}) async {
    final chest = map.chest;
    if (chest == null || !chest.canOpen) return null;
    final cubit = _cubit;
    try {
      final opened = await context.read<JourneyRepository>().openChest(chest.period);
      if (!context.mounted) return opened;
      Juice.big(Sound.chest);
      Celebration.show(context, burst: Burst.stars, from: from, count: 90);
      await showChestReward(context, opened, kids: map.look == JourneyLook.kids);
      return opened;
    } on ApiProblem catch (p) {
      if (context.mounted) _tell(context.problemText(p));
      return null;
    } finally {
      unawaited(cubit.load());
    }
  }

  /// Hatches the egg the stars filled, on the island the learner is on, then the naming and the album.
  Future<void> hatch({bool cracked = false}) async {
    final cubit = _cubit;
    final router = GoRouter.of(context);
    final island = map.courses.map((c) => c.here?.art?.key).nonNulls.firstOrNull;
    try {
      final creature = await context.read<GamesRepository>().hatch(island: island);
      if (!context.mounted) return;
      if (creature == null) {
        _tell(context.l10n.journeyEggNone);
        unawaited(cubit.load());
        return;
      }
      final named = await Navigator.of(context, rootNavigator: true).push<String>(
        PageRouteBuilder<String>(
          transitionDuration: const Duration(milliseconds: 320),
          reverseTransitionDuration: const Duration(milliseconds: 240),
          pageBuilder: (context, _, _) => Scaffold(
            backgroundColor: const Color(0xFF2B1A66),
            body: HatchView(
              creature: creature,
              need: map.stars.perEgg,
              cracked: cracked,
              onLeave: () => Navigator.of(context).pop(),
              onNamed: (key) => Navigator.of(context).pop(key),
            ),
          ),
          transitionsBuilder: (context, animation, _, child) => context.calm ? child : FadeTransition(opacity: animation, child: child),
        ),
      );
      unawaited(cubit.load());
      if (named != null) router.go('${place.album}?new=$named');
    } on ApiProblem catch (p) {
      if (context.mounted) _tell(context.problemText(p));
    }
  }
}

/// The week's chest, opened: the sticker (kids), the stars counting into the total, a new rank.
Future<void> showChestReward(BuildContext context, ChestOpened opened, {required bool kids}) {
  if (opened.rankedUp) Juice.big(Sound.levelup);
  return showGeneralDialog<void>(
    context: context,
    barrierDismissible: true,
    barrierLabel: context.l10n.close,
    barrierColor: Colors.black.withValues(alpha: 0.45),
    transitionDuration: context.calm ? Duration.zero : context.springDuration,
    transitionBuilder: (context, a, _, child) => FadeTransition(
      opacity: CurvedAnimation(parent: a, curve: Curves.easeOut),
      child: ScaleTransition(
        scale: CurvedAnimation(parent: a, curve: context.springCurve),
        child: child,
      ),
    ),
    pageBuilder: (context, _, _) => _ChestReward(opened: opened, kids: kids),
  );
}

class _ChestReward extends StatelessWidget {
  const _ChestReward({required this.opened, required this.kids});

  final ChestOpened opened;
  final bool kids;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final sticker = opened.sticker;
    Widget badge = Stack(
      alignment: Alignment.center,
      children: [
        if (context.calm)
          SunRays(size: 170, colour: c.rewardSoft)
        else
          SunRays(size: 170, colour: c.rewardSoft).animate(onPlay: (a) => a.repeat()).rotate(duration: const Duration(seconds: 12)),
        if (sticker != null && kids)
          StickerArt(sticker.key, size: 104)
        else
          Container(
            width: 92,
            height: 92,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: c.reward,
              border: Border.all(color: Colors.white, width: 5),
            ),
            child: const Icon(Icons.star_rounded, color: Colors.white, size: 54),
          ),
      ],
    );
    if (!context.calm) {
      badge = badge.animate().scaleXY(begin: 0.3, end: 1, duration: const Duration(milliseconds: 900), curve: Curves.elasticOut);
    }
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Material(
          color: c.card,
          borderRadius: BorderRadius.circular(context.shape.card + 6),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(22, 16, 22, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                badge,
                Text(kids ? l.rewardKids : l.rewardTitle, style: context.text.headlineSmall, textAlign: TextAlign.center),
                const SizedBox(height: 4),
                Text(
                  l.journeyChestGot(opened.stars),
                  textAlign: TextAlign.center,
                  style: context.text.titleLarge?.copyWith(color: const Color(0xFF7A4B00)),
                ),
                if (sticker != null && kids) ...[
                  const SizedBox(height: 10),
                  Pill(l.newSticker, tone: Tone.reward, icon: Icons.auto_awesome_rounded),
                  const SizedBox(height: 4),
                  Text(sticker.name, style: context.text.titleMedium),
                ],
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(color: c.chip, borderRadius: BorderRadius.circular(999)),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.star_rounded, color: c.reward, size: 22),
                      const SizedBox(width: 8),
                      Text(l.journeyYourStars, style: context.text.bodyMedium?.copyWith(color: c.muted)),
                      const SizedBox(width: 8),
                      CountUp(opened.total, from: opened.totalBefore, style: context.text.titleLarge),
                    ],
                  ),
                ),
                if (opened.rankedUp && opened.rank != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    decoration: BoxDecoration(color: c.brand, borderRadius: BorderRadius.circular(context.shape.button.clamp(0, 18))),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.military_tech_rounded, color: Colors.white),
                        const SizedBox(width: 8),
                        Text(l.rankUp(opened.rank!), style: context.text.titleMedium?.copyWith(color: Colors.white)),
                      ],
                    ),
                  ).appear(context, 8),
                ],
                const SizedBox(height: 18),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(onPressed: () => Navigator.of(context).pop(), child: Text(l.awesome)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Whether the student already saw their last lesson's stars (kept on this phone, per learner).
class SeenLesson {
  SeenLesson._();

  static String _key(String learnerId) => 'lms.journey.lesson.$learnerId';

  static Future<bool> seen(String learnerId, String lessonId) async {
    try {
      return await SharedPreferencesAsync().getString(_key(learnerId)) == lessonId;
    } on Object {
      return false;
    }
  }

  static Future<void> see(String learnerId, String lessonId) async {
    try {
      await SharedPreferencesAsync().setString(_key(learnerId), lessonId);
    } on Object {
      // Shown again next time: harmless.
    }
  }
}
