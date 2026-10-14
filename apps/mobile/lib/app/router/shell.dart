import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../shared/motion/juice.dart';
import '../../shared/motion/motion.dart';
import '../../shared/motion/tab_pop.dart';

/// One tab of a look's bar; [count] puts a dot with a number on it (rewards waiting).
class ShellTab {
  const ShellTab(this.icon, this.selectedIcon, this.label, {this.count = 0});

  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final int count;
}

/// The frame of a signed-in look: its tabs at the bottom, each keeping its
/// own stack. The tab picked pops its icon, with a tap sound.
class AppShell extends StatelessWidget {
  const AppShell({required this.shell, required this.tabs, super.key});

  final StatefulNavigationShell shell;
  final List<ShellTab> tabs;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: shell,
    bottomNavigationBar: NavigationBar(
      selectedIndex: shell.currentIndex,
      onDestinationSelected: (i) {
        if (i != shell.currentIndex) context.flavour == MotionFlavour.kids ? Juice.tap() : Juice.press();
        shell.goBranch(i, initialLocation: i == shell.currentIndex);
      },
      destinations: [
        for (final t in tabs)
          NavigationDestination(
            icon: _counted(t, Icon(t.icon)),
            selectedIcon: _counted(t, TabPop(child: Icon(t.selectedIcon))),
            label: t.label,
          ),
      ],
    ),
  );

  static Widget _counted(ShellTab t, Widget icon) => t.count == 0
      ? icon
      : Badge(
          label: Text('${t.count}'),
          child: Builder(builder: (context) => icon.pulse(context)),
        );
}
