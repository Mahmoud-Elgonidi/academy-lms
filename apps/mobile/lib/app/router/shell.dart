import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../shared/motion/juice.dart';
import '../../shared/motion/motion.dart';

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
        if (i != shell.currentIndex) Juice.tap();
        shell.goBranch(i, initialLocation: i == shell.currentIndex);
      },
      destinations: [
        for (final t in tabs)
          NavigationDestination(
            icon: _counted(t, Icon(t.icon)),
            selectedIcon: _counted(t, _Pop(child: Icon(t.selectedIcon))),
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

/// The icon of the tab just picked springs up from small.
class _Pop extends StatelessWidget {
  const _Pop({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (context.calm) return child;
    return child.animate().scaleXY(begin: 0.55, end: 1, duration: context.springDuration, curve: context.springCurve);
  }
}
