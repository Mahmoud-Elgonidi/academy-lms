import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/api_problem.dart';
import '../../../support/data/support_repository.dart';
import '../../../support/domain/support.dart';

class StartPlanState extends Equatable {
  const StartPlanState({this.options, this.loading = true, this.problem, this.sending = false, this.sentId, this.sendProblem});

  /// What the family may write about (null until read; closed when nobody here may write).
  final TicketOptions? options;
  final bool loading;
  final ApiProblem? problem;
  final bool sending;

  /// The request sent: its ticket, where the academy's answer comes.
  final String? sentId;
  final ApiProblem? sendProblem;

  /// The family cannot write to the academy from the app: they reach it the usual way.
  bool get noHelp => options != null && options!.closed;

  StartPlanState copyWith({
    TicketOptions? Function()? options,
    bool? loading,
    ApiProblem? Function()? problem,
    bool? sending,
    String? Function()? sentId,
    ApiProblem? Function()? sendProblem,
  }) => StartPlanState(
    options: options == null ? this.options : options(),
    loading: loading ?? this.loading,
    problem: problem == null ? this.problem : problem(),
    sending: sending ?? this.sending,
    sentId: sentId == null ? this.sentId : sentId(),
    sendProblem: sendProblem == null ? this.sendProblem : sendProblem(),
  );

  @override
  List<Object?> get props => [options, loading, problem, sending, sentId, sendProblem];
}

/// "Start with this plan" until signing up and paying come to the app (J3):
/// an honest request to the academy through Help (`support/tickets`), about
/// the learner and linked to the trial lesson when Help offers it, in money
/// matters ("billing") when the family may write about them, else "other".
/// The academy's team sets up the course and its invoice (with the offer
/// while it is open) and answers in the ticket.
class StartPlanCubit extends Cubit<StartPlanState> {
  StartPlanCubit(this._support, {required this.learnerId, required this.lessonId}) : super(const StartPlanState());

  final SupportRepository _support;

  /// The learner the request is about (null: the student themself).
  final String? learnerId;

  /// The trial lesson, linked when Help offers it.
  final String lessonId;

  Future<void> load() async {
    emit(state.copyWith(loading: true, problem: () => null));
    try {
      final options = await _support.options(learnerId: learnerId);
      if (!isClosed) emit(state.copyWith(options: () => options, loading: false));
    } on ApiProblem catch (p) {
      if (isClosed) return;
      // Help is not this family's to use here (a look without it, an area left out): the usual ways, said calmly.
      final none = p.status == 403 || p.isNotFound;
      emit(
        none
            ? state.copyWith(
                options: () => const TicketOptions(kinds: [], lessons: []),
                loading: false,
              )
            : state.copyWith(loading: false, problem: () => p),
      );
    }
  }

  /// The kind of request: money matters when offered, else anything else, else the first there is.
  String? get kind {
    final kinds = state.options?.kinds.map((k) => k.key).toList() ?? const <String>[];
    if (kinds.contains('billing')) return 'billing';
    if (kinds.contains('other')) return 'other';
    return kinds.where((k) => k != 'complaint').firstOrNull;
  }

  /// Sends the request; true once it reached the academy.
  Future<bool> send({required String subject, required String body, String note = ''}) async {
    final kind = this.kind;
    if (kind == null || state.sending) return false;
    emit(state.copyWith(sending: true, sendProblem: () => null));
    final linkable = state.options?.lessons.any((l) => l.id == lessonId) ?? false;
    try {
      final ticket = await _support.open(
        kind: kind,
        subject: subject,
        body: note.trim().isEmpty ? body : '$body\n\n${note.trim()}',
        learnerId: learnerId,
        lessonId: linkable ? lessonId : null,
      );
      if (!isClosed) emit(state.copyWith(sending: false, sentId: () => ticket.summary.id));
      return true;
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(sending: false, sendProblem: () => p));
      return false;
    }
  }
}
