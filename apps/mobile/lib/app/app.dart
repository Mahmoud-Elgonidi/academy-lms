import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/session_cubit.dart';
import '../core/l10n/l10n.dart';
import '../core/push/push.dart';
import '../core/time/clock_style.dart';
import '../features/auth/presentation/bloc/sign_in_cubit.dart';
import '../shared/motion/juice.dart';
import 'di/services.dart';
import 'router/router.dart';
import 'router/targets.dart';
import 'theme/app_theme.dart';

/// One app for every academy: the open account picks the colours (its
/// academy's branding), the look (teacher, guardian, or a student's stage),
/// the language and how a clock is written.
class AcademyApp extends StatefulWidget {
  const AcademyApp({required this.services, required this.session, super.key});

  final Services services;
  final SessionCubit session;

  @override
  State<AcademyApp> createState() => _AcademyAppState();
}

class _AcademyAppState extends State<AcademyApp> {
  late final GoRouter _router = buildRouter(widget.session);
  final _messenger = GlobalKey<ScaffoldMessengerState>();
  final _subs = <StreamSubscription<Object?>>[];

  /// The live socket follows the open account, and only while the app is in front.
  late final AppLifecycleListener _life = AppLifecycleListener(
    onResume: () => unawaited(widget.services.live.foreground(true)),
    onPause: () => unawaited(widget.services.live.foreground(false)),
  );

  @override
  void initState() {
    super.initState();
    _life;
    _subs.add(widget.session.stream.listen(_follow));
    _follow(widget.session.state);
    final push = Push.instance;
    _subs
      ..add(push.opened.listen(_open))
      ..add(push.received.listen(_show));
    final first = push.pending;
    if (first != null) {
      push.pending = null;
      // After the session has opened the account.
      WidgetsBinding.instance.addPostFrameCallback((_) => Future<void>.delayed(const Duration(milliseconds: 600), () => _open(first)));
    }
  }

  void _follow(SessionState state) =>
      unawaited(widget.services.live.follow(state.status == SessionStatus.signedIn ? state.current?.id : null));

  @override
  void dispose() {
    _life.dispose();
    // No socket or timer outlives the app (or a test).
    unawaited(widget.services.live.follow(null));
    for (final s in _subs) {
      unawaited(s.cancel());
    }
    _router.dispose();
    super.dispose();
  }

  /// A tapped push opens where it belongs, in its own account.
  void _open(Map<String, String> data) => unawaited(openMessage(widget.session, _router, data));

  /// A push while the app is open: a line at the bottom, with a way in.
  void _show(({String title, String body, Map<String, String> data}) m) {
    final l = lookupAppLocalizations(Locale(widget.session.state.locale));
    _messenger.currentState?.showSnackBar(
      SnackBar(
        content: Text([m.title, m.body].where((t) => t.isNotEmpty).join('\n')),
        action: SnackBarAction(label: l.openIt, onPressed: () => _open(m.data)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => widget.services.provide(
    child: MultiBlocProvider(
      providers: [
        BlocProvider.value(value: widget.session),
        BlocProvider(create: (_) => SignInCubit(widget.services.auth, widget.session)),
      ],
      child: BlocConsumer<SessionCubit, SessionState>(
        listenWhen: (a, b) => (b.notice != null && a.notice != b.notice) || a.sounds != b.sounds,
        listener: (context, state) {
          Juice.sounds = state.sounds;
          if (state.notice == null) return;
          final l = lookupAppLocalizations(Locale(state.locale));
          final text = switch (state.notice!) {
            SessionNotice.expired => l.noticeExpired,
            SessionNotice.signedOut => l.noticeSignedOut,
            SessionNotice.notAdded => [
              l.notAddedTitle(state.notAdded.length),
              for (final a in state.notAdded) l.notAddedLine(a.name, state.locale == 'ar' ? a.academyAr : a.academyEn, a.reason),
            ].join('\n'),
          };
          _messenger.currentState?.showSnackBar(SnackBar(content: Text(text)));
          context.read<SessionCubit>().noticeShown();
        },
        buildWhen: (a, b) => a.branding != b.branding || a.locale != b.locale || a.stage != b.stage || a.timeFormat != b.timeFormat,
        builder: (context, state) {
          // Set here rather than in the listener: the first build (and a
          // test's new app) takes it too, before anything below writes a clock.
          ClockStyle.format = state.timeFormat;
          final use24 = ClockStyle.use24;
          return MaterialApp.router(
            onGenerateTitle: (c) => c.l10n.appName,
            debugShowCheckedModeBanner: false,
            scaffoldMessengerKey: _messenger,
            theme: buildTheme(state.branding, state.stage),
            locale: Locale(state.locale),
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
              _ClockWay(state.timeFormat),
            ],
            // Flutter's own time pickers and TimeOfDay.format write the same way.
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context).copyWith(alwaysUse24HourFormat: use24),
              child: child ?? const SizedBox.shrink(),
            ),
            routerConfig: _router,
          );
        },
      ),
    ),
  );
}

/// Clocks are written from [ClockStyle], not read from the widget tree, so a
/// change of the way reloads the app's localizations, as a change of
/// language does: every text that reads them (all of them) is written again.
class _ClockWay extends LocalizationsDelegate<TimeFormat> {
  const _ClockWay(this.format);

  final TimeFormat format;

  @override
  bool isSupported(Locale locale) => true;

  @override
  Future<TimeFormat> load(Locale locale) => SynchronousFuture(format);

  @override
  bool shouldReload(_ClockWay old) => old.format != format;
}
