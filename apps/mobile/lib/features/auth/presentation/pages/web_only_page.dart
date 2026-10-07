import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../profile/presentation/widgets/accounts_card.dart';

/// A staff-only account: the back office is on the web. The person can
/// switch to another account here or sign this one out.
class WebOnlyPage extends StatelessWidget {
  const WebOnlyPage({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(22, 40, 22, 24),
        children: [
          Icon(Icons.laptop_mac_rounded, size: 48, color: context.colors.brand),
          const SizedBox(height: 16),
          Text(context.l10n.webOnlyTitle, style: context.text.headlineSmall),
          const SizedBox(height: 8),
          Text(context.l10n.webOnlyBody, style: context.text.bodyLarge?.copyWith(color: context.colors.muted)),
          const SizedBox(height: 24),
          const AccountsCard(),
          const SizedBox(height: 12),
          TextButton.icon(
            style: TextButton.styleFrom(foregroundColor: context.colors.dangerInk),
            onPressed: () => context.read<SessionCubit>().signOut(),
            icon: const Icon(Icons.logout_rounded),
            label: Text(context.l10n.signOut),
          ),
        ],
      ),
    ),
  );
}
