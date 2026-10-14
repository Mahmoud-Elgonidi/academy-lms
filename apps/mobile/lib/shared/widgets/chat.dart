import 'package:flutter/material.dart';

import '../../app/theme/app_theme.dart';
import '../../core/l10n/l10n.dart';
import 'lms.dart';

/// A conversation, the same for messages with a teacher and a ticket with
/// the academy: [top] (who and what it is about), the lines (oldest first;
/// the newest at the bottom, where the eye and the keyboard are), and
/// [bottom]: writing, or why not.
class ChatThread extends StatelessWidget {
  const ChatThread({required this.lines, this.top, this.bottom, this.empty, super.key});

  final List<Widget> lines;
  final Widget? top;
  final Widget? bottom;

  /// What shows while there is nothing said yet.
  final Widget? empty;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      if (top != null) Padding(padding: const EdgeInsets.fromLTRB(14, Space.s, 14, 0), child: top),
      Expanded(
        child: lines.isEmpty
            ? (empty ?? const SizedBox.shrink())
            : ListView.builder(
                reverse: true,
                padding: const EdgeInsets.fromLTRB(14, Space.m, 14, Space.m),
                itemCount: lines.length,
                itemBuilder: (context, i) => lines[lines.length - 1 - i],
              ),
      ),
      if (bottom != null) SafeArea(top: false, child: bottom!),
    ],
  );
}

/// One line said: the reader's own in the academy's colour on their side,
/// the others' on a card (a guardian's tinted); who and when under the words
/// ([meta]), and a [note] under the bubble (what was hidden from it). The
/// kids' look keeps its own bubbles.
class ChatBubble extends StatelessWidget {
  const ChatBubble({required this.mine, required this.meta, this.body, this.extra, this.note, this.tinted = false, super.key});

  final bool mine;
  final String meta;
  final String? body;

  /// A picture or a file under the words.
  final Widget? extra;
  final String? note;
  final bool tinted;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final kids = context.isKids;
    final bg = mine ? c.brand : (tinted ? c.attentionSoft : (kids ? c.chip : c.card));
    final ink = mine ? Colors.white : c.ink;
    final radius = Radius.circular(context.shape.card - 2);
    final words = (body ?? '').isEmpty ? null : Text(body!, style: context.type.body.copyWith(color: ink, height: 1.6));
    final when = Text(
      meta,
      style: context.type.label.copyWith(color: mine && !kids ? Colors.white.withValues(alpha: 0.8) : c.muted, fontWeight: FontWeight.w500),
    );
    return Align(
      alignment: mine ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .8),
        child: Padding(
          padding: const EdgeInsets.only(bottom: Space.s + 2),
          child: Column(
            crossAxisAlignment: mine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.fromLTRB(14, 10, 14, 8),
                decoration: BoxDecoration(
                  color: bg,
                  border: mine || tinted || kids ? null : Border.all(color: c.line),
                  borderRadius: kids
                      ? BorderRadius.all(radius)
                      // The corner toward the speaker's side stays sharp, like a speech bubble.
                      : BorderRadiusDirectional.only(
                          topStart: radius,
                          topEnd: radius,
                          bottomStart: mine ? radius : const Radius.circular(4),
                          bottomEnd: mine ? const Radius.circular(4) : radius,
                        ).resolve(Directionality.of(context)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ?words,
                    if (extra != null) ...[if (words != null) const Gap(Space.s), extra!],
                    if (!kids) ...[const Gap(Space.xs), when],
                  ],
                ),
              ),
              if (note != null) ...[
                const Gap(3),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.lock_outline_rounded, size: 14, color: c.muted),
                    const Gap(Space.xs),
                    Flexible(
                      child: Text(
                        note!,
                        style: context.type.label.copyWith(color: c.muted, fontWeight: FontWeight.w500),
                      ),
                    ),
                  ],
                ),
              ],
              if (kids) ...[const Gap(2), when],
            ],
          ),
        ),
      ),
    );
  }
}

/// A line about the conversation itself (resolved, reopened), centred between the others.
class ChatEvent extends StatelessWidget {
  const ChatEvent(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: Space.s),
    child: Center(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: Space.m, vertical: Space.xs),
        decoration: BoxDecoration(color: context.colors.chip, borderRadius: BorderRadius.circular(999)),
        child: Text(text, style: context.type.label, textAlign: TextAlign.center),
      ),
    ),
  );
}

/// Writing at the foot of a conversation: the words, and a round send
/// button; [leading] goes before them (attaching a file), [above] over them
/// (the file picked).
class ChatComposer extends StatelessWidget {
  const ChatComposer({
    required this.controller,
    required this.onSend,
    required this.hint,
    this.busy = false,
    this.fieldKey,
    this.sendKey,
    this.maxLength = 2000,
    this.leading,
    this.above,
    super.key,
  });

  final TextEditingController controller;
  final VoidCallback onSend;
  final String hint;
  final bool busy;
  final Key? fieldKey;
  final Key? sendKey;
  final int maxLength;
  final Widget? leading;
  final Widget? above;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final kids = context.isKids;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: kids ? null : c.card,
        border: kids ? null : Border(top: BorderSide(color: c.line)),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(Space.m, Space.s, Space.m, Space.s + 2),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ?above,
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                ?leading,
                Expanded(
                  child: TextField(
                    key: fieldKey,
                    controller: controller,
                    minLines: 1,
                    maxLines: 5,
                    maxLength: maxLength,
                    textInputAction: TextInputAction.newline,
                    decoration: kids
                        ? InputDecoration(hintText: hint, counterText: '')
                        : InputDecoration(
                            hintText: hint,
                            counterText: '',
                            filled: true,
                            fillColor: c.page,
                            contentPadding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.m),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(26),
                              borderSide: BorderSide(color: c.line),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(26),
                              borderSide: BorderSide(color: c.line),
                            ),
                          ),
                  ),
                ),
                const Gap(Space.s),
                IconButton.filled(
                  key: sendKey,
                  tooltip: context.l10n.send,
                  iconSize: 22,
                  style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
                  onPressed: busy ? null : onSend,
                  // Points the way the words are read (the icon turns in Arabic by itself).
                  icon: const Icon(Icons.send_rounded),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Why nothing can be written here (closed, locked, ended).
class ChatClosed extends StatelessWidget {
  const ChatClosed(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    color: context.colors.chip,
    padding: const EdgeInsets.all(14),
    child: Text(text, style: context.type.caption),
  );
}
