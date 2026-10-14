import 'package:go_router/go_router.dart';

import '../../core/auth/session_cubit.dart';
import 'areas.dart';

/// Where a notification (in the inbox, by push, or a link
/// `academyapp://app/open/{screen}/{id}`) opens in each look. Its `screen`
/// and `id` are the ones the API puts on every message.
String targetOf(String? screen, String? id, Area area) {
  final home = area.home;
  if (area == Area.teacher) {
    return switch (screen) {
      'write_report' when id != null => '/teach/reports/$id',
      'write_report' => '/teach/reports',
      'homework_to_mark' => '/teach/marking',
      'shift_proposal' => '/teach/hours',
      'time_off' => '/teach/more/time-off',
      'payroll_month' => '/teach/more/earnings',
      'ticket' when id != null => '/teach/more/help/$id',
      'my_data' => '/teach/more/my-data',
      'account' => '/account',
      'my_concerns' => '/teach/more/concern',
      'my_checks' => '/teach/more/documents',
      'conversation' when id != null => '/teach/more/messages/$id',
      'conversation' => '/teach/more/messages',
      'session' || 'trial' || 'lesson_rating' => '/teach/today',
      'announcement' => '/inbox',
      _ => home,
    };
  }
  final base = area.prefix;
  return switch ((area, screen)) {
    (_, 'session' || 'holiday' || 'lesson_rating') => '$base/lessons',
    // An academy's announcement has no screen of its own: it is read in full in the inbox.
    (_, 'announcement') => '/inbox',
    // The journey: games, the old challenges and the journey itself (a lesson's stars, an egg).
    (Area.kids || Area.middle || Area.teen || Area.family, 'journey' || 'games' || 'challenges') => '$base/journey',
    (Area.family, 'invoice') => '/family/money',
    (Area.family, 'guardian_consent') => '/family/me/privacy',
    (Area.family, 'my_data') => '/family/me/my-data',
    (Area.family, 'account') => '/family/me',
    (Area.kids || Area.middle || Area.teen, 'account') => '/account',
    (Area.family, 'my_concerns') => '/family/me/tell-us',
    (Area.kids || Area.middle || Area.teen, 'my_concerns') => '/account/tell-us',
    (Area.family, 'ticket') when id != null => '/family/me/help/$id',
    (Area.family, 'conversation') when id != null => '/family/me/messages/$id',
    (Area.family, 'conversation') => '/family/me/messages',
    (Area.kids || Area.middle, 'conversation') when id != null => '$base/messages/$id',
    (Area.kids || Area.middle, 'conversation') => '$base/messages',
    (Area.teen, 'conversation') when id != null => '/account/messages/$id',
    (Area.teen, 'conversation') => '/account/messages',
    (Area.middle || Area.teen, 'ticket') when id != null => '/account/help/$id',
    (Area.family, 'report' || 'roadmap') => '/family/journey/courses',
    (Area.teen, 'report') => '/teen/reports',
    (Area.kids || Area.middle, 'homework') => '$base/more/homework',
    (Area.teen, 'homework') => '/teen/homework',
    _ => home,
  };
}

/// The teacher's tasks (a report to write, marking, hours) and the inbox open
/// over the tabs (pushed, with a way back); any other place is a tab of the
/// look, which is switched to (the list of reports is one).
bool opensOverTabs(String location) =>
    const ['/teach/reports/', '/teach/marking', '/teach/hours', '/inbox', '/account'].any(location.startsWith);

/// A message read where it is, in full, in the inbox: it opens nowhere else.
bool readsInPlace(String? screen) => screen == 'announcement';

void openTarget(GoRouter router, String location) {
  if (opensOverTabs(location)) {
    router.push(location);
  } else {
    router.go(location);
  }
}

/// A tapped push or its snack: the message's own account first (one phone
/// may hold accounts in several academies), then its place in that look. A
/// message for an account no longer on this phone opens nothing.
Future<void> openMessage(SessionCubit session, GoRouter router, Map<String, String> data) async {
  if (session.state.status != SessionStatus.signedIn) return;
  final academy = data['academy'];
  final user = data['user'];
  if (academy != null && user != null) {
    final owner = '$academy:$user';
    if (!session.state.accounts.any((a) => a.id == owner)) return;
    if (owner != session.state.current?.id) await session.open(owner);
  }
  openTarget(router, targetOf(data['screen'], data['id'], Area.of(session.state)));
}
