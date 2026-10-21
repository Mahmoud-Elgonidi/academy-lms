import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/recitation.dart';

/// A recited passage word by word, in colour: words missed (struck through,
/// dotted), said wrong (with what was said instead), said but not in the
/// text, and each verse's number at its end; the colours' key under it. The
/// teacher's marking and a learner's hifz review read a check the same way.
class RecitedWords extends StatelessWidget {
  const RecitedWords({required this.tokens, this.size = 19, super.key});

  final List<RecitedToken> tokens;

  /// The words' size (the verses' numbers and what was said come a little smaller).
  final double size;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final words = context.type.body.copyWith(fontSize: size, height: 2.1);
    final small = words.copyWith(fontSize: size - 6, color: c.muted);
    TextStyle styleOf(WordState state) => switch (state) {
      WordState.ok => words,
      WordState.missing => words.copyWith(
        color: c.attentionInk,
        backgroundColor: c.attentionSoft,
        decoration: TextDecoration.lineThrough,
        decorationStyle: TextDecorationStyle.dotted,
      ),
      WordState.wrong => words.copyWith(color: c.dangerInk, backgroundColor: c.dangerSoft),
    };
    final extra = words.copyWith(color: c.infoInk, backgroundColor: c.infoSoft, fontStyle: FontStyle.italic);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Directionality(
          textDirection: TextDirection.rtl,
          child: Text.rich(
            key: const Key('recited-words'),
            TextSpan(
              children: [
                for (final token in tokens)
                  switch (token) {
                    RecitedWord(:final text, :final state, :final heard) => TextSpan(
                      children: [
                        TextSpan(text: text, style: styleOf(state), semanticsLabel: said(l, text, state, heard)),
                        if (state == WordState.wrong && heard != null) TextSpan(text: ' ($heard)', style: small),
                        const TextSpan(text: ' '),
                      ],
                    ),
                    ExtraWord(:final text) => TextSpan(
                      children: [
                        TextSpan(text: text, style: extra, semanticsLabel: '${l.reciteExtra}: $text'),
                        const TextSpan(text: ' '),
                      ],
                    ),
                    VerseEnd(:final number) => TextSpan(text: '($number) ', style: small),
                  },
              ],
            ),
          ),
        ),
        Wrap(
          spacing: Space.s,
          runSpacing: Space.xs,
          children: [
            Pill(l.reciteMissing, tone: Tone.attention),
            Pill(l.reciteWrong, tone: Tone.danger),
            Pill(l.reciteExtra, tone: Tone.info),
          ],
        ),
      ],
    );
  }

  /// What a screen reader says for a word: how it was said, and what was said in its place.
  static String said(AppLocalizations l, String text, WordState state, String? heard) => switch (state) {
    WordState.ok => text,
    WordState.missing => '${l.reciteMissing}: $text',
    WordState.wrong => '${l.reciteWrong}: $text. ${l.reciteSaid(heard ?? '')}',
  };
}
