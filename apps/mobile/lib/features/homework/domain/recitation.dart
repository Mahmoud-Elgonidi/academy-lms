/// What homework asks to recite, and each recording's check word by word:
/// the teacher's alone (the family's answers never carry them).
library;

/// A passage of the Quran by surah and verses (title: as the API writes it).
class Expected {
  const Expected({required this.surah, required this.from, required this.to, this.title});

  static Expected? fromJson(Object? json) => json is Map<String, dynamic>
      ? Expected(
          surah: (json['surah'] as num).toInt(),
          from: (json['from'] as num).toInt(),
          to: (json['to'] as num).toInt(),
          title: json['title'] as String?,
        )
      : null;

  final int surah;
  final int from;
  final int to;
  final String? title;

  Map<String, int> toJson() => {'surah': surah, 'from': from, 'to': to};
}

/// One of the Quran's surahs, as GET teacher/texts/quran gives them.
class Surah {
  const Surah({required this.number, required this.nameAr, required this.nameEn, required this.verses});

  factory Surah.fromJson(Map<String, dynamic> json) => Surah(
    number: (json['number'] as num).toInt(),
    nameAr: json['name_ar'] as String? ?? '',
    nameEn: json['name_en'] as String? ?? '',
    verses: (json['verses'] as num?)?.toInt() ?? 0,
  );

  final int number;
  final String nameAr;
  final String nameEn;
  final int verses;

  String get label => '$number. $nameAr · $nameEn';
}

/// Homework that may come with a recording, so its recitation may be checked.
const reciteAccepts = {'audio', 'any'};

/// What the report sends for the homework's recitation: nothing while the form does not ask it
/// (`send` false), else the passage while the switch is on, or none.
({bool send, Map<String, int>? value}) expectedPayload({
  required bool offered,
  required bool on,
  required String accepts,
  int? surah,
  int? from,
  int? to,
}) {
  if (!offered) return (send: false, value: null);
  if (!on || !reciteAccepts.contains(accepts) || surah == null || from == null || to == null) return (send: true, value: null);
  return (send: true, value: {'surah': surah, 'from': from, 'to': to});
}

enum WordState { ok, missing, wrong }

/// A word of the check as it reads: a word of the text, a word said that is not in it, or a verse's number at its end.
sealed class RecitedToken {
  const RecitedToken();
}

class RecitedWord extends RecitedToken {
  const RecitedWord(this.text, this.state, this.heard);

  final String text;
  final WordState state;

  /// What was said in its place (wrong only).
  final String? heard;
}

class ExtraWord extends RecitedToken {
  const ExtraWord(this.text);

  final String text;
}

class VerseEnd extends RecitedToken {
  const VerseEnd(this.number);

  final int number;
}

/// A recording's check: pending, ready or failed (with why, in the reader's words).
class RecitationCheck {
  const RecitationCheck({
    required this.status,
    required this.statusLabel,
    required this.note,
    this.failure,
    this.summaryLabel,
    this.heard,
    this.tokens = const [],
  });

  static RecitationCheck? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final extra = <int, List<String>>{};
    for (final e in json['extra'] as List<dynamic>? ?? const []) {
      final m = e as Map<String, dynamic>;
      extra.putIfAbsent((m['after'] as num).toInt(), () => []).add(m['text'] as String? ?? '');
    }
    List<RecitedToken> after(int n) => [for (final t in extra[n] ?? const <String>[]) ExtraWord(t)];

    final tokens = <RecitedToken>[...after(0)];
    var n = 0;
    for (final v in json['verses'] as List<dynamic>? ?? const []) {
      final verse = v as Map<String, dynamic>;
      for (final w in verse['words'] as List<dynamic>? ?? const []) {
        final word = w as Map<String, dynamic>;
        n++;
        final state = WordState.values.asNameMap()[word['state']] ?? WordState.ok;
        tokens
          ..add(RecitedWord(word['text'] as String? ?? '', state, word['heard'] as String?))
          ..addAll(after(n));
      }
      if (verse['verse'] case final num number) tokens.add(VerseEnd(number.toInt()));
    }
    return RecitationCheck(
      status: json['status'] as String? ?? 'pending',
      statusLabel: json['status_label'] as String? ?? '',
      note: json['note'] as String? ?? '',
      failure: json['failure'] as String?,
      summaryLabel: json['summary_label'] as String?,
      heard: json['heard'] as String?,
      tokens: tokens,
    );
  }

  /// pending, ready, failed.
  final String status;
  final String statusLabel;

  /// That it is a help while marking, not a mark.
  final String note;
  final String? failure;
  final String? summaryLabel;
  final String? heard;

  /// The text's words in order, each extra word after the one it followed, and each verse's number at its end.
  final List<RecitedToken> tokens;

  bool get ready => status == 'ready';
  bool get failed => status == 'failed';
}
