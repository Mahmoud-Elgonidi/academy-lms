import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../app/theme/branding.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/sign_in_models.dart';
import '../bloc/sign_in_cubit.dart';
import '../widgets/sign_in_frame.dart';

/// Step three: the accounts the code found, in every academy. The person
/// picks which to keep on this phone; staff-only accounts belong on the web.
class AccountsPage extends StatelessWidget {
  const AccountsPage({this.base = '/welcome', super.key});

  final String base;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<SignInCubit, SignInState>(
      builder: (context, state) {
        final found = state.discovery?.accounts ?? const <FoundAccount>[];
        final error = state.problem == null ? null : context.problemText(state.problem!);
        return SignInFrame(
          title: found.isEmpty ? l.accountsTitle : l.accountsFound(found.length),
          lead: Text(found.isEmpty ? l.noAccounts : l.accountsLead, style: context.text.bodyLarge?.copyWith(color: context.colors.muted)),
          bottom: found.isEmpty
              ? null
              : BusyButton(
                  label: l.addAccounts(state.selected.length),
                  busy: state.busy,
                  onPressed: state.selected.isEmpty
                      ? null
                      : () async {
                          if (await context.read<SignInCubit>().addSelected() && context.mounted) context.go('/');
                        },
                ),
          children: [
            for (final account in found) ...[
              _AccountTile(account: account, selected: state.selected.contains(account.id)),
              const SizedBox(height: 10),
            ],
            if (error != null) ...[const SizedBox(height: 4), Note(error, tone: Tone.danger, icon: Icons.error_outline_rounded)],
            if (found.isNotEmpty) ...[const SizedBox(height: 10), Text(l.accountsNote, style: context.text.bodySmall)],
          ],
        );
      },
    );
  }
}

String roleLabel(BuildContext context, String role) => switch (role) {
  'student' => context.l10n.roleStudent,
  'guardian' => context.l10n.roleGuardian,
  'teacher' => context.l10n.roleTeacher,
  _ => context.l10n.roleStaff,
};

class _AccountTile extends StatelessWidget {
  const _AccountTile({required this.account, required this.selected});

  final FoundAccount account;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final usable = account.hasAppArea;
    final roles = {for (final r in account.roles) roleLabel(context, r)}.toList();
    return Opacity(
      opacity: usable ? 1 : 0.55,
      child: Material(
        color: selected ? c.brandSoft : c.card,
        shape: RoundedRectangleBorder(
          borderRadius: context.shape.cardRadius,
          side: BorderSide(color: selected ? c.brand : c.line, width: selected ? 2 : 1),
        ),
        child: InkWell(
          borderRadius: context.shape.cardRadius,
          onTap: usable ? () => context.read<SignInCubit>().toggle(account.id) : null,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                InitialAvatar(account.academyName(context.locale), colour: Branding.parseColour(account.colour)),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(account.academyName(context.locale), style: context.text.titleMedium),
                      const SizedBox(height: 2),
                      Text(account.userName, style: context.text.bodySmall),
                      const SizedBox(height: 6),
                      Wrap(spacing: 6, runSpacing: 4, children: [for (final r in roles) Pill(r)]),
                    ],
                  ),
                ),
                Checkbox(value: selected, onChanged: usable ? (_) => context.read<SignInCubit>().toggle(account.id) : null),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
