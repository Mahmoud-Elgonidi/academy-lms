import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../bloc/sign_in_cubit.dart';
import '../widgets/sign_in_frame.dart';

/// Step two: the six digits. A new code after the wait, or by text instead
/// of WhatsApp.
class CodePage extends StatefulWidget {
  const CodePage({this.base = '/welcome', super.key});

  final String base;

  @override
  State<CodePage> createState() => _CodePageState();
}

class _CodePageState extends State<CodePage> {
  final _code = TextEditingController();
  Timer? _tick;

  @override
  void initState() {
    super.initState();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) => mounted ? setState(() {}) : null);
  }

  @override
  void dispose() {
    _tick?.cancel();
    _code.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    if (_code.text.trim().length < 4) return;
    if (await context.read<SignInCubit>().verify(_code.text) && mounted) {
      await context.push('${widget.base}/accounts');
    }
  }

  Future<void> _resend({bool bySms = false}) async {
    _code.clear();
    await context.read<SignInCubit>().resend(bySms: bySms);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return BlocBuilder<SignInCubit, SignInState>(
      builder: (context, state) {
        final sent = state.sent;
        if (sent == null) return const SizedBox.shrink();
        final to = '\u2066${sent.sentTo}\u2069';
        final lead = switch (sent.channel) {
          'sms' => l.codeSentSms(to),
          'email' => l.codeSentEmail(to),
          _ => l.codeSentWhatsapp(to),
        };
        final wait = sent.resendAt.difference(DateTime.now());
        final canResend = wait.inSeconds <= 0;
        final mmss = '${wait.inMinutes}:${(wait.inSeconds % 60).toString().padLeft(2, '0')}';
        return SignInFrame(
          title: l.codeTitle,
          lead: Text(lead, style: context.text.bodyLarge?.copyWith(color: context.colors.muted)),
          bottom: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              BusyButton(label: l.confirm, busy: state.busy, onPressed: _verify),
              TextButton(
                onPressed: () {
                  context.read<SignInCubit>().restart();
                  context.go(widget.base);
                },
                child: Text(l.otherContact),
              ),
            ],
          ),
          children: [
            AutofillGroup(
              child: TextField(
                key: const Key('code'),
                controller: _code,
                autofocus: true,
                keyboardType: TextInputType.number,
                autofillHints: const [AutofillHints.oneTimeCode],
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
                textAlign: TextAlign.center,
                textDirection: TextDirection.ltr,
                style: TextStyle(fontSize: 28, letterSpacing: 12, fontWeight: FontWeight.w700, color: context.colors.ink),
                onChanged: (v) {
                  if (v.length == 6) _verify();
                },
                decoration: InputDecoration(
                  labelText: l.codeLabel,
                  errorText: state.problem == null ? null : context.problemText(state.problem!),
                  errorMaxLines: 3,
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(l.codeValid, style: context.text.bodySmall),
            const SizedBox(height: 22),
            Text(l.noCode, style: context.text.titleSmall),
            const SizedBox(height: 4),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton(onPressed: canResend && !state.busy ? _resend : null, child: Text(canResend ? l.resend : l.resendIn(mmss))),
            ),
            if (sent.canSwitchToSms)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton.icon(
                  onPressed: canResend && !state.busy ? () => _resend(bySms: true) : null,
                  icon: const Icon(Icons.sms_outlined),
                  label: Text(l.sendBySms),
                ),
              ),
          ],
        );
      },
    );
  }
}
