import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/realtime/live.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/widgets/chat.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../data/teaching_repository.dart';
import '../../domain/shifts.dart';
import 'shifts_page.dart' show calendarDay;

/// The open proposal's conversation with the academy: the teacher's words
/// on their side, the academy's on the other, each with its time; the
/// proposal's steps (sent, the academy's notes) as small lines between them.
class ShiftChatPage extends StatelessWidget {
  const ShiftChatPage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<MyShifts>(c.read<TeachingRepository>().shifts, refreshOn: c.read<Live>().on({Live.lessons}))..load(),
    child: Scaffold(
      appBar: AppBar(title: Text(context.l10n.conversation)),
      body: LoadView<MyShifts>(builder: (context, mine) => _Talk(mine: mine)),
    ),
  );
}

class _Talk extends StatefulWidget {
  const _Talk({required this.mine});

  final MyShifts mine;

  @override
  State<_Talk> createState() => _TalkState();
}

class _TalkState extends State<_Talk> {
  final _body = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final body = _body.text.trim();
    final open = widget.mine.proposal;
    if (body.isEmpty || _busy || open == null) return;
    setState(() => _busy = true);
    final cubit = context.read<LoadCubit<MyShifts>>();
    try {
      final saved = await context.read<TeachingRepository>().commentOnShifts(body);
      if (!mounted) return;
      Juice.tap();
      _body.clear();
      cubit.show(widget.mine.withProposal(open.withComments(saved.comments)));
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// The messages in order, with "sent" placed where it happened.
  List<Widget> _lines(BuildContext context, ShiftProposal p) {
    final l = context.l10n;
    final sent = p.submittedAt;
    final lines = <Widget>[];
    var sentShown = sent == null;
    for (final m in p.comments) {
      if (!sentShown && m.at != null && m.at!.utc.isAfter(sent!.utc)) {
        lines.add(ChatEvent('${l.proposalSentEvent} · ${sent.label}'));
        sentShown = true;
      }
      final when = m.at?.label;
      lines.add(
        m.system
            ? ChatEvent([m.body, ?when].join(' · '))
            : ChatBubble(mine: m.mine, body: m.body, meta: [m.mine ? l.messagesYou : l.ticketAcademy, ?when].join(' · ')),
      );
    }
    if (!sentShown) lines.add(ChatEvent('${l.proposalSentEvent} · ${sent!.label}'));
    return lines;
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = widget.mine.proposal;
    final open = p != null && p.status != 'draft';
    return ChatThread(
      top: p == null
          ? null
          : Row(
              children: [
                Pill(p.statusLabel, tone: p.myTurn ? Tone.attention : Tone.info),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    l.startsOn(calendarDay(p.effectiveFrom, context.locale)),
                    style: context.text.bodySmall,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
      empty: EmptyNote(l.conversationEmpty, icon: Icons.forum_outlined),
      lines: p == null ? const [] : _lines(context, p),
      bottom: open
          ? ChatComposer(
              controller: _body,
              fieldKey: const Key('shift-message'),
              sendKey: const Key('shift-send'),
              hint: l.writeMessage,
              busy: _busy,
              onSend: _send,
            )
          : ChatClosed(l.conversationClosed),
    );
  }
}
