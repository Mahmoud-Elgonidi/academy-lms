import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/social/social_sign_in.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/contact.dart';
import '../bloc/sign_in_cubit.dart';
import '../widgets/contact_field.dart';
import '../widgets/sign_in_frame.dart';

/// Step one: one field for a mobile number or an email (told apart as it
/// is typed), then a code to it. Or a password, or Google or Apple.
class WelcomePage extends StatefulWidget {
  const WelcomePage({this.base = '/welcome', super.key});

  /// /welcome when signed out, /add when adding another account.
  final String base;

  @override
  State<WelcomePage> createState() => _WelcomePageState();
}

class _WelcomePageState extends State<WelcomePage> {
  final _contact = TextEditingController();
  late String _country = Contact.countryFor(context.read<SessionCubit>().state.deviceTimezone);

  /// What is missing, found here before asking the API.
  String? _missing;
  int _shakes = 0;

  @override
  void initState() {
    super.initState();
    // Typing again clears what was wrong.
    _contact.addListener(() {
      if (_missing != null) setState(() => _missing = null);
      final cubit = context.read<SignInCubit>();
      if (cubit.state.problem != null && !cubit.state.busy) cubit.clearProblem();
    });
  }

  @override
  void dispose() {
    _contact.dispose();
    super.dispose();
  }

  void _wrong(String why) {
    Juice.wrong();
    setState(() {
      _missing = why;
      _shakes++;
    });
  }

  Future<void> _send() async {
    final l = context.l10n;
    final text = _contact.text;
    final kind = Contact.kindOf(text);
    if (kind == ContactKind.none) return _wrong(l.contactEmpty);
    if (kind == ContactKind.phone && !Contact.phoneComplete(text)) return _wrong(l.contactShortPhone);
    if (kind == ContactKind.email && !Contact.emailComplete(text)) return _wrong(l.contactBadEmail);
    final contact = kind == ContactKind.phone ? Contact.phone(text, _country) : text.trim();
    final cubit = context.read<SignInCubit>();
    if (await cubit.sendCode(contact) && mounted) {
      await context.push('${widget.base}/code');
    } else if (mounted && cubit.state.problem != null) {
      Juice.wrong();
      setState(() => _shakes++);
    }
  }

  void _toPassword() {
    context.read<SignInCubit>().clearProblem();
    context.push('${widget.base}/password');
  }

  /// Google or Apple instead of a code: the same list of accounts comes next.
  Future<void> _withProvider(String provider) async {
    final cubit = context.read<SignInCubit>();
    final l = context.l10n;
    final label = provider == 'apple' ? l.providerApple : l.providerGoogle;
    try {
      if (await cubit.withProvider(context.read<SocialSignIn>(), provider) && mounted) {
        await context.push('${widget.base}/accounts');
      }
    } catch (_) {
      // The phone's own sign-in failed (not a refusal from the academy: that shows under the field).
      if (mounted) showSnack(context, l.socialFailed(label));
    }
  }

  List<Widget> _socialButtons(BuildContext context, SignInState state) {
    final social = context.read<SocialSignIn>();
    final l = context.l10n;
    final c = context.colors;
    final providers = [
      for (final p in const ['google', 'apple'])
        if (social.available(p)) p,
    ];
    if (providers.isEmpty) return const [];
    return [
      Padding(
        padding: const EdgeInsets.symmetric(vertical: Space.s),
        child: Row(
          children: [
            Expanded(child: Divider(color: c.line)),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Space.m),
              child: Text(l.signInOr, style: TextStyle(fontSize: 13, color: c.muted)),
            ),
            Expanded(child: Divider(color: c.line)),
          ],
        ),
      ),
      for (final p in providers)
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: p == 'apple'
              // Apple's own button, as its guidelines ask.
              ? SignInWithAppleButton(
                  key: const Key('social-apple'),
                  text: l.signInWithApple,
                  height: 52,
                  borderRadius: const BorderRadius.all(Radius.circular(16)),
                  onPressed: () => state.busy ? null : _withProvider('apple'),
                )
              : PressScale(
                  child: SizedBox(
                    height: 52,
                    child: OutlinedButton(
                      key: const Key('social-google'),
                      style: OutlinedButton.styleFrom(
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        side: BorderSide(color: c.line, width: 1.5),
                        foregroundColor: c.ink,
                      ),
                      onPressed: state.busy ? null : () => _withProvider('google'),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 26,
                            height: 26,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              border: Border.all(color: c.ink, width: 1.5),
                            ),
                            child: Text(
                              'G',
                              style: TextStyle(fontFamily: context.shape.headingFont, fontSize: 14, fontWeight: FontWeight.w700),
                            ),
                          ),
                          const Gap(10),
                          Text(l.signInWithGoogle, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  ),
                ),
        ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final first = widget.base == '/welcome';
    return BlocBuilder<SignInCubit, SignInState>(
      builder: (context, state) {
        final error = _missing ?? (state.problem == null ? null : context.problemText(state.problem!));
        return SignInFrame(
          title: l.welcomeTitle,
          mark: first,
          showBack: !first,
          lead: Text(l.welcomeLead, style: TextStyle(fontSize: 16, height: 1.65, color: context.colors.muted)),
          bottom: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(
                height: 56,
                child: BusyButton(label: l.sendCode, busy: state.busy, onPressed: _send),
              ),
              const Gap(Space.xs),
              TextButton(
                style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48), foregroundColor: context.colors.brandDeep),
                onPressed: _toPassword,
                child: Text(l.havePassword, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
              ),
              ..._socialButtons(context, state),
            ],
          ),
          children: [
            ContactField(
              controller: _contact,
              country: _country,
              onCountry: (code) => setState(() => _country = code),
              onSubmit: _send,
              error: error,
              shakes: _shakes,
            ),
          ],
        );
      },
    );
  }
}
