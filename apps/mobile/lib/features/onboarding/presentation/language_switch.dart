import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/auth/session_cubit.dart';
import '../../../shared/motion/motion.dart';

/// ع / EN before signing in: the app's language, kept on this phone. Each
/// language is named in itself, so whoever reads either finds theirs.
class LanguageSwitch extends StatelessWidget {
  const LanguageSwitch({super.key});

  @override
  Widget build(BuildContext context) {
    final locale = context.select((SessionCubit s) => s.state.locale);
    final c = context.colors;
    Widget option(String code, String short, String name) {
      final on = locale == code;
      return Semantics(
        button: true,
        selected: on,
        label: name,
        excludeSemantics: true,
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: on ? null : () => context.read<SessionCubit>().setLocale(code),
          child: AnimatedContainer(
            key: Key('language-$code'),
            duration: context.calm ? Duration.zero : const Duration(milliseconds: 200),
            constraints: const BoxConstraints(minWidth: 48, minHeight: 42),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: on ? c.card : Colors.transparent,
              borderRadius: BorderRadius.circular(11),
              boxShadow: on ? const [BoxShadow(color: Color(0x1F1C1917), blurRadius: 4, offset: Offset(0, 1))] : null,
            ),
            child: Text(
              short,
              style: TextStyle(fontSize: code == 'ar' ? 15 : 13, fontWeight: FontWeight.w700, color: on ? c.ink : c.muted),
            ),
          ),
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(color: c.chip, borderRadius: BorderRadius.circular(14)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        // The same order in both languages, as on any language switch.
        textDirection: TextDirection.rtl,
        children: [option('ar', 'ع', 'العربية'), const SizedBox(width: 2), option('en', 'EN', 'English')],
      ),
    );
  }
}
