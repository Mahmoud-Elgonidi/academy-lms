import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/auth/account.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/social/social_sign_in.dart';
import '../../data/auth_repository.dart';
import '../../domain/sign_in_models.dart';

class SignInState extends Equatable {
  const SignInState({
    this.contact = '',
    this.sent,
    this.discovery,
    this.selected = const {},
    this.twoFactor,
    this.busy = false,
    this.problem,
  });

  final String contact;
  final SentCode? sent;
  final Discovery? discovery;

  /// A sign-in waiting on the authenticator app's code.
  final TwoFactorStep? twoFactor;

  /// Ids of the found accounts to add.
  final Set<String> selected;
  final bool busy;
  final ApiProblem? problem;

  SignInState copyWith({
    String? contact,
    SentCode? Function()? sent,
    Discovery? Function()? discovery,
    Set<String>? selected,
    TwoFactorStep? Function()? twoFactor,
    bool? busy,
    ApiProblem? Function()? problem,
  }) => SignInState(
    contact: contact ?? this.contact,
    sent: sent != null ? sent() : this.sent,
    discovery: discovery != null ? discovery() : this.discovery,
    selected: selected ?? this.selected,
    twoFactor: twoFactor != null ? twoFactor() : this.twoFactor,
    busy: busy ?? this.busy,
    problem: problem != null ? problem() : this.problem,
  );

  @override
  List<Object?> get props => [contact, sent, discovery, selected, twoFactor, busy, problem];
}

/// The sign-in flow: contact → code → the accounts it found → signed in.
/// Each step returns whether it worked so the page can move on; an account
/// with two-step sign-in stops on [SignInState.twoFactor] for the
/// authenticator's code ([completeTwoFactor] goes on from there).
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

  /// The phone's Google or Apple sign-in instead of a code: the same list of accounts to add.
  /// False (and nothing said) when the person cancelled.
  Future<bool> withProvider(SocialSignIn social, String provider) async {
    final credential = await social.signIn(provider);
    if (credential == null) return false;
    return _run(() async {
      final discovery = await _repo.discoverWith(credential);
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
    final chosen = discovery.accounts.where((a) => state.selected.contains(a.id)).toList();
    return _run(() => _signInFound(discovery, chosen, const []));
  }

  Future<bool> signInWithPassword({required String academy, required String login, required String password}) => _run(() async {
    final slug = academy.trim().toLowerCase();
    try {
      await _keep([await _repo.signInWithPassword(academy: slug, login: login.trim(), password: password)]);
    } on ApiProblem catch (problem) {
      final token = problem.twoFactorToken;
      if (token == null) rethrow;
      emit(
        state.copyWith(
          twoFactor: () => TwoFactorStep(token: token, academySlug: slug),
        ),
      );
    }
  });

  /// The authenticator's code (or a recovery code) for the sign-in waiting
  /// on it; then on to the accounts still chosen.
  Future<bool> completeTwoFactor(String code) {
    final step = state.twoFactor;
    if (step == null) return Future.value(false);
    return _run(() async {
      final account = await _repo.completeTwoFactor(step, code.trim());
      emit(state.copyWith(twoFactor: () => null));
      final discovery = state.discovery;
      if (step.queue.isEmpty || discovery == null) return _keep([...step.signed, account]);
      await _signInFound(discovery, step.queue, [...step.signed, account]);
    });
  }

  Future<void> _signInFound(Discovery discovery, List<FoundAccount> queue, List<Account> signed) async {
    final done = [...signed];
    for (var i = 0; i < queue.length; i++) {
      try {
        done.add(await _repo.signInFound(discovery, queue[i]));
      } on ApiProblem catch (problem) {
        final token = problem.twoFactorToken;
        if (token == null) rethrow;
        emit(
          state.copyWith(
            twoFactor: () =>
                TwoFactorStep(token: token, academySlug: queue[i].academySlug, found: queue[i], queue: queue.sublist(i + 1), signed: done),
          ),
        );
        return;
      }
    }
    await _keep(done);
  }

  /// Keeps the signed accounts on this phone together, so the first one opens.
  Future<void> _keep(List<Account> accounts) async {
    for (final account in accounts.reversed) {
      await _session.add(account);
    }
    emit(const SignInState());
  }

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
