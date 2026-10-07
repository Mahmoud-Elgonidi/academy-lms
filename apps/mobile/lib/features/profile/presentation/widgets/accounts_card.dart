import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/branding.dart';
import '../../../../core/auth/account.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../auth/presentation/pages/accounts_page.dart';

/// Every account on this phone: the open one marked, the others one tap
/// away, and a way to add another.
class AccountsCard extends StatelessWidget {
  const AccountsCard({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<SessionCubit>().state;
    final c = context.colors;
    final current = s.current;
    final accounts = [?current, ...s.accounts.where((a) => a.id != current?.id)];
    return LmsCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (final a in accounts) ...[_Row(account: a, open: a.id == current?.id), Divider(height: 1, color: c.line)],
          InkWell(
            onTap: () => context.push('/add'),
            child: SizedBox(
              height: 54,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: [
                    Icon(Icons.add_rounded, color: c.brand),
                    const SizedBox(width: 10),
                    Text(context.l10n.addAccount, style: context.text.titleSmall?.copyWith(color: c.brandDeep)),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.account, required this.open});

  final Account account;
  final bool open;

  @override
  Widget build(BuildContext context) {
    final roles = {for (final r in account.roles) roleLabel(context, r)}.join('، ');
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          InitialAvatar(
            account.academyName(context.locale),
            colour: Branding.parseColour(account.academyColour) ?? (open ? context.colors.brand : null),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(account.academyName(context.locale), style: context.text.titleMedium),
                Text('${account.userName} · $roles', style: context.text.bodySmall),
              ],
            ),
          ),
          if (open)
            Pill(context.l10n.openNow, tone: Tone.brand)
          else
            OutlinedButton(
              style: OutlinedButton.styleFrom(minimumSize: const Size(64, 40), padding: const EdgeInsets.symmetric(horizontal: 14)),
              onPressed: () => context.read<SessionCubit>().open(account.id),
              child: Text(context.l10n.switchTo),
            ),
        ],
      ),
    );
  }
}
