import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../data/games_repository.dart';
import '../../domain/games.dart';
import '../art/art.dart';
import '../game_sound.dart';
import '../games_ui.dart';
import '../player/game_frame.dart';
import '../player/game_session.dart';
import 'hatch_view.dart';
import 'result_cards.dart';

/// What a round is started with: the game, the quest it plays (or all of the
/// learner's practice), whose screen it is on, and the island's sky around it.
class PlayRequest {
  const PlayRequest({
    required this.gameId,
    required this.look,
    this.quest,
    this.learnerId,
    this.sky,
    this.bestBefore,
    this.guardian = false,
  });

  final String gameId;
  final String? quest;

  /// A guardian's child (null: the student themself).
  final String? learnerId;

  /// kids, middle, teen or classic.
  final String look;
  final String? sky;

  /// The quest's best time before this round (the race against yourself).
  final int? bestBefore;

  /// A guardian playing on their child's screen: no naming creatures.
  final bool guardian;
}

/// How a round ended: a creature hatched and was named ([named]: go and see it in the album).
class PlayOutcome {
  const PlayOutcome({this.named});

  final String? named;
}

/// Opens a round over everything (the tabs too) and answers how it ended.
Future<PlayOutcome?> openRound(BuildContext context, PlayRequest request) {
  Juice.play(Sound.pop);
  GameSound.stopMusic();
  return Navigator.of(context, rootNavigator: true).push<PlayOutcome>(
    PageRouteBuilder<PlayOutcome>(
      transitionDuration: const Duration(milliseconds: 320),
      reverseTransitionDuration: const Duration(milliseconds: 240),
      pageBuilder: (_, _, _) => PlayPage(request: request),
      transitionsBuilder: (context, animation, _, child) {
        if (context.calm) return child;
        final curved = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic);
        return FadeTransition(
          opacity: curved,
          child: SlideTransition(
            position: Tween(begin: const Offset(0, 0.06), end: Offset.zero).animate(curved),
            child: child,
          ),
        );
      },
    ),
  );
}

enum _Step { starting, playing, finishing, done, failed }

/// A round, full screen: the game's page in its frame (the island's sky
/// around it), then the stars, and a creature hatching when one did.
class PlayPage extends StatefulWidget {
  const PlayPage({super.key, required this.request});

  final PlayRequest request;

  @override
  State<PlayPage> createState() => _PlayPageState();
}

class _PlayPageState extends State<PlayPage> {
  _Step _step = _Step.starting;
  StartedRound? _round;
  GameSession? _session;
  RoundResult? _result;
  Object? _problem;
  bool _hatching = false;
  int _rounds = 0;

  PlayRequest get _r => widget.request;

  @override
  void initState() {
    super.initState();
    _start();
  }

  Future<void> _start() async {
    setState(() {
      _step = _Step.starting;
      _problem = null;
      _result = null;
      _hatching = false;
    });
    final games = context.read<GamesRepository>();
    await GameSound.load();
    try {
      final round = await games.start(_r.gameId, quest: _r.quest, learnerId: _r.learnerId);
      if (!mounted) return;
      setState(() {
        _round = round;
        _session = GameSession(round, muted: GameSound.muted);
        _rounds++;
        _step = _Step.playing;
      });
    } on ApiProblem catch (p) {
      if (mounted) setState(() => _fail(p));
    }
  }

  void _fail(ApiProblem p) {
    _problem = p;
    _step = _Step.failed;
  }

  void _onEvent(GameEvent event) {
    if (event == GameEvent.finish) _finish();
  }

  Future<void> _finish() async {
    final round = _round;
    final session = _session;
    if (round == null || session == null) return;
    setState(() {
      _step = _Step.finishing;
      _problem = null;
    });
    try {
      final result = await context.read<GamesRepository>().finish(round.playId, session.answers);
      if (!mounted) return;
      setState(() {
        _result = result;
        _hatching = result.creature != null && _r.look == 'kids' && !_r.guardian;
        _step = _Step.done;
      });
      if (!_hatching && result.stars == 3) Celebration.show(context, burst: Burst.stars);
    } on ApiProblem catch (p) {
      if (mounted) setState(() => _fail(p));
    }
  }

  void _leave([PlayOutcome? outcome]) {
    GameSound.play(GameEffect.back);
    Navigator.of(context).pop(outcome);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final sky = hexColor(_r.sky, const Color(0xFF8ED8FF));
    final result = _result;
    final round = _round;
    return PopScope(
      canPop: true,
      child: Scaffold(
        backgroundColor: _hatching ? const Color(0xFF2B1A66) : sky,
        body: switch (_step) {
          _Step.done when _hatching && result?.creature != null => HatchView(
            creature: result!.creature!,
            need: result.eggNeed,
            onLeave: () => _leave(),
            onNamed: (key) => _leave(PlayOutcome(named: key)),
          ),
          _ => Stack(
            children: [
              SafeArea(
                child: Column(
                  children: [
                    _Bar(title: round?.title ?? '', sub: _sub(l, round), onClose: () => _leave()),
                    Expanded(
                      child:
                          Container(
                                margin: const EdgeInsets.fromLTRB(10, 0, 10, 10),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(26),
                                  border: Border.all(color: gwInk, width: 4),
                                  boxShadow: const [BoxShadow(color: gwInk, offset: Offset(0, 6))],
                                ),
                                child: ClipRRect(
                                  borderRadius: BorderRadius.circular(22),
                                  child: switch (_step) {
                                    _Step.starting => _Wait(text: l.gamesLoading),
                                    // A round not started tries again; one played sends its answers again.
                                    _Step.failed => _Problem(
                                      text: context.problemText(_problem ?? l.gamesPageProblem),
                                      onRetry: _session?.finished ?? false ? _finish : _start,
                                    ),
                                    _Step.finishing => _Wait(text: l.gamesSending),
                                    _ => GameFrame(
                                      key: ValueKey('round-$_rounds'),
                                      url: round!.pageUrl,
                                      session: _session!,
                                      onEvent: _onEvent,
                                    ),
                                  },
                                ),
                              )
                              .animate(key: ValueKey('frame-$_rounds'))
                              .moveY(begin: 24, end: 0, duration: 500.ms, curve: const Cubic(0.2, 0.9, 0.3, 1.1))
                              .fadeIn(),
                    ),
                  ],
                ),
              ),
              if (_step == _Step.done && result != null)
                Positioned.fill(
                  child: _r.look == 'kids'
                      ? KidsResultCard(result: result, onAgain: _start, onLeave: () => _leave())
                      : HubResultCard(result: result, look: _r.look, bestBefore: _r.bestBefore, onAgain: _start, onLeave: () => _leave()),
                ),
            ],
          ),
        },
      ),
    );
  }

  String _sub(AppLocalizations l, StartedRound? round) {
    if (round == null) return '';
    final focus = round.focusTitle;
    return focus != null && focus.isNotEmpty ? l.gamesPractising(focus) : round.howToPlay;
  }
}

class _Bar extends StatelessWidget {
  const _Bar({required this.title, required this.sub, required this.onClose});

  final String title;
  final String sub;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
    child: Row(
      children: [
        Button3d(
          width: 40,
          height: 40,
          label: context.l10n.gamesClose,
          onTap: onClose,
          child: const Icon(Icons.close_rounded, color: gwInk, size: 22),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (title.isNotEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
                  decoration: BoxDecoration(color: gwInk.withValues(alpha: 0.7), borderRadius: BorderRadius.circular(999)),
                  child: Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: gwText(context, 17, color: Colors.white),
                  ),
                ),
              if (sub.isNotEmpty) ...[
                const SizedBox(height: 3),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 1),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: gwInk, width: 2),
                  ),
                  child: Text(sub, maxLines: 1, overflow: TextOverflow.ellipsis, style: gwText(context, 12.5)),
                ),
              ],
            ],
          ),
        ),
      ],
    ),
  );
}

class _Wait extends StatelessWidget {
  const _Wait({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const CircularProgressIndicator.adaptive(),
        const SizedBox(height: 14),
        Text(text, style: gwText(context, 16, weight: FontWeight.w700)),
      ],
    ),
  );
}

class _Problem extends StatelessWidget {
  const _Problem({required this.text, required this.onRetry});

  final String text;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            text,
            textAlign: TextAlign.center,
            style: gwText(context, 16, weight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          Button3d(
            color: gwSun,
            height: 50,
            padding: const EdgeInsets.symmetric(horizontal: 24),
            onTap: onRetry,
            child: Text(context.l10n.retry, style: gwText(context, 17)),
          ),
        ],
      ),
    ),
  );
}
