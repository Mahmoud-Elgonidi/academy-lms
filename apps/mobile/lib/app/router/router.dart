import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session_cubit.dart';
import '../../core/l10n/l10n.dart';
import '../../features/auth/presentation/pages/accounts_page.dart';
import '../../features/auth/presentation/pages/code_page.dart';
import '../../features/auth/presentation/pages/password_page.dart';
import '../../features/auth/presentation/pages/welcome_page.dart';
import '../../features/auth/presentation/pages/web_only_page.dart';
import '../../features/billing/presentation/pages/money_page.dart';
import '../../features/classroom/presentation/pages/classroom_page.dart';
import '../../features/dashboard/presentation/bloc/learner_cubit.dart';
import '../../features/dashboard/presentation/pages/family_home_page.dart';
import '../../features/dashboard/presentation/pages/kids_home_page.dart';
import '../../features/dashboard/presentation/pages/middle_home_page.dart';
import '../../features/dashboard/presentation/pages/badges_page.dart';
import '../../features/dashboard/presentation/pages/teen_home_page.dart';
import '../../features/homework/presentation/pages/homework_page.dart';
import '../../features/profile/presentation/pages/account_page.dart';
import '../../features/roadmap/presentation/pages/progress_page.dart';
import '../../features/roadmap/presentation/pages/reports_page.dart';
import '../../features/schedule/presentation/pages/lessons_page.dart';
import '../../features/teaching/presentation/pages/earnings_page.dart';
import '../../features/teaching/presentation/pages/more_page.dart';
import '../../features/teaching/presentation/pages/students_page.dart';
import '../../features/teaching/presentation/pages/time_off_page.dart';
import '../../features/teaching/presentation/pages/today_page.dart';
import '../../features/teaching/presentation/pages/week_page.dart';
import 'areas.dart';
import 'shell.dart';

/// Every screen and who may see it. The open account decides the look: a
/// location outside its area goes to the area's home, and signing out goes
/// to the welcome screen.
GoRouter buildRouter(SessionCubit session) {
  GoRoute page(String path, Widget Function(GoRouterState s) build, {List<RouteBase> routes = const []}) =>
      GoRoute(path: path, builder: (_, s) => build(s), routes: routes);

  StatefulShellBranch branch(String path, Widget Function(GoRouterState s) build, {List<RouteBase> routes = const []}) =>
      StatefulShellBranch(routes: [page(path, build, routes: routes)]);

  List<RouteBase> signIn(String base) => [
    page(
      base,
      (_) => WelcomePage(base: base),
      routes: [
        page('code', (_) => CodePage(base: base)),
        page('accounts', (_) => AccountsPage(base: base)),
        page('password', (_) => PasswordPage(base: base)),
      ],
    ),
  ];

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: _StreamListenable(session.stream),
    redirect: (context, state) {
      final s = session.state;
      final loc = state.matchedLocation;
      final welcome = loc.startsWith('/welcome');
      switch (s.status) {
        case SessionStatus.starting:
          return loc == '/splash' ? null : '/splash';
        case SessionStatus.signedOut:
          return welcome ? null : '/welcome';
        case SessionStatus.signedIn:
          final area = Area.of(s);
          if (loc == '/' || loc == '/splash' || welcome) return area.home;
          final elsewhere = Area.values.any((a) => a != area && loc.startsWith(a.prefix));
          return elsewhere ? area.home : null;
      }
    },
    routes: [
      page('/', (_) => const _Splash()),
      page('/splash', (_) => const _Splash()),
      ...signIn('/welcome'),
      ...signIn('/add'),
      page('/web-only', (_) => const WebOnlyPage()),
      page('/account', (_) => const AccountPage(standalone: true)),
      page('/classroom/:id', (s) => ClassroomPage(lessonId: s.pathParameters['id']!, ticket: s.extra)),
      page('/reports/:enrollment', (s) => ReportsPage(enrollmentId: s.pathParameters['enrollment'], title: s.uri.queryParameters['title'])),

      // Guardians and adult students: the academy's own look.
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => BlocProvider(
          create: (c) => LearnerCubit(c.read(), LearnerScope.family)..load(),
          child: Builder(
            builder: (context) => AppShell(
              shell: shell,
              tabs: [
                ShellTab(Icons.home_outlined, Icons.home_rounded, context.l10n.navHome),
                ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, context.l10n.navLessons),
                ShellTab(Icons.insights_outlined, Icons.insights_rounded, context.l10n.navProgress),
                ShellTab(Icons.account_balance_wallet_outlined, Icons.account_balance_wallet_rounded, context.l10n.navMoney),
                ShellTab(Icons.person_outline_rounded, Icons.person_rounded, context.l10n.navMe),
              ],
            ),
          ),
        ),
        branches: [
          branch('/family/home', (_) => const FamilyHomePage()),
          branch('/family/lessons', (_) => const LessonsPage()),
          branch('/family/progress', (_) => const ProgressPage()),
          branch('/family/money', (_) => const MoneyPage()),
          branch('/family/me', (_) => const AccountPage()),
        ],
      ),

      // A child in the kids' look: three big tabs.
      _studentShell(
        tabs: (l) => [
          ShellTab(Icons.map_outlined, Icons.map_rounded, l.navJourney),
          ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, l.navMyLessons),
          ShellTab(Icons.mic_none_rounded, Icons.mic_rounded, l.navMyHomework),
        ],
        branches: [
          branch('/kids/journey', (_) => const KidsHomePage()),
          branch('/kids/lessons', (_) => const LessonsPage()),
          branch('/kids/homework', (_) => const HomeworkPage()),
        ],
      ),

      _studentShell(
        tabs: (l) => [
          ShellTab(Icons.home_outlined, Icons.home_rounded, l.navHome),
          ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, l.navMyLessons),
          ShellTab(Icons.assignment_outlined, Icons.assignment_rounded, l.navMyHomework),
          ShellTab(Icons.emoji_events_outlined, Icons.emoji_events_rounded, l.navBadges),
        ],
        branches: [
          branch('/middle/home', (_) => const MiddleHomePage()),
          branch('/middle/lessons', (_) => const LessonsPage()),
          branch('/middle/homework', (_) => const HomeworkPage()),
          branch('/middle/badges', (_) => const BadgesPage()),
        ],
      ),

      _studentShell(
        tabs: (l) => [
          ShellTab(Icons.flag_outlined, Icons.flag_rounded, l.navPlan),
          ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, l.navLessons),
          ShellTab(Icons.assignment_outlined, Icons.assignment_rounded, l.navHomework),
          ShellTab(Icons.article_outlined, Icons.article_rounded, l.navReports),
        ],
        branches: [
          branch('/teen/plan', (_) => const TeenHomePage()),
          branch('/teen/lessons', (_) => const LessonsPage()),
          branch('/teen/homework', (_) => const HomeworkPage()),
          branch('/teen/reports', (_) => const ReportsPage()),
        ],
      ),

      // Teachers.
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => AppShell(
          shell: shell,
          tabs: [
            ShellTab(Icons.schedule_outlined, Icons.schedule_rounded, context.l10n.navToday),
            ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, context.l10n.navWeek),
            ShellTab(Icons.groups_outlined, Icons.groups_rounded, context.l10n.navStudents),
            ShellTab(Icons.account_balance_wallet_outlined, Icons.account_balance_wallet_rounded, context.l10n.navEarnings),
            ShellTab(Icons.more_horiz_rounded, Icons.more_horiz_rounded, context.l10n.navMore),
          ],
        ),
        branches: [
          branch('/teach/today', (_) => const TodayPage()),
          branch('/teach/week', (_) => const WeekPage()),
          branch('/teach/students', (_) => const StudentsPage()),
          branch('/teach/earnings', (_) => const EarningsPage()),
          branch('/teach/more', (_) => const MorePage(), routes: [page('time-off', (_) => const TimeOffPage())]),
        ],
      ),
    ],
  );
}

StatefulShellRoute _studentShell({
  required List<ShellTab> Function(AppLocalizations l) tabs,
  required List<StatefulShellBranch> branches,
}) => StatefulShellRoute.indexedStack(
  builder: (context, state, shell) => BlocProvider(
    create: (c) => LearnerCubit(c.read(), LearnerScope.self)..load(),
    child: AppShell(shell: shell, tabs: tabs(context.l10n)),
  ),
  branches: branches,
);

class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) => const Scaffold(body: Center(child: CircularProgressIndicator.adaptive()));
}

/// Re-runs the router's redirect whenever the session changes.
class _StreamListenable extends ChangeNotifier {
  _StreamListenable(Stream<Object?> stream) {
    _sub = stream.listen((_) => notifyListeners());
  }

  late final StreamSubscription<Object?> _sub;

  @override
  void dispose() {
    unawaited(_sub.cancel());
    super.dispose();
  }
}
