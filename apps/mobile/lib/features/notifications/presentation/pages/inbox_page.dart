import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/router/areas.dart';
import '../../../../app/router/targets.dart';
import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/notifications_repository.dart';
import '../../domain/notifications.dart';

/// The inbox: newest first, unread marked; a tap marks it read and opens
/// where it belongs in this look. More on demand, all read in one tap.
class InboxPage extends StatefulWidget {
  const InboxPage({super.key});

  @override
  State<InboxPage> createState() => _InboxPageState();
}

class _InboxPageState extends State<InboxPage> {
  final _items = <InboxItem>[];
  String? _cursor;
  bool _loading = true;
  bool _more = false;
  ApiProblem? _problem;

  @override
  void initState() {
    super.initState();
    _load(first: true);
  }

  Future<void> _load({bool first = false}) async {
    setState(() {
      _loading = true;
      _problem = null;
    });
    try {
      final page = await context.read<NotificationsRepository>().inbox(cursor: first ? null : _cursor);
      if (!mounted) return;
      setState(() {
        if (first) _items.clear();
        _items.addAll(page.items);
        _cursor = page.nextCursor;
        _more = page.nextCursor != null;
        _loading = false;
      });
    } on ApiProblem catch (p) {
      if (mounted) {
        setState(() {
          _problem = p;
          _loading = false;
        });
      }
    }
  }

  Future<void> _allRead() async {
    try {
      await context.read<NotificationsRepository>().markRead();
      if (mounted) setState(() => _items.setAll(0, _items.map((i) => i.readNow()).toList()));
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    }
  }

  Future<void> _open(int index) async {
    final item = _items[index];
    if (!item.read) {
      setState(() => _items[index] = item.readNow());
      try {
        await context.read<NotificationsRepository>().markRead([item.id]);
      } on ApiProblem {
        // Read on this screen; the server catches up next time.
      }
    }
    if (!mounted) return;
    final area = Area.of(context.read<SessionCubit>().state);
    openTarget(GoRouter.of(context), targetOf(item.screen, item.targetId, area));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.inboxTitle),
        actions: [
          if (_items.any((i) => !i.read)) IconButton(tooltip: l.markAllRead, onPressed: _allRead, icon: const Icon(Icons.done_all_rounded)),
          IconButton(tooltip: l.notificationChoices, onPressed: () => context.push('/inbox/choices'), icon: const Icon(Icons.tune_rounded)),
        ],
      ),
      body: _problem != null && _items.isEmpty
          ? ProblemView(message: context.problemText(_problem!), onRetry: () => _load(first: true))
          : _loading && _items.isEmpty
          ? const Center(child: CircularProgressIndicator.adaptive())
          : RefreshIndicator.adaptive(
              onRefresh: () => _load(first: true),
              child: ListView(
                padding: pagePadding,
                physics: const AlwaysScrollableScrollPhysics(),
                children: staggered(context, [
                  if (_items.isEmpty) EmptyNote(l.noNotifications, icon: Icons.notifications_none_rounded),
                  for (final (i, n) in _items.indexed) ...[
                    LmsCard(
                      color: n.read ? null : c.brandSoft,
                      onTap: () => _open(i),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Padding(
                            padding: const EdgeInsets.only(top: 6),
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 300),
                              width: 9,
                              height: 9,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: n.read ? Colors.transparent : (n.urgent ? c.danger : c.brand),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(n.title, style: context.text.titleSmall),
                                if (n.body.isNotEmpty)
                                  Text(n.body, style: context.text.bodyMedium, maxLines: 3, overflow: TextOverflow.ellipsis),
                                if (n.at != null) ...[const SizedBox(height: 2), Text(n.at!.when(context), style: context.text.bodySmall)],
                              ],
                            ),
                          ),
                          const Icon(Icons.chevron_right_rounded),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                  if (_more)
                    Center(
                      child: TextButton(onPressed: _loading ? null : () => _load(), child: Text(l.loadMore)),
                    ),
                ]),
              ),
            ),
    );
  }
}
