import 'package:flutter/material.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../domain/report_answers.dart';

/// The answers to the academy's own questions, as small tiles two by two:
/// stars drawn, the rest as the API wrote them; a lock on what only the
/// academy reads.
class ReportAnswersView extends StatelessWidget {
  const ReportAnswersView({required this.answers, super.key});

  final List<ReportAnswer> answers;

  @override
  Widget build(BuildContext context) {
    if (answers.isEmpty) return const SizedBox.shrink();
    final c = context.colors;
    return LayoutBuilder(
      builder: (context, box) {
        final width = (box.maxWidth - 8) / 2;
        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final a in answers)
              Container(
                width: width,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(context.shape.card - 8)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        if (!a.family) ...[
                          Icon(Icons.lock_outline_rounded, size: 12, color: c.muted, semanticLabel: context.l10n.academyOnly),
                          const SizedBox(width: 3),
                        ],
                        Expanded(
                          child: Text(a.label, style: context.text.bodySmall, maxLines: 2, overflow: TextOverflow.ellipsis),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    if (a.stars case final int n)
                      Semantics(
                        label: a.text,
                        excludeSemantics: true,
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [for (var i = 1; i <= 5; i++) Icon(Icons.star_rounded, size: 16, color: i <= n ? c.attention : c.line)],
                        ),
                      )
                    else
                      Text(a.text, style: context.text.titleSmall),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }
}
