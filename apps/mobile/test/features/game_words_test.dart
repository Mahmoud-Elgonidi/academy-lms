import 'package:academy_app/core/l10n/l10n.dart';
import 'package:academy_app/features/games/domain/games.dart';
import 'package:academy_app/features/games/presentation/game_words.dart';
import 'package:academy_app/features/homework/domain/homework.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

/// The academy's words for a closed day and a round's result carry emoji
/// (a moon, a star) that only the kids' look keeps; the others read the app's.
void main() {
  final l = lookupAppLocalizations(const Locale('ar'));
  const night = Allowance(may: false, used: 0, reason: 'night', reasonLabel: 'الطيور نامت 🌙 الألعاب بترجع الساعة 7 الصبح.');

  test('why a round cannot start: the app\'s words, the kids\' keep the academy\'s', () {
    expect(night.why(l, kids: false), 'الطيور نامت. الألعاب بترجع الساعة 7 الصبح.');
    expect(night.why(l, kids: true), 'الطيور نامت 🌙 الألعاب بترجع الساعة 7 الصبح.');
    for (final (reason, words) in [('off', l.gamesRefusedOff), ('paused', l.gamesRefusedPaused), ('limit', l.gamesRefusedLimit)]) {
      expect(Allowance(may: false, used: 0, reason: reason, reasonLabel: '🌙').why(l, kids: false), words);
    }
    // A reason this version does not know yet: the academy's words.
    expect(const Allowance(may: false, used: 0, reason: 'storm', reasonLabel: 'مقفول').why(l, kids: false), 'مقفول');
  });

  test('a round\'s result: counted in the app\'s words, the academy\'s when a number is missing', () {
    const done = HomeworkPractice(
      done: true,
      label: 'التدريب: 9 من 10 صح · 3 ⭐ (توأم العش)',
      correct: 9,
      total: 10,
      stars: 3,
      game: 'توأم العش',
    );
    expect(done.result(l, kids: false), 'التدريب: 9 من 10 صح · 3 نجوم (توأم العش)');
    expect(done.result(l, kids: true), done.label);
    const one = HomeworkPractice(done: true, label: '', correct: 1, total: 4, stars: 1, game: 'العش');
    expect(one.result(l, kids: false), 'التدريب: 1 من 4 صح · نجمة واحدة (العش)');
    const partial = HomeworkPractice(done: true, label: 'من السيرفر', correct: 9, total: 10);
    expect(partial.result(l, kids: false), 'من السيرفر');
  });
}
