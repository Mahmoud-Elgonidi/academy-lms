import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';

/// The teacher's other screens: reports, marking, hours, time off, support and the account.
class MorePage extends StatelessWidget {
  const MorePage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.navMore)),
      body: ListView(
        padding: pagePadding,
        children: [
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.article_outlined),
                  title: Text(l.navReports),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.push('/teach/reports'),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.rate_review_outlined),
                  title: Text(l.markTitle),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.push('/teach/marking'),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.schedule_rounded),
                  title: Text(l.myHours),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.push('/teach/hours'),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.beach_access_outlined),
                  title: Text(l.timeOff),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.push('/teach/more/time-off'),
                ),
                const Divider(height: 1),
                ListTile(
                  key: const Key('open-recordings'),
                  leading: const Icon(Icons.video_library_outlined),
                  title: Text(l.recordingsMine),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go('/teach/more/recordings'),
                ),
                const Divider(height: 1),
                ListTile(
                  key: const Key('open-support'),
                  leading: const Icon(Icons.support_agent_outlined),
                  title: Text(l.supportTitle),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go('/teach/more/help'),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.person_outline_rounded),
                  title: Text(l.meTitle),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.push('/account'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
