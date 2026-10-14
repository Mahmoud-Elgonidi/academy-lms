import 'dart:typed_data';

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
import '../data/support_repository.dart';
import '../domain/support.dart';
import 'help_page.dart';

/// One of the person's tickets: the conversation both ways (their own lines on
/// one side, the academy's on the other, what was done in between), an answer
/// with words or a picture, "it is not solved" within the week, and closing
/// it. It follows the ticket live.
class TicketPage extends StatelessWidget {
  const TicketPage({required this.ticketId, super.key});

  final String ticketId;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) =>
        LoadCubit<Ticket>(() => c.read<SupportRepository>().ticket(ticketId), refreshOn: c.read<Live>().on({Live.tickets}))..load(),
    child: Scaffold(
      appBar: AppBar(title: BlocBuilder<LoadCubit<Ticket>, LoadState<Ticket>>(builder: (_, s) => Text(s.data?.summary.reference ?? ''))),
      body: LoadView<Ticket>(refreshable: false, builder: (context, ticket) => _Conversation(ticket: ticket)),
    ),
  );
}

class _Conversation extends StatefulWidget {
  const _Conversation({required this.ticket});

  final Ticket ticket;

  @override
  State<_Conversation> createState() => _ConversationState();
}

class _ConversationState extends State<_Conversation> {
  final _body = TextEditingController();
  Attachment? _file;
  bool _busy = false;

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function(SupportRepository repo) step, {String? done}) async {
    final repo = context.read<SupportRepository>();
    setState(() => _busy = true);
    try {
      await step(repo);
      if (!mounted) return;
      if (done != null) showSnack(context, done);
      await context.read<LoadCubit<Ticket>>().load();
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _reply() async {
    final body = _body.text.trim();
    if (body.isEmpty && _file == null) return;
    await _run((repo) async {
      await repo.reply(widget.ticket.summary.id, body: body.isEmpty ? null : body, file: _file);
      _body.clear();
      _file = null;
    });
  }

  Future<void> _close() async {
    final l = context.l10n;
    final sure = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(l.ticketCloseHint),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(l.back)),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(l.ticketClose)),
        ],
      ),
    );
    if (sure == true) await _run((repo) => repo.close(widget.ticket.summary.id));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final t = widget.ticket;
    final s = t.summary;
    return ChatThread(
      // What the ticket is about, where it stands, and what may still be done with it.
      top: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text([s.kindLabel, if (s.learnerName != null) l.ticketAbout(s.learnerName!)].join(' · '), style: context.type.caption),
          Text(s.subject, style: context.type.title),
          if (t.lesson != null)
            Text(
              [t.lesson!.subject, t.lesson!.startsAt?.label, t.lesson!.withName].whereType<String>().where((x) => x.isNotEmpty).join(' · '),
              style: context.type.caption,
            ),
          const Gap(Space.s),
          Row(
            children: [
              StatusChip(s.statusLabel, tone: statusTone(s.status)),
              const Spacer(),
              if (t.canClose)
                TextButton(
                  style: TextButton.styleFrom(foregroundColor: c.brandDeep, visualDensity: VisualDensity.compact),
                  onPressed: _busy ? null : _close,
                  child: Text(l.ticketClose),
                ),
            ],
          ),
          if (t.canReopen) ...[
            const Gap(Space.s),
            Note(
              l.ticketReopenHint(t.reopenUntil?.label ?? ''),
              action: OutlinedButton(onPressed: _busy ? null : () => _run((repo) => repo.reopen(s.id)), child: Text(l.ticketReopen)),
            ),
          ],
        ],
      ),
      lines: [for (final line in t.lines) _Line(ticketId: s.id, line: line)],
      bottom: t.canReply
          ? ChatComposer(
              controller: _body,
              fieldKey: const Key('ticket-reply'),
              sendKey: const Key('ticket-reply-send'),
              hint: l.ticketReplyHint,
              maxLength: 5000,
              busy: _busy,
              onSend: _reply,
              leading: IconButton(
                tooltip: l.ticketAttach,
                onPressed: _busy
                    ? null
                    : () async {
                        final picked = await pickAttachment(context);
                        if (picked != null && mounted) setState(() => _file = picked);
                      },
                icon: const Icon(Icons.attach_file_rounded),
              ),
              above: _file == null
                  ? null
                  : Padding(
                      padding: const EdgeInsets.only(bottom: Space.s),
                      child: Row(
                        children: [
                          Icon(Icons.insert_drive_file_outlined, size: 18, color: c.muted),
                          const Gap(Space.xs),
                          Expanded(
                            child: Text(_file!.name, style: context.type.caption, overflow: TextOverflow.ellipsis),
                          ),
                          IconButton(
                            tooltip: l.close,
                            visualDensity: VisualDensity.compact,
                            onPressed: () => setState(() => _file = null),
                            icon: const Icon(Icons.close_rounded, size: 18),
                          ),
                        ],
                      ),
                    ),
            )
          : ChatClosed(l.ticketClosed),
    );
  }
}

class _Line extends StatefulWidget {
  const _Line({required this.ticketId, required this.line});

  final String ticketId;
  final TicketLine line;

  @override
  State<_Line> createState() => _LineState();
}

class _LineState extends State<_Line> {
  Future<Uint8List>? _picture;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final line = widget.line;
    if (line.isEvent) return ChatEvent([line.event, line.at?.label].whereType<String>().join(' · '));
    final file = line.file;
    final who = line.author ?? (line.mine ? l.ticketYou : l.ticketAcademy);
    Widget? extra;
    if (file != null && file.mime.startsWith('image/') && file.mime != 'image/heic') {
      extra = _picture == null
          ? OutlinedButton.icon(
              onPressed: () => setState(() => _picture = context.read<SupportRepository>().file(widget.ticketId, line.id)),
              icon: const Icon(Icons.image_outlined),
              label: Text(l.showPicture),
            )
          : FutureBuilder<Uint8List>(
              future: _picture,
              builder: (context, snap) => snap.hasData
                  ? ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: Image.memory(snap.data!, height: 220, fit: BoxFit.contain),
                    )
                  : const Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator.adaptive()),
            );
    } else if (file != null) {
      extra = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.attach_file_rounded, size: 18),
              const Gap(Space.xs),
              Flexible(child: Text(file.name, style: context.type.body)),
            ],
          ),
          Text(l.fileOnWeb, style: context.type.caption),
        ],
      );
    }
    return ChatBubble(mine: line.mine, body: line.body, extra: extra, meta: [who, line.at?.label].whereType<String>().join(' · '));
  }
}
