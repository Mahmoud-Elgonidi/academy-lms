import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/network/api_problem.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../dashboard/presentation/widgets/learner_switcher.dart';
import '../../data/billing_repository.dart';
import '../../domain/billing.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../core/realtime/live.dart';
import '../../../../shared/widgets/latin_digits.dart';

class _Money {
  const _Money(this.invoices, this.balance);

  final Invoices invoices;
  final List<Balance> balance;
}

/// What is owed and paying it on the academy's own gateway page, or by hand
/// (where the academy's transfer details say, to copy) with the receipt for
/// the academy to confirm; the lessons left, and every
/// invoice (each opens its printable document with the tax QR). Coming back
/// to the app reloads it.
class MoneyPage extends StatefulWidget {
  const MoneyPage({super.key});

  @override
  State<MoneyPage> createState() => _MoneyPageState();
}

class _MoneyPageState extends State<MoneyPage> {
  late final AppLifecycleListener _resume;
  LoadCubit<_Money>? _cubit;

  @override
  void initState() {
    super.initState();
    _resume = AppLifecycleListener(onResume: () => _cubit?.load());
  }

  @override
  void dispose() {
    _resume.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final learnerId = context.learnerId();
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.navMoney)),
      body: BlocProvider(
        key: ValueKey(learnerId),
        create: (c) {
          final repo = c.read<BillingRepository>();
          return _cubit = LoadCubit<_Money>(() async {
            final results = await Future.wait<Object>([repo.invoices(), repo.balance(learnerId: learnerId)]);
            return _Money(results[0] as Invoices, results[1] as List<Balance>);
          }, refreshOn: c.read<Live>().on({Live.payments, Live.lessons}))..load();
        },
        child: LoadView<_Money>(builder: (context, m) => _MoneyList(money: m)),
      ),
    );
  }
}

class _MoneyList extends StatelessWidget {
  const _MoneyList({required this.money});

  final _Money money;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final due = money.invoices.payable.firstOrNull;
    return ListView(
      padding: pagePadding,
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        if (due != null) ...[
          LmsCard(
            color: c.attentionSoft,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.owedNow, style: context.text.labelMedium?.copyWith(color: c.attentionInk, fontSize: 13)),
                const SizedBox(height: 4),
                Text(money.invoices.owed ?? due.outstanding, style: context.text.headlineMedium),
                Text(
                  l.invoiceDue(due.number, due.dueAt?.day(context.locale) ?? ''),
                  style: context.text.bodySmall?.copyWith(color: c.attentionInk),
                ),
                if (due.proof case final proof?) ...[const SizedBox(height: 10), _ProofLine(proof: proof)],
                if (due.transferText case final transfer?) ...[const SizedBox(height: 10), _TransferDetails(text: transfer)],
                const SizedBox(height: 12),
                // No gateway takes it and its proof waits for the academy: nothing to press, the line above says where it stands.
                if (due.payWith.isNotEmpty || (!due.canSendProof && due.proof?.status != 'pending'))
                  SizedBox(
                    width: double.infinity,
                    child: _PayButton(invoice: due),
                  ),
                if (due.canSendProof) ...[
                  if (due.payWith.isNotEmpty) const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: _ProofButton(invoice: due, manual: money.invoices.manual, primary: due.payWith.isEmpty),
                  ),
                ],
                if (due.payWith.isNotEmpty) ...[const SizedBox(height: 6), Text(l.payNote, style: context.text.bodySmall)],
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],
        if (money.balance.isNotEmpty) ...[
          SectionLabel(l.lessonsLeftTitle),
          LmsCard(
            child: Column(
              children: [
                for (final b in money.balance)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(child: Text(b.shared ? l.sharedPackage(b.subject) : b.subject, style: context.text.titleSmall)),
                            Pill(l.lessonsCount(b.sessions), tone: b.sessions <= 1 ? Tone.attention : Tone.brand),
                          ],
                        ),
                        if (b.extra case final extra?)
                          extra.waitingNumber != null
                              ? Padding(
                                  padding: const EdgeInsets.only(top: 4),
                                  child: Text(l.extraWaiting(extra.waitingNumber!), style: context.text.bodySmall),
                                )
                              : Align(
                                  alignment: AlignmentDirectional.centerStart,
                                  child: TextButton.icon(
                                    key: Key('extra-${b.enrollmentId}'),
                                    style: TextButton.styleFrom(padding: EdgeInsets.zero, visualDensity: VisualDensity.compact),
                                    onPressed: () => _buyExtra(context, b, extra),
                                    icon: const Icon(Icons.add_circle_outline, size: 18),
                                    label: Text('${l.extraAction} · ${l.extraHint(extra.price, extra.minutes)}'),
                                  ),
                                ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],
        SectionLabel(l.invoices),
        if (money.invoices.list.isEmpty) EmptyNote(l.noInvoices, icon: Icons.receipt_long_outlined),
        if (money.invoices.list.isNotEmpty)
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                for (final (i, inv) in money.invoices.list.indexed) ...[if (i > 0) const Divider(height: 1), _InvoiceRow(invoice: inv)],
              ],
            ),
          ),
        const SizedBox(height: 10),
        Text(l.invoiceNote, style: context.text.bodySmall),
      ]),
    );
  }
}

/// Extra lessons on a course: how many, then the invoice, which waits with the others to be paid.
Future<void> _buyExtra(BuildContext context, Balance course, ExtraOffer offer) async {
  final made = await showModalBottomSheet<({String number, String total})>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (_) => RepositoryProvider.value(
      value: context.read<BillingRepository>(),
      child: _ExtraSheet(course: course, offer: offer, learnerId: context.learnerId()),
    ),
  );
  if (made == null || !context.mounted) return;
  showSnack(context, context.l10n.extraDone(made.number, made.total));
  await context.read<LoadCubit<_Money>>().load();
}

class _ExtraSheet extends StatefulWidget {
  const _ExtraSheet({required this.course, required this.offer, required this.learnerId});

  final Balance course;
  final ExtraOffer offer;
  final String? learnerId;

  @override
  State<_ExtraSheet> createState() => _ExtraSheetState();
}

class _ExtraSheetState extends State<_ExtraSheet> {
  int _count = 1;
  bool _busy = false;
  String? _error;

  Future<void> _make() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final made = await context.read<BillingRepository>().buyExtraLessons(widget.course.enrollmentId, _count, learnerId: widget.learnerId);
      if (mounted) Navigator.pop(context, made);
    } on ApiProblem catch (p) {
      if (mounted) setState(() => _error = context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.extraTitle(widget.course.subject), style: context.text.titleLarge),
          const SizedBox(height: 6),
          Text(l.extraBody, style: context.text.bodyMedium?.copyWith(color: context.colors.muted)),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (var n = 1; n <= widget.offer.max; n++)
                ChoiceChip(
                  key: Key('extra-count-$n'),
                  label: Text('$n'),
                  selected: _count == n,
                  onSelected: _busy ? null : (_) => setState(() => _count = n),
                ),
            ],
          ),
          const SizedBox(height: 10),
          Text(l.extraEach(_count, widget.offer.price), style: context.text.bodySmall),
          if (_error != null) ...[const SizedBox(height: 10), Note(_error!, tone: Tone.danger)],
          const SizedBox(height: 14),
          FilledButton(key: const Key('extra-submit'), onPressed: _busy ? null : _make, child: Text(l.extraSubmit)),
        ],
      ),
    );
  }
}

class _InvoiceRow extends StatelessWidget {
  const _InvoiceRow({required this.invoice});

  final Invoice invoice;

  @override
  Widget build(BuildContext context) {
    final tone = switch (invoice.status) {
      'paid' => Tone.brand,
      'void' || 'cancelled' => Tone.neutral,
      'overdue' => Tone.danger,
      _ => Tone.attention,
    };
    final url = invoice.documentUrl;
    return InkWell(
      onTap: url == null ? null : () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(invoice.forWhom, style: context.text.titleSmall),
                  Text(
                    '${invoice.number}${invoice.family ? ' · ${context.l10n.familyInvoice}' : ''}${invoice.issuedAt == null ? '' : ' · ${invoice.issuedAt!.day(context.locale)}'}',
                    style: context.text.bodySmall,
                  ),
                  for (final off in invoice.lines.where((x) => x.isDiscount))
                    Text('${off.description}  ${off.amount}', style: context.text.bodySmall?.copyWith(color: context.colors.brand)),
                  if (invoice.proof case final proof? when proof.status != 'rejected' || invoice.payable) ...[
                    const SizedBox(height: 6),
                    _ProofLine(proof: proof),
                  ],
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(invoice.total, style: context.text.titleSmall),
                const SizedBox(height: 2),
                Pill(invoice.statusLabel, tone: tone),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PayButton extends StatefulWidget {
  const _PayButton({required this.invoice});

  final Invoice invoice;

  @override
  State<_PayButton> createState() => _PayButtonState();
}

class _PayButtonState extends State<_PayButton> {
  bool _busy = false;

  Future<void> _pay() async {
    final l = context.l10n;
    final gateways = widget.invoice.payWith;
    if (gateways.isEmpty) {
      showSnack(context, l.cantPayOnline);
      return;
    }
    var gateway = gateways.first.id;
    if (gateways.length > 1) {
      final picked = await showModalBottomSheet<String>(
        context: context,
        useRootNavigator: true,
        builder: (c) => SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: Text(l.pickGateway, style: c.text.titleMedium),
              ),
              for (final g in gateways)
                ListTile(leading: const Icon(Icons.credit_card_rounded), title: Text(g.label), onTap: () => Navigator.pop(c, g.id)),
            ],
          ),
        ),
      );
      if (picked == null) return;
      gateway = picked;
    }
    if (!mounted) return;
    setState(() => _busy = true);
    try {
      final url = await context.read<BillingRepository>().checkout(widget.invoice.id, accountId: gateway);
      await launchUrl(url, mode: LaunchMode.externalApplication);
    } on ApiProblem catch (p) {
      if (mounted) showSnack(context, context.problemText(p));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => FilledButton.icon(
    onPressed: _busy ? null : _pay,
    icon: const Icon(Icons.lock_outline_rounded),
    label: Text(context.l10n.payAmount(widget.invoice.outstanding)),
  );
}

/// Where to send the money by hand, in the academy's words (an account and
/// its IBAN, an InstaPay or wallet number): selectable, line breaks kept,
/// with a button that copies it as written.
class _TransferDetails extends StatefulWidget {
  const _TransferDetails({required this.text});

  final String text;

  @override
  State<_TransferDetails> createState() => _TransferDetailsState();
}

class _TransferDetailsState extends State<_TransferDetails> {
  bool _copied = false;

  Future<void> _copy() async {
    await Clipboard.setData(ClipboardData(text: widget.text));
    if (!mounted) return;
    setState(() => _copied = true);
    showSnack(context, context.l10n.transferCopied);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    return Container(
      key: const Key('transfer-details'),
      width: double.infinity,
      padding: const EdgeInsetsDirectional.fromSTEB(12, 4, 4, 10),
      decoration: BoxDecoration(color: c.brandSoft, borderRadius: BorderRadius.circular(context.shape.field)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(l.transferTitle, style: context.text.labelLarge?.copyWith(color: c.brandDeep)),
              ),
              TextButton.icon(
                key: const Key('transfer-copy'),
                onPressed: _copy,
                style: TextButton.styleFrom(visualDensity: VisualDensity.compact),
                icon: Icon(_copied ? Icons.check_rounded : Icons.copy_rounded, size: 18),
                label: Text(l.transferCopy),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsetsDirectional.only(end: 8),
            child: SelectableText(widget.text, style: context.text.bodyMedium),
          ),
        ],
      ),
    );
  }
}

/// Where the latest proof stands, in the API's words.
class _ProofLine extends StatelessWidget {
  const _ProofLine({required this.proof});

  final ProofState proof;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final (bg, ink, icon) = switch (proof.status) {
      'approved' => (c.brandSoft, c.brandDeep, Icons.check_circle_outline_rounded),
      'rejected' => (c.dangerSoft, c.dangerInk, Icons.error_outline_rounded),
      _ => (c.infoSoft, c.infoInk, Icons.hourglass_top_rounded),
    };
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(context.shape.field)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: ink),
          const SizedBox(width: 8),
          Expanded(
            child: Text(proof.text, style: context.text.bodySmall?.copyWith(color: ink)),
          ),
        ],
      ),
    );
  }
}

class _ProofButton extends StatelessWidget {
  const _ProofButton({required this.invoice, required this.manual, required this.primary});

  final Invoice invoice;
  final ManualPayment manual;
  final bool primary;

  Future<void> _open(BuildContext context) async {
    final repo = context.read<BillingRepository>();
    final cubit = context.read<LoadCubit<_Money>>();
    final sent = await showModalBottomSheet<bool>(
      context: context,
      useRootNavigator: true,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => _ProofSheet(invoice: invoice, manual: manual, repo: repo),
    );
    if (sent == true && context.mounted) {
      showSnack(context, context.l10n.proofSent);
      unawaited(cubit.load());
    }
  }

  @override
  Widget build(BuildContext context) {
    final label = Text(context.l10n.paidByTransfer);
    const icon = Icon(Icons.receipt_long_outlined);
    return primary
        ? FilledButton.icon(onPressed: () => _open(context), icon: icon, label: label)
        : OutlinedButton.icon(onPressed: () => _open(context), icon: icon, label: label);
  }
}

/// The receipt and what it says: how, how much, the day, the transfer's
/// number. The day is the payer's own, as picked on the phone.
class _ProofSheet extends StatefulWidget {
  const _ProofSheet({required this.invoice, required this.manual, required this.repo});

  final Invoice invoice;
  final ManualPayment manual;
  final BillingRepository repo;

  @override
  State<_ProofSheet> createState() => _ProofSheetState();
}

class _ProofSheetState extends State<_ProofSheet> {
  static const _mimes = {
    'pdf': 'application/pdf',
    'jpg': 'image/jpeg',
    'jpeg': 'image/jpeg',
    'png': 'image/png',
    'webp': 'image/webp',
    'heic': 'image/heic',
  };
  static const _maxBytes = 10 * 1024 * 1024;

  late final _amount = TextEditingController(text: widget.invoice.outstandingDecimal);
  final _reference = TextEditingController();
  final _note = TextEditingController();
  late String _method = widget.manual.methods.firstOrNull?.value ?? 'bank_transfer';
  DateTime _paidOn = DateUtils.dateOnly(DateTime.now());
  Receipt? _receipt;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _amount.dispose();
    _reference.dispose();
    _note.dispose();
    super.dispose();
  }

  String get _day =>
      '${_paidOn.year.toString().padLeft(4, '0')}-${_paidOn.month.toString().padLeft(2, '0')}-${_paidOn.day.toString().padLeft(2, '0')}';

  Future<void> _pickDay() async {
    final today = DateUtils.dateOnly(DateTime.now());
    final picked = await showDatePicker(
      context: context,
      initialDate: _paidOn,
      firstDate: today.subtract(const Duration(days: 60)),
      lastDate: today,
    );
    if (picked != null && mounted) setState(() => _paidOn = picked);
  }

  Future<void> _pick() async {
    final l = context.l10n;
    PlatformFile? picked;
    try {
      picked = await FilePicker.pickFile(type: FileType.custom, allowedExtensions: _mimes.keys.toList());
    } on Object {
      if (mounted) setState(() => _error = l.problem);
      return;
    }
    final file = picked;
    if (file == null || !mounted) return;
    // A big video picked by mistake is turned down before it is read into memory.
    final size = file.lengthSync();
    if (size != null && size > _maxBytes) {
      setState(() => _error = l.proofFileTooBig);
      return;
    }
    final bytes = await file.readAsBytes();
    if (!mounted) return;
    if (bytes.length > _maxBytes) {
      setState(() => _error = l.proofFileTooBig);
      return;
    }
    final ext = (file.extension ?? '').toLowerCase();
    setState(() {
      _receipt = Receipt(bytes: bytes, name: file.name, mime: _mimes[ext] ?? 'application/octet-stream');
      _error = null;
    });
  }

  Future<void> _send() async {
    if (_busy) return;
    final l = context.l10n;
    final receipt = _receipt;
    if (receipt == null) {
      setState(() => _error = l.proofNeedsReceipt);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.repo.sendProof(
        widget.invoice.id,
        method: _method,
        amount: _amount.text.trim(),
        paidOn: _day,
        receipt: receipt,
        reference: _reference.text.trim().isEmpty ? null : _reference.text.trim(),
        note: _note.text.trim().isEmpty ? null : _note.text.trim(),
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
    final c = context.colors;
    // This invoice's own (its payer's region's words); an earlier server says one text for all.
    final note = widget.invoice.transferText ?? widget.manual.note;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(l.proofTitle, style: context.text.titleLarge),
              const SizedBox(height: 4),
              Text(l.proofHint, style: context.text.bodySmall),
              if (note != null && note.trim().isNotEmpty) ...[const SizedBox(height: 12), _TransferDetails(text: note.trim())],
              const SizedBox(height: 16),
              Text(l.proofMethod, style: context.text.labelLarge),
              const SizedBox(height: 6),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final m in widget.manual.methods)
                    ChoiceChip(label: Text(m.label), selected: _method == m.value, onSelected: (_) => setState(() => _method = m.value)),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _amount,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      inputFormatters: const [LatinDigits(decimal: true)],
                      decoration: InputDecoration(labelText: l.proofAmount),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: InkWell(
                      onTap: _pickDay,
                      child: InputDecorator(
                        decoration: InputDecoration(labelText: l.proofPaidOn, suffixIcon: const Icon(Icons.event_outlined)),
                        child: Text(MaterialLocalizations.of(context).formatMediumDate(_paidOn)),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _reference,
                decoration: InputDecoration(labelText: l.proofReference),
              ),
              const SizedBox(height: 12),
              Text(l.proofReceipt, style: context.text.labelLarge),
              const SizedBox(height: 6),
              OutlinedButton.icon(
                onPressed: _busy ? null : _pick,
                icon: Icon(_receipt == null ? Icons.add_photo_alternate_outlined : Icons.check_circle_outline_rounded),
                label: Text(_receipt?.name ?? l.proofPickReceipt, overflow: TextOverflow.ellipsis),
              ),
              const SizedBox(height: 4),
              Text(l.proofReceiptHint, style: context.text.bodySmall),
              const SizedBox(height: 12),
              TextField(
                controller: _note,
                maxLines: 2,
                decoration: InputDecoration(labelText: l.proofNote),
              ),
              if (_error case final error?) ...[
                const SizedBox(height: 12),
                Text(error, style: context.text.bodyMedium?.copyWith(color: c.danger)),
              ],
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: _busy ? null : _send,
                icon: _busy
                    ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.send_rounded),
                label: Text(l.proofSend),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
