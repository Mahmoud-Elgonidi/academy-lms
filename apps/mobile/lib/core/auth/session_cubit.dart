import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../app/theme/branding.dart';
import '../../app/theme/stage.dart';
import '../app_policy.dart';
import '../prefs/app_prefs.dart';
import '../push/push.dart';
import 'account.dart';
import 'account_store.dart';
import 'session_holder.dart';
import 'session_repository.dart';

enum SessionStatus { starting, signedOut, signedIn }

/// Something to tell the person once (a snack bar), then forget.
enum SessionNotice { expired, signedOut }

class SessionState extends Equatable {
  const SessionState({
    this.status = SessionStatus.starting,
    this.accounts = const [],
    this.current,
    this.branding = Branding.fallback,
    this.locale = 'ar',
    this.sounds = true,
    this.seenIntro = true,
    this.deviceTimezone,
    this.clock,
    this.me,
    this.notice,
    this.policy,
    this.appVersion,
  });

  final SessionStatus status;
  final List<Account> accounts;
  final Account? current;
  final Branding branding;
  final String locale;

  /// Little sounds on taps and rewards.
  final bool sounds;

  /// The slides about the app were seen (or the person signed in before they came).
  final bool seenIntro;
  final String? deviceTimezone;

  /// The zone this account chose to keep instead of the phone's (null: the phone's).
  final String? clock;
  final Me? me;
  final SessionNotice? notice;

  /// The platform's word on app versions (with the academy's look).
  final AppPolicy? policy;

  /// This app's version (null in tests: never asked to update).
  final String? appVersion;

  /// This app is older than the oldest still served: it asks to be updated first.
  bool get updateRequired => policy != null && appVersion != null && policy!.tooOld(appVersion!);

  /// The zone times are asked in.
  String? get timezone => clock ?? deviceTimezone;

  /// The look of the open account's area: a student's stage; classic for
  /// teachers, guardians and adults.
  Stage get stage {
    final a = current;
    if (a == null || a.isTeacher || a.isGuardian) return Stage.classic;
    return Stage.parse(a.stage);
  }

  /// The phone's clock differs from the profile's and the person has not chosen yet.
  bool get offerClock => me?.mismatch == true && clock == null && deviceTimezone != null;

  SessionState copyWith({
    SessionStatus? status,
    List<Account>? accounts,
    Account? Function()? current,
    Branding? branding,
    String? locale,
    bool? sounds,
    bool? seenIntro,
    String? Function()? clock,
    Me? Function()? me,
    SessionNotice? Function()? notice,
    AppPolicy? policy,
  }) => SessionState(
    status: status ?? this.status,
    accounts: accounts ?? this.accounts,
    current: current != null ? current() : this.current,
    branding: branding ?? this.branding,
    locale: locale ?? this.locale,
    sounds: sounds ?? this.sounds,
    seenIntro: seenIntro ?? this.seenIntro,
    deviceTimezone: deviceTimezone,
    clock: clock != null ? clock() : this.clock,
    me: me != null ? me() : this.me,
    notice: notice != null ? notice() : this.notice,
    policy: policy ?? this.policy,
    appVersion: appVersion,
  );

  @override
  List<Object?> get props => [
    status,
    accounts,
    current,
    branding,
    locale,
    sounds,
    seenIntro,
    deviceTimezone,
    clock,
    me?.mismatch,
    me?.timezone,
    notice,
    policy?.minVersion,
  ];
}

/// The accounts on this device and the open one. Everything that depends on
/// who is signed in (the router, the theme, the API's headers) follows this.
class SessionCubit extends Cubit<SessionState> {
  SessionCubit({
    required this._holder,
    required this._store,
    required this._prefs,
    required SessionRepository repository,
    String? deviceTimezone,
    this._deviceLocale,
    String? appVersion,
    this._push,
  }) : _repo = repository,
       super(SessionState(deviceTimezone: deviceTimezone, appVersion: appVersion)) {
    _holder
      ..timezone = deviceTimezone
      ..expired = expired;
    _push?.refreshed.listen((_) => unawaited(_registerPush()));
  }

  final SessionHolder _holder;
  final AccountStore _store;
  final AppPrefs _prefs;
  final SessionRepository _repo;
  final String? _deviceLocale;
  final PushTokens? _push;

  Future<void> start() async {
    final locale = await _prefs.locale() ?? (_deviceLocale == 'en' ? 'en' : 'ar');
    final sounds = await _prefs.sounds();
    final (saved, currentId) = await _store.load();
    // Someone who signed in on this phone before the slides came has no need of them.
    var seenIntro = await _prefs.seenIntro();
    if (!seenIntro && saved.isNotEmpty) {
      await _prefs.setSeenIntro();
      seenIntro = true;
    }
    final now = DateTime.now();
    final accounts = saved.where((a) => !a.expired(now)).toList();
    final current = accounts.where((a) => a.id == currentId).firstOrNull ?? accounts.firstOrNull;
    if (accounts.length != saved.length) {
      await _store.save(accounts, current?.id);
    }
    _set(state.copyWith(locale: locale, sounds: sounds, seenIntro: seenIntro, accounts: accounts));
    await _open(current);
  }

  /// The slides about the app were seen (or skipped): they do not come again.
  Future<void> introSeen() async {
    await _prefs.setSeenIntro();
    _set(state.copyWith(seenIntro: true));
  }

  /// A new sign-in: add it (or replace the same account's old token) and open it.
  Future<void> add(Account account) async {
    final accounts = [...state.accounts.where((a) => a.id != account.id), account];
    _set(state.copyWith(accounts: accounts));
    await _store.save(accounts, account.id);
    await _open(account);
  }

  Future<void> open(String id) async {
    final account = state.accounts.where((a) => a.id == id).firstOrNull;
    if (account == null || account.id == state.current?.id) return;
    await _store.save(state.accounts, account.id);
    await _open(account);
  }

  /// Signs an account out of this device (the open one by default) and opens the next, if any.
  Future<void> signOut([String? id]) async {
    final target = state.accounts.where((a) => a.id == (id ?? state.current?.id)).firstOrNull;
    if (target == null) return;
    final pushToken = _push?.token;
    unawaited(() async {
      if (pushToken != null) await _repo.forgetDevice(academy: target.academySlug, token: target.token, pushToken: pushToken);
      await _repo.signOut(academy: target.academySlug, token: target.token);
    }());
    await _drop(target, SessionNotice.signedOut);
  }

  /// The API refused the open account's token (it ended or was signed out elsewhere).
  void expired() {
    final current = state.current;
    if (current != null) unawaited(_drop(current, SessionNotice.expired));
  }

  void noticeShown() => _set(state.copyWith(notice: () => null));

  Future<void> setLocale(String locale) async {
    if (locale == state.locale) return;
    await _prefs.setLocale(locale);
    _set(state.copyWith(locale: locale));
    if (state.current != null) {
      unawaited(_quietly(() => _repo.updateMe(locale: locale)));
    }
  }

  Future<void> setSounds(bool on) async {
    await _prefs.setSounds(on);
    _set(state.copyWith(sounds: on));
  }

  /// "Yes, use my phone's clock": the profile takes the phone's zone.
  Future<void> usePhoneClock() async {
    final zone = state.deviceTimezone;
    if (zone == null || state.current == null) return;
    await _repo.updateMe(timezone: zone);
    await _prefs.setClock(state.current!.id, null);
    _set(state.copyWith(clock: () => null, me: () => null));
    unawaited(_loadMe());
  }

  /// "Keep my profile's clock": times are asked in the profile's zone on this phone.
  Future<void> keepProfileClock() async {
    final zone = state.me?.timezone;
    final account = state.current;
    if (zone == null || account == null) return;
    await _prefs.setClock(account.id, zone);
    _set(state.copyWith(clock: () => zone, me: () => null));
  }

  /// Reloads the open account's look, stage and profile (after a sign-in, a switch, or on demand).
  Future<void> refresh() async {
    final account = state.current;
    if (account == null) return;
    await Future.wait([
      _quietly(() async {
        final json = await _repo.branding(account.academySlug);
        await _prefs.setBranding(account.academySlug, json);
        if (state.current?.id == account.id) {
          _set(state.copyWith(branding: Branding.fromJson(json), policy: AppPolicy.fromJson(json['app'])));
        }
      }),
      if (account.isFamily && !account.isTeacher)
        _quietly(() async {
          final stage = await _repo.viewerStage();
          final latest = state.current;
          if (latest?.id != account.id || latest!.stage == stage) return;
          final updated = latest.copyWith(stage: stage);
          final accounts = [for (final a in state.accounts) a.id == updated.id ? updated : a];
          await _store.save(accounts, updated.id);
          _set(state.copyWith(accounts: accounts, current: () => updated));
        }),
      _loadMe(),
      _registerPush(),
    ]);
  }

  /// This phone gets the open account's notifications (when push is up).
  Future<void> _registerPush() => _quietly(() async {
    final token = _push?.token;
    if (token == null || state.current == null) return;
    await _repo.registerDevice(platform: _push!.platform, token: token, appVersion: state.appVersion);
  });

  Future<void> _loadMe() => _quietly(() async {
    final id = state.current?.id;
    final me = await _repo.me();
    if (state.current?.id == id) _set(state.copyWith(me: () => me));
  });

  Future<void> _open(Account? next) async {
    if (next == null) {
      _set(
        state.copyWith(
          status: SessionStatus.signedOut,
          current: () => null,
          me: () => null,
          clock: () => null,
          branding: Branding.fallback,
        ),
      );
      return;
    }
    var account = next;
    // A student's first opening: learn their stage before the first frame,
    // so a child never sees the grown-up look flash by.
    if (account.isFamily && !account.isTeacher && !account.isGuardian && account.stage == null) {
      _holder.account = account;
      try {
        account = account.copyWith(stage: await _repo.viewerStage());
        final opened = account;
        final accounts = [for (final a in state.accounts) a.id == opened.id ? opened : a];
        await _store.save(accounts, opened.id);
        _set(state.copyWith(accounts: accounts));
      } on Exception {
        // Opens in the classic look; refresh() tries again.
      }
    }
    final cached = await _prefs.branding(account.academySlug);
    final clock = await _prefs.clock(account.id);
    final opened = account;
    _set(
      state.copyWith(
        status: SessionStatus.signedIn,
        current: () => opened,
        branding: cached != null ? Branding.fromJson(cached) : Branding.fromColour(opened.academyColour),
        policy: AppPolicy.fromJson(cached?['app']),
        clock: () => clock,
        me: () => null,
      ),
    );
    unawaited(refresh());
  }

  Future<void> _drop(Account account, SessionNotice notice) async {
    final accounts = state.accounts.where((a) => a.id != account.id).toList();
    final wasOpen = state.current?.id == account.id;
    final next = wasOpen ? accounts.firstOrNull : state.current;
    await _store.save(accounts, next?.id);
    await _prefs.setClock(account.id, null);
    _set(state.copyWith(accounts: accounts, notice: () => notice));
    if (wasOpen) await _open(next);
  }

  void _set(SessionState next) {
    _holder
      ..account = next.current
      ..locale = next.locale
      ..timezone = next.timezone;
    if (!isClosed) emit(next);
  }

  /// Background loads: a failure leaves what is shown (offline, or a 401 the holder already handles).
  Future<void> _quietly(Future<void> Function() load) async {
    try {
      await load();
    } on Exception {
      // Keep the cached look and profile.
    }
  }
}
