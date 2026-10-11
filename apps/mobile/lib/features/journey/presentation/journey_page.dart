import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router/areas.dart';
import '../../../core/auth/session_cubit.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/widgets/load_view.dart';
import '../../challenges/data/challenges_repository.dart';
import '../../challenges/presentation/widgets/cheers.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../games/presentation/art/art.dart';
import '../data/journey_repository.dart';
import '../domain/journey.dart';
import 'calm/calm_journey.dart';
import 'guardian/guardian_journey.dart';
import 'journey_ui.dart';
import 'kids/kids_journey.dart';
import 'middle/middle_journey.dart';

/// The journey, in each look: a child's islands, middle school's levels,
/// the calm look (secondary school, adults, or everyone when the academy
/// chose it) and a guardian's view of their child. One map from the API
/// for all; it follows the learner's signals (a round finished anywhere,
/// homework, lessons) and the moments the clock alone changes (the next
/// lesson's room opening). Cheers a guardian sent pop up once.
class JourneyPage extends StatelessWidget {
  const JourneyPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final session = context.watch<SessionCubit>().state;
    final place = JourneyPlace.of(Area.of(session));
    final learners = context.watch<LearnerCubit>().state;
    final family = context.read<LearnerCubit>().scope == LearnerScope.family;
    final learnerId = context.learnerId();
    final learner = learners.selected;
    final name = (family ? learner?.firstName : null) ?? (session.current?.userName ?? '').split(' ').first;
    final Widget body = family && !learners.loaded
        ? const Center(child: CircularProgressIndicator.adaptive())
        : BlocProvider(
            key: ValueKey(learnerId),
            create: (c) => LoadCubit<JourneyMap>(
              () => c.read<JourneyRepository>().map(learnerId: learnerId),
              refreshOn: c.read<Live>().on({Live.practice, Live.homework, Live.lessons}),
              refreshAt: (m) => m.clockMoments,
            )..load(),
            child: _Journey(place: place, name: name, family: family),
          );
    if (!family) return body;
    return Scaffold(
      appBar: AppBar(
        title: Text(learner == null || learner.isSelf ? l.navJourney : l.journeyTitle(learner.firstName)),
        actions: [
          // The courses' materials and reports (where Progress was).
          IconButton(
            key: const Key('journey-courses-action'),
            tooltip: l.journeyCourses,
            icon: const Icon(Icons.menu_book_outlined),
            onPressed: () => context.go('${place.base}/courses'),
          ),
        ],
      ),
      body: Column(
        children: [
          const LearnerSwitcher(),
          Expanded(child: body),
        ],
      ),
    );
  }
}

class _Journey extends StatefulWidget {
  const _Journey({required this.place, required this.name, required this.family});

  final JourneyPlace place;
  final String name;
  final bool family;

  @override
  State<_Journey> createState() => _JourneyState();
}

class _JourneyState extends State<_Journey> {
  final _shown = <String>{};

  bool _fresh(JourneyMap? m) => m != null && !m.guardian && m.cheers.any((c) => !_shown.contains(c.id));

  Future<void> _cheers(BuildContext context, JourneyMap m) async {
    final fresh = m.cheers.where((c) => !_shown.contains(c.id)).toList();
    _shown.addAll(fresh.map((c) => c.id));
    final repo = context.read<ChallengesRepository>();
    await showCheers(context, [for (final c in fresh) c.kind]);
    try {
      await repo.seen();
    } on ApiProblem {
      // Seen again next time: harmless.
    }
  }

  @override
  Widget build(BuildContext context) => BlocListener<LoadCubit<JourneyMap>, LoadState<JourneyMap>>(
    listenWhen: (_, b) => _fresh(b.data),
    listener: (context, state) => _cheers(context, state.data!),
    child: BlocBuilder<LoadCubit<JourneyMap>, LoadState<JourneyMap>>(
      buildWhen: (a, b) => a.data?.look != b.data?.look,
      builder: (context, state) {
        final kids = state.data?.look == JourneyLook.kids;
        final view = LoadView<JourneyMap>(
          refreshable: !kids,
          builder: (context, map) => switch (map.look) {
            JourneyLook.kids => KidsJourney(map: map, place: widget.place, name: widget.name),
            JourneyLook.middle => MiddleJourney(map: map, place: widget.place, name: widget.name),
            JourneyLook.guardian => GuardianJourney(map: map, place: widget.place, name: widget.name),
            JourneyLook.calm => CalmJourney(
              map: map,
              place: widget.place,
              name: widget.name,
              header: !widget.family,
              courses: widget.family,
            ),
          },
        );
        if (widget.family) return view;
        return Scaffold(
          backgroundColor: kids ? gwSea : null,
          body: SafeArea(bottom: false, child: view),
        );
      },
    ),
  );
}
