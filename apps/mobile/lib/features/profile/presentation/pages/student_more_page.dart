import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/router/areas.dart';
import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';

/// "More" for a child and for middle school: their homework, their
/// creatures, the kids' sticker album, help (not the kids' look: their
/// guardian writes), telling a grown-up, and their account.
class StudentMorePage extends StatelessWidget {
  const StudentMorePage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final area = Area.of(context.watch<SessionCubit>().state);
    final kids = area == Area.kids;
    final base = area.prefix;
    final items = <(Key, IconData, String, VoidCallback)>[
      (
        const Key('more-homework'),
        Icons.assignment_outlined,
        kids ? l.navMyHomework : l.navHomework,
        () => context.go('$base/more/homework'),
      ),
      (const Key('more-creatures'), Icons.pets_outlined, l.gamesAlbum, () => context.go('$base/journey/album')),
      if (kids) (const Key('more-stickers'), Icons.auto_awesome_outlined, l.album, () => context.go('$base/more/stickers')),
      if (!kids) (const Key('more-help'), Icons.support_agent_outlined, l.helpTitle, () => context.push('/account/help')),
      (
        const Key('more-tell-us'),
        Icons.shield_outlined,
        kids ? l.tellUsNavKids : l.tellUsNavMiddle,
        () => context.push('/account/tell-us'),
      ),
      (const Key('more-account'), Icons.person_outline_rounded, l.meTitle, () => context.push('/account')),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(l.navMore)),
      body: ListView(
        padding: pagePadding,
        children: [
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                for (final (i, (key, icon, label, onTap)) in items.indexed) ...[
                  if (i > 0) const Divider(height: 1),
                  ListTile(
                    key: key,
                    leading: Icon(icon, color: context.colors.brand),
                    title: Text(label, style: kids ? context.text.titleMedium : context.text.titleSmall),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    minVerticalPadding: kids ? 16 : null,
                    onTap: onTap,
                  ),
                ],
              ],
            ),
          ).appear(context, 1),
        ],
      ),
    );
  }
}
