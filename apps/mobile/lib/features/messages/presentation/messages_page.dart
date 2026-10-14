import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router/areas.dart';
import '../../../app/theme/app_theme.dart';
import '../../../core/auth/session_cubit.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../data/messages_repository.dart';
import '../domain/messages.dart';

/// Who is reading: a guardian (about the learner the family tabs show), a
/// student about themself (a child too), or a teacher.
enum MessagesScope { family, self, teacher }

/// "Messages": a teacher's students, or the learner's teachers, the newest
/// first, with what is new since they last read; the student's guardians are
/// in every conversation. It follows them live.
class MessagesPage extends StatelessWidget {
  const MessagesPage({required this.scope, this.at, super.key});

  final MessagesScope scope;

  /// Where this list is, when it is a tab of its own (a child's "talk to your teacher", middle school's messages).
  final String? at;

  /// Where a conversation of this look opens.
  String get base =>
      at ??
      switch (scope) {
        MessagesScope.family => '/family/me/messages',
        MessagesScope.self => '/account/messages',
        MessagesScope.teacher => '/teach/more/messages',
      };

  @override
  Widget build(BuildContext context) {
    final learnerId = scope == MessagesScope.family ? context.watch<LearnerCubit>().state.selected?.id : null;
    final repo = context.read<MessagesRepository>();
    final l = context.l10n;
    final area = Area.of(context.watch<SessionCubit>().state);
    final title = switch ((scope, area)) {
      (MessagesScope.teacher, _) => l.messagesTitle,
      (MessagesScope.family, _) => l.messagesFamilyTitle,
      (_, Area.kids) => l.messagesKidsTitle,
      _ => l.messagesStudentTitle,
    };
    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        // A guardian's conversations are about one child: which, and another a tap away.
        actions: [if (scope == MessagesScope.family) const LearnerChip()],
      ),
      body: BlocProvider(
        key: ValueKey(learnerId),
        create: (c) =>
            LoadCubit<ConversationList>(() => repo.conversations(learnerId: learnerId), refreshOn: c.read<Live>().on({Live.messages}))
              ..load(),
        child: LoadView<ConversationList>(
          builder: (context, list) => ListView(
            padding: pagePadding,
            physics: const AlwaysScrollableScrollPhysics(),
            children: staggered(context, [
              if (list.notice.isNotEmpty) Note(list.notice, icon: Icons.visibility_outlined),
              const SizedBox(height: 14),
              if (list.rows.isEmpty)
                EmptyNote(scope == MessagesScope.teacher ? l.messagesEmptyTeacher : l.messagesEmptyFamily, icon: Icons.forum_outlined),
              // The kids keep a card each; the other looks list them on one card, the newest first.
              if (context.isKids)
                for (final row in list.rows) ...[_Row(row: row, onTap: () => _open(context, row, learnerId)), const SizedBox(height: 10)]
              else if (list.rows.isNotEmpty)
                LmsCard(
                  padding: EdgeInsets.zero,
                  child: Column(
                    children: [
                      for (final (i, row) in list.rows.indexed) ...[
                        if (i > 0) const Divider(height: 1, indent: Space.l, endIndent: Space.l),
                        _Row(row: row, grouped: true, onTap: () => _open(context, row, learnerId)),
                      ],
                    ],
                  ),
                ),
            ]),
          ),
        ),
      ),
    );
  }

  Future<void> _open(BuildContext context, ConversationRow row, String? learnerId) async {
    final id = row.id;
    if (id != null) {
      context.go('$base/$id');
      return;
    }
    try {
      final opened = await context.read<MessagesRepository>().open(row.withId, learnerId: learnerId);
      if (context.mounted) context.go('$base/${opened.id}');
    } on ApiProblem catch (p) {
      if (context.mounted) showSnack(context, context.problemText(p));
    }
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.row, required this.onTap, this.grouped = false});

  final ConversationRow row;
  final VoidCallback onTap;

  /// One of the rows on a shared card, rather than a card of its own.
  final bool grouped;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final last = row.lastBody;
    final body = Row(
      children: [
        grouped ? SoftAvatar(row.withName) : InitialAvatar(row.withName),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(row.withName, style: context.text.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 2),
              Text(
                last == null ? l.messagesStart : (row.lastMine ? '${l.messagesYou}: $last' : last),
                style: context.text.bodySmall?.copyWith(color: c.muted),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            if (row.lastAt != null) Text(row.lastAt!.label, style: context.text.labelSmall?.copyWith(color: c.muted)),
            const SizedBox(height: 4),
            if (row.unread > 0)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
                decoration: BoxDecoration(color: c.brand, borderRadius: BorderRadius.circular(999)),
                child: Text(
                  '${row.unread}',
                  style: context.text.labelSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w700),
                ),
              )
            else if (!row.canWrite)
              Icon(Icons.lock_outline_rounded, size: 16, color: c.muted),
          ],
        ),
      ],
    );
    if (!grouped) return LmsCard(key: Key('conversation-${row.withId}'), onTap: onTap, child: body);
    return InkWell(
      key: Key('conversation-${row.withId}'),
      onTap: PressScale.withPress(context, onTap),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m + 2),
        child: body,
      ),
    );
  }
}
