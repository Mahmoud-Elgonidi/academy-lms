import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../homework/domain/recitation.dart';

/// One recording's check, word by word, on the teacher's marking screen
/// alone: words missed or said wrong (with what was said), words said that
/// are not in the text, and what was heard. A help while marking, never a mark.
class RecitationCheckView extends StatelessWidget {
  const RecitationCheckView({required this.check, super.key});

  final RecitationCheck check;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final words = context.type.body.copyWith(fontSize: 19, height: 2.1);
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

    return Padding(
      padding: const EdgeInsets.only(top: Space.s),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(l.reciteTitle, style: context.type.label)),
              StatusChip(check.statusLabel, tone: check.ready ? Tone.brand : (check.failed ? Tone.danger : Tone.neutral)),
            ],
          ),
          if (check.failed && check.failure != null) ...[const Gap(Space.xs), Text(check.failure!, style: context.type.caption)],
          if (check.ready) ...[
            if (check.summaryLabel != null) ...[const Gap(Space.xs), Text(check.summaryLabel!, style: context.type.label)],
            const Gap(Space.xs),
            Directionality(
              textDirection: TextDirection.rtl,
              child: Text.rich(
                key: const Key('recited-words'),
                TextSpan(
                  children: [
                    for (final token in check.tokens)
                      switch (token) {
                        RecitedWord(:final text, :final state, :final heard) => TextSpan(
                          children: [
                            TextSpan(text: text, style: styleOf(state), semanticsLabel: _said(l, text, state, heard)),
                            if (state == WordState.wrong && heard != null)
                              TextSpan(
                                text: ' ($heard)',
                                style: words.copyWith(fontSize: 13, color: c.muted),
                              ),
                            const TextSpan(text: ' '),
                          ],
                        ),
                        ExtraWord(:final text) => TextSpan(
                          children: [
                            TextSpan(text: text, style: extra, semanticsLabel: '${l.reciteExtra}: $text'),
                            const TextSpan(text: ' '),
                          ],
                        ),
                        VerseEnd(:final number) => TextSpan(
                          text: '($number) ',
                          style: words.copyWith(fontSize: 13, color: c.muted),
                        ),
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
            if ((check.heard ?? '').isNotEmpty)
              ExpansionTile(
                tilePadding: EdgeInsets.zero,
                childrenPadding: const EdgeInsets.only(bottom: Space.s),
                expandedAlignment: AlignmentDirectional.centerStart,
                title: Text(l.reciteHeard, style: context.type.label),
                children: [
                  Directionality(
                    textDirection: TextDirection.rtl,
                    child: Text(check.heard!, style: context.type.body),
                  ),
                ],
              ),
          ],
          const Gap(Space.xs),
          Text(check.note, style: context.type.caption),
        ],
      ),
    );
  }

  static String _said(AppLocalizations l, String text, WordState state, String? heard) => switch (state) {
    WordState.ok => text,
    WordState.missing => '${l.reciteMissing}: $text',
    WordState.wrong => '${l.reciteWrong}: $text. ${l.reciteSaid(heard ?? '')}',
  };
}
