import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/widgets/chat.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../data/messages_repository.dart';
import '../domain/messages.dart';

/// One conversation: who else is in it (the guardians always), the messages
/// (the reader's on one side, a guardian's tinted), what was hidden from each,
/// and writing. Opening it reads it; it follows the conversation live.
class ConversationPage extends StatelessWidget {
  const ConversationPage({required this.conversationId, super.key});

  final String conversationId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<Conversation>(
      () => c.read<MessagesRepository>().conversation(conversationId),
      refreshOn: c.read<Live>().on({Live.messages}),
    )..load(),
    child: Scaffold(
      appBar: AppBar(
        title: BlocBuilder<LoadCubit<Conversation>, LoadState<Conversation>>(
          builder: (context, s) {
            final talk = s.data;
            if (talk == null) return const SizedBox.shrink();
            final others = talk.others.map((m) => '${m.name} (${m.label})').join('، ');
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(talk.withName),
                if (others.isNotEmpty) Text(context.l10n.messagesAlsoHere(others), style: context.text.bodySmall),
              ],
            );
          },
        ),
      ),
      body: LoadView<Conversation>(builder: (context, talk) => _Talk(talk: talk)),
    ),
  );
}

class _Talk extends StatefulWidget {
  const _Talk({required this.talk});

  final Conversation talk;

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
    if (body.isEmpty || _busy) return;
    setState(() => _busy = true);
    try {
      final sent = await context.read<MessagesRepository>().send(widget.talk.id, body);
      if (!mounted) return;
      _body.clear();
      if (sent.hidden != null) showSnack(context, sent.hidden!);
      await context.read<LoadCubit<Conversation>>().load();
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final talk = widget.talk;
    return ChatThread(
      top: Note(talk.notice, icon: Icons.visibility_outlined),
      empty: EmptyNote(l.messagesFirst, icon: Icons.waving_hand_outlined),
      lines: [
        for (final m in talk.messages)
          ChatBubble(
            mine: m.mine,
            body: m.body,
            tinted: !m.mine && m.side == 'guardian',
            note: m.hidden,
            meta: [if (m.mine) l.messagesYou else '${m.author} · ${m.sideLabel}', ?m.at?.label].join(' · '),
          ),
      ],
      bottom: talk.canWrite
          ? ChatComposer(
              controller: _body,
              fieldKey: const Key('message-body'),
              sendKey: const Key('message-send'),
              hint: l.messagesWrite,
              busy: _busy,
              onSend: _send,
            )
          : ChatClosed(talk.whyNot ?? ''),
    );
  }
}
