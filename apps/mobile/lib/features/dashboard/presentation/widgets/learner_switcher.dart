import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../bloc/learner_cubit.dart';

/// A guardian's children as chips: the tabs follow the one picked. Hidden
/// with a single learner.
class LearnerSwitcher extends StatelessWidget {
  const LearnerSwitcher({super.key});

  @override
  Widget build(BuildContext context) => BlocBuilder<LearnerCubit, LearnerState>(
    builder: (context, state) {
      if (state.learners.length < 2) return const SizedBox.shrink();
      return SizedBox(
        height: 48,
        child: ListView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 18),
          children: [
            for (final learner in state.learners)
              Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: ChoiceChip(
                  avatar: CircleAvatar(
                    backgroundColor: context.colors.brand,
                    child: Text(learner.firstName.characters.first, style: const TextStyle(color: Colors.white, fontSize: 12)),
                  ),
                  label: Text(learner.firstName),
                  selected: learner.id == state.selected?.id,
                  showCheckmark: false,
                  onSelected: (_) => context.read<LearnerCubit>().select(learner.id),
                ),
              ),
          ],
        ),
      );
    },
  );
}

extension LearnerOf on BuildContext {
  /// The learner a family screen is about: the picked child for a guardian,
  /// null (the student themself) in a student's own area.
  String? learnerId() {
    final cubit = read<LearnerCubit>();
    return cubit.scope == LearnerScope.family ? cubit.state.selected?.id : null;
  }
}
