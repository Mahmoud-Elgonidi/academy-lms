import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/l10n.dart';
import '../../../../core/realtime/live.dart';
import '../../../../shared/motion/bell_ring.dart';
import '../../../../shared/motion/motion.dart';
import '../../data/notifications_repository.dart';

/// The bell on a home: how many unread, a swing when there are some; opens
/// the inbox, and counts again on the way back, or when a message arrives
/// or is read on another device (the live signal).
class NotificationBell extends StatefulWidget {
  const NotificationBell({super.key});

  @override
  State<NotificationBell> createState() => _NotificationBellState();
}

class _NotificationBellState extends State<NotificationBell> {
  int _unread = 0;
  StreamSubscription<void>? _live;

  @override
  void initState() {
    super.initState();
    unawaited(_count());
    _live = context.read<Live>().on({Live.inbox}).listen((_) => unawaited(_count()));
  }

  @override
  void dispose() {
    unawaited(_live?.cancel());
    super.dispose();
  }

  Future<void> _count() async {
    try {
      final n = await context.read<NotificationsRepository>().unread();
      if (mounted) setState(() => _unread = n);
    } on Exception {
      // The bell just stays quiet.
    }
  }

  @override
  Widget build(BuildContext context) {
    Widget icon = Icon(_unread > 0 ? Icons.notifications_active_rounded : Icons.notifications_none_rounded);
    // The kids' bell wiggles while something waits; the others ring once
    // when something new comes in.
    icon = context.flavour == MotionFlavour.kids ? (_unread > 0 ? icon.wiggle(context) : icon) : BellRing(count: _unread, child: icon);
    return IconButton(
      tooltip: '${context.l10n.inboxTitle} · ${context.l10n.unreadCount(_unread)}',
      onPressed: () async {
        await context.push('/inbox');
        if (mounted) await _count();
      },
      icon: Badge(isLabelVisible: _unread > 0, label: Text('$_unread'), child: icon),
    );
  }
}
