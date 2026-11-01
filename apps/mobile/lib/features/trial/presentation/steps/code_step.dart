import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/open_link.dart';
import '../../../auth/presentation/widgets/code_boxes.dart';
import '../../../auth/presentation/widgets/retry_wait.dart';
import '../trial_cubit.dart';
import '../widgets.dart';

/// The six digits sent to the family's number; typed whole, they book the
/// time. A wrong code shakes red with the API's words (how many tries are
/// left); a locked or ended one says so and offers a new code; a new code
/// waits out its minute on a ring. When the academy cannot book this trial
/// from here (this learner, this number), its words and the ways on.
class CodeStep extends StatefulWidget {
  const CodeStep({super.key});

  @override
  State<CodeStep> createState() => _CodeStepState();
}

class _CodeStepState extends State<CodeStep> {
  final _code = TextEditingController();
  int _shakes = 0;
  bool _resent = false;

  @override
  void initState() {
    super.initState();
    _code.addListener(() {
      final cubit = context.read<TrialCubit>();
      if (_code.text.isNotEmpty && cubit.state.trouble == CodeTrouble.wrong) cubit.clearProblem();
    });
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _book(String code) async {
    if (code.length < CodeBoxes.length) return;
    final cubit = context.read<TrialCubit>();
    setState(() => _resent = false);
    if (await cubit.book(code)) {
      Juice.done();
      return;
    }
    if (!mounted) return;
    _code.clear();
    if (cubit.state.trouble != null) {
      Juice.wrong();
      setState(() => _shakes++);
    }
  }

  Future<void> _resend() async {
    _code.clear();
    if (await context.read<TrialCubit>().sendCode() && mounted) setState(() => _resent = true);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return BlocBuilder<TrialCubit, TrialState>(
      builder: (context, state) {
        final cubit = context.read<TrialCubit>();
        final sent = state.code;
        if (state.refusal != null) return _Refused(state: state);
        if (sent == null) return const SizedBox.shrink();
        final to = '\u2066${sent.sentTo}\u2069';
        final p = state.problem;
        final waiting = p?.status == 429;
        final dead = state.trouble == CodeTrouble.locked || state.trouble == CodeTrouble.expired;
        final status = switch (state) {
          _ when waiting => RetryWait(
            key: ValueKey(p),
            body: context.l10n.trialTooMany,
            seconds: p!.retryAfter ?? 60,
            onDone: cubit.clearProblem,
          ),
          TrialState(busy: true) => _Line.busy(l.trialBooking),
          TrialState(trouble: CodeTrouble.locked) => _Line.trouble(l.trialCodeLocked, context.problemText(p!), Icons.lock_outline_rounded),
          TrialState(trouble: CodeTrouble.expired) => _Line.trouble(l.trialCodeExpired, context.problemText(p!), Icons.timer_off_outlined),
          TrialState(problem: final p?) => _Line(text: context.problemText(p), colour: c.danger, icon: Icons.error_outline_rounded),
          _ when _resent => _Line(text: l.codeResent, colour: c.brandDeep, icon: Icons.mark_chat_read_outlined),
          _ => _Line(text: l.codeValid, colour: c.muted),
        };
        final newCode = dead
            ? SizedBox(
                height: 52,
                child: PrimaryAction(l.resend, key: const Key('trial-new-code'), busy: state.busy, onPressed: _resend),
              )
            : TextButton(
                key: const Key('trial-resend'),
                style: TextButton.styleFrom(minimumSize: const Size(0, 48), foregroundColor: c.brandDeep),
                onPressed: state.busy ? null : _resend,
                child: Text(l.resend, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
              );
        return StepBody(
          title: l.codeTitle,
          badge: sent.channel == 'sms' ? Icons.sms_outlined : Icons.chat_bubble_outline_rounded,
          lead: Lead('${sent.channel == 'sms' ? l.codeSentSms(to) : l.codeSentWhatsapp(to)}\n${l.trialCodeLead}'),
          bottom: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (!dead) Text(l.noCode, style: TextStyle(fontSize: 14, color: c.muted)),
              ResendCountdown(key: ValueKey(sent.at), sentAt: sent.at, seconds: sent.resendAfter, ready: newCode),
              TextButton(
                key: const Key('trial-change-number'),
                style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44), foregroundColor: c.brandDeep),
                onPressed: state.busy ? null : cubit.changeNumber,
                child: Text(l.trialChangeNumber, style: const TextStyle(fontWeight: FontWeight.w600)),
              ),
            ],
          ),
          children: [
            Center(
              child: CodeBoxes(
                controller: _code,
                onComplete: _book,
                look: state.trouble == null ? CodeLook.typing : CodeLook.wrong,
                shakes: _shakes,
                enabled: !state.busy && !dead && !waiting,
              ),
            ),
            ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 64),
              child: Center(
                child: Semantics(
                  liveRegion: true,
                  child: AnimatedSwitcher(duration: context.calm ? Duration.zero : const Duration(milliseconds: 200), child: status),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

/// The line under the boxes: how long the code lasts, a new one sent, "booking…", or what went wrong.
class _Line extends StatelessWidget {
  _Line({required this.text, this.colour, this.icon, this.heading, this.spinner = false}) : super(key: ValueKey('$heading:$text:$spinner'));

  factory _Line.busy(String text) => _Line(text: text, spinner: true);

  factory _Line.trouble(String heading, String text, IconData icon) => _Line(heading: heading, text: text, icon: icon);

  final String text;
  final Color? colour;
  final IconData? icon;

  /// A locked or ended code: a short heading over the API's words.
  final String? heading;
  final bool spinner;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    if (spinner) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2.5, color: c.brand)),
          const Gap(Space.s),
          Text(text, style: TextStyle(fontSize: 15, color: c.muted)),
        ],
      );
    }
    if (heading != null) {
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: c.dangerSoft, borderRadius: BorderRadius.circular(context.shape.card - 2)),
        child: Column(
          children: [
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 20, color: c.dangerInk),
                const Gap(6),
                Text(
                  heading!,
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: c.dangerInk),
                ),
              ],
            ),
            const Gap(4),
            Text(
              text,
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 14.5, height: 1.5, color: c.dangerInk),
            ),
          ],
        ),
      );
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (icon != null) ...[Icon(icon, size: 18, color: colour), const Gap(6)],
        Flexible(
          child: Text(
            text,
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 15, height: 1.5, color: colour, fontWeight: icon == null ? null : FontWeight.w600),
          ),
        ),
      ],
    );
  }
}

/// The academy cannot book this trial from here (a trial already booked for
/// this learner, too many for this number, a learner who studies the subject…):
/// its own words, then the ways on: the request instead, its WhatsApp, or
/// signing in with this number.
class _Refused extends StatelessWidget {
  const _Refused({required this.state});

  final TrialState state;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final cubit = context.read<TrialCubit>();
    final whatsapp = state.page?.whatsapp;
    return StepBody(
      title: l.trialRefusedTitle,
      badge: Icons.info_outline_rounded,
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            height: 56,
            child: PrimaryAction(l.trialRefusedSend, key: const Key('trial-refused-send'), onPressed: cubit.sendInstead),
          ),
          if (whatsapp != null) ...[
            const Gap(Space.s),
            SecondaryAction(
              l.trialWhatsapp,
              key: const Key('trial-whatsapp'),
              icon: Icons.chat_rounded,
              onPressed: () => openLink(context, Uri.parse('https://wa.me/${whatsapp.replaceAll(RegExp(r'\D'), '')}')),
            ),
          ],
          TextButton(
            key: const Key('trial-refused-sign-in'),
            style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44), foregroundColor: c.brandDeep),
            onPressed: () => context.go(signInWith(context, state.draft.phone)),
            child: Text(l.trialSignIn),
          ),
        ],
      ),
      children: [
        Semantics(
          liveRegion: true,
          child: Note(state.refusal!, tone: Tone.attention, icon: Icons.info_outline_rounded),
        ),
      ],
    );
  }
}
