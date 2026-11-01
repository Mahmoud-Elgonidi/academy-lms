import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/api_problem.dart';
import '../../data/trial_reports_repository.dart';
import '../../domain/trial.dart';
import '../../domain/trial_report.dart';

class NextTrialState extends Equatable {
  const NextTrialState({
    this.slots,
    this.loading = true,
    this.problem,
    this.chosen,
    this.busy = false,
    this.note,
    this.refusal,
    this.booked,
    this.bookProblem,
  });

  /// The times on offer (null until the first answer).
  final List<TrialSlot>? slots;
  final bool loading;

  /// The times could not be read (the network, the server).
  final ApiProblem? problem;
  final TrialSlot? chosen;

  /// Booking now.
  final bool busy;

  /// The API's words over fresh times: the chosen one was taken meanwhile.
  final String? note;

  /// Why one more trial cannot be booked now, in the API's own sentence (no times then).
  final String? refusal;
  final NextTrialBooked? booked;

  /// Booking failed for a reason that decided nothing (the network, a wait, the server): try again.
  final ApiProblem? bookProblem;

  List<SlotDay> get days => SlotDay.group(slots ?? const []);

  NextTrialState copyWith({
    List<TrialSlot>? Function()? slots,
    bool? loading,
    ApiProblem? Function()? problem,
    TrialSlot? Function()? chosen,
    bool? busy,
    String? Function()? note,
    String? Function()? refusal,
    NextTrialBooked? Function()? booked,
    ApiProblem? Function()? bookProblem,
  }) => NextTrialState(
    slots: slots == null ? this.slots : slots(),
    loading: loading ?? this.loading,
    problem: problem == null ? this.problem : problem(),
    chosen: chosen == null ? this.chosen : chosen(),
    busy: busy ?? this.busy,
    note: note == null ? this.note : note(),
    refusal: refusal == null ? this.refusal : refusal(),
    booked: booked == null ? this.booked : booked(),
    bookProblem: bookProblem == null ? this.bookProblem : bookProblem(),
  );

  @override
  List<Object?> get props => [slots, loading, problem, chosen, busy, note, refusal, booked, bookProblem];
}

/// One more free trial from a trial (docs/architecture/02, section 27, J2):
/// the second with another teacher after a report, or a new time after one
/// that did not take place. The times come from the API (any teacher but
/// the first for the second trial); a refusal is the API's own sentence and
/// shows instead of the times; a time taken meanwhile brings fresh times
/// with a line over them. The booking is sent once (`Idempotency-Key`).
class NextTrialCubit extends Cubit<NextTrialState> {
  NextTrialCubit(this._repo, this.session) : super(const NextTrialState());

  final TrialReportsRepository _repo;

  /// The trial it follows (its lesson's id).
  final String session;

  Future<void> load() async {
    emit(state.copyWith(loading: true, problem: () => null));
    try {
      final slots = await _repo.slots(session);
      if (isClosed) return;
      final chosen = state.chosen;
      emit(
        state.copyWith(
          slots: () => slots,
          loading: false,
          refusal: () => null,
          chosen: () => chosen != null && slots.contains(chosen) ? chosen : null,
        ),
      );
    } on ApiProblem catch (p) {
      if (isClosed) return;
      final why = p.isValidation ? p.field('starts_at') ?? p.message : null;
      emit(
        why != null
            ? state.copyWith(loading: false, refusal: () => why, slots: () => const [])
            : state.copyWith(loading: false, problem: () => p),
      );
    }
  }

  void choose(TrialSlot slot) => emit(state.copyWith(chosen: () => slot, bookProblem: () => null));

  /// Books the chosen time; true once it is booked.
  Future<bool> book() async {
    final slot = state.chosen;
    if (slot == null || state.busy) return false;
    emit(state.copyWith(busy: true, bookProblem: () => null, note: () => null));
    try {
      final booked = await _repo.book(session, slot.startsAt);
      if (!isClosed) emit(state.copyWith(busy: false, booked: () => booked));
      return true;
    } on ApiProblem catch (p) {
      if (isClosed) return false;
      final why = p.isValidation ? p.field('starts_at') ?? p.message : null;
      if (why == null) {
        emit(state.copyWith(busy: false, bookProblem: () => p));
        return false;
      }
      // Taken meanwhile, or no longer allowed: the fresh times tell which.
      await load();
      if (isClosed) return false;
      final stillThere = state.refusal == null && (state.slots ?? const []).contains(slot);
      if (state.refusal != null) {
        emit(state.copyWith(busy: false, chosen: () => null));
      } else if (stillThere) {
        // The time is still on offer: the refusal is about this learner or number.
        emit(state.copyWith(busy: false, refusal: () => why, chosen: () => null));
      } else {
        emit(state.copyWith(busy: false, chosen: () => null, note: () => why));
      }
      return false;
    }
  }
}
