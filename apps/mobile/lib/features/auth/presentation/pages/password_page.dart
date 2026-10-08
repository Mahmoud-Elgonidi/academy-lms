import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../bloc/sign_in_cubit.dart';
import '../widgets/sign_in_frame.dart';

/// The other way in: an academy's code, then email, mobile or username with
/// a password (for people the academy gave a password).
class PasswordPage extends StatefulWidget {
  const PasswordPage({this.base = '/welcome', super.key});

  final String base;

  @override
  State<PasswordPage> createState() => _PasswordPageState();
}

class _PasswordPageState extends State<PasswordPage> {
  final _academy = TextEditingController();
  final _login = TextEditingController();
  final _password = TextEditingController();
  bool _hidden = true;

  @override
  void dispose() {
    _academy.dispose();
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_academy.text.trim().isEmpty || _login.text.trim().isEmpty || _password.text.isEmpty) return;
    final cubit = context.read<SignInCubit>();
    final ok = await cubit.signInWithPassword(academy: _academy.text, login: _login.text, password: _password.text);
    if (!ok || !mounted) return;
    // Two-step sign-in on: the authenticator's code comes next.
    cubit.state.twoFactor != null ? await context.push('${widget.base}/two-factor') : context.go('/');
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<SignInCubit, SignInState>(
      builder: (context, state) {
        final p = state.problem;
        final general = p != null && p.field('login') == null && p.field('password') == null ? context.problemText(p) : null;
        return SignInFrame(
          title: l.passwordTitle,
          bottom: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              BusyButton(label: l.signIn, busy: state.busy, onPressed: _submit),
              TextButton(
                onPressed: () {
                  context.read<SignInCubit>().clearProblem();
                  context.go(widget.base);
                },
                child: Text(l.useCodeInstead),
              ),
            ],
          ),
          children: [
            AutofillGroup(
              child: Column(
                children: [
                  TextField(
                    key: const Key('academy'),
                    controller: _academy,
                    textDirection: TextDirection.ltr,
                    autocorrect: false,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: l.academyCode,
                      helperText: l.academyCodeHint,
                      errorText: general,
                      errorMaxLines: 3,
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    key: const Key('login'),
                    controller: _login,
                    textDirection: TextDirection.ltr,
                    autocorrect: false,
                    keyboardType: TextInputType.emailAddress,
                    autofillHints: const [AutofillHints.username],
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(labelText: l.loginLabel, errorText: p?.field('login'), errorMaxLines: 3),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    key: const Key('password'),
                    controller: _password,
                    obscureText: _hidden,
                    autofillHints: const [AutofillHints.password],
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => _submit(),
                    decoration: InputDecoration(
                      labelText: l.passwordLabel,
                      errorText: p?.field('password'),
                      suffixIcon: IconButton(
                        onPressed: () => setState(() => _hidden = !_hidden),
                        icon: Icon(_hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined, color: context.colors.muted),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        );
      },
    );
  }
}
