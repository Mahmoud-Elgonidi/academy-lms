import '../../../core/l10n/l10n.dart';
import '../../homework/domain/homework.dart';
import '../domain/games.dart';

/// Why a round cannot start now. The kids' look keeps the academy's words
/// (moon and all); the others get the app's own, without emoji, and a reason
/// this version does not know yet falls back to the academy's.
extension AllowanceWords on Allowance {
  String why(AppLocalizations l, {required bool kids}) {
    if (kids) return reasonLabel ?? '';
    return switch (reason) {
      'off' => l.gamesRefusedOff,
      'paused' => l.gamesRefusedPaused,
      'night' => l.gamesRefusedNight,
      'limit' => l.gamesRefusedLimit,
      _ => reasonLabel ?? '',
    };
  }
}

/// How a homework's round went ("التدريب: 9 من 10 صح · 3 نجوم (…)"), in the
/// app's words outside the kids' look; the academy's label when a number is missing.
extension PracticeWords on HomeworkPractice {
  String result(AppLocalizations l, {required bool kids}) {
    final (c, t, s, g) = (correct, total, stars, game);
    if (kids || c == null || t == null || s == null || g == null) return label;
    return l.homeworkPracticeResult(c, t, l.gamesStars(s), g);
  }
}
