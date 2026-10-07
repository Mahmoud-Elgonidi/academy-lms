import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/session_cubit.dart';
import '../core/l10n/l10n.dart';
import '../features/auth/presentation/bloc/sign_in_cubit.dart';
import 'di/services.dart';
import 'router/router.dart';
import 'theme/app_theme.dart';

/// One app for every academy: the open account picks the colours (its
/// academy's branding), the look (teacher, guardian, or a student's stage)
/// and the language.
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

  @override
  void dispose() {
    _router.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.services.provide(
    child: MultiBlocProvider(
      providers: [
        BlocProvider.value(value: widget.session),
        BlocProvider(create: (_) => SignInCubit(widget.services.auth, widget.session)),
      ],
      child: BlocConsumer<SessionCubit, SessionState>(
        listenWhen: (a, b) => b.notice != null && a.notice != b.notice,
        listener: (context, state) {
          final l = lookupAppLocalizations(Locale(state.locale));
          final text = switch (state.notice!) {
            SessionNotice.expired => l.noticeExpired,
            SessionNotice.signedOut => l.noticeSignedOut,
          };
          _messenger.currentState?.showSnackBar(SnackBar(content: Text(text)));
          context.read<SessionCubit>().noticeShown();
        },
        buildWhen: (a, b) => a.branding != b.branding || a.locale != b.locale || a.stage != b.stage,
        builder: (context, state) => MaterialApp.router(
          onGenerateTitle: (c) => c.l10n.appName,
          debugShowCheckedModeBanner: false,
          scaffoldMessengerKey: _messenger,
          theme: buildTheme(state.branding, state.stage),
          locale: Locale(state.locale),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          routerConfig: _router,
        ),
      ),
    ),
  );
}
