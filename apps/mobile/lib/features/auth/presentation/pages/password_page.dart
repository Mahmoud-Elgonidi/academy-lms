import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/juice.dart';
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

  /// The fields left empty, each saying what it wants.
  Set<String> _missing = const {};

  @override
  void dispose() {
    _academy.dispose();
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final missing = {
      if (_academy.text.trim().isEmpty) 'academy',
      if (_login.text.trim().isEmpty) 'login',
      if (_password.text.isEmpty) 'password',
    };
    setState(() => _missing = missing);
    if (missing.isNotEmpty) return Juice.wrong();
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
        String? wants(String field, String words) => _missing.contains(field) ? words : null;
        return SignInFrame(
          title: l.passwordTitle,
          badge: Icons.key_rounded,
          pinBottom: true,
          bottom: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                height: 56,
                child: BusyButton(label: l.signIn, busy: state.busy, onPressed: _submit),
              ),
              TextButton(
                style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48)),
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
                      errorText: wants('academy', l.academyCodeMissing) ?? general,
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
                    decoration: InputDecoration(
                      labelText: l.loginLabel,
                      errorText: wants('login', l.loginMissing) ?? p?.field('login'),
                      errorMaxLines: 3,
                    ),
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
                      errorText: wants('password', l.passwordMissing) ?? p?.field('password'),
                      suffixIcon: IconButton(
                        tooltip: _hidden ? l.passwordShow : l.passwordHide,
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
