import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router/areas.dart';
import '../../../core/auth/modules_of.dart';
import '../../../core/auth/session_cubit.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../../challenges/data/challenges_repository.dart';
import '../../challenges/presentation/widgets/cheers.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/family_header.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../games/presentation/art/art.dart';
import '../../quran/presentation/widgets/quran_entry.dart';
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
/// lesson's room opening). Cheers a guardian sent pop up once. While the
/// academy has the curriculum off, the tab stays and says so calmly, with
/// the lessons, homework and Quran a tap away.
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
            // Asked again when the academy turns the curriculum back on (or off).
            key: ValueKey('$learnerId:${context.hasModule(Feature.curriculum)}'),
            create: (c) => LoadCubit<JourneyMap>(
              () => c.read<JourneyRepository>().map(learnerId: learnerId),
              refreshOn: c.read<Live>().on({Live.practice, Live.homework, Live.lessons, Live.hifz}),
              refreshAt: (m) => m.clockMoments,
            )..load(),
            child: _Journey(place: place, name: name, family: family),
          );
    if (!family) return body;
    final many = learners.learners.length > 1;
    final self = learner == null || learner.isSelf;
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            FamilyHeader(
              eyebrow: self ? null : l.journeyHow(learner.firstName),
              title: self ? l.navJourney : (many ? l.navJourneyTab : l.journeyTitle(learner.firstName)),
            ),
            Expanded(child: body),
          ],
        ),
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

  /// The academy has the curriculum off: its map answered 403 `feature-not-in-plan`.
  static bool _off(LoadState<JourneyMap> s) => s.data == null && (s.problem?.isFeatureOff ?? false);

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
      buildWhen: (a, b) => a.data?.look != b.data?.look || _off(a) != _off(b),
      builder: (context, state) {
        final kids = state.data?.look == JourneyLook.kids;
        final Widget view = _off(state)
            ? _JourneyOff(problem: state.problem!, place: widget.place, family: widget.family)
            : LoadView<JourneyMap>(
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

/// The journey's tab while the academy has the curriculum off: said calmly (by the academy, or outside its plan),
/// and the lessons, homework, a family's reports and the Quran still a tap away.
class _JourneyOff extends StatelessWidget {
  const _JourneyOff({required this.problem, required this.place, required this.family});

  final ApiProblem problem;
  final JourneyPlace place;
  final bool family;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final area = Area.of(context.watch<SessionCubit>().state);
    return ListView(
      padding: pagePadding,
      children: [
        ErrorState.of(problem),
        Text(l.journeyOffLead, textAlign: TextAlign.center, style: context.type.caption),
        const Gap(Space.l),
        MenuGroup(
          children: [
            MenuRow(
              key: const Key('journey-off-lessons'),
              icon: Icons.calendar_month_outlined,
              label: l.navLessons,
              onTap: () => context.go('${area.prefix}/lessons'),
            ),
            MenuRow(
              key: const Key('journey-off-homework'),
              icon: Icons.edit_outlined,
              tone: Tone.attention,
              label: l.navHomework,
              onTap: () => context.go(place.homework),
            ),
            if (family)
              MenuRow(
                key: const Key('journey-off-reports'),
                icon: Icons.description_outlined,
                tone: Tone.info,
                label: l.allReports,
                onTap: () => context.go('${place.base}/courses'),
              ),
          ],
        ),
        const Gap(Space.m),
        QuranJourneyCard(base: place.base),
      ],
    );
  }
}
