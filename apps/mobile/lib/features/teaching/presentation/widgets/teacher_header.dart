import 'package:flutter/material.dart';

import '../../../../shared/widgets/lms.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';

/// The top of each of a teacher's tabs: a line above, the title and the
/// bell; [leading] is the academy's logo on Today, [bottom] what sits under
/// it (the week's days).
class TeacherHeader extends StatelessWidget {
  const TeacherHeader({required this.title, this.eyebrow, this.leading, this.bottom, super.key});

  final String title;
  final String? eyebrow;
  final Widget? leading;
  final Widget? bottom;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(18, Space.m, 18, Space.xs),
    child: AppHeader(title: title, eyebrow: eyebrow, leading: leading, actions: const [NotificationBell(framed: true)], bottom: bottom),
  );
}

/// A teacher's tab: its header over what scrolls.
class TeacherTab extends StatelessWidget {
  const TeacherTab({required this.header, required this.body, super.key});

  final Widget header;
  final Widget body;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      bottom: false,
      child: Column(
        children: [
          header,
          Expanded(child: body),
        ],
      ),
    ),
  );
}
