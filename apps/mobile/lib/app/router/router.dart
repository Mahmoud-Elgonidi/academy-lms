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
import '../../features/challenges/data/challenges_repository.dart';
import '../../features/challenges/domain/challenges.dart';
import '../../features/challenges/presentation/pages/album_page.dart';
import '../../features/games/presentation/album_page.dart';
import '../../features/homework/presentation/pages/homework_page.dart';
import '../../features/journey/presentation/journey_page.dart';
import '../../features/profile/presentation/pages/student_more_page.dart';
import '../../features/messages/presentation/conversation_page.dart';
import '../../features/messages/presentation/messages_page.dart';
import '../../features/notifications/presentation/pages/choices_page.dart';
import '../../features/notifications/presentation/pages/inbox_page.dart';
import '../../features/notifications/presentation/pages/update_page.dart';
import '../../features/onboarding/presentation/intro_page.dart';
import '../../features/onboarding/presentation/splash_page.dart';
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
import '../../features/teaching/presentation/pages/meeting_link_page.dart';
import '../../features/teaching/presentation/pages/today_page.dart';
import '../../features/teaching/presentation/pages/week_page.dart';
import 'areas.dart';
import 'shell.dart';
import 'targets.dart';
import 'transitions.dart';

/// Every screen and who may see it. The open account decides the look: a
/// location outside its area goes to the area's home, and signing out goes
/// to the welcome screen (the first time the app opens, after three slides
/// about it). Screens come in fading and growing; tabs fade into place and
/// keep their own stacks.
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

  // A look's journey and the creatures' album under it (an island's creatures, a creature just named).
  GoRoute journey(String path, {List<RouteBase> routes = const []}) => page(
    path,
    (_) => const JourneyPage(),
    routes: [
      page('album', (s) => GamesAlbumPage(base: path, island: s.uri.queryParameters['island'], fresh: s.uri.queryParameters['new'])),
      ...routes,
    ],
  );

  // A student's conversations with their teachers as a tab of its own, and each one.
  GoRoute messagesTab(String path) => page(
    path,
    (_) => MessagesPage(scope: MessagesScope.self, at: path),
    routes: [page(':id', (s) => ConversationPage(conversationId: s.pathParameters['id']!))],
  );

  // The kids' sticker album (the week's chests), read from their journey.
  Widget stickers() =>
      BlocProvider(create: (c) => LoadCubit<ChallengeBoard>(c.read<ChallengesRepository>().board)..load(), child: const AlbumPage());

  // An address from before the journey: it leads to where that is now (an album to the album).
  String to(GoRouterState s, String target, [String? album]) {
    final query = s.uri.hasQuery ? '?${s.uri.query}' : '';
    return album != null && s.uri.path.endsWith('/album') ? '$album$query' : target;
  }

  GoRoute moved(String path, String target, {String? album, List<String> under = const []}) => GoRoute(
    path: path,
    redirect: (_, s) => to(s, target, album),
    routes: [
      if (album != null) GoRoute(path: 'album', redirect: (_, s) => to(s, target, album)),
      for (final p in under) GoRoute(path: p, redirect: (_, s) => to(s, target, album)),
    ],
  );

  // Signing in, a step at a time: each comes in from the reading side.
  GoRoute step(String path, Widget Function(GoRouterState s) build) => GoRoute(path: path, pageBuilder: (_, s) => stepPage(s, build(s)));

  List<RouteBase> signIn(String base) => [
    page(
      base,
      (_) => WelcomePage(base: base),
      routes: [
        step('code', (_) => CodePage(base: base)),
        step('accounts', (_) => AccountsPage(base: base)),
        step('password', (_) => PasswordPage(base: base)),
        step('two-factor', (_) => TwoFactorPage(base: base)),
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
          if (!s.seenIntro) return loc == '/intro' ? null : '/intro';
          return welcome ? null : '/welcome';
        case SessionStatus.signedIn:
          // Too old for the platform: nothing else until it is updated.
          if (s.updateRequired) return loc == '/update' ? null : '/update';
          if (loc == '/update') return Area.of(s).home;
          final area = Area.of(s);
          // A link into the app (academyapp://app/open/{screen}/{id}) or a tapped push.
          if (loc.startsWith('/open/')) return targetOf(state.pathParameters['screen'], state.pathParameters['id'], area);
          if (loc == '/' || loc == '/splash' || loc == '/intro' || welcome) return area.home;
          final elsewhere = Area.values.any((a) => a != area && loc.startsWith(a.prefix));
          return elsewhere ? area.home : null;
      }
    },
    routes: [
      page('/', (_) => const SplashPage()),
      page('/splash', (_) => const SplashPage()),
      page('/intro', (_) => const IntroPage()),
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
      page('/open/:screen', (_) => const SplashPage(), routes: [page(':id', (_) => const SplashPage())]),
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
                ShellTab(Icons.map_outlined, Icons.map_rounded, context.l10n.navJourneyTab),
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
            routes: [
              GoRoute(path: 'challenges', redirect: (_, _) => '/family/journey'),
              GoRoute(
                path: 'games',
                redirect: (_, s) => to(s, '/family/journey', '/family/journey/album'),
                routes: [GoRoute(path: 'album', redirect: (_, s) => to(s, '/family/journey', '/family/journey/album'))],
              ),
            ],
          ),
          branch('/family/lessons', (_) => const LessonsPage()),
          StatefulShellBranch(
            routes: [
              journey(
                '/family/journey',
                routes: [page('courses', (_) => const ProgressPage()), page('homework', (_) => const HomeworkPage())],
              ),
            ],
          ),
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

      // A child in the kids' look: big tabs, their journey (the islands of their course), talking to their teacher.
      _studentShell(
        tabs: (l) => [
          ShellTab(Icons.map_outlined, Icons.map_rounded, l.navJourney),
          ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, l.navMyLessons),
          ShellTab(Icons.forum_outlined, Icons.forum_rounded, l.messagesKidsTitle),
          ShellTab(Icons.more_horiz_rounded, Icons.more_horiz_rounded, l.navMore),
        ],
        branches: [
          StatefulShellBranch(routes: [journey('/kids/journey')]),
          branch('/kids/lessons', (_) => const LessonsPage()),
          StatefulShellBranch(routes: [messagesTab('/kids/messages')]),
          branch(
            '/kids/more',
            (_) => const StudentMorePage(),
            routes: [page('homework', (_) => const HomeworkPage()), page('stickers', (_) => stickers())],
          ),
        ],
      ),
      moved('/kids/islands', '/kids/journey', album: '/kids/journey/album'),
      moved('/kids/missions', '/kids/journey', album: '/kids/more/stickers'),
      moved('/kids/homework', '/kids/more/homework'),

      _studentShell(
        tabs: (l) => [
          ShellTab(Icons.map_outlined, Icons.map_rounded, l.navJourney),
          ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, l.navMyLessons),
          ShellTab(Icons.forum_outlined, Icons.forum_rounded, l.messagesTitle),
          ShellTab(Icons.more_horiz_rounded, Icons.more_horiz_rounded, l.navMore),
        ],
        branches: [
          StatefulShellBranch(routes: [journey('/middle/journey')]),
          branch('/middle/lessons', (_) => const LessonsPage()),
          StatefulShellBranch(routes: [messagesTab('/middle/messages')]),
          branch('/middle/more', (_) => const StudentMorePage(), routes: [page('homework', (_) => const HomeworkPage())]),
        ],
      ),
      moved('/middle/home', '/middle/journey'),
      moved('/middle/games', '/middle/journey', album: '/middle/journey/album'),
      moved('/middle/quests', '/middle/journey', under: ['badges']),
      moved('/middle/homework', '/middle/more/homework'),

      _studentShell(
        tabs: (l) => [
          ShellTab(Icons.map_outlined, Icons.map_rounded, l.navJourney),
          ShellTab(Icons.calendar_month_outlined, Icons.calendar_month_rounded, l.navMyLessons),
          ShellTab(Icons.assignment_outlined, Icons.assignment_rounded, l.navHomework),
          ShellTab(Icons.article_outlined, Icons.article_rounded, l.navReports),
        ],
        branches: [
          StatefulShellBranch(routes: [journey('/teen/journey')]),
          branch('/teen/lessons', (_) => const LessonsPage()),
          branch('/teen/homework', (_) => const HomeworkPage()),
          branch('/teen/reports', (_) => const ReportsPage()),
        ],
      ),
      GoRoute(
        path: '/teen/plan',
        redirect: (_, s) => to(s, '/teen/journey', '/teen/journey/album'),
        routes: [
          GoRoute(
            path: 'games',
            redirect: (_, s) => to(s, '/teen/journey', '/teen/journey/album'),
            routes: [GoRoute(path: 'album', redirect: (_, s) => to(s, '/teen/journey', '/teen/journey/album'))],
          ),
        ],
      ),
      moved('/teen/goals', '/teen/journey'),
      moved('/family/progress', '/family/journey'),

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
              page('meeting-link', (_) => const MeetingLinkPage()),
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

/// A student's own area: they are its only learner.
StatefulShellRoute _studentShell({
  required List<ShellTab> Function(AppLocalizations l) tabs,
  required List<StatefulShellBranch> branches,
}) => StatefulShellRoute(
  navigatorContainerBuilder: _branches,
  builder: (context, state, shell) => BlocProvider(
    create: (c) => LearnerCubit(c.read(), LearnerScope.self)..load(),
    child: Builder(
      builder: (context) => AppShell(shell: shell, tabs: tabs(context.l10n)),
    ),
  ),
  branches: branches,
);

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
