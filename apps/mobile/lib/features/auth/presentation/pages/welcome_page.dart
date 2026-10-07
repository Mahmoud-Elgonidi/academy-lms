import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../bloc/sign_in_cubit.dart';
import '../widgets/sign_in_frame.dart';

/// Step one: a mobile number (with its country) or an email.
class WelcomePage extends StatefulWidget {
  const WelcomePage({this.base = '/welcome', super.key});

  /// /welcome when signed out, /add when adding another account.
  final String base;

  @override
  State<WelcomePage> createState() => _WelcomePageState();
}

class _WelcomePageState extends State<WelcomePage> {
  static const _codes = ['+966', '+20', '+971', '+965', '+974', '+973', '+968', '+962', '+212', '+1', '+44'];

  final _phone = TextEditingController();
  final _email = TextEditingController();
  bool _byEmail = false;
  late String _country = _guessCountry(context.read<SessionCubit>().state.deviceTimezone);

  static String _guessCountry(String? zone) => switch (zone) {
    'Africa/Cairo' => '+20',
    'Asia/Dubai' => '+971',
    'Asia/Kuwait' => '+965',
    'Asia/Qatar' => '+974',
    'Asia/Bahrain' => '+973',
    'Asia/Muscat' => '+968',
    'Asia/Amman' => '+962',
    'Africa/Casablanca' => '+212',
    'Europe/London' => '+44',
    final String z when z.startsWith('America/') => '+1',
    _ => '+966',
  };

  @override
  void dispose() {
    _phone.dispose();
    _email.dispose();
    super.dispose();
  }

  String get _contact {
    if (_byEmail) return _email.text.trim();
    final digits = _phone.text.replaceAll(RegExp(r'\D'), '').replaceFirst(RegExp('^0+'), '');
    return '$_country$digits';
  }

  Future<void> _send() async {
    final cubit = context.read<SignInCubit>();
    if (await cubit.sendCode(_contact) && mounted) {
      await context.push('${widget.base}/code');
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<SignInCubit, SignInState>(
      builder: (context, state) {
        final error = state.problem == null ? null : context.problemText(state.problem!);
        return SignInFrame(
          title: l.welcomeTitle,
          mark: true,
          showBack: widget.base != '/welcome',
          lead: Text(l.welcomeLead, style: context.text.bodyLarge?.copyWith(color: context.colors.muted)),
          bottom: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              BusyButton(label: l.sendCode, busy: state.busy, onPressed: _send),
              const SizedBox(height: 6),
              TextButton(
                onPressed: () {
                  context.read<SignInCubit>().clearProblem();
                  context.push('${widget.base}/password');
                },
                child: Text(l.havePassword),
              ),
            ],
          ),
          children: [
            SegmentedButton<bool>(
              showSelectedIcon: false,
              segments: [
                ButtonSegment(value: false, label: Text(l.byPhone), icon: const Icon(Icons.phone_iphone_rounded)),
                ButtonSegment(value: true, label: Text(l.byEmail), icon: const Icon(Icons.alternate_email_rounded)),
              ],
              selected: {_byEmail},
              onSelectionChanged: (v) => setState(() {
                _byEmail = v.first;
                context.read<SignInCubit>().clearProblem();
              }),
            ),
            const SizedBox(height: 18),
            if (_byEmail)
              TextField(
                key: const Key('email'),
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
                textDirection: TextDirection.ltr,
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => _send(),
                decoration: InputDecoration(labelText: l.byEmail, errorText: error),
              )
            else
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                textDirection: TextDirection.ltr,
                children: [
                  SizedBox(
                    width: 112,
                    child: DropdownButtonFormField<String>(
                      initialValue: _country,
                      isExpanded: true,
                      decoration: InputDecoration(labelText: l.countryCode),
                      items: [
                        for (final c in _codes)
                          DropdownMenuItem(
                            value: c,
                            child: Text(c, textDirection: TextDirection.ltr),
                          ),
                      ],
                      onChanged: (v) => setState(() => _country = v ?? _country),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      key: const Key('phone'),
                      controller: _phone,
                      keyboardType: TextInputType.phone,
                      autofillHints: const [AutofillHints.telephoneNumberNational],
                      inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9 ]'))],
                      textDirection: TextDirection.ltr,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: InputDecoration(labelText: l.byPhone, errorText: error, errorMaxLines: 3),
                    ),
                  ),
                ],
              ),
            const SizedBox(height: 12),
            Text(_byEmail ? l.emailHint : l.phoneHint, style: context.text.bodySmall),
          ],
        );
      },
    );
  }
}
