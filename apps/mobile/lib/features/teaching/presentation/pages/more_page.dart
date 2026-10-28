import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../profile/presentation/pages/account_page.dart';
import '../../../profile/presentation/widgets/more_rows.dart';
import '../../data/teaching_repository.dart';
import '../../domain/teaching.dart';
import '../widgets/teacher_header.dart';

/// A teacher's More: who is signed in, then three groups: teaching
/// (messages with their count, the lesson link, recordings, my card on the
/// academy's page), work (hours, time off, earnings, my performance,
/// documents) and the account (settings, support, raising a concern, my
/// data), and signing out.
class MorePage extends StatelessWidget {
  const MorePage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final account = context.watch<SessionCubit>().state.current;
    return TeacherTab(
      header: TeacherHeader(title: l.navMore),
      body: ListView(
        padding: pagePadding.copyWith(top: Space.m),
        children: [
          const ClockPrompt(),
          if (account != null)
            MoreWho(line: [l.roleTeacher, account.academyName(context.locale)].join(' · '), onTap: () => context.push('/account')),
          const _Groups(),
          const Gap(Space.xl),
          const SignOutButton(),
          const AppVersionLine(),
        ],
      ),
    );
  }
}

/// The rows, with what is worth knowing before opening them: the link's
/// service, where a change to the hours stands.
class _Groups extends StatefulWidget {
  const _Groups();

  @override
  State<_Groups> createState() => _GroupsState();
}

class _GroupsState extends State<_Groups> {
  String? _link;
  bool _linkMissing = false;
  String? _hours;

  @override
  void initState() {
    super.initState();
    unawaited(_read());
  }

  Future<void> _read() async {
    final repo = context.read<TeachingRepository>();
    final link = repo.meetingLink().then<MeetingLinkInfo?>((m) => m).catchError((_) => null);
    final hours = repo.shifts().then<String?>((s) => s.proposal?.statusLabel).catchError((_) => null);
    final (m, h) = (await link, await hours);
    if (!mounted) return;
    setState(() {
      _link = m?.url == null ? null : linkService(m!.url!);
      _linkMissing = m != null && m.url == null && m.used;
      _hours = h;
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        MenuGroup(
          title: l.moreTeaching,
          children: [
            MessagesRow(at: '/teach/more/messages', label: l.messagesTitle),
            MenuRow(
              key: const Key('open-meeting-link'),
              icon: Icons.link_rounded,
              label: l.meetingLinkTitle,
              value: _link ?? (_linkMissing ? l.meetingLinkMissing : null),
              onTap: () async {
                await context.push('/teach/more/meeting-link');
                if (mounted) await _read();
              },
            ),
            MenuRow(
              key: const Key('open-recordings'),
              icon: Icons.videocam_outlined,
              label: l.recordingsMine,
              onTap: () => context.go('/teach/more/recordings'),
            ),
            MenuRow(
              key: const Key('open-card'),
              icon: Icons.badge_outlined,
              label: l.teacherCardTitle,
              onTap: () => context.go('/teach/more/card'),
            ),
          ],
        ),
        MenuGroup(
          title: l.moreWork,
          children: [
            MenuRow(
              key: const Key('open-hours'),
              icon: Icons.schedule_rounded,
              label: l.myHours,
              value: _hours,
              onTap: () async {
                await context.push('/teach/hours');
                if (mounted) await _read();
              },
            ),
            MenuRow(
              key: const Key('open-time-off'),
              icon: Icons.beach_access_outlined,
              label: l.timeOff,
              onTap: () => context.go('/teach/more/time-off'),
            ),
            MenuRow(
              key: const Key('open-earnings'),
              icon: Icons.account_balance_wallet_outlined,
              label: l.navEarnings,
              onTap: () => context.go('/teach/more/earnings'),
            ),
            MenuRow(
              key: const Key('open-performance'),
              icon: Icons.insights_outlined,
              label: l.performanceTitle,
              onTap: () => context.go('/teach/more/performance'),
            ),
            MenuRow(
              key: const Key('open-documents'),
              icon: Icons.fact_check_outlined,
              label: l.documentsTitle,
              onTap: () => context.go('/teach/more/documents'),
            ),
          ],
        ),
        MenuGroup(
          title: l.moreMine,
          children: [
            MenuRow(
              key: const Key('open-account'),
              icon: Icons.settings_outlined,
              label: l.moreAccountSettings,
              onTap: () => context.push('/account'),
            ),
            MenuRow(
              key: const Key('open-support'),
              icon: Icons.help_outline_rounded,
              label: l.supportTitle,
              onTap: () => context.go('/teach/more/help'),
            ),
            MenuRow(
              key: const Key('open-concern'),
              icon: Icons.flag_outlined,
              label: l.concernTeacherNav,
              onTap: () => context.go('/teach/more/concern'),
            ),
            MenuRow(
              key: const Key('open-my-data'),
              icon: Icons.description_outlined,
              label: l.myDataTitle,
              onTap: () => context.go('/teach/more/my-data'),
            ),
          ],
        ),
      ],
    );
  }
}

/// The service a lesson link is on, by its address ("Google Meet", "Zoom"), else its host.
String linkService(String url) {
  final host = Uri.tryParse(url.trim())?.host.toLowerCase() ?? '';
  if (host.endsWith('meet.google.com')) return 'Google Meet';
  if (host.endsWith('zoom.us') || host.endsWith('zoom.com')) return 'Zoom';
  if (host.endsWith('teams.microsoft.com') || host.endsWith('teams.live.com')) return 'Teams';
  return host.startsWith('www.') ? host.substring(4) : host;
}
