import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../../games/presentation/art/art.dart';
import '../../../games/presentation/games_ui.dart';
import '../../data/quran_repository.dart';

/// Opens a lesson's Quran portion and record, for its teacher.
Future<void> openQuranLesson(BuildContext context, String lessonId) => context.push('/teach/lessons/$lessonId/quran');

/// Draws [builder] only once [ask] says yes; nothing while it asks, and nothing when the Quran is not in the
/// academy's plan.
class _WhenYes extends StatefulWidget {
  const _WhenYes({required this.askKey, required this.ask, required this.builder});

  /// Asks again when it changes (another learner).
  final String askKey;
  final Future<bool> Function(BuildContext context) ask;
  final WidgetBuilder builder;

  @override
  State<_WhenYes> createState() => _WhenYesState();
}

class _WhenYesState extends State<_WhenYes> {
  late Future<bool> _yes = widget.ask(context);

  @override
  void didUpdateWidget(_WhenYes old) {
    super.didUpdateWidget(old);
    if (old.askKey != widget.askKey) _yes = widget.ask(context);
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<bool>(
    future: _yes,
    builder: (context, snap) => snap.data == true ? widget.builder(context).appear(context, 0) : const SizedBox.shrink(),
  );
}

/// The way from the journey to the Quran screen, once the learner has a Quran course with a plan. On the kids' sea
/// it is one of the islands' white buttons, in a child's words.
class QuranJourneyCard extends StatelessWidget {
  const QuranJourneyCard({required this.base, this.kids = false, super.key});

  /// The journey ('/kids/journey'); the Quran screen is under it.
  final String base;
  final bool kids;

  @override
  Widget build(BuildContext context) {
    final learnerId = context.learnerId();
    return _WhenYes(
      askKey: learnerId ?? '',
      ask: (c) => c.read<QuranRepository>().follows(learnerId: learnerId),
      builder: (context) => _card(context),
    );
  }

  Widget _card(BuildContext context) {
    final l = context.l10n;
    void open() => context.push('$base/quran');
    if (kids) {
      return Button3d(
        key: const Key('quran-card'),
        color: Colors.white,
        radius: 24,
        depth: 5,
        padding: const EdgeInsets.all(14),
        label: '${l.quranTitleKids}. ${l.quranCardKids}',
        onTap: open,
        child: ExcludeSemantics(
          child: Row(
            children: [
              const Icon(Icons.menu_book_rounded, color: gwInk, size: 34),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.quranTitleKids, style: gwText(context, 18)),
                    Text(l.quranCardKids, style: gwText(context, 14, weight: FontWeight.w700)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: gwInk),
            ],
          ),
        ),
      );
    }
    return Semantics(
      button: true,
      label: '${l.quranTitle}: ${l.quranCardLead}',
      excludeSemantics: true,
      child: LmsCard(
        key: const Key('quran-card'),
        onTap: open,
        child: Row(
          children: [
            const IconBadge(Icons.menu_book_rounded, size: 48),
            const Gap(Space.m),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.quranTitle, style: context.type.cardTitle),
                  const Gap(Space.xs),
                  Text(l.quranCardLead, style: context.type.caption),
                ],
              ),
            ),
            const Gap(Space.s),
            Icon(Icons.chevron_right_rounded, color: context.colors.faint),
          ],
        ),
      ),
    );
  }
}

/// The teacher's way from a lesson to its Quran portion, shown once the API says the lesson follows a Quran plan;
/// [builder] draws the button the place it stands in wants.
class QuranLessonEntry extends StatelessWidget {
  const QuranLessonEntry({required this.lessonId, required this.builder, this.onOpen, super.key});

  final String lessonId;
  final Widget Function(BuildContext context, String label, VoidCallback open) builder;

  /// What opening does, when not simply the screen (a sheet closes first).
  final VoidCallback? onOpen;

  @override
  Widget build(BuildContext context) => _WhenYes(
    askKey: lessonId,
    ask: (c) => c.read<QuranRepository>().hasPortion(lessonId),
    builder: (context) => KeyedSubtree(
      key: Key('quran-lesson-$lessonId'),
      child: builder(context, context.l10n.quranLessonOpen, onOpen ?? () => openQuranLesson(context, lessonId)),
    ),
  );
}

/// The examiner's queue in a teacher's More, a group of its own shown only to someone with exams booked or recorded.
class QuranExaminerGroup extends StatelessWidget {
  const QuranExaminerGroup({super.key});

  @override
  Widget build(BuildContext context) => _WhenYes(
    askKey: 'examiner',
    ask: (c) => c.read<QuranRepository>().examines(),
    builder: (context) => MenuGroup(
      title: context.l10n.quranTitle,
      children: [
        MenuRow(
          key: const Key('open-quran-exams'),
          icon: Icons.fact_check_outlined,
          label: context.l10n.quranExaminerTitle,
          onTap: () => context.go('/teach/more/quran-exams'),
        ),
      ],
    ),
  );
}
