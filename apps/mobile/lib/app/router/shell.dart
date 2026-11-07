import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session_cubit.dart';
import '../../features/onboarding/presentation/welcome_moment.dart';
import '../../shared/motion/juice.dart';
import '../../shared/motion/motion.dart';
import '../../shared/motion/tab_pop.dart';

/// One tab of a look's bar; [count] puts a dot with a number on it
/// (rewards waiting, which beat; work waiting, which keeps still). A tab
/// not [shown] (its area is off for the academy) leaves the bar; its branch
/// stays, and nothing leads there.
class ShellTab {
  const ShellTab(this.icon, this.selectedIcon, this.label, {this.count = 0, this.beat = true, this.shown = true});

  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final int count;
  final bool beat;
  final bool shown;
}

/// The frame of a signed-in look: its tabs at the bottom, each keeping its
/// own stack. The tab picked pops its icon, with a tap sound. Just after
/// signing in, the academy's greeting plays over it once.
class AppShell extends StatelessWidget {
  const AppShell({required this.shell, required this.tabs, super.key});

  final StatefulNavigationShell shell;
  final List<ShellTab> tabs;

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionCubit>();
    final account = session.state.current;
    final frame = _frame(context);
    if (!session.state.greeting || account == null) return frame;
    return Stack(
      children: [
        frame,
        Positioned.fill(
          child: WelcomeMoment(account: account, onDone: session.greeted),
        ),
      ],
    );
  }

  Widget _frame(BuildContext context) {
    // The bar's places and the branches they stand for (a hidden tab's branch has no place).
    final branches = [
      for (final (i, t) in tabs.indexed)
        if (t.shown) i,
    ];
    final selected = branches.indexOf(shell.currentIndex);
    return Scaffold(
      body: shell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: selected < 0 ? 0 : selected,
        onDestinationSelected: (i) {
          final branch = branches[i];
          if (branch != shell.currentIndex) context.flavour == MotionFlavour.kids ? Juice.tap() : Juice.press();
          shell.goBranch(branch, initialLocation: branch == shell.currentIndex);
        },
        destinations: [
          for (final t in tabs.where((t) => t.shown))
            NavigationDestination(
              icon: _counted(t, Icon(t.icon)),
              selectedIcon: _counted(t, TabPop(child: Icon(t.selectedIcon))),
              label: t.label,
            ),
        ],
      ),
    );
  }

  static Widget _counted(ShellTab t, Widget icon) => t.count == 0
      ? icon
      : Badge(
          label: Text('${t.count}'),
          child: t.beat ? Builder(builder: (context) => icon.pulse(context)) : icon,
        );
}
