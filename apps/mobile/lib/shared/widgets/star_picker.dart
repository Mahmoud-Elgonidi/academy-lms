import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../motion/juice.dart';
import '../motion/motion.dart';

/// One to five stars, tapped (tap the chosen one again to clear it when
/// [clearable]). Each star pops as it lights.
class StarPicker extends StatelessWidget {
  const StarPicker({required this.value, required this.onChanged, required this.label, this.clearable = false, this.size = 32, super.key});

  final int? value;
  final ValueChanged<int?> onChanged;
  final String label;
  final bool clearable;
  final double size;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Semantics(
      container: true,
      explicitChildNodes: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 1; i <= 5; i++)
            // Each star says what it is: "Focus: 4", selected when lit.
            Semantics(
              button: true,
              selected: (value ?? 0) >= i,
              label: '$label: $i',
              excludeSemantics: true,
              child: InkResponse(
                radius: size * 0.7,
                onTap: () {
                  Juice.tap();
                  onChanged(clearable && value == i ? null : i);
                },
                child: Padding(
                  padding: const EdgeInsets.all(2),
                  child: (value ?? 0) >= i
                      ? Icon(Icons.star_rounded, size: size, color: c.reward)
                            .animate(key: ValueKey('$i-$value'))
                            .scaleXY(
                              begin: 0.6,
                              end: 1,
                              duration: context.calm ? Duration.zero : const Duration(milliseconds: 380),
                              curve: Curves.easeOutBack,
                            )
                      : Icon(Icons.star_outline_rounded, size: size, color: c.faint),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
