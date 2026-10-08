import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../consent/presentation/privacy_page.dart';
import '../../data/profile_repository.dart';
import '../widgets/accounts_card.dart';

/// "Me": the accounts on this phone, the clock, language and sounds, the devices
/// signed in to this account, and signing out.
class AccountPage extends StatelessWidget {
  const AccountPage({this.standalone = false, super.key});

  /// Opened on its own (a student's avatar, the teacher's More) rather than as a tab.
  final bool standalone;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<List<DeviceSession>>(c.read<ProfileRepository>().devices)..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.meTitle), automaticallyImplyLeading: standalone),
      body: BlocListener<SessionCubit, SessionState>(
        listenWhen: (a, b) => a.current?.id != b.current?.id,
        listener: (context, _) => context.read<LoadCubit<List<DeviceSession>>>().load(),
        child: ListView(
          padding: pagePadding,
          children: [
            const AccountsCard(),
            const SizedBox(height: 14),
            if (!standalone) const _Privacy(),
            const _ClockPrompt(),
            const _Settings(),
            const SizedBox(height: 18),
            const _Devices(),
            const SizedBox(height: 18),
            const _SignOut(),
          ],
        ),
      ),
    ),
  );
}

/// A guardian's way to their children's privacy (their consent to each child's account).
class _Privacy extends StatelessWidget {
  const _Privacy();

  @override
  Widget build(BuildContext context) {
    final account = context.watch<SessionCubit>().state.current;
    if (account == null || !account.isGuardian) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const ConsentNudge(),
        LmsCard(
          padding: EdgeInsets.zero,
          child: ListTile(
            leading: const Icon(Icons.verified_user_outlined),
            title: Text(context.l10n.privacyTitle, style: context.text.titleSmall),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.go('/family/me/privacy'),
          ),
        ),
        const SizedBox(height: 14),
      ],
    );
  }
}

class _ClockPrompt extends StatelessWidget {
  const _ClockPrompt();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<SessionCubit>().state;
    if (!s.offerClock) return const SizedBox.shrink();
    final device = zoneName(s.deviceTimezone, context.locale);
    final profile = zoneName(s.me?.timezone, context.locale);
    final cubit = context.read<SessionCubit>();
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Note(
        context.l10n.clockPrompt(device, profile),
        icon: Icons.schedule_rounded,
        action: Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            FilledButton(
              style: FilledButton.styleFrom(minimumSize: const Size(64, 42), backgroundColor: context.colors.info),
              onPressed: () async {
                try {
                  await cubit.usePhoneClock();
                } on ApiProblem catch (p) {
                  if (context.mounted) showSnack(context, context.problemText(p));
                }
              },
              child: Text(context.l10n.clockYes(device)),
            ),
            OutlinedButton(
              style: OutlinedButton.styleFrom(minimumSize: const Size(64, 42), backgroundColor: Colors.white),
              onPressed: cubit.keepProfileClock,
              child: Text(context.l10n.clockKeep(profile)),
            ),
          ],
        ),
      ),
    );
  }
}

class _Settings extends StatelessWidget {
  const _Settings();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<SessionCubit>().state;
    return LmsCard(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(child: Text(context.l10n.language, style: context.text.titleSmall)),
              SegmentedButton<String>(
                showSelectedIcon: false,
                segments: const [
                  ButtonSegment(value: 'ar', label: Text('العربي')),
                  ButtonSegment(value: 'en', label: Text('English')),
                ],
                selected: {s.locale},
                onSelectionChanged: (v) => context.read<SessionCubit>().setLocale(v.first),
              ),
            ],
          ),
          const Divider(height: 22),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(context.l10n.sounds, style: context.text.titleSmall),
                    Text(context.l10n.soundsHint, style: context.text.bodySmall),
                  ],
                ),
              ),
              Switch.adaptive(value: s.sounds, onChanged: (on) => context.read<SessionCubit>().setSounds(on)),
            ],
          ),
          const Divider(height: 22),
          Row(
            children: [
              Expanded(child: Text(context.l10n.timezone, style: context.text.titleSmall)),
              Text(zoneName(s.timezone, context.locale), style: context.text.bodyMedium?.copyWith(color: context.colors.muted)),
            ],
          ),
          const SizedBox(height: 6),
        ],
      ),
    );
  }
}

class _Devices extends StatelessWidget {
  const _Devices();

  @override
  Widget build(BuildContext context) => BlocBuilder<LoadCubit<List<DeviceSession>>, LoadState<List<DeviceSession>>>(
    builder: (context, state) {
      final devices = state.data;
      if (devices == null || devices.isEmpty) return const SizedBox.shrink();
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SectionLabel(context.l10n.myDevices),
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                for (final (i, d) in devices.indexed) ...[
                  if (i > 0) const Divider(height: 1),
                  ListTile(
                    leading: Icon(d.device.toLowerCase().contains('web') ? Icons.laptop_rounded : Icons.phone_iphone_rounded),
                    title: Text(d.device, style: context.text.titleSmall),
                    subtitle: Text(
                      d.current ? context.l10n.thisDevice : context.l10n.lastUsed(d.lastUsedAt?.when(context) ?? ''),
                      style: context.text.bodySmall,
                    ),
                    trailing: d.current
                        ? null
                        : TextButton(
                            style: TextButton.styleFrom(foregroundColor: context.colors.dangerInk),
                            onPressed: () async {
                              try {
                                await context.read<ProfileRepository>().signOutDevice(d.id);
                                if (!context.mounted) return;
                                showSnack(context, context.l10n.deviceSignedOut);
                                await context.read<LoadCubit<List<DeviceSession>>>().load();
                              } on ApiProblem catch (p) {
                                if (context.mounted) showSnack(context, context.problemText(p));
                              }
                            },
                            child: Text(context.l10n.signOutDevice),
                          ),
                  ),
                ],
              ],
            ),
          ),
        ],
      );
    },
  );
}

class _SignOut extends StatelessWidget {
  const _SignOut();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<SessionCubit>().state;
    final current = s.current;
    if (current == null) return const SizedBox.shrink();
    return TextButton.icon(
      style: TextButton.styleFrom(foregroundColor: context.colors.dangerInk),
      icon: const Icon(Icons.logout_rounded),
      label: Text(context.l10n.signOut),
      onPressed: () async {
        final sure = await showDialog<bool>(
          context: context,
          builder: (c) => AlertDialog(
            content: Text(c.l10n.signOutConfirm(current.academyName(c.locale))),
            actions: [
              TextButton(onPressed: () => Navigator.pop(c, false), child: Text(c.l10n.back)),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: c.colors.danger, minimumSize: const Size(64, 44)),
                onPressed: () => Navigator.pop(c, true),
                child: Text(c.l10n.signOutDevice),
              ),
            ],
          ),
        );
        if (sure == true && context.mounted) await context.read<SessionCubit>().signOut();
      },
    );
  }
}
