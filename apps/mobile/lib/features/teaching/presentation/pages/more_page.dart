import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';

/// The teacher's other screens: reports, marking, hours, the lesson link, time off, support and the account.
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
                  key: const Key('open-meeting-link'),
                  leading: const Icon(Icons.link_rounded),
                  title: Text(l.meetingLinkTitle),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go('/teach/more/meeting-link'),
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
                  key: const Key('open-messages'),
                  leading: const Icon(Icons.forum_outlined),
                  title: Text(l.messagesTitle),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go('/teach/more/messages'),
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
                  key: const Key('open-concern'),
                  leading: const Icon(Icons.shield_outlined),
                  title: Text(l.concernTeacherNav),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go('/teach/more/concern'),
                ),
                const Divider(height: 1),
                ListTile(
                  key: const Key('open-documents'),
                  leading: const Icon(Icons.badge_outlined),
                  title: Text(l.documentsTitle),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go('/teach/more/documents'),
                ),
                const Divider(height: 1),
                ListTile(
                  key: const Key('open-my-data'),
                  leading: const Icon(Icons.fingerprint_rounded),
                  title: Text(l.myDataTitle),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => context.go('/teach/more/my-data'),
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
