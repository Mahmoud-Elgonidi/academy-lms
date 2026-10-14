import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../bloc/learner_cubit.dart';

/// A guardian's children as pills: the tabs follow the one picked (a check
/// on it). Hidden with a single learner.
class LearnerSwitcher extends StatelessWidget {
  const LearnerSwitcher({this.padding = const EdgeInsets.symmetric(horizontal: 18), super.key});

  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) => BlocBuilder<LearnerCubit, LearnerState>(
    builder: (context, state) {
      if (state.learners.length < 2) return const SizedBox.shrink();
      return SizedBox(
        height: 48,
        child: ListView(
          scrollDirection: Axis.horizontal,
          padding: padding,
          children: [
            for (final learner in state.learners)
              Padding(
                padding: const EdgeInsetsDirectional.only(end: Space.s),
                child: _LearnerPill(
                  key: Key('learner-${learner.id}'),
                  name: learner.firstName,
                  selected: learner.id == state.selected?.id,
                  onTap: () => context.read<LearnerCubit>().select(learner.id),
                ),
              ),
          ],
        ),
      );
    },
  );
}

class _LearnerPill extends StatelessWidget {
  const _LearnerPill({required this.name, required this.selected, required this.onTap, super.key});

  final String name;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Semantics(
      selected: selected,
      button: true,
      child: Material(
        color: selected ? c.brandSoft : c.card,
        shape: StadiumBorder(
          side: BorderSide(color: selected ? c.brand : c.line, width: selected ? 1.5 : 1),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: selected ? null : PressScale.withPress(context, onTap),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minWidth: 64, minHeight: 44),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: Space.l),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  AnimatedSize(
                    duration: context.calm ? Duration.zero : const Duration(milliseconds: 200),
                    child: selected
                        ? Padding(
                            padding: const EdgeInsetsDirectional.only(end: 6),
                            child: Icon(Icons.check_rounded, size: 18, color: c.brandDeep),
                          )
                        : const SizedBox.shrink(),
                  ),
                  Text(
                    name,
                    style: TextStyle(
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                      fontSize: 15,
                      color: selected ? c.brandDeep : c.ink,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// "About Ziad ⌄": on the screens over the tabs (messages, help, telling
/// the academy), which child they are about; a tap picks another. Nothing
/// with a single learner.
class LearnerChip extends StatelessWidget {
  const LearnerChip({super.key});

  @override
  Widget build(BuildContext context) => BlocBuilder<LearnerCubit, LearnerState>(
    builder: (context, state) {
      final learner = state.selected;
      if (state.learners.length < 2 || learner == null) return const SizedBox.shrink();
      final c = context.colors;
      final l = context.l10n;
      return Padding(
        padding: const EdgeInsetsDirectional.only(end: Space.s),
        child: Material(
          color: c.card,
          shape: StadiumBorder(side: BorderSide(color: c.line)),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            key: const Key('learner-chip'),
            onTap: () => _pick(context, state),
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 40),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: Space.m),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l.aboutLearner(learner.firstName),
                      style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: c.ink),
                    ),
                    const Gap(Space.xs),
                    Icon(Icons.expand_more_rounded, size: 20, color: c.ink),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
    },
  );

  Future<void> _pick(BuildContext context, LearnerState state) async {
    final cubit = context.read<LearnerCubit>();
    final picked = await showActionSheet<String>(
      context,
      title: context.l10n.pickLearner,
      builder: (ctx) => SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final learner in state.learners)
              MenuRow(
                key: Key('pick-${learner.id}'),
                label: learner.name,
                value: learner.id == state.selected?.id ? ctx.l10n.pickedNow : null,
                onTap: () => Navigator.pop(ctx, learner.id),
              ),
            const Gap(Space.l),
          ],
        ),
      ),
    );
    if (picked != null) cubit.select(picked);
  }
}

extension LearnerOf on BuildContext {
  /// The learner a family screen is about: the picked child for a guardian,
  /// null (the student themself) in a student's own area.
  String? learnerId() {
    final cubit = read<LearnerCubit>();
    return cubit.scope == LearnerScope.family ? cubit.state.selected?.id : null;
  }
}
