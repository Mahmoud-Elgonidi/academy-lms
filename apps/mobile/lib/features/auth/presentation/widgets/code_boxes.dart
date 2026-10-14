import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/motion/shake.dart';
import '../../../../shared/widgets/latin_digits.dart';

/// How the six boxes look: being typed, refused (red, after a shake) or
/// right (in the brand's colour).
enum CodeLook { typing, wrong, right }

/// Six boxes for the six digits, over one hidden field that the keyboard,
/// the phone's code suggestion and a paste all fill. Each digit pops into
/// its box; the next box waits with a caret. Six digits typed call
/// [onComplete]. The numbers read left to right in both languages.
class CodeBoxes extends StatefulWidget {
  const CodeBoxes({
    required this.controller,
    required this.onComplete,
    this.look = CodeLook.typing,
    this.shakes = 0,
    this.enabled = true,
    super.key,
  });

  static const length = 6;

  final TextEditingController controller;
  final ValueChanged<String> onComplete;
  final CodeLook look;

  /// Goes up each time the boxes should shake (a wrong code).
  final int shakes;
  final bool enabled;

  @override
  State<CodeBoxes> createState() => _CodeBoxesState();
}

class _CodeBoxesState extends State<CodeBoxes> {
  final _focus = FocusNode();

  @override
  void initState() {
    super.initState();
    _focus.addListener(_redraw);
    widget.controller.addListener(_redraw);
  }

  @override
  void didUpdateWidget(CodeBoxes old) {
    super.didUpdateWidget(old);
    if (old.controller != widget.controller) {
      old.controller.removeListener(_redraw);
      widget.controller.addListener(_redraw);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_redraw);
    _focus.dispose();
    super.dispose();
  }

  void _redraw() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final code = widget.controller.text;
    final boxes = Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < CodeBoxes.length; i++) ...[
          if (i > 0) const SizedBox(width: 8),
          _Box(
            digit: i < code.length ? code[i] : null,
            waiting: _focus.hasFocus && widget.look == CodeLook.typing && i == code.length,
            look: widget.look,
            // On a right code the boxes turn one after another.
            index: i,
          ),
        ],
      ],
    );
    return Directionality(
      textDirection: TextDirection.ltr,
      child: Shake(
        count: widget.shakes,
        child: Stack(
          alignment: Alignment.center,
          children: [
            ExcludeSemantics(child: boxes),
            Positioned.fill(
              // Unseen but there: tapped, read aloud and filled like any field.
              child: Opacity(
                opacity: 0,
                alwaysIncludeSemantics: true,
                child: TextField(
                  key: const Key('code'),
                  controller: widget.controller,
                  focusNode: _focus,
                  enabled: widget.enabled,
                  autofocus: true,
                  showCursor: false,
                  enableInteractiveSelection: false,
                  keyboardType: TextInputType.number,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  inputFormatters: [
                    const LatinDigits(),
                    FilteringTextInputFormatter.digitsOnly,
                    LengthLimitingTextInputFormatter(CodeBoxes.length),
                  ],
                  onChanged: (v) {
                    if (v.length == CodeBoxes.length) widget.onComplete(v);
                  },
                  decoration: InputDecoration(
                    labelText: context.l10n.codeLabel,
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    filled: false,
                    counterText: '',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Box extends StatelessWidget {
  const _Box({required this.digit, required this.waiting, required this.look, required this.index});

  final String? digit;
  final bool waiting;
  final CodeLook look;
  final int index;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final (border, fill, ink) = switch (look) {
      CodeLook.wrong => (c.danger, c.dangerSoft, c.dangerInk),
      CodeLook.right => (c.brand, c.brandSoft, c.brandDeep),
      CodeLook.typing => (waiting || digit != null ? c.brand : c.line, c.card, c.ink),
    };
    final calm = context.calm;
    final box = AnimatedContainer(
      duration: calm ? Duration.zero : Duration(milliseconds: 180 + (look == CodeLook.right ? index * 60 : 0)),
      width: 48,
      height: 60,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: fill,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: border, width: 2),
        boxShadow: [if (waiting) BoxShadow(color: c.brandSoft, spreadRadius: 3)],
      ),
      child: digit != null
          ? Text(
              digit!,
              key: ValueKey('$index$digit'),
              style: TextStyle(fontFamily: context.shape.headingFont, fontSize: 26, fontWeight: FontWeight.w700, color: ink),
            ).popDigit(context)
          : waiting
          ? Container(width: 2, height: 26, color: c.brand).blinkCaret(context)
          : null,
    );
    if (calm) return box;
    // On a right code each box swells and settles, one after another (always wrapped, so nothing inside starts over).
    return box
        .animate(target: look == CodeLook.right ? 1 : 0)
        .custom(
          duration: const Duration(milliseconds: 700),
          builder: (context, v, child) {
            final t = Interval(index * 0.08, index * 0.08 + 0.5).transform(v);
            return Transform.scale(scale: 1 + 0.12 * math.sin(t * math.pi), child: child);
          },
        );
  }
}

extension on Widget {
  Widget popDigit(BuildContext context) =>
      context.calm ? this : animate().scaleXY(begin: 0.4, end: 1, duration: const Duration(milliseconds: 320), curve: Curves.easeOutBack);

  Widget blinkCaret(BuildContext context) => context.calm
      ? this
      : animate(onPlay: (c) => c.repeat(reverse: true))
            .fade(begin: 1, end: 0, duration: const Duration(milliseconds: 500), curve: const Threshold(0.5));
}
