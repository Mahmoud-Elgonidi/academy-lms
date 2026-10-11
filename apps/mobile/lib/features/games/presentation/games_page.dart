import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart' show LearnerOf;
import '../data/games_repository.dart';
import '../domain/games.dart';
import 'art/art.dart';
import 'guardian_view.dart';
import 'hub_view.dart';
import 'kids/kids_world.dart';

/// The islands' games in each look: a child's islands map, middle school's
/// race against yourself, the older students' review, and a guardian's look
/// at their child with the limits they set. It follows the learner's own
/// signal (a round finished anywhere, a creature hatched, a limit changed).
class GamesPage extends StatelessWidget {
  const GamesPage({super.key, required this.base, this.island});

  /// Where the games are in this look (the album is under it).
  final String base;

  /// An island to open on arrival (coming back from the album).
  final String? island;

  @override
  Widget build(BuildContext context) {
    final id = context.learnerId();
    final pushed = context.read<LearnerCubit>().scope == LearnerScope.family;
    return BlocProvider(
      key: ValueKey(id),
      create: (c) => LoadCubit<GamesWorld>(
        () => c.read<GamesRepository>().world(learnerId: id),
        refreshOn: c.read<Live>().on({Live.practice, Live.homework, Live.lessons}),
      )..load(),
      child: BlocBuilder<LoadCubit<GamesWorld>, LoadState<GamesWorld>>(
        buildWhen: (a, b) => a.data?.stage != b.data?.stage || (a.data?.limits == null) != (b.data?.limits == null),
        builder: (context, state) {
          final w = state.data;
          final kids = w != null && w.limits == null && w.stage == 'kids';
          final dark = w != null && w.limits == null && w.stage != 'kids';
          return Scaffold(
            backgroundColor: kids ? gwSea : null,
            appBar: pushed && (w == null || w.limits != null || w.stage == 'classic')
                ? AppBar(title: Text(context.l10n.gamesTitleClassic))
                : null,
            body: LoadView<GamesWorld>(
              refreshable: !kids && !dark,
              builder: (context, w) {
                if (w.limits != null) return GuardianGames(world: w);
                if (w.stage == 'kids') {
                  final name = context.watch<LearnerCubit>().state.selected?.firstName ?? '';
                  return SafeArea(
                    bottom: false,
                    child: KidsWorld(world: w, name: name, base: base, island: island),
                  );
                }
                return GamesHub(world: w, base: base);
              },
            ),
          );
        },
      ),
    );
  }
}

/// The way into the games from a home screen: what to practise now (a
/// student) or today's play and limits (a guardian).
class GamesCard extends StatelessWidget {
  const GamesCard({super.key, required this.to, this.lead});

  final String to;

  /// What the card says under its title (the first quest, or a guardian's line).
  final String? lead;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return LmsCard(
      onTap: () => context.go(to),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: gwSea, borderRadius: BorderRadius.circular(14)),
            alignment: Alignment.center,
            child: const Text('🏝️', style: TextStyle(fontSize: 24)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.gamesCardTitle, style: context.text.titleMedium),
                if (lead != null) Text(lead!, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.text.bodySmall),
              ],
            ),
          ),
          Icon(Directionality.of(context) == TextDirection.rtl ? Icons.chevron_left_rounded : Icons.chevron_right_rounded, color: c.faint),
        ],
      ),
    );
  }
}
