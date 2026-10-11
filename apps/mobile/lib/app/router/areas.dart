import '../../core/auth/session_cubit.dart';
import '../theme/stage.dart';

/// Where each kind of account lives in the app: teachers, guardians and
/// adult students (classic), and a student's own stage. Staff-only accounts
/// are sent to the web.
enum Area {
  teacher('/teach', '/teach/today'),
  family('/family', '/family/home'),
  kids('/kids', '/kids/journey'),
  middle('/middle', '/middle/journey'),
  teen('/teen', '/teen/journey'),
  web('/web-only', '/web-only');

  const Area(this.prefix, this.home);

  final String prefix;
  final String home;

  static Area of(SessionState s) {
    final a = s.current;
    if (a == null || !a.hasAppArea) return Area.web;
    if (a.isTeacher) return Area.teacher;
    return switch (s.stage) {
      Stage.kids => Area.kids,
      Stage.middle => Area.middle,
      Stage.teen => Area.teen,
      Stage.classic => Area.family,
    };
  }
}
