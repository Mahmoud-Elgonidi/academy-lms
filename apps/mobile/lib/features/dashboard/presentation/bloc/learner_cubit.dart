import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/api_problem.dart';
import '../../data/family_repository.dart';
import '../../domain/learner.dart';

/// family: a guardian picks which child the tabs show; self: a student's own area.
enum LearnerScope { family, self }

class LearnerState extends Equatable {
  const LearnerState({this.learners = const [], this.selectedId, this.loaded = false, this.problem});

  final List<Learner> learners;
  final String? selectedId;
  final bool loaded;
  final ApiProblem? problem;

  Learner? get selected => learners.where((l) => l.id == selectedId).firstOrNull ?? learners.firstOrNull;

  @override
  List<Object?> get props => [learners.map((l) => l.id).toList(), selectedId, loaded, problem];
}

/// The learner every tab of the family look is about.
class LearnerCubit extends Cubit<LearnerState> {
  LearnerCubit(this._repo, this.scope) : super(const LearnerState());

  final FamilyRepository _repo;
  final LearnerScope scope;

  Future<void> load() async {
    try {
      final learners = await _repo.learners();
      if (!isClosed) emit(LearnerState(learners: learners, selectedId: state.selectedId ?? learners.firstOrNull?.id, loaded: true));
    } on ApiProblem catch (p) {
      if (!isClosed) emit(LearnerState(learners: state.learners, selectedId: state.selectedId, loaded: true, problem: p));
    }
  }

  void select(String id) => emit(LearnerState(learners: state.learners, selectedId: id, loaded: true));
}
