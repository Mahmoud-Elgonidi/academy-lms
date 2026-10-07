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
      'payroll_month' => '/teach/earnings',
      'session' || 'trial' || 'lesson_rating' => '/teach/today',
      _ => home,
    };
  }
  final base = area.prefix;
  return switch ((area, screen)) {
    (_, 'session' || 'holiday' || 'lesson_rating') => '$base/lessons',
    (Area.family, 'invoice') => '/family/money',
    (Area.family, 'report' || 'roadmap') => '/family/progress',
    (Area.family, 'challenges') => '/family/home/challenges',
    (Area.kids, 'challenges') => '/kids/missions',
    (Area.middle, 'challenges') => '/middle/quests',
    (Area.teen, 'challenges') => '/teen/goals',
    (Area.teen, 'report') => '/teen/reports',
    (Area.kids || Area.middle || Area.teen, 'homework') => '$base/homework',
    _ => home,
  };
}

/// The teacher's tasks open over the tabs (pushed, with a way back); any
/// other place is a tab of the look, which is switched to.
bool opensOverTabs(String location) => const ['/teach/reports', '/teach/marking', '/teach/hours'].any(location.startsWith);

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
