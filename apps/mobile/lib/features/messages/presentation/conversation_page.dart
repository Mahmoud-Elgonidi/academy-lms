import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
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
    final c = context.colors;
    final talk = widget.talk;
    final newestFirst = talk.messages.reversed.toList();
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
          child: Note(talk.notice, icon: Icons.visibility_outlined),
        ),
        Expanded(
          child: talk.messages.isEmpty
              ? EmptyNote(l.messagesFirst, icon: Icons.waving_hand_outlined)
              : ListView.builder(
                  // Newest at the bottom, where the eye and the keyboard are.
                  reverse: true,
                  padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                  itemCount: newestFirst.length,
                  itemBuilder: (context, i) => _Bubble(message: newestFirst[i]),
                ),
        ),
        SafeArea(
          top: false,
          child: talk.canWrite
              ? Padding(
                  padding: const EdgeInsets.fromLTRB(12, 6, 12, 10),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: TextField(
                          key: const Key('message-body'),
                          controller: _body,
                          minLines: 1,
                          maxLines: 5,
                          maxLength: 2000,
                          textInputAction: TextInputAction.newline,
                          decoration: InputDecoration(hintText: l.messagesWrite, counterText: ''),
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton.filled(
                        key: const Key('message-send'),
                        tooltip: l.send,
                        onPressed: _busy ? null : _send,
                        icon: const Icon(Icons.send_rounded),
                      ),
                    ],
                  ),
                )
              : Container(
                  width: double.infinity,
                  color: c.chip,
                  padding: const EdgeInsets.all(14),
                  child: Text(talk.whyNot ?? '', style: context.text.bodySmall?.copyWith(color: c.muted)),
                ),
        ),
      ],
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message});

  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final m = message;
    final bg = m.mine ? c.brand : (m.side == 'guardian' ? c.attentionSoft : c.chip);
    return Align(
      alignment: m.mine ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .8),
        child: Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Column(
            crossAxisAlignment: m.mine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(context.shape.card - 2)),
                child: Text(m.body, style: context.text.bodyMedium?.copyWith(color: m.mine ? Colors.white : null)),
              ),
              if (m.hidden != null) ...[
                const SizedBox(height: 3),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.visibility_off_outlined, size: 14, color: c.muted),
                    const SizedBox(width: 4),
                    Flexible(
                      child: Text(m.hidden!, style: context.text.labelSmall?.copyWith(color: c.muted)),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 2),
              Text(
                [if (m.mine) l.messagesYou else '${m.author} · ${m.sideLabel}', ?m.at?.label].join(' · '),
                style: context.text.labelSmall?.copyWith(color: c.muted),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
