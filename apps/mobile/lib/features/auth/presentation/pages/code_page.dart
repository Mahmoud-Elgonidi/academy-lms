import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/drawn_check.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/latin_digits.dart';
import '../../../../shared/widgets/lms.dart';
import '../../domain/sign_in_models.dart';
import '../bloc/sign_in_cubit.dart';
import '../widgets/code_boxes.dart';
import '../widgets/sign_in_frame.dart';

/// Step two: the six digits, in six boxes that fill as they are typed or
/// pasted, and go by themselves once complete. A wrong code shakes them red
/// with the API's words; a right one turns them and draws a check before
/// the accounts it found. A new code after the ring runs out, or by text
/// instead of WhatsApp.
class CodePage extends StatefulWidget {
  const CodePage({this.base = '/welcome', super.key});

  final String base;

  @override
  State<CodePage> createState() => _CodePageState();
}

class _CodePageState extends State<CodePage> with SingleTickerProviderStateMixin {
  final _code = TextEditingController();

  /// The right code's moment: the boxes turn, the check draws, then on.
  late final AnimationController _right = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
  CodeLook _look = CodeLook.typing;
  int _shakes = 0;
  bool _resent = false;

  @override
  void initState() {
    super.initState();
    // Typing again after a wrong code starts afresh.
    _code.addListener(() {
      if (_look != CodeLook.wrong || _code.text.isEmpty) return;
      context.read<SignInCubit>().clearProblem();
      setState(() => _look = CodeLook.typing);
    });
  }

  @override
  void dispose() {
    _right.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _verify(String code) async {
    if (code.length < CodeBoxes.length) return;
    final cubit = context.read<SignInCubit>();
    setState(() => _resent = false);
    if (await cubit.verify(code)) {
      if (!mounted) return;
      Juice.done(light: true);
      setState(() => _look = CodeLook.right);
      if (!context.calm) await _right.forward(from: 0);
      if (mounted) await _accounts();
    } else if (mounted && cubit.state.problem != null) {
      Juice.wrong();
      setState(() {
        _look = CodeLook.wrong;
        _shakes++;
      });
      _code.clear();
    }
  }

  Future<void> _accounts() async {
    await context.push('${widget.base}/accounts');
    // Back from the accounts: the code was spent, so the boxes are empty again.
    if (!mounted) return;
    _right.reset();
    _code.clear();
    setState(() => _look = CodeLook.typing);
  }

  Future<void> _paste() async {
    final data = await Clipboard.getData(Clipboard.kTextPlain);
    final found = RegExp(r'\d{6}').firstMatch(latinDigits(data?.text ?? ''))?.group(0);
    if (!mounted) return;
    if (found == null) return showSnack(context, context.l10n.codePasteNone);
    _code.text = found;
    await _verify(found);
  }

  Future<void> _resend({bool bySms = false}) async {
    _code.clear();
    setState(() {
      _look = CodeLook.typing;
      _resent = false;
    });
    if (await context.read<SignInCubit>().resend(bySms: bySms) && mounted) setState(() => _resent = true);
  }

  void _otherContact() {
    context.read<SignInCubit>().restart();
    context.go(widget.base);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return BlocBuilder<SignInCubit, SignInState>(
      builder: (context, state) {
        final sent = state.sent;
        if (sent == null) {
          // Opened again with nothing sent (the phone closed the app meanwhile): back to the start.
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) context.go(widget.base);
          });
          return const Scaffold();
        }
        final to = '\u2066${sent.sentTo}\u2069';
        final lead = switch (sent.channel) {
          'sms' => l.codeSentSms(to),
          'email' => l.codeSentEmail(to),
          _ => l.codeSentWhatsapp(to),
        };
        final right = _look == CodeLook.right;
        final status = switch (state) {
          _ when right => _Status.right(_right),
          SignInState(busy: true) => _Status.checking(l.codeChecking),
          SignInState(problem: final p?) => _Status.text(context.problemText(p), colour: c.danger, icon: Icons.error_outline_rounded),
          _ when _resent => _Status.text(l.codeResent, colour: c.brandDeep, icon: Icons.mark_chat_read_outlined),
          _ => _Status.text(l.codeValid, colour: c.muted),
        };
        return SignInFrame(
          title: l.codeTitle,
          badge: sent.channel == 'email' ? Icons.mark_email_unread_outlined : Icons.chat_bubble_outline_rounded,
          centered: true,
          lead: Text(
            lead,
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 15, height: 1.65, color: c.muted),
          ),
          bottom: Column(
            children: [
              const Gap(Space.l),
              Text(l.noCode, style: TextStyle(fontSize: 14, color: c.muted)),
              _Resend(key: ValueKey(sent.at), sent: sent, busy: state.busy, onResend: _resend),
              TextButton(
                style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48), foregroundColor: c.brandDeep),
                onPressed: _otherContact,
                child: Text(l.otherContact, style: const TextStyle(fontWeight: FontWeight.w600)),
              ),
            ],
          ),
          children: [
            Center(
              child: CodeBoxes(controller: _code, onComplete: _verify, look: _look, shakes: _shakes, enabled: !right),
            ),
            ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 56),
              child: Center(
                child: Semantics(
                  liveRegion: true,
                  child: AnimatedSwitcher(duration: context.calm ? Duration.zero : const Duration(milliseconds: 200), child: status),
                ),
              ),
            ),
            if (!right && !state.busy)
              Center(
                child: PressScale(
                  child: OutlinedButton.icon(
                    key: const Key('paste-code'),
                    onPressed: _paste,
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size(0, 44),
                      shape: const StadiumBorder(),
                      side: BorderSide(color: c.brand, width: 1.5),
                      foregroundColor: c.brandDeep,
                    ),
                    icon: const Icon(Icons.content_paste_rounded, size: 18),
                    label: Text(l.codePaste, style: const TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

/// The line under the boxes: how long the code lasts, a new one sent, the
/// check, "checking…", or what went wrong.
class _Status extends StatelessWidget {
  const _Status._({required this.kind, this.text = '', this.colour, this.icon, this.right, super.key});

  factory _Status.text(String text, {required Color colour, IconData? icon}) =>
      _Status._(key: ValueKey(text), kind: 0, text: text, colour: colour, icon: icon);

  factory _Status.checking(String text) => _Status._(key: const ValueKey('checking'), kind: 1, text: text);

  factory _Status.right(Animation<double> drawn) => _Status._(key: const ValueKey('right'), kind: 2, right: drawn);

  final int kind;
  final String text;
  final Color? colour;
  final IconData? icon;
  final Animation<double>? right;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return switch (kind) {
      1 => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2.5, color: c.brand)),
          const Gap(Space.s),
          Text(text, style: TextStyle(fontSize: 15, color: c.muted)),
        ],
      ),
      2 => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox.square(
            dimension: 30,
            child: AnimatedBuilder(
              animation: right!,
              builder: (context, _) => CustomPaint(
                painter: CheckPainter(
                  progress: context.calm ? 1 : Curves.easeInOut.transform(right!.value.clamp(0, 0.7) / 0.7),
                  colour: c.brand,
                ),
              ),
            ),
          ),
          const Gap(Space.s),
          Text(
            context.l10n.codeRight,
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: c.brandDeep),
          ),
        ],
      ),
      _ => Row(
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
      ),
    };
  }
}

/// "Send a new code in 0:42" with a ring running down, then the buttons
/// to send one (and by text instead of WhatsApp, where the academy can).
/// The ring runs on the screen's own clock, not a timer.
class _Resend extends StatefulWidget {
  const _Resend({required this.sent, required this.busy, required this.onResend, super.key});

  final SentCode sent;
  final bool busy;
  final Future<void> Function({bool bySms}) onResend;

  @override
  State<_Resend> createState() => _ResendState();
}

class _ResendState extends State<_Resend> with SingleTickerProviderStateMixin {
  late final Duration _wait = Duration(seconds: widget.sent.resendAfter);
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: _wait.inMilliseconds > 0 ? _wait : const Duration(milliseconds: 1),
  );

  @override
  void initState() {
    super.initState();
    final gone = DateTime.now().difference(widget.sent.at);
    _c.value = _wait.inMilliseconds <= 0 ? 1 : (gone.inMilliseconds / _wait.inMilliseconds).clamp(0.0, 1.0);
    if (_c.value < 1) _c.forward();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) {
        final ready = _c.value >= 1;
        final left = Duration(milliseconds: ((1 - _c.value) * _wait.inMilliseconds).ceil());
        final seconds = (left.inMilliseconds / 1000).ceil();
        final mmss = '${seconds ~/ 60}:${(seconds % 60).toString().padLeft(2, '0')}';
        final sms = widget.sent.canSwitchToSms;
        return Column(
          children: [
            if (ready)
              TextButton(
                style: TextButton.styleFrom(minimumSize: const Size(0, 48), foregroundColor: c.brandDeep),
                onPressed: widget.busy ? null : () => widget.onResend(),
                child: Text(l.resend, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
              )
            else
              SizedBox(
                height: 48,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    SizedBox.square(
                      dimension: 22,
                      child: CustomPaint(
                        painter: _Ring(left: 1 - _c.value, track: c.line, colour: c.brand),
                      ),
                    ),
                    const Gap(10),
                    Text(l.resendIn(mmss), style: TextStyle(fontSize: 15, color: c.muted)),
                  ],
                ),
              ),
            if (sms)
              TextButton.icon(
                style: TextButton.styleFrom(minimumSize: const Size(0, 48), foregroundColor: c.brandDeep),
                onPressed: ready && !widget.busy ? () => widget.onResend(bySms: true) : null,
                icon: const Icon(Icons.sms_outlined, size: 20),
                label: Text(l.sendBySms),
              ),
          ],
        );
      },
    );
  }
}

class _Ring extends CustomPainter {
  _Ring({required this.left, required this.track, required this.colour});

  final double left;
  final Color track;
  final Color colour;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromCircle(center: size.center(Offset.zero), radius: size.shortestSide / 2 - 2);
    final line = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round;
    canvas.drawArc(rect, 0, 2 * math.pi, false, line..color = track);
    canvas.drawArc(rect, -math.pi / 2, 2 * math.pi * left, false, line..color = colour);
  }

  @override
  bool shouldRepaint(_Ring old) => old.left != left || old.colour != colour;
}
