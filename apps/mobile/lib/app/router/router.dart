import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session_cubit.dart';
import '../../core/l10n/l10n.dart';
import '../../core/state/load_cubit.dart';
import '../../features/auth/presentation/pages/accounts_page.dart';
import '../../features/auth/presentation/pages/code_page.dart';
import '../../features/auth/presentation/pages/password_page.dart';
import '../../features/auth/presentation/pages/two_factor_page.dart';
import '../../features/consent/presentation/privacy_page.dart';
import '../../features/privacy/presentation/my_data_page.dart';
import '../../features/recordings/presentation/recordings_page.dart';
import '../../features/auth/presentation/pages/welcome_page.dart';
import '../../features/auth/presentation/pages/web_only_page.dart';
import '../../features/billing/presentation/pages/money_page.dart';
import '../../features/classroom/presentation/pages/classroom_page.dart';
import '../../features/dashboard/presentation/bloc/learner_cubit.dart';
import '../../features/dashboard/presentation/pages/family_home_page.dart';
import '../../features/dashboard/presentation/pages/kids_home_page.dart';
import '../../features/dashboard/presentation/pages/middle_home_page.dart';
import '../../features/dashboard/presentation/pages/badges_page.dart';
import '../../features/challenges/data/challenges_repository.dart';
import '../../features/challenges/domain/challenges.dart';
import '../../features/challenges/presentation/pages/album_page.dart';
import '../../features/challenges/presentation/pages/challenges_page.dart';
import '../../features/challenges/presentation/widgets/cheers.dart';
import '../../features/dashboard/presentation/pages/teen_home_page.dart';
import '../../features/games/presentation/album_page.dart';
import '../../features/games/presentation/games_page.dart';
import '../../features/homework/presentation/pages/homework_page.dart';
import '../../features/messages/presentation/conversation_page.dart';
import '../../features/messages/presentation/messages_page.dart';
import '../../features/notifications/presentation/pages/choices_page.dart';
import '../../features/notifications/presentation/pages/inbox_page.dart';
import '../../features/notifications/presentation/pages/update_page.dart';
import '../../features/profile/presentation/pages/account_page.dart';
import '../../features/roadmap/presentation/pages/progress_page.dart';
import '../../features/roadmap/presentation/pages/reports_page.dart';
import '../../features/schedule/presentation/pages/lessons_page.dart';
import '../../features/safeguarding/presentation/documents_page.dart';
import '../../features/safeguarding/presentation/tell_us_page.dart';
import '../../features/support/presentation/help_page.dart';
import '../../features/support/presentation/ticket_page.dart';
import '../../features/teaching/presentation/pages/earnings_page.dart';
import '../../features/teaching/presentation/pages/marking_page.dart';
import '../../features/teaching/presentation/pages/more_page.dart';
import '../../features/teaching/presentation/pages/report_page.dart';
import '../../features/teaching/presentation/pages/shift_editor_page.dart';
import '../../features/teaching/presentation/pages/shifts_page.dart';
import '../../features/teaching/presentation/pages/reports_page.dart';
import '../../features/teaching/presentation/pages/students_page.dart';
import '../../features/teaching/presentation/pages/time_off_page.dart';
import '../../features/teaching/presentation/pages/today_page.dart';
import '../../features/teaching/presentation/pages/week_page.dart';
import 'areas.dart';
import 'shell.dart';
import 'targets.dart';
import 'transitions.dart';

/// Every screen and who may see it. The open account decides the look: a
/// location outside its area goes to the area's home, and signing out goes
/// to the welcome screen. Screens come in fading and growing; tabs fade
/// into place and keep their own stacks.
GoRouter buildRouter(SessionCubit session) {
  GoRoute page(String path, Widget Function(GoRouterState s) build, {List<RouteBase> routes = const []}) =>
      GoRoute(path: path, pageBuilder: (_, s) => fadeScalePage(s, build(s)), routes: routes);

  StatefulShellBranch branch(String path, Widget Function(GoRouterState s) build, {List<RouteBase> routes = const []}) =>
      StatefulShellBranch(routes: [page(path, build, routes: routes)]);

  // Writing to the academy, and each ticket, under the look's own place: Me, More, or a student's account.
  GoRoute help(HelpScope scope) => page(
    'help',
    (_) => HelpPage(scope: scope),
    routes: [page(':id', (s) => TicketPage(ticketId: s.pathParameters['id']!))],
  );

  // Conversations with a teacher or a student, and each one, under the look's own place: Me, More, or a student's account.
  GoRoute messages(MessagesScope scope) => page(
    'messages',
    (_) => MessagesPage(scope: scope),
    routes: [page(':id', (s) => ConversationPage(conversationId: s.pathParameters['id']!))],
  );

  // The games of a look and their album (an island to open, a creature just named).
  GoRoute games(String path, String base) => page(
    path,
    (s) => GamesPage(base: base, island: s.uri.queryParameters['island']),
    routes: [
      page('album', (s) => GamesAlbumPage(base: base, island: s.uri.queryParameters['island'], fresh: s.uri.queryParameters['new'])),
    ],
  );

  List<RouteBase> signIn(String base) => [
    page(
      base,
      (_) => WelcomePage(base: base),
      routes: [
        page('code', (_) => CodePage(base: base)),
        page('accounts', (_) => AccountsPage(base: base)),
        page('password', (_) => PasswordPage(base: base)),
        page('two-factor', (_) => TwoFactorPage(base: base)),
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
          // Too old for the platform: nothing else until it is updated.
          if (s.updateRequired) return loc == '/update' ? null : '/update';
          if (loc == '/update') return Area.of(s).home;
          final area = Area.of(s);
          // A link into the app (academyapp://app/open/{screen}/{id}) or a tapped push.
          if (loc.startsWith('/open/')) return targetOf(state.pathParameters['screen'], state.pathParameters['id'], area);
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
      page(
        '/account',
        (_) => const AccountPage(standalone: true),
        routes: [
          help(HelpScope.self),
          messages(MessagesScope.self),
          page('my-data', (_) => const MyDataPage()),
          page('tell-us', (_) => const TellUsPage(scope: ConcernScope.self)),
        ],
      ),
      page('/update', (_) => const UpdatePage()),
      page('/inbox', (_) => const InboxPage(), routes: [page('choices', (_) => const ChoicesPage())]),
      page('/open/:screen', (_) => const _Splash(), routes: [page(':id', (_) => const _Splash())]),
      page('/classroom/:id', (s) => ClassroomPage(lessonId: s.pathParameters['id']!, ticket: s.extra)),
      // The teacher's focused tasks open over the tabs.
      page(
        '/teach/reports',
        (_) => const TeacherReportsPage(),
        routes: [page(':id', (s) => TeacherReportPage(reportId: s.pathParameters['id']!))],
      ),
      page('/teach/marking', (_) => const MarkingPage()),
      page(
        '/teach/hours',
        (_) => const ShiftsPage(),
        routes: [
          // The editor needs the week it starts from; opened without it (a link), it goes back to My hours.
          GoRoute(
            path: 'edit',
            pageBuilder: (_, s) =>
                fadeScalePage(s, s.extra is ShiftDraft ? ShiftEditorPage(draft: s.extra! as ShiftDraft) : const ShiftsPage()),
          ),
        ],
      ),
      page('/reports/:enrollment', (s) => ReportsPage(enrollmentId: s.pathParameters['enrollment'], title: s.uri.queryParameters['title'])),

      // Guardians and adult students: the academy's own look.
      StatefulShellRoute(
        navigatorContainerBuilder: _branches,
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
          branch(
            '/family/home',
            (_) => const FamilyHomePage(),
            routes: [page('challenges', (_) => const ChallengesPage()), games('games', '/family/home/games')],
          ),
          branch('/family/lessons', (_) => const LessonsPage()),
          branch('/family/progress', (_) => const ProgressPage()),
          branch('/family/money', (_) => const MoneyPage()),
          branch(
            '/family/me',
            (_) => const AccountPage(),
            routes: [
              page('privacy', (_) => const PrivacyPage()),
              page('my-data', (_) => const MyDataPage()),
              page('recordings', (_) => const RecordingsPage(teacher: false)),
              page('tell-us', (_) => const TellUsPage(scope: ConcernScope.family)),
              help(HelpScope.family),
              messages(MessagesScope.family),
            ],
          ),
        ],
      ),

      // A child in the kids' look: big tabs, the islands, the missions with their chest.
      _studentShell(
        tabs: (l, ready) => [
          ShellTab(Icons.map_outlined, Icons.map_rounded, l.navJourney),
          ShellTab(Icons.sailing_outlined, Icons.sailing_rounded, l.navIslands),
          ShellTab(Icons.redeem_outlined, Icons.redeem_rounded, l.navMissions, count: ready),
          ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, l.navMyLessons),
          ShellTab(Icons.mic_none_rounded, Icons.mic_rounded, l.navMyHomework),
        ],
        branches: [
          branch('/kids/journey', (_) => const KidsHomePage()),
          StatefulShellBranch(routes: [games('/kids/islands', '/kids/islands')]),
          branch('/kids/missions', (_) => const ChallengesPage(), routes: [page('album', (_) => const AlbumPage())]),
          branch('/kids/lessons', (_) => const LessonsPage()),
          branch('/kids/homework', (_) => const HomeworkPage()),
        ],
      ),

      _studentShell(
        tabs: (l, ready) => [
          ShellTab(Icons.home_outlined, Icons.home_rounded, l.navHome),
          ShellTab(Icons.videogame_asset_outlined, Icons.videogame_asset_rounded, l.navGames),
          ShellTab(Icons.sports_esports_outlined, Icons.sports_esports_rounded, l.navQuests, count: ready),
          ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, l.navMyLessons),
          ShellTab(Icons.assignment_outlined, Icons.assignment_rounded, l.navMyHomework),
        ],
        branches: [
          branch('/middle/home', (_) => const MiddleHomePage()),
          StatefulShellBranch(routes: [games('/middle/games', '/middle/games')]),
          branch('/middle/quests', (_) => const ChallengesPage(), routes: [page('badges', (_) => const BadgesPage())]),
          branch('/middle/lessons', (_) => const LessonsPage()),
          branch('/middle/homework', (_) => const HomeworkPage()),
        ],
      ),

      _studentShell(
        tabs: (l, ready) => [
          ShellTab(Icons.flag_outlined, Icons.flag_rounded, l.navPlan),
          ShellTab(Icons.track_changes_outlined, Icons.track_changes_rounded, l.navGoals, count: ready),
          ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, l.navLessons),
          ShellTab(Icons.assignment_outlined, Icons.assignment_rounded, l.navHomework),
          ShellTab(Icons.article_outlined, Icons.article_rounded, l.navReports),
        ],
        branches: [
          branch('/teen/plan', (_) => const TeenHomePage(), routes: [games('games', '/teen/plan/games')]),
          branch('/teen/goals', (_) => const ChallengesPage()),
          branch('/teen/lessons', (_) => const LessonsPage()),
          branch('/teen/homework', (_) => const HomeworkPage()),
          branch('/teen/reports', (_) => const ReportsPage()),
        ],
      ),

      // Teachers.
      StatefulShellRoute(
        navigatorContainerBuilder: _branches,
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
          branch(
            '/teach/more',
            (_) => const MorePage(),
            routes: [
              page('time-off', (_) => const TimeOffPage()),
              page('recordings', (_) => const RecordingsPage(teacher: true)),
              page('my-data', (_) => const MyDataPage(teacher: true)),
              page('concern', (_) => const TellUsPage(scope: ConcernScope.teacher)),
              page('documents', (_) => const DocumentsPage()),
              help(HelpScope.teacher),
              messages(MessagesScope.teacher),
            ],
          ),
        ],
      ),
    ],
  );
}

Widget _branches(BuildContext context, StatefulNavigationShell shell, List<Widget> children) =>
    AnimatedBranches(index: shell.currentIndex, children: children);

/// A student's own area: their challenges load with it, so the challenges
/// tab shows the rewards waiting, and guardians' cheers pop up on any tab.
StatefulShellRoute _studentShell({
  required List<ShellTab> Function(AppLocalizations l, int ready) tabs,
  required List<StatefulShellBranch> branches,
}) => StatefulShellRoute(
  navigatorContainerBuilder: _branches,
  builder: (context, state, shell) => MultiBlocProvider(
    providers: [
      BlocProvider(create: (c) => LearnerCubit(c.read(), LearnerScope.self)..load()),
      BlocProvider(create: (c) => LoadCubit<ChallengeBoard>(c.read<ChallengesRepository>().board)..load()),
    ],
    child: CheersListener(
      child: BlocBuilder<LoadCubit<ChallengeBoard>, LoadState<ChallengeBoard>>(
        buildWhen: (a, b) => a.data?.ready != b.data?.ready,
        builder: (context, board) => AppShell(shell: shell, tabs: tabs(context.l10n, board.data?.ready ?? 0)),
      ),
    ),
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
