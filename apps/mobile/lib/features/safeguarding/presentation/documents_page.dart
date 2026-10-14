import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/motion/done_moment.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../data/safeguarding_repository.dart';
import '../domain/safeguarding.dart';

const _maxBytes = 10 * 1024 * 1024;
const _mimes = {'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'png': 'image/png', 'webp': 'image/webp', 'pdf': 'application/pdf'};

/// "My documents" (teachers): the documents the academy's vetting rests on (a
/// criminal record check above all) and where each stands, whether the academy
/// asks for a valid check before they teach under-18s, and sending a new one.
class DocumentsPage extends StatelessWidget {
  const DocumentsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final repo = context.read<SafeguardingRepository>();
    return BlocProvider(
      create: (c) => LoadCubit<MyDocuments>(repo.documents, refreshOn: c.read<Live>().on({Live.checks}))..load(),
      child: Scaffold(
        appBar: AppBar(title: Text(l.documentsTitle)),
        body: LoadView<MyDocuments>(
          builder: (context, d) => ListView(
            padding: pagePadding,
            physics: const AlwaysScrollableScrollPhysics(),
            children: staggered(context, [
              Text(l.documentsLead, style: context.text.bodyMedium?.copyWith(color: context.colors.muted)),
              const SizedBox(height: 12),
              if (d.required && d.stateLabel.isNotEmpty) ...[
                Note(
                  d.stateLabel,
                  tone: switch (d.state) {
                    'verified' => Tone.brand,
                    'pending' => Tone.attention,
                    _ => Tone.danger,
                  },
                  icon: d.state == 'verified' ? Icons.verified_user_outlined : Icons.warning_amber_rounded,
                ),
                const SizedBox(height: 12),
              ],
              FilledButton.icon(
                key: const Key('send-document'),
                onPressed: () => _send(context, d),
                icon: const Icon(Icons.upload_file_rounded),
                label: Text(l.documentSend),
              ),
              const SizedBox(height: 18),
              Text(l.documentsList, style: context.text.titleMedium),
              const SizedBox(height: 8),
              if (d.checks.isEmpty) EmptyNote(l.documentsNone, icon: Icons.description_outlined),
              for (final c in d.checks) ...[_DocumentTile(document: c), const SizedBox(height: 10)],
            ]),
          ),
        ),
      ),
    );
  }

  Future<void> _send(BuildContext context, MyDocuments d) async {
    final sent = await showActionSheet<bool>(
      context,
      builder: (_) => RepositoryProvider.value(
        value: context.read<SafeguardingRepository>(),
        child: _SendSheet(kinds: d.kinds),
      ),
    );
    if (sent != true || !context.mounted) return;
    DoneMoment.show(context, title: context.l10n.documentSent);
    await context.read<LoadCubit<MyDocuments>>().load();
  }
}

/// A calendar day the API wrote (YYYY-MM-DD), in the reader's language; never a zone conversion.
String _day(BuildContext context, String day) => DateFormat(
  'd MMM y',
  context.locale,
).format(DateTime(int.parse(day.substring(0, 4)), int.parse(day.substring(5, 7)), int.parse(day.substring(8, 10))));

class _DocumentTile extends StatelessWidget {
  const _DocumentTile({required this.document});

  final TeacherDocument document;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final d = document;
    return LmsCard(
      key: Key('document-${d.id}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(d.kindLabel, style: context.text.titleSmall)),
              Pill(
                d.statusLabel,
                tone: switch (d.status) {
                  'verified' => Tone.brand,
                  'pending' => Tone.attention,
                  _ => Tone.danger,
                },
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            [
              ?d.reference,
              if (d.expiresOn != null) l.documentUntil(_day(context, d.expiresOn!)) else if (d.status == 'verified') l.documentNoEnd,
            ].join(' · '),
            style: context.text.bodySmall,
          ),
          if (d.reason != null) ...[const SizedBox(height: 8), Note(d.reason!, tone: Tone.danger)],
        ],
      ),
    );
  }
}

class _SendSheet extends StatefulWidget {
  const _SendSheet({required this.kinds});

  final List<({String key, String label})> kinds;

  @override
  State<_SendSheet> createState() => _SendSheetState();
}

class _SendSheetState extends State<_SendSheet> {
  late String _kind = widget.kinds.first.key;
  final _reference = TextEditingController();
  DateTime? _expires;
  PickedFile? _file;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  Future<void> _pick() async {
    final picked = await FilePicker.pickFile(type: FileType.custom, allowedExtensions: _mimes.keys.toList());
    if (picked == null || !mounted) return;
    final bytes = await picked.xFile.readAsBytes();
    if (!mounted) return;
    if (bytes.length > _maxBytes) {
      setState(() => _error = context.l10n.documentTooBig);
      return;
    }
    setState(() {
      _error = null;
      _file = PickedFile(
        bytes: bytes,
        name: picked.name,
        mime: _mimes[(picked.extension ?? '').toLowerCase()] ?? 'application/octet-stream',
      );
    });
  }

  Future<void> _date() async {
    final now = DateTime.now();
    final day = await showDatePicker(context: context, firstDate: now, lastDate: DateTime(now.year + 15), initialDate: _expires ?? now);
    if (day != null && mounted) setState(() => _expires = day);
  }

  Future<void> _send() async {
    if (_file == null) {
      setState(() => _error = context.l10n.documentPickFirst);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<SafeguardingRepository>().sendDocument(
        kind: _kind,
        file: _file!,
        reference: _reference.text.trim().isEmpty ? null : _reference.text.trim(),
        expiresOn: _expires == null ? null : DateFormat('yyyy-MM-dd').format(_expires!),
      );
      if (mounted) Navigator.pop(context, true);
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
            Text(l.documentSend, style: context.text.titleLarge),
            const SizedBox(height: 6),
            Text(l.documentSendHint, style: context.text.bodySmall),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _kind,
              isExpanded: true,
              decoration: InputDecoration(labelText: l.documentKind),
              items: [for (final k in widget.kinds) DropdownMenuItem(value: k.key, child: Text(k.label))],
              onChanged: (v) => setState(() => _kind = v ?? _kind),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _reference,
              maxLength: 120,
              decoration: InputDecoration(labelText: l.documentReference),
            ),
            OutlinedButton.icon(
              onPressed: _busy ? null : _date,
              icon: const Icon(Icons.event_outlined),
              label: Text(_expires == null ? l.documentExpires : l.documentUntil(DateFormat('d MMM y', context.locale).format(_expires!))),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              key: const Key('document-file'),
              onPressed: _busy ? null : _pick,
              icon: const Icon(Icons.attach_file_rounded),
              label: Text(_file?.name ?? l.documentPick, overflow: TextOverflow.ellipsis),
            ),
            if (_error != null) ...[const SizedBox(height: 10), Note(_error!, tone: Tone.danger)],
            const SizedBox(height: 14),
            FilledButton(key: const Key('document-send'), onPressed: _busy ? null : _send, child: Text(l.send)),
          ],
        ),
      ),
    );
  }
}
