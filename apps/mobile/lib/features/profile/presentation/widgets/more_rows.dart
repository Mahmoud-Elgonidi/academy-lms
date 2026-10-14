import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../messages/data/messages_repository.dart';

/// Who is signed in, at the top of a More tab: their name, a [line] under it
/// (whose guardian, or what they teach, and where), and their account when
/// [onTap] opens it.
class MoreWho extends StatelessWidget {
  const MoreWho({required this.line, this.onTap, super.key});

  final String line;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final account = context.watch<SessionCubit>().state.current;
    if (account == null) return const SizedBox.shrink();
    final c = context.colors;
    return LmsCard(
      key: const Key('more-who'),
      onTap: onTap,
      child: Row(
        children: [
          SoftAvatar(account.userName, size: 52),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(account.userName, style: context.type.cardTitle.copyWith(fontSize: 17)),
                Text(line, style: context.type.caption),
              ],
            ),
          ),
          if (onTap != null) Icon(Icons.chevron_right_rounded, color: c.faint, size: 22),
        ],
      ),
    );
  }
}

/// Messages, with how many wait unread (live); [at] is where they open.
class MessagesRow extends StatefulWidget {
  const MessagesRow({required this.at, required this.label, super.key});

  final String at;
  final String label;

  @override
  State<MessagesRow> createState() => _MessagesRowState();
}

class _MessagesRowState extends State<MessagesRow> {
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
    label: widget.label,
    count: _unread,
    onTap: () async {
      await context.push(widget.at);
      if (mounted) await _count();
    },
  );
}

/// Signing out of this account on this phone, after asking (in red).
class SignOutButton extends StatelessWidget {
  const SignOutButton({super.key});

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

/// The app's version under a More tab.
class AppVersionLine extends StatelessWidget {
  const AppVersionLine({super.key});

  @override
  Widget build(BuildContext context) {
    final v = context.select<SessionCubit, String?>((s) => s.state.appVersion);
    if (v == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: Space.m),
      child: Text(context.l10n.appVersion(v), textAlign: TextAlign.center, style: context.type.caption),
    );
  }
}
