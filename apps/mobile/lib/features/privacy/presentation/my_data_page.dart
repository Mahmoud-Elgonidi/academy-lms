import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/theme/app_theme.dart';
import '../../../core/l10n/l10n.dart';
import '../../../core/network/api_problem.dart';
import '../../../core/realtime/live.dart';
import '../../../core/state/load_cubit.dart';
import '../../../shared/motion/motion.dart';
import '../../../shared/widgets/lms.dart';
import '../../../shared/widgets/load_view.dart';
import '../data/privacy_repository.dart';
import '../domain/my_data.dart';

/// "My data" (the same as in /my, /teach and the portal): for each person one
/// may ask about, their details put right on the spot (the name; a student's
/// gender, country, grade and who should teach them) or a correction asked of
/// the academy for the rest, a copy to download and erasure of the account
/// (asked, answered by the academy, taken back until it is done); every
/// request; and the academy's privacy notice, terms and account-deletion
/// page. A younger student sees only that their guardian asks, and the pages.
/// Live on the person's own channel (privacy.changed).
class MyDataPage extends StatelessWidget {
  const MyDataPage({this.teacher = false, super.key});

  final bool teacher;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l10n.myDataTitle)),
    body: BlocProvider(
      create: (c) => LoadCubit<MyData>(c.read<PrivacyRepository>().load, refreshOn: c.read<Live>().on({Live.privacy}))..load(),
      child: LoadView<MyData>(
        builder: (context, mine) => ListView(
          padding: pagePadding,
          physics: const AlwaysScrollableScrollPhysics(),
          children: staggered(context, [
            Text(
              teacher ? context.l10n.myDataLeadTeacher : context.l10n.myDataLead,
              style: context.text.bodyMedium?.copyWith(color: context.colors.muted),
            ),
            const SizedBox(height: 14),
            if (mine.subjects.isEmpty) ...[Note(context.l10n.myDataYoung), const SizedBox(height: 14)],
            for (final subject in mine.subjects) ...[_PersonCard(subject: subject, mine: mine), const SizedBox(height: 14)],
            Text(context.l10n.myDataHistory, style: context.text.titleMedium),
            const SizedBox(height: 8),
            if (mine.requests.isEmpty) EmptyNote(context.l10n.myDataNone),
            if (mine.requests.isNotEmpty)
              LmsCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: [
                    for (final r in mine.requests)
                      ListTile(
                        dense: true,
                        title: Text('${r.kindLabel} · ${context.l10n.myDataAbout(r.subjectName)}'),
                        subtitle: Text([r.statusLabel, if (r.createdAt != null) r.createdAt!.label].join(' · ')),
                      ),
                  ],
                ),
              ),
            const SizedBox(height: 14),
            _Pages(links: mine.links),
          ]),
        ),
      ),
    ),
  );
}

class _PersonCard extends StatefulWidget {
  const _PersonCard({required this.subject, required this.mine});

  final DataSubject subject;
  final MyData mine;

  @override
  State<_PersonCard> createState() => _PersonCardState();
}

class _PersonCardState extends State<_PersonCard> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() step, String done) async {
    setState(() => _busy = true);
    try {
      await step();
      if (!mounted) return;
      showSnack(context, done);
      await context.read<LoadCubit<MyData>>().load();
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _askErasure() async {
    // The reason, or null when they went back.
    final reason = await showDialog<String>(
      context: context,
      builder: (_) => _EraseDialog(name: widget.subject.name, answerDays: widget.mine.answerDays),
    );
    if (reason != null && mounted) {
      await _run(() => context.read<PrivacyRepository>().askErasure(widget.subject.id, reason), context.l10n.myDataEraseAsked);
    }
  }

  Future<void> _correct() async {
    // What changed, or null when they went back.
    final changes = await showDialog<Map<String, String?>>(
      context: context,
      builder: (_) => _CorrectDialog(subject: widget.subject, choices: widget.mine.choices),
    );
    if (changes == null || !mounted) return;
    if (changes.isEmpty) return showSnack(context, context.l10n.myDataFixUnchanged);
    await _run(() => context.read<PrivacyRepository>().correct(widget.subject.id, changes), context.l10n.myDataFixSaved);
  }

  Future<void> _askCorrection() async {
    final says = await showDialog<String>(
      context: context,
      builder: (_) => _AskCorrectionDialog(name: widget.subject.name, mine: widget.mine),
    );
    if (says != null && mounted) {
      await _run(() => context.read<PrivacyRepository>().askCorrection(widget.subject.id, says), context.l10n.myDataFixAsked);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final subject = widget.subject;
    final copy = widget.mine.copyOf(subject.id);
    final erasure = widget.mine.erasureOf(subject.id);
    final fix = subject.correction;
    final waiting = fix?.status == 'pending';
    final repo = context.read<PrivacyRepository>();
    return LmsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(subject.name, style: context.text.titleMedium),
          const SizedBox(height: 12),
          Text(l.myDataFixTitle, style: context.text.titleSmall),
          const SizedBox(height: 6),
          for (final d in subject.details)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    flex: 2,
                    child: Text(d.label, style: context.text.bodySmall?.copyWith(color: c.muted)),
                  ),
                  Expanded(flex: 3, child: Text(d.shown, style: context.text.bodyMedium)),
                ],
              ),
            ),
          if (subject.details.isNotEmpty)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: OutlinedButton.icon(
                key: Key('fix-${subject.id}'),
                icon: const Icon(Icons.edit_outlined),
                label: Text(l.myDataFixEdit),
                onPressed: _busy ? null : _correct,
              ),
            ),
          const SizedBox(height: 6),
          if (subject.self) Text(l.myDataFixContact, style: context.text.bodySmall?.copyWith(color: c.muted)),
          Text(l.myDataFixBody(widget.mine.answerDays), style: context.text.bodySmall),
          const SizedBox(height: 8),
          if (waiting)
            Note([l.myDataFixWaiting(fix!.dueAt?.label ?? ''), if (fix.reason != null) '«${fix.reason}»'].join('\n'), tone: Tone.attention),
          if (fix?.status == 'done') Note(fix!.answer == null ? l.myDataFixDone : l.myDataFixDoneWith(fix.answer!), tone: Tone.brand),
          if (fix?.status == 'refused') Note(l.myDataFixRefused(fix!.answer ?? '')),
          if (waiting && fix!.canCancel)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                key: Key('cancel-fix-${subject.id}'),
                icon: const Icon(Icons.undo_rounded),
                label: Text(l.myDataFixCancel),
                onPressed: _busy ? null : () => _run(() => repo.cancel(fix.id), l.myDataFixCancelled),
              ),
            ),
          if (!waiting)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                key: Key('ask-fix-${subject.id}'),
                icon: const Icon(Icons.rate_review_outlined),
                label: Text(l.myDataFixAsk),
                onPressed: _busy ? null : _askCorrection,
              ),
            ),
          const Divider(height: 28),
          Text(l.myDataCopyTitle, style: context.text.titleSmall),
          const SizedBox(height: 4),
          Text(l.myDataCopyBody(7), style: context.text.bodySmall),
          const SizedBox(height: 8),
          if (copy?.status == 'pending') Note(l.myDataCopyPreparing, tone: Tone.attention),
          if (copy?.downloadUrl != null)
            Note(
              l.myDataCopyUntil(copy!.expiresAt?.label ?? ''),
              tone: Tone.brand,
              icon: Icons.download_rounded,
              action: FilledButton.icon(
                key: Key('download-${subject.id}'),
                icon: const Icon(Icons.download_rounded),
                label: Text(l.myDataCopyDownload),
                onPressed: () => launchUrl(Uri.parse(copy.downloadUrl!), mode: LaunchMode.externalApplication),
              ),
            ),
          if (subject.canCopy)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: OutlinedButton.icon(
                key: Key('copy-${subject.id}'),
                icon: const Icon(Icons.inventory_2_outlined),
                label: Text(l.myDataCopyAction),
                onPressed: _busy ? null : () => _run(() => repo.askCopy(subject.id), l.myDataCopyPreparing),
              ),
            ),
          const Divider(height: 28),
          Text(l.myDataEraseTitle, style: context.text.titleSmall),
          const SizedBox(height: 4),
          Text(l.myDataEraseBody(widget.mine.answerDays), style: context.text.bodySmall),
          const SizedBox(height: 8),
          if (erasure?.status == 'pending') Note(l.myDataEraseWaiting(erasure!.dueAt?.label ?? ''), tone: Tone.attention),
          if (erasure?.status == 'approved') Note(l.myDataEraseApproved(erasure!.eraseAfter?.label ?? ''), tone: Tone.danger),
          if (erasure?.status == 'refused') Note(l.myDataEraseRefused(erasure!.answer ?? '')),
          if (erasure != null && erasure.isOpenErasure && erasure.canCancel)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                key: Key('cancel-${subject.id}'),
                icon: const Icon(Icons.undo_rounded),
                label: Text(l.myDataEraseCancel),
                onPressed: _busy ? null : () => _run(() => repo.cancel(erasure.id), l.myDataEraseCancelled),
              ),
            ),
          if (erasure == null || !erasure.isOpenErasure)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                key: Key('erase-${subject.id}'),
                style: TextButton.styleFrom(foregroundColor: c.dangerInk),
                icon: const Icon(Icons.delete_outline_rounded),
                label: Text(l.myDataEraseAction),
                onPressed: _busy ? null : _askErasure,
              ),
            ),
          ExpansionTile(
            tilePadding: EdgeInsets.zero,
            title: Text(l.myDataWhatGoes, style: context.text.bodySmall?.copyWith(color: c.brandDeep)),
            childrenPadding: const EdgeInsetsDirectional.only(bottom: 8),
            expandedCrossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final g in widget.mine.goes) Text('• $g', style: context.text.bodySmall),
              const SizedBox(height: 8),
              Text(l.myDataWhatStays, style: context.text.titleSmall),
              for (final g in widget.mine.stays) Text('• $g', style: context.text.bodySmall),
              const SizedBox(height: 6),
              Text(widget.mine.staysNote, style: context.text.bodySmall?.copyWith(color: c.muted)),
            ],
          ),
        ],
      ),
    );
  }
}

/// "Erase this account?", with a reason if they like; it pops the reason, or nothing when they go back.
class _EraseDialog extends StatefulWidget {
  const _EraseDialog({required this.name, required this.answerDays});

  final String name;
  final int answerDays;

  @override
  State<_EraseDialog> createState() => _EraseDialogState();
}

class _EraseDialogState extends State<_EraseDialog> {
  final _reason = TextEditingController();

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return AlertDialog(
      title: Text(l.myDataEraseConfirm(widget.name)),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${l.myDataEraseBody(widget.answerDays)} ${l.myDataEraseCopyFirst}', style: context.text.bodySmall),
          const SizedBox(height: 12),
          TextField(
            key: const Key('erase-reason'),
            controller: _reason,
            maxLength: 1000,
            maxLines: 3,
            minLines: 2,
            decoration: InputDecoration(labelText: l.myDataEraseReason),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(l.back)),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: context.colors.danger),
          onPressed: () => Navigator.pop(context, _reason.text),
          child: Text(l.myDataEraseAction),
        ),
      ],
    );
  }
}

/// Correcting the details on the spot; it pops what changed (an empty value clears a choice), or nothing when they go back.
class _CorrectDialog extends StatefulWidget {
  const _CorrectDialog({required this.subject, required this.choices});

  final DataSubject subject;
  final Map<String, List<DetailChoice>> choices;

  @override
  State<_CorrectDialog> createState() => _CorrectDialogState();
}

class _CorrectDialogState extends State<_CorrectDialog> {
  late final _name = TextEditingController(text: _was('name'));
  late final Map<String, String> _values = {for (final d in widget.subject.details) d.key: d.value ?? ''};

  String _was(String key) => widget.subject.details.where((d) => d.key == key).firstOrNull?.value ?? '';

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Map<String, String?> _changes() {
    final next = {..._values, 'name': _name.text.trim()};
    return {
      for (final d in widget.subject.details)
        if ((next[d.key] ?? '') != (d.value ?? '')) d.key: (next[d.key] ?? '').isEmpty ? null : next[d.key],
    };
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return AlertDialog(
      title: Text(l.myDataFixEditTitle(widget.subject.name)),
      scrollable: true,
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (final d in widget.subject.details)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: d.key == 'name'
                  ? TextField(
                      key: const Key('fix-name'),
                      controller: _name,
                      maxLength: 255,
                      decoration: InputDecoration(labelText: d.label),
                      onChanged: (_) => setState(() {}),
                    )
                  : DropdownButtonFormField<String>(
                      key: Key('fix-${d.key}'),
                      initialValue: _values[d.key],
                      isExpanded: true,
                      decoration: InputDecoration(labelText: d.label),
                      items: [
                        if (!(widget.choices[d.key] ?? const []).any((c) => c.value.isEmpty))
                          DropdownMenuItem(value: '', child: Text(l.myDataFixUnset)),
                        for (final c in widget.choices[d.key] ?? const <DetailChoice>[])
                          DropdownMenuItem(
                            value: c.value,
                            child: Text(c.label, overflow: TextOverflow.ellipsis),
                          ),
                      ],
                      onChanged: (v) => setState(() => _values[d.key] = v ?? ''),
                    ),
            ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(l.back)),
        FilledButton(
          key: const Key('fix-save'),
          onPressed: _name.text.trim().isEmpty ? null : () => Navigator.pop(context, _changes()),
          child: Text(l.myDataFixSave),
        ),
      ],
    );
  }
}

/// "Ask to correct": what is wrong and what is right; it pops the words, or nothing when they go back.
class _AskCorrectionDialog extends StatefulWidget {
  const _AskCorrectionDialog({required this.name, required this.mine});

  final String name;
  final MyData mine;

  @override
  State<_AskCorrectionDialog> createState() => _AskCorrectionDialogState();
}

class _AskCorrectionDialogState extends State<_AskCorrectionDialog> {
  final _says = TextEditingController();

  @override
  void dispose() {
    _says.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final short = _says.text.trim().length < widget.mine.saysMin;
    return AlertDialog(
      title: Text(l.myDataFixAskTitle(widget.name)),
      scrollable: true,
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.myDataFixAskBody(widget.mine.answerDays), style: context.text.bodySmall),
          const SizedBox(height: 12),
          TextField(
            key: const Key('fix-says'),
            controller: _says,
            maxLength: widget.mine.saysMax,
            maxLines: 5,
            minLines: 3,
            decoration: InputDecoration(
              labelText: l.myDataFixSays,
              hintText: l.myDataFixPlaceholder,
              helperText: short ? l.myDataFixTooShort(widget.mine.saysMin) : null,
            ),
            onChanged: (_) => setState(() {}),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(l.back)),
        FilledButton(
          key: const Key('fix-send'),
          onPressed: short ? null : () => Navigator.pop(context, _says.text.trim()),
          child: Text(l.myDataFixSend),
        ),
      ],
    );
  }
}

/// The academy's privacy notice, terms and account-deletion page, in the browser.
class _Pages extends StatelessWidget {
  const _Pages({required this.links});

  final Map<String, String> links;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final names = {'privacy': l.myDataPrivacy, 'terms': l.myDataTerms, 'deletion': l.myDataDeletion};
    return LmsCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (final key in const ['privacy', 'terms', 'deletion'])
            if (links[key] != null)
              ListTile(
                key: Key('page-$key'),
                leading: const Icon(Icons.open_in_new_rounded),
                title: Text(names[key]!),
                onTap: () => launchUrl(Uri.parse(links[key]!), mode: LaunchMode.inAppBrowserView),
              ),
        ],
      ),
    );
  }
}
