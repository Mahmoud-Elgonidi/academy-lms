import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/widgets/lms.dart';
import '../../data/mushaf_repository.dart';
import '../../domain/mushaf.dart';
import 'mushaf_view.dart';

/// The mistakes notebook as a part of a hifz screen: the words marked in
/// the learner's lessons in the last six months, each surah with how many,
/// each verse in the Quran's face with its marked words in their kind's
/// colour (a screen reader hears what they were, how many times and when
/// last). In the kids' look it is the words we are practising, in gentle
/// colours and their own words, with no counts or dates. A word marked in
/// a lesson shows by itself.
class MistakesNotebook extends StatelessWidget {
  const MistakesNotebook({required this.read, this.teacher = false, super.key});

  /// The learner's notebook, or a student's for their teacher.
  final Future<MistakesBook> Function(MushafRepository repo) read;

  /// The student's teacher is reading it.
  final bool teacher;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<MistakesBook>(() => read(c.read<MushafRepository>()), refreshOn: c.read<Live>().on({Live.mushaf}))..load(),
    child: BlocBuilder<LoadCubit<MistakesBook>, LoadState<MistakesBook>>(
      builder: (context, state) {
        final book = state.data;
        if (book == null) return const SizedBox.shrink();
        return _Book(book: book, teacher: teacher);
      },
    ),
  );
}

class _Book extends StatelessWidget {
  const _Book({required this.book, required this.teacher});

  final MistakesBook book;
  final bool teacher;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final kids = context.isKids;
    return Column(
      key: const Key('mistakes-book'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // The kids' look has its own words, and no counts or dates.
        SectionHeader(kids ? l.mistakesTitleKids : l.mistakesTitle, note: book.isEmpty || kids ? null : l.mistakesWords(book.words)),
        if (!book.isEmpty) ...[
          Text(kids ? l.mistakesLeadKids : (teacher ? l.mistakesLeadTeacher : l.mistakesLead), style: context.type.caption),
          const Gap(Space.s),
        ],
        if (book.isEmpty)
          EmptyNote(kids ? l.mistakesEmptyKids : l.mistakesEmpty, icon: kids ? Icons.emoji_events_outlined : Icons.task_alt_rounded)
        else ...[
          for (final (i, s) in book.surahs.indexed) ...[if (i > 0) const Gap(Space.m), _SurahCard(surah: s, book: book, kids: kids)],
          if (!kids) ...[const Gap(Space.m), MistakesKey(kinds: book.label, earlier: false)],
        ],
      ],
    );
  }
}

/// A surah of the notebook: its name and how many words, then each verse with its marked words.
class _SurahCard extends StatelessWidget {
  const _SurahCard({required this.surah, required this.book, required this.kids});

  final BookSurah surah;
  final MistakesBook book;
  final bool kids;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final s = surah;
    final words = quranStyle(context, size: 22);
    final ends = words.copyWith(fontSize: 18, color: c.brandText);
    final comma = context.locale == 'ar' ? '، ' : ', ';

    InlineSpan word(String text, BookMark? mark) {
      const space = TextSpan(text: ' ');
      if (mark == null) {
        return TextSpan(
          children: [
            TextSpan(text: text),
            space,
          ],
        );
      }
      final (bg, ink) = (kids ? Tone.reward : mistakeTone(mark.worst)).colours(context);
      return TextSpan(
        children: [
          TextSpan(
            text: text,
            style: words.copyWith(backgroundColor: bg, color: ink),
            semanticsLabel: kids
                ? l.mistakesWordKids(text)
                : l.mistakesWord(text, mark.kinds.map(book.label).join(comma), l.mistakesTimes(mark.times), mark.lastAt?.label ?? ''),
          ),
          space,
        ],
      );
    }

    return LmsCard(
      key: Key('mistakes-surah-${s.surah}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Semantics(header: true, child: Text(l.mushafSurah(s.name(context.locale)), style: context.type.cardTitle)),
              ),
              if (!kids) ...[const Gap(Space.s), StatusChip(l.mistakesWords(s.words))],
            ],
          ),
          const Gap(Space.xs),
          for (final v in s.verses)
            Directionality(
              textDirection: TextDirection.rtl,
              child: Text.rich(
                TextSpan(
                  children: [
                    for (final (i, w) in v.words.indexed) word(w, v.marks[i + 1]),
                    TextSpan(text: '﴿${arabicDigits(v.verse)}﴾', style: ends, semanticsLabel: l.mushafVerseEnd(v.verse)),
                  ],
                ),
                key: Key('mistakes-verse-${s.surah}-${v.verse}'),
                style: words,
              ),
            ),
        ],
      ),
    );
  }
}
