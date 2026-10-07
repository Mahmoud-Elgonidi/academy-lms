import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/auth/session_cubit.dart';
import '../../../../core/network/api_problem.dart';
import '../../data/auth_repository.dart';
import '../../domain/sign_in_models.dart';

class SignInState extends Equatable {
  const SignInState({this.contact = '', this.sent, this.discovery, this.selected = const {}, this.busy = false, this.problem});

  final String contact;
  final SentCode? sent;
  final Discovery? discovery;

  /// Ids of the found accounts to add.
  final Set<String> selected;
  final bool busy;
  final ApiProblem? problem;

  SignInState copyWith({
    String? contact,
    SentCode? Function()? sent,
    Discovery? Function()? discovery,
    Set<String>? selected,
    bool? busy,
    ApiProblem? Function()? problem,
  }) => SignInState(
    contact: contact ?? this.contact,
    sent: sent != null ? sent() : this.sent,
    discovery: discovery != null ? discovery() : this.discovery,
    selected: selected ?? this.selected,
    busy: busy ?? this.busy,
    problem: problem != null ? problem() : this.problem,
  );

  @override
  List<Object?> get props => [contact, sent, discovery, selected, busy, problem];
}

/// The sign-in flow: contact → code → the accounts it found → signed in.
/// Each step returns whether it worked so the page can move on.
class SignInCubit extends Cubit<SignInState> {
  SignInCubit(this._repo, this._session) : super(const SignInState());

  final AuthRepository _repo;
  final SessionCubit _session;

  Future<bool> sendCode(String contact, {String? channel}) => _run(() async {
    final sent = await _repo.sendCode(contact, channel: channel);
    emit(state.copyWith(contact: contact, sent: () => sent, discovery: () => null));
  });

  Future<bool> resend({bool bySms = false}) => sendCode(state.contact, channel: bySms ? 'sms' : null);

  Future<bool> verify(String code) {
    final sent = state.sent;
    if (sent == null) return Future.value(false);
    return _run(() async {
      final discovery = await _repo.verify(sent.challengeId, code.trim());
      emit(
        state.copyWith(
          discovery: () => discovery,
          selected: {
            for (final a in discovery.accounts)
              if (a.hasAppArea) a.id,
          },
        ),
      );
    });
  }

  void toggle(String id) {
    final selected = {...state.selected};
    if (!selected.remove(id)) selected.add(id);
    emit(state.copyWith(selected: selected));
  }

  /// Signs in to each chosen account; the first one opens.
  Future<bool> addSelected() {
    final discovery = state.discovery;
    if (discovery == null || state.selected.isEmpty) return Future.value(false);
    return _run(() async {
      final chosen = discovery.accounts.where((a) => state.selected.contains(a.id)).toList();
      final accounts = [for (final found in chosen) await _repo.signInFound(discovery, found)];
      for (final account in accounts.reversed) {
        await _session.add(account);
      }
      emit(const SignInState());
    });
  }

  Future<bool> signInWithPassword({required String academy, required String login, required String password}) => _run(() async {
    final account = await _repo.signInWithPassword(academy: academy.trim().toLowerCase(), login: login.trim(), password: password);
    await _session.add(account);
    emit(const SignInState());
  });

  void clearProblem() => emit(state.copyWith(problem: () => null));

  void restart() => emit(const SignInState());

  Future<bool> _run(Future<void> Function() step) async {
    if (state.busy) return false;
    emit(state.copyWith(busy: true, problem: () => null));
    try {
      await step();
      emit(state.copyWith(busy: false));
      return true;
    } on ApiProblem catch (problem) {
      emit(state.copyWith(busy: false, problem: () => problem));
      return false;
    }
  }
}
