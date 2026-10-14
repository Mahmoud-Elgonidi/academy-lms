import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/motion/done_moment.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../../dashboard/presentation/bloc/learner_cubit.dart';
import '../../dashboard/presentation/widgets/learner_switcher.dart';
import '../data/support_repository.dart';
import '../domain/support.dart';

/// Who is writing: a guardian (about the learner the family tabs show), a
/// student about themself, or a teacher.
enum HelpScope { family, self, teacher }

typedef _Help = ({TicketOptions options, List<TicketSummary> tickets});

const _maxBytes = 10 * 1024 * 1024;
const _mimes = {
  'jpg': 'image/jpeg',
  'jpeg': 'image/jpeg',
  'png': 'image/png',
  'webp': 'image/webp',
  'heic': 'image/heic',
  'pdf': 'application/pdf',
};

/// Picks a picture or PDF for a ticket; null when nothing was picked, or it is too big (said in a snack).
Future<Attachment?> pickAttachment(BuildContext context) async {
  final picked = await FilePicker.pickFile(type: FileType.custom, allowedExtensions: _mimes.keys.toList());
  if (picked == null || !context.mounted) return null;
  final bytes = await picked.xFile.readAsBytes();
  if (!context.mounted) return null;
  if (bytes.length > _maxBytes) {
    showSnack(context, context.l10n.ticketTooBig);
    return null;
  }
  return Attachment(bytes: bytes, name: picked.name, mime: _mimes[(picked.extension ?? '').toLowerCase()] ?? 'application/octet-stream');
}

/// "Help" (a family) or "Support" (a teacher): writing to the academy, and the
/// person's own tickets, the active ones first. It follows them live.
class HelpPage extends StatelessWidget {
  const HelpPage({required this.scope, this.at, super.key});

  final HelpScope scope;

  /// Where this page is, when the look keeps it somewhere of its own (secondary school's More).
  final String? at;

  /// Where a ticket of this look opens.
  String get base =>
      at ??
      switch (scope) {
        HelpScope.family => '/family/me/help',
        HelpScope.self => '/account/help',
        HelpScope.teacher => '/teach/more/help',
      };

  @override
  Widget build(BuildContext context) {
    final learnerId = scope == HelpScope.family ? context.watch<LearnerCubit>().state.selected?.id : null;
    final repo = context.read<SupportRepository>();
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(
        title: Text(scope == HelpScope.teacher ? l.supportTitle : l.helpTitle),
        // A guardian writes about one child: which, and another a tap away.
        actions: [if (scope == HelpScope.family) const LearnerChip()],
      ),
      body: BlocProvider(
        key: ValueKey(learnerId),
        create: (c) => LoadCubit<_Help>(
          () async => (options: await repo.options(learnerId: learnerId), tickets: await repo.tickets(learnerId: learnerId)),
          refreshOn: c.read<Live>().on({Live.tickets}),
        )..load(),
        child: LoadView<_Help>(
          builder: (context, help) => ListView(
            padding: pagePadding,
            physics: const AlwaysScrollableScrollPhysics(),
            children: staggered(context, [
              Text(
                scope == HelpScope.teacher ? l.supportLead : l.helpLead,
                style: context.text.bodyMedium?.copyWith(color: context.colors.muted),
              ),
              const SizedBox(height: 14),
              if (!help.options.closed)
                FilledButton.icon(
                  key: const Key('new-ticket'),
                  onPressed: () => _write(context, help.options, learnerId),
                  icon: const Icon(Icons.edit_outlined),
                  label: Text(l.ticketNew),
                ),
              const SizedBox(height: 18),
              Text(l.ticketsMine, style: context.text.titleMedium),
              const SizedBox(height: 8),
              if (help.tickets.isEmpty) EmptyNote(l.ticketsEmpty, icon: Icons.support_agent_outlined),
              for (final t in help.tickets) ...[
                _TicketTile(ticket: t, onTap: () => context.go('$base/${t.id}')),
                const SizedBox(height: 10),
              ],
            ]),
          ),
        ),
      ),
    );
  }

  Future<void> _write(BuildContext context, TicketOptions options, String? learnerId) async {
    final ticket = await showActionSheet<Ticket>(
      context,
      builder: (_) => RepositoryProvider.value(
        value: context.read<SupportRepository>(),
        child: _NewTicketSheet(options: options, learnerId: learnerId, teacher: scope == HelpScope.teacher),
      ),
    );
    if (ticket == null || !context.mounted) return;
    DoneMoment.show(context, title: context.l10n.ticketSent);
    context.go('$base/${ticket.summary.id}');
  }
}

class _TicketTile extends StatelessWidget {
  const _TicketTile({required this.ticket, required this.onTap});

  final TicketSummary ticket;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return LmsCard(
      key: Key('ticket-${ticket.id}'),
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            [ticket.reference, ticket.kindLabel, if (ticket.learnerName != null) l.ticketAbout(ticket.learnerName!)].join(' · '),
            style: context.text.bodySmall,
          ),
          const SizedBox(height: 2),
          Text(ticket.subject, style: context.text.titleSmall),
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Pill(ticket.statusLabel, tone: statusTone(ticket.status)),
              if (ticket.yourTurn) Text(l.ticketYourTurn, style: context.text.labelMedium?.copyWith(color: context.colors.brandText)),
              if (ticket.lastMessageAt != null) Text(ticket.lastMessageAt!.label, style: context.text.bodySmall),
            ],
          ),
        ],
      ),
    );
  }
}

/// How a ticket's status looks: the person's own turn stands out.
Tone statusTone(String status) => switch (status) {
  'waiting' => Tone.brand,
  'resolved' => Tone.info,
  _ => Tone.neutral,
};

class _NewTicketSheet extends StatefulWidget {
  const _NewTicketSheet({required this.options, required this.learnerId, required this.teacher});

  final TicketOptions options;
  final String? learnerId;
  final bool teacher;

  @override
  State<_NewTicketSheet> createState() => _NewTicketSheetState();
}

class _NewTicketSheetState extends State<_NewTicketSheet> {
  late String _kind = widget.options.kinds.first.key;
  String? _lesson;
  final _subject = TextEditingController();
  final _body = TextEditingController();
  Attachment? _file;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _subject.dispose();
    _body.dispose();
    super.dispose();
  }

  String _hint(String kind) {
    final l = context.l10n;
    return switch (kind) {
      'complaint' => l.ticketHintComplaint,
      'billing' => widget.teacher ? l.ticketHintPay : l.ticketHintBilling,
      'technical' => l.ticketHintTechnical,
      _ => l.ticketHintOther,
    };
  }

  Future<void> _send() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final ticket = await context.read<SupportRepository>().open(
        kind: _kind,
        subject: _subject.text.trim(),
        body: _body.text.trim(),
        learnerId: widget.learnerId,
        lessonId: _lesson,
        file: _file,
      );
      if (mounted) Navigator.pop(context, ticket);
    } on ApiProblem catch (p) {
      if (mounted) setState(() => _error = context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l.ticketNewTitle, style: context.text.titleLarge),
            const SizedBox(height: 12),
            Text(l.ticketKind, style: context.text.labelLarge),
            RadioGroup<String>(
              groupValue: _kind,
              onChanged: (v) => setState(() => _kind = v ?? _kind),
              child: Column(
                children: [
                  for (final k in widget.options.kinds)
                    RadioListTile<String>(
                      key: Key('kind-${k.key}'),
                      value: k.key,
                      contentPadding: EdgeInsets.zero,
                      title: Text(k.label, style: context.text.titleSmall),
                      subtitle: Text(_hint(k.key), style: context.text.bodySmall),
                    ),
                ],
              ),
            ),
            if (widget.options.lessons.isNotEmpty) ...[
              const SizedBox(height: 6),
              DropdownButtonFormField<String?>(
                initialValue: _lesson,
                isExpanded: true,
                decoration: InputDecoration(labelText: l.ticketLesson),
                items: [
                  DropdownMenuItem<String?>(child: Text(l.ticketNoLesson)),
                  for (final lesson in widget.options.lessons)
                    DropdownMenuItem<String?>(
                      value: lesson.id,
                      child: Text(
                        [
                          lesson.subject,
                          lesson.startsAt?.label,
                          lesson.withName,
                        ].whereType<String>().where((s) => s.isNotEmpty).join(' · '),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: (v) => setState(() => _lesson = v),
              ),
            ],
            const SizedBox(height: 12),
            TextField(
              key: const Key('ticket-subject'),
              controller: _subject,
              maxLength: 150,
              decoration: InputDecoration(labelText: l.ticketSubject, hintText: l.ticketSubjectHint),
            ),
            TextField(
              key: const Key('ticket-body'),
              controller: _body,
              minLines: 3,
              maxLines: 8,
              maxLength: 5000,
              decoration: InputDecoration(labelText: l.ticketBody),
            ),
            OutlinedButton.icon(
              onPressed: _busy
                  ? null
                  : () async {
                      final picked = await pickAttachment(context);
                      if (picked != null && mounted) setState(() => _file = picked);
                    },
              icon: const Icon(Icons.attach_file_rounded),
              label: Text(_file?.name ?? l.ticketAttach, overflow: TextOverflow.ellipsis),
            ),
            if (_error != null) ...[const SizedBox(height: 10), Note(_error!, tone: Tone.danger)],
            const SizedBox(height: 14),
            FilledButton(key: const Key('ticket-send'), onPressed: _busy ? null : _send, child: Text(l.send)),
          ],
        ),
      ),
    );
  }
}
