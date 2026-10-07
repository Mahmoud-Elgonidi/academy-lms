import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// One tab of a look's bar.
class ShellTab {
  const ShellTab(this.icon, this.selectedIcon, this.label);

  final IconData icon;
  final IconData selectedIcon;
  final String label;
}

/// The frame of a signed-in look: its tabs at the bottom, each keeping its
/// own stack.
class AppShell extends StatelessWidget {
  const AppShell({required this.shell, required this.tabs, super.key});

  final StatefulNavigationShell shell;
  final List<ShellTab> tabs;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: shell,
    bottomNavigationBar: NavigationBar(
      selectedIndex: shell.currentIndex,
      onDestinationSelected: (i) => shell.goBranch(i, initialLocation: i == shell.currentIndex),
      destinations: [for (final t in tabs) NavigationDestination(icon: Icon(t.icon), selectedIcon: Icon(t.selectedIcon), label: t.label)],
    ),
  );
}
