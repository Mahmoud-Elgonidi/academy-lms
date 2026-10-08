import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../domain/materials.dart';

/// The lesson's materials as chips: a tap opens one outside the app (the
/// phone's PDF viewer, player or browser), so the lesson screen stays.
class MaterialChips extends StatelessWidget {
  const MaterialChips({required this.items, super.key});

  final List<LessonMaterial> items;

  static IconData iconOf(String type) => switch (type) {
    'pdf' => Icons.picture_as_pdf_outlined,
    'audio' => Icons.headphones_outlined,
    'video' => Icons.play_circle_outline_rounded,
    'image' => Icons.image_outlined,
    _ => Icons.link_rounded,
  };

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    final l = context.l10n;
    final c = context.colors;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l.lessonMaterials, style: context.text.labelMedium?.copyWith(color: c.muted)),
        const SizedBox(height: 6),
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: [
            for (final m in items)
              ActionChip(
                avatar: Icon(iconOf(m.type), size: 18, color: c.brandDeep),
                label: Text(m.teachersOnly ? '${m.title} · ${l.teachersOnly}' : m.title, overflow: TextOverflow.ellipsis),
                tooltip: m.title,
                onPressed: () => launchUrl(Uri.parse(m.url), mode: LaunchMode.externalApplication),
              ),
          ],
        ),
      ],
    );
  }
}
