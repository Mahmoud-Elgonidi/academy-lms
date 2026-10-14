import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/branding.dart';
import '../../../../core/auth/account.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';
import '../bloc/learner_cubit.dart';
import 'learner_switcher.dart';

/// The top of each of a guardian's tabs: a line above, the title, the bell,
/// and the children to switch between (with more than one, and where the
/// tab is about a child).
class FamilyHeader extends StatelessWidget {
  const FamilyHeader({required this.title, this.eyebrow, this.leading, this.switcher = true, super.key});

  final String title;
  final String? eyebrow;
  final Widget? leading;
  final bool switcher;

  @override
  Widget build(BuildContext context) {
    final many = context.select<LearnerCubit, bool>((c) => c.state.learners.length > 1);
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, Space.m, 18, Space.xs),
      child: AppHeader(
        title: title,
        eyebrow: eyebrow,
        leading: leading,
        actions: const [NotificationBell(framed: true)],
        bottom: switcher && many ? const LearnerSwitcher(padding: EdgeInsets.zero) : null,
      ),
    );
  }
}

/// The academy's logo on a home's header, or its first letter in its colour
/// while there is none (or it does not load).
class AcademyLogo extends StatelessWidget {
  const AcademyLogo({required this.account, this.size = 48, super.key});

  final Account account;
  final double size;

  @override
  Widget build(BuildContext context) {
    final name = account.academyName(context.locale);
    final colour = Branding.parseColour(account.academyColour) ?? context.colors.brand;
    final letter = InitialAvatar(name, colour: colour, size: size);
    final url = account.logoUrl;
    // The academy's name is said by the header's line next to it.
    return ExcludeSemantics(
      child: url == null || url.isEmpty
          ? letter
          : Container(
              width: size,
              height: size,
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: context.colors.card,
                borderRadius: BorderRadius.circular(size * 0.32),
                border: Border.all(color: context.colors.line),
              ),
              child: Image.network(url, fit: BoxFit.contain, errorBuilder: (_, _, _) => letter),
            ),
    );
  }
}
