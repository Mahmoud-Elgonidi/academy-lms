import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
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
      body: LoadView<Ticket>(builder: (context, ticket) => _Conversation(ticket: ticket)),
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
    final t = widget.ticket;
    final s = t.summary;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        Text([s.kindLabel, if (s.learnerName != null) l.ticketAbout(s.learnerName!)].join(' · '), style: context.text.bodySmall),
        const SizedBox(height: 2),
        Text(s.subject, style: context.text.titleLarge),
        if (t.lesson != null)
          Text(
            [t.lesson!.subject, t.lesson!.startsAt?.label, t.lesson!.withName].whereType<String>().where((x) => x.isNotEmpty).join(' · '),
            style: context.text.bodySmall,
          ),
        const SizedBox(height: 8),
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: Pill(s.statusLabel, tone: statusTone(s.status)),
        ),
        const SizedBox(height: 14),
        for (final line in t.lines) _Line(ticketId: s.id, line: line),
        if (t.canReopen) ...[
          const SizedBox(height: 8),
          Note(
            l.ticketReopenHint(t.reopenUntil?.label ?? ''),
            action: OutlinedButton(onPressed: _busy ? null : () => _run((repo) => repo.reopen(s.id)), child: Text(l.ticketReopen)),
          ),
        ],
        const SizedBox(height: 12),
        if (t.canReply) ...[
          TextField(
            key: const Key('ticket-reply'),
            controller: _body,
            minLines: 2,
            maxLines: 6,
            maxLength: 5000,
            decoration: InputDecoration(hintText: l.ticketReplyHint),
          ),
          Row(
            children: [
              Expanded(
                child: TextButton.icon(
                  onPressed: _busy
                      ? null
                      : () async {
                          final picked = await pickAttachment(context);
                          if (picked != null && mounted) setState(() => _file = picked);
                        },
                  icon: const Icon(Icons.attach_file_rounded),
                  label: Text(_file?.name ?? l.ticketAttach, overflow: TextOverflow.ellipsis),
                ),
              ),
              FilledButton.icon(
                key: const Key('ticket-reply-send'),
                onPressed: _busy ? null : _reply,
                icon: const Icon(Icons.send_rounded),
                label: Text(l.send),
              ),
            ],
          ),
        ] else
          Text(l.ticketClosed, style: context.text.bodySmall),
        if (t.canClose) ...[
          const SizedBox(height: 8),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton(onPressed: _busy ? null : _close, child: Text(l.ticketClose)),
          ),
        ],
      ],
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
    final c = context.colors;
    final line = widget.line;
    if (line.isEvent) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Center(
          child: Text(
            [line.event, line.at?.label].whereType<String>().join(' · '),
            style: context.text.bodySmall,
            textAlign: TextAlign.center,
          ),
        ),
      );
    }
    final file = line.file;
    final who = line.author ?? (line.mine ? l.ticketYou : l.ticketAcademy);
    return Align(
      alignment: line.mine ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .82),
        child: Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Column(
            crossAxisAlignment: line.mine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: line.mine ? c.brandSoft : c.chip,
                  borderRadius: BorderRadius.circular(context.shape.card - 4),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if ((line.body ?? '').isNotEmpty) Text(line.body!, style: context.text.bodyMedium),
                    if (file != null && file.mime.startsWith('image/') && file.mime != 'image/heic') ...[
                      const SizedBox(height: 6),
                      _picture == null
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
                            ),
                    ] else if (file != null) ...[
                      const SizedBox(height: 6),
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.attach_file_rounded, size: 18),
                          const SizedBox(width: 4),
                          Flexible(child: Text(file.name, style: context.text.bodyMedium)),
                        ],
                      ),
                      Text(l.fileOnWeb, style: context.text.bodySmall),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 2),
              Text([who, line.at?.label].whereType<String>().join(' · '), style: context.text.bodySmall),
            ],
          ),
        ),
      ),
    );
  }
}
