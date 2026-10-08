import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/widgets/lms.dart';
import '../bloc/sign_in_cubit.dart';
import '../widgets/sign_in_frame.dart';

/// The second step for an account with two-step sign-in: the six digits the
/// authenticator app shows now, or one of the recovery codes. Several chosen
/// accounts may each ask in turn; too late or too many tries starts again.
class TwoFactorPage extends StatefulWidget {
  const TwoFactorPage({this.base = '/welcome', super.key});

  final String base;

  @override
  State<TwoFactorPage> createState() => _TwoFactorPageState();
}

class _TwoFactorPageState extends State<TwoFactorPage> {
  final _code = TextEditingController();

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final code = _code.text.replaceAll(RegExp(r'\s'), '');
    if (code.length < 6) return;
    final cubit = context.read<SignInCubit>();
    if (!await cubit.completeTwoFactor(code) || !mounted) return;
    // The next chosen account asks for its own code; otherwise everyone is in.
    if (cubit.state.twoFactor != null) {
      _code.clear();
    } else {
      context.go('/');
    }
  }

  void _startAgain() {
    context.read<SignInCubit>().restart();
    context.go(widget.base);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<SignInCubit, SignInState>(
      builder: (context, state) {
        final step = state.twoFactor;
        final p = state.problem;
        // The step itself ran out (time or tries): only starting again helps.
        final over = p?.field('two_factor_token') != null;
        final found = step?.found;
        return SignInFrame(
          title: l.twoFactorTitle,
          lead: Text(l.twoFactorLead, style: context.text.bodyLarge?.copyWith(color: context.colors.muted)),
          bottom: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (!over) BusyButton(label: l.confirm, busy: state.busy, onPressed: _submit),
              TextButton(onPressed: _startAgain, child: Text(l.startAgain)),
            ],
          ),
          children: [
            if (found != null) ...[
              Text(l.twoFactorAccount(found.userName, found.academyName(context.locale)), style: context.text.titleSmall),
              const SizedBox(height: 14),
            ],
            if (over)
              Note(context.problemText(p!), tone: Tone.danger, icon: Icons.timer_off_outlined)
            else
              AutofillGroup(
                child: TextField(
                  key: const Key('two-factor-code'),
                  controller: _code,
                  autofocus: true,
                  autocorrect: false,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  maxLength: 40,
                  textAlign: TextAlign.center,
                  textDirection: TextDirection.ltr,
                  textInputAction: TextInputAction.done,
                  style: TextStyle(fontSize: 24, letterSpacing: 6, fontWeight: FontWeight.w700, color: context.colors.ink),
                  onChanged: (v) {
                    if (RegExp(r'^\d{6}$').hasMatch(v)) _submit();
                  },
                  onSubmitted: (_) => _submit(),
                  decoration: InputDecoration(
                    labelText: l.twoFactorCode,
                    counterText: '',
                    errorText: p == null ? null : context.problemText(p),
                    errorMaxLines: 3,
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
