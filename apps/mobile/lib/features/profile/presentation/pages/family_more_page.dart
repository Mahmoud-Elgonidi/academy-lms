import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../auth/presentation/pages/accounts_page.dart';
import '../../../consent/presentation/privacy_page.dart';
import '../../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../../dashboard/presentation/widgets/family_header.dart';
import '../../../messages/data/messages_repository.dart';
import 'account_page.dart';

/// A family's More (the last tab, where "Me" was): who is signed in, then
/// what belongs together on one card each: the children (consents,
/// recordings), getting in touch (messages with their count, help, telling
/// the academy of a worry), the account (the accounts on this phone,
/// notifications, language and clock, sign-in and security, my data), and
/// signing out.
class FamilyMorePage extends StatelessWidget {
  const FamilyMorePage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = context.watch<SessionCubit>().state;
    final account = s.current;
    final guardian = account?.isGuardian ?? false;
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            FamilyHeader(title: l.navMore, switcher: false),
            Expanded(
              child: ListView(
                padding: pagePadding.copyWith(top: Space.m),
                children: [
                  if (guardian) const ConsentNudge(),
                  const ClockPrompt(),
                  const _Who(),
                  if (guardian)
                    MenuGroup(
                      title: l.moreChildren,
                      children: [
                        MenuRow(
                          key: const Key('open-privacy'),
                          icon: Icons.verified_user_outlined,
                          label: l.privacyTitle,
                          onTap: () => context.go('/family/me/privacy'),
                        ),
                        MenuRow(
                          key: const Key('open-recordings'),
                          icon: Icons.videocam_outlined,
                          label: l.recordingsTitle,
                          onTap: () => context.go('/family/me/recordings'),
                        ),
                      ],
                    ),
                  MenuGroup(
                    title: l.moreTalk,
                    children: [
                      const _MessagesRow(),
                      MenuRow(
                        key: const Key('open-help'),
                        icon: Icons.help_outline_rounded,
                        label: l.helpTitle,
                        onTap: () => context.go('/family/me/help'),
                      ),
                      MenuRow(
                        key: const Key('open-tell-us'),
                        icon: Icons.flag_outlined,
                        label: l.tellUsTitle,
                        onTap: () => context.go('/family/me/tell-us'),
                      ),
                    ],
                  ),
                  MenuGroup(
                    title: l.moreMine,
                    children: [
                      // An adult student's own recordings are theirs to see too.
                      if (!guardian)
                        MenuRow(
                          key: const Key('open-recordings'),
                          icon: Icons.videocam_outlined,
                          label: l.recordingsTitle,
                          onTap: () => context.go('/family/me/recordings'),
                        ),
                      MenuRow(
                        key: const Key('open-accounts'),
                        icon: Icons.swap_horiz_rounded,
                        label: l.moreAccounts,
                        value: l.moreAcademies(s.accounts.length),
                        onTap: () => context.go('/family/me/accounts'),
                      ),
                      MenuRow(
                        key: const Key('open-choices'),
                        icon: Icons.notifications_none_rounded,
                        label: l.inboxTitle,
                        onTap: () => context.push('/inbox/choices'),
                      ),
                      MenuRow(
                        key: const Key('open-settings'),
                        icon: Icons.language_rounded,
                        label: l.moreLanguage,
                        value: '${s.locale == 'ar' ? 'العربي' : 'English'} · ${zoneName(s.timezone, context.locale)}',
                        onTap: () => context.go('/family/me/settings'),
                      ),
                      MenuRow(
                        key: const Key('open-security'),
                        icon: Icons.lock_outline_rounded,
                        label: l.moreSecurity,
                        onTap: () => context.go('/family/me/security'),
                      ),
                      MenuRow(
                        key: const Key('open-my-data'),
                        icon: Icons.description_outlined,
                        label: l.myDataTitle,
                        onTap: () => context.go('/family/me/my-data'),
                      ),
                    ],
                  ),
                  const Gap(Space.xl),
                  const _SignOutButton(),
                  if (s.appVersion case final v?) ...[
                    const Gap(Space.m),
                    Text(l.appVersion(v), textAlign: TextAlign.center, style: context.type.caption),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Who is signed in: their name, whose guardian (or what they are) and where.
class _Who extends StatelessWidget {
  const _Who();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final account = context.watch<SessionCubit>().state.current;
    if (account == null) return const SizedBox.shrink();
    final children = context.watch<LearnerCubit>().state.learners.where((x) => !x.isSelf).map((x) => x.firstName).toList();
    final who = account.isGuardian && children.isNotEmpty
        ? l.guardianOf(children.join('، '))
        : {for (final r in account.roles) roleLabel(context, r)}.join('، ');
    return LmsCard(
      key: const Key('more-who'),
      child: Row(
        children: [
          InitialAvatar(account.userName, size: 52, colour: context.colors.brandDeep),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(account.userName, style: context.type.cardTitle.copyWith(fontSize: 17)),
                Text([if (who.isNotEmpty) who, account.academyName(context.locale)].join(' · '), style: context.type.caption),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Messages with the learner's teachers, with how many wait unread (live).
class _MessagesRow extends StatefulWidget {
  const _MessagesRow();

  @override
  State<_MessagesRow> createState() => _MessagesRowState();
}

class _MessagesRowState extends State<_MessagesRow> {
  int _unread = 0;
  StreamSubscription<void>? _live;

  @override
  void initState() {
    super.initState();
    unawaited(_count());
    _live = context.read<Live>().on({Live.messages}).listen((_) => unawaited(_count()));
  }

  @override
  void dispose() {
    unawaited(_live?.cancel());
    super.dispose();
  }

  Future<void> _count() async {
    try {
      final n = await context.read<MessagesRepository>().unread();
      if (mounted) setState(() => _unread = n);
    } on Exception {
      // The row stays without a count.
    }
  }

  @override
  Widget build(BuildContext context) => MenuRow(
    key: const Key('open-messages'),
    icon: Icons.chat_bubble_outline_rounded,
    label: context.l10n.messagesFamilyTitle,
    count: _unread,
    onTap: () async {
      await context.push('/family/me/messages');
      if (mounted) await _count();
    },
  );
}

/// Signing out of this account on this phone, after asking (in red).
class _SignOutButton extends StatelessWidget {
  const _SignOutButton();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final current = context.watch<SessionCubit>().state.current;
    if (current == null) return const SizedBox.shrink();
    return OutlinedButton.icon(
      key: const Key('sign-out'),
      style: OutlinedButton.styleFrom(
        foregroundColor: c.dangerInk,
        side: BorderSide(color: c.danger.withValues(alpha: 0.45)),
        minimumSize: const Size.fromHeight(54),
      ),
      icon: const Icon(Icons.logout_rounded),
      label: Text(l.signOut, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
      onPressed: () async {
        final sure = await confirmDestructive(
          context,
          title: l.signOutConfirm(current.academyName(context.locale)),
          confirm: l.signOutDevice,
          keep: l.back,
        );
        if (sure && context.mounted) await context.read<SessionCubit>().signOut();
      },
    );
  }
}
