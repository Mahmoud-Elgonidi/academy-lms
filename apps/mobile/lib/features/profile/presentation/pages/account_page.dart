import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/router/areas.dart';
import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/social/social_sign_in.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../consent/presentation/privacy_page.dart';
import '../../data/profile_repository.dart';
import '../widgets/accounts_card.dart';

/// "Me": the accounts on this phone, the clock, language and sounds, Google
/// and Apple sign-in, the devices signed in to this account, and signing out.
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
            if (!standalone) const _Recordings(),
            const _Help(),
            const _TellUs(),
            const _MyData(),
            const _ClockPrompt(),
            const _Settings(),
            const _SignInMethods(),
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

/// Lesson recordings for a family (guardians and adult students): consent and the recordings to watch.
class _Recordings extends StatelessWidget {
  const _Recordings();

  @override
  Widget build(BuildContext context) {
    if (Area.of(context.watch<SessionCubit>().state) != Area.family) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: LmsCard(
        padding: EdgeInsets.zero,
        child: ListTile(
          key: const Key('open-recordings'),
          leading: const Icon(Icons.video_library_outlined),
          title: Text(context.l10n.recordingsTitle, style: context.text.titleSmall),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.go('/family/me/recordings'),
        ),
      ),
    );
  }
}

/// Writing to the academy: a family's "Help" from Me, a student's from their account; not a child in the kids' look
/// (their guardian writes), and a teacher's is in More.
class _Help extends StatelessWidget {
  const _Help();

  @override
  Widget build(BuildContext context) {
    final target = switch (Area.of(context.watch<SessionCubit>().state)) {
      Area.family => '/family/me/help',
      Area.middle || Area.teen => '/account/help',
      _ => null,
    };
    if (target == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: LmsCard(
        padding: EdgeInsets.zero,
        child: ListTile(
          key: const Key('open-help'),
          leading: const Icon(Icons.support_agent_outlined),
          title: Text(context.l10n.helpTitle, style: context.text.titleSmall),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.go(target),
        ),
      ),
    );
  }
}

/// Telling the academy's safeguarding leads that something is wrong: everyone, a child in the kids' look too (a
/// family's from Me, a student's from their account); a teacher's is in More.
class _TellUs extends StatelessWidget {
  const _TellUs();

  @override
  Widget build(BuildContext context) {
    final area = Area.of(context.watch<SessionCubit>().state);
    final target = switch (area) {
      Area.family => '/family/me/tell-us',
      Area.kids || Area.middle || Area.teen => '/account/tell-us',
      _ => null,
    };
    if (target == null) return const SizedBox.shrink();
    final l = context.l10n;
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: LmsCard(
        padding: EdgeInsets.zero,
        child: ListTile(
          key: const Key('open-tell-us'),
          leading: const Icon(Icons.shield_outlined),
          title: Text(switch (area) {
            Area.kids => l.tellUsNavKids,
            Area.middle => l.tellUsNavMiddle,
            _ => l.tellUsTitle,
          }, style: context.text.titleSmall),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.go(target),
        ),
      ),
    );
  }
}

/// One's own data (a copy, erasure) and the academy's privacy pages: a family's from Me, a student's from their
/// account (a younger student sees that their guardian asks, and the pages); not a child in the kids' look, and a
/// teacher's is in More.
class _MyData extends StatelessWidget {
  const _MyData();

  @override
  Widget build(BuildContext context) {
    final target = switch (Area.of(context.watch<SessionCubit>().state)) {
      Area.family => '/family/me/my-data',
      Area.middle || Area.teen => '/account/my-data',
      _ => null,
    };
    if (target == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: LmsCard(
        padding: EdgeInsets.zero,
        child: ListTile(
          key: const Key('open-my-data'),
          leading: const Icon(Icons.fingerprint_rounded),
          title: Text(context.l10n.myDataTitle, style: context.text.titleSmall),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.go(target),
        ),
      ),
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

/// Google and Apple on this account: linked as which email, or link it with the phone's own sign-in; unlink (the API
/// refuses when it is the only way in). Only what this phone can offer, or what is linked already; not a child in the
/// kids' look (their guardian signs in for them).
class _SignInMethods extends StatelessWidget {
  const _SignInMethods();

  @override
  Widget build(BuildContext context) {
    if (Area.of(context.watch<SessionCubit>().state) == Area.kids) return const SizedBox.shrink();
    return BlocProvider(
      create: (c) => LoadCubit<List<SignInMethod>>(c.read<ProfileRepository>().signInMethods)..load(),
      child: const _SignInMethodsCard(),
    );
  }
}

class _SignInMethodsCard extends StatefulWidget {
  const _SignInMethodsCard();

  @override
  State<_SignInMethodsCard> createState() => _SignInMethodsCardState();
}

class _SignInMethodsCardState extends State<_SignInMethodsCard> {
  String? _busy;

  String _label(String provider) => provider == 'apple' ? context.l10n.providerApple : context.l10n.providerGoogle;

  Future<void> _run(String provider, Future<String?> Function() step) async {
    setState(() => _busy = provider);
    try {
      final done = await step();
      if (!mounted) return;
      if (done != null) showSnack(context, done);
      await context.read<LoadCubit<List<SignInMethod>>>().load();
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } catch (_) {
      if (mounted) showSnack(context, context.l10n.socialFailed(_label(provider)));
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  Future<void> _link(String provider) => _run(provider, () async {
    final credential = await context.read<SocialSignIn>().signIn(provider);
    if (credential == null || !mounted) return null;
    await context.read<ProfileRepository>().link(credential);
    return mounted ? context.l10n.signInMethodLinkedDone(_label(provider)) : null;
  });

  Future<void> _unlink(String provider) async {
    final l = context.l10n;
    final sure = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        content: Text(l.signInMethodUnlinkConfirm(_label(provider))),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: Text(l.back)),
          FilledButton(onPressed: () => Navigator.pop(d, true), child: Text(l.signInMethodUnlink)),
        ],
      ),
    );
    if (sure != true || !mounted) return;
    await _run(provider, () async {
      await context.read<ProfileRepository>().unlink(provider);
      return mounted ? l.signInMethodUnlinkedDone(_label(provider)) : null;
    });
  }

  @override
  Widget build(BuildContext context) => BlocBuilder<LoadCubit<List<SignInMethod>>, LoadState<List<SignInMethod>>>(
    builder: (context, state) {
      final social = context.read<SocialSignIn>();
      final methods = [
        for (final m in state.data ?? const <SignInMethod>[])
          if (m.linked || social.available(m.provider)) m,
      ];
      if (methods.isEmpty) return const SizedBox.shrink();
      final l = context.l10n;
      return Padding(
        padding: const EdgeInsets.only(top: 18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SectionLabel(l.signInMethodsTitle),
            LmsCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(l.signInMethodsHint, style: context.text.bodySmall?.copyWith(color: context.colors.muted)),
                  for (final m in methods) ...[
                    const Divider(height: 22),
                    Row(
                      key: Key('sign-in-method-${m.provider}'),
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(_label(m.provider), style: context.text.titleSmall),
                              Text(
                                m.linked
                                    ? (m.email == null ? l.signInMethodLinked : l.signInMethodLinkedAs(m.email!))
                                    : l.signInMethodNotLinked,
                                style: context.text.bodySmall?.copyWith(color: context.colors.muted),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        if (m.linked)
                          OutlinedButton(onPressed: _busy != null ? null : () => _unlink(m.provider), child: Text(l.signInMethodUnlink))
                        else
                          FilledButton.tonal(onPressed: _busy != null ? null : () => _link(m.provider), child: Text(l.signInMethodLink)),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      );
    },
  );
}
