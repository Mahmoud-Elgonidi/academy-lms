import 'package:flutter/material.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../homework/domain/recitation.dart';
import '../../../homework/presentation/widgets/recited_words.dart';

/// One recording's check, word by word, on the teacher's marking screen
/// alone: words missed or said wrong (with what was said), words said that
/// are not in the text, and what was heard. A help while marking, never a mark.
class RecitationCheckView extends StatelessWidget {
  const RecitationCheckView({required this.check, super.key});

  final RecitationCheck check;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
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
            RecitedWords(tokens: check.tokens),
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
}
