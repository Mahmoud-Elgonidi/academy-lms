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
import '../../../../core/time/course_days.dart';
import '../../../../core/time/formats.dart';
import '../../../../shared/widgets/course_state.dart';
import '../../../../shared/motion/done_moment.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../dashboard/presentation/widgets/family_header.dart';
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
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            FamilyHeader(eyebrow: context.l10n.moneyEyebrow, title: context.l10n.navMoney),
            Expanded(child: _body(learnerId)),
          ],
        ),
      ),
    );
  }

  Widget _body(String? learnerId) => BlocProvider(
    key: ValueKey(learnerId),
    create: (c) {
      final repo = c.read<BillingRepository>();
      return _cubit = LoadCubit<_Money>(() async {
        final results = await Future.wait<Object>([repo.invoices(), repo.balance(learnerId: learnerId)]);
        return _Money(results[0] as Invoices, results[1] as List<Balance>);
      }, refreshOn: c.read<Live>().on({Live.payments, Live.lessons}))..load();
    },
    child: LoadView<_Money>(
      skeleton: const SkeletonList(),
      builder: (context, m) => _MoneyList(money: m),
    ),
  );
}

class _MoneyList extends StatelessWidget {
  const _MoneyList({required this.money});

  final _Money money;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final due = money.invoices.payable.firstOrNull;
    return ListView(
      padding: pagePadding.copyWith(top: Space.m),
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        if (due != null) _OwedCard(invoice: due, owed: money.invoices.owed ?? due.outstanding, manual: money.invoices.manual),
        if (due?.transferText case final transfer?) ...[SectionHeader(l.transferTitle), _TransferDetails(text: transfer, titled: false)],
        if (money.balance.isNotEmpty) ...[
          SectionHeader(l.lessonsLeftTitle),
          MenuGroup(children: [for (final b in money.balance) _BalanceRow(balance: b)]),
        ],
        SectionHeader(l.invoices),
        if (money.invoices.list.isEmpty) EmptyState(icon: Icons.receipt_long_outlined, title: l.noInvoices),
        if (money.invoices.list.isNotEmpty)
          LmsCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: [
                for (final (i, inv) in money.invoices.list.indexed) ...[
                  if (i > 0) const Divider(height: 1, indent: Space.l, endIndent: Space.l),
                  _InvoiceRow(invoice: inv),
                ],
              ],
            ),
          ),
        const Gap(Space.m),
        Text(l.invoiceNote, style: context.type.caption),
      ]),
    );
  }
}

/// What is owed now, the one surface in the academy's colour: how much, which
/// invoice and by when, where its receipt stands, and the ways to pay it.
class _OwedCard extends StatelessWidget {
  const _OwedCard({required this.invoice, required this.owed, required this.manual});

  final Invoice invoice;
  final String owed;
  final ManualPayment manual;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    const ink = Colors.white;
    final due = invoice;
    return Container(
      key: const Key('money-owed'),
      padding: const EdgeInsets.fromLTRB(Space.l + 4, Space.l + 2, Space.l + 4, Space.l + 4),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(context.shape.card + 6),
        gradient: LinearGradient(
          colors: [c.brand, Color.lerp(c.brand, Colors.black, 0.18)!],
          begin: AlignmentDirectional.topStart,
          end: AlignmentDirectional.bottomEnd,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.owedNow,
            style: TextStyle(color: ink.withValues(alpha: 0.88), fontWeight: FontWeight.w700, fontSize: 14),
          ),
          const Gap(Space.s),
          Text(owed, style: context.type.number.copyWith(color: ink, fontSize: 34)),
          const Gap(Space.xs),
          Text(
            l.invoiceDue(due.number, due.dueAt?.day(context.locale) ?? ''),
            style: TextStyle(color: ink.withValues(alpha: 0.9), fontSize: 14),
          ),
          if (due.proof case final proof?) ...[const Gap(Space.m), _ProofLine(proof: proof)],
          const Gap(Space.l),
          // No gateway takes it and its proof waits for the academy: nothing to press, the line above says where it stands.
          if (due.payWith.isNotEmpty || (!due.canSendProof && due.proof?.status != 'pending'))
            SizedBox(
              width: double.infinity,
              height: 52,
              child: _PayButton(invoice: due),
            ),
          if (due.canSendProof) ...[
            if (due.payWith.isNotEmpty) const Gap(Space.s),
            SizedBox(
              width: double.infinity,
              height: 52,
              child: _ProofButton(invoice: due, manual: manual, primary: due.payWith.isEmpty),
            ),
          ],
          if (due.payWith.isNotEmpty) ...[
            const Gap(Space.s),
            Text(l.payNote, style: TextStyle(color: ink.withValues(alpha: 0.85), fontSize: 12.5, height: 1.5)),
          ],
        ],
      ),
    );
  }
}

/// A course's lessons left (or a shared package's), whether it is paused or
/// ended, and extra lessons to add while it runs.
class _BalanceRow extends StatelessWidget {
  const _BalanceRow({required this.balance});

  final Balance balance;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final b = balance;
    final low = b.days.isOn && b.sessions <= 1;
    return Padding(
      padding: const EdgeInsets.fromLTRB(Space.l, Space.m, Space.l, Space.m),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(b.shared ? l.sharedPackage(b.subject) : b.subject, style: context.type.cardTitle)),
              StatusChip(l.lessonsCount(b.sessions), tone: low ? Tone.attention : Tone.brand),
            ],
          ),
          // A group plan: the group's seats and the learner's own.
          if (b.group case final g?) ...[
            const Gap(Space.xs),
            CourseGroupLine(taken: g.seatsTaken, capacity: g.capacity, seat: g.seat, forming: g.forming, plan: true),
          ],
          if (b.days.stand != CourseStand.on) ...[const Gap(Space.xs), CourseStatePill(b.days)],
          if (b.extra case final extra?)
            extra.waitingNumber != null
                ? Padding(
                    padding: const EdgeInsets.only(top: Space.xs),
                    child: Text(l.extraWaiting(extra.waitingNumber!), style: context.type.caption),
                  )
                : Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: TextButton.icon(
                      key: Key('extra-${b.enrollmentId}'),
                      style: TextButton.styleFrom(
                        padding: EdgeInsets.zero,
                        foregroundColor: c.brandDeep,
                        visualDensity: VisualDensity.compact,
                      ),
                      onPressed: () => _buyExtra(context, b, extra),
                      icon: const Icon(Icons.add_circle_outline, size: 18),
                      label: Text('${l.extraAction} · ${l.extraHint(extra.price, extra.minutes)}'),
                    ),
                  ),
        ],
      ),
    );
  }
}

/// Extra lessons on a course: how many, then the invoice, which waits with the others to be paid.
Future<void> _buyExtra(BuildContext context, Balance course, ExtraOffer offer) async {
  final made = await showActionSheet<({String number, String total})>(
    context,
    builder: (_) => RepositoryProvider.value(
      value: context.read<BillingRepository>(),
      child: _ExtraSheet(course: course, offer: offer, learnerId: context.learnerId()),
    ),
  );
  if (made == null || !context.mounted) return;
  DoneMoment.show(context, title: context.l10n.extraDone(made.number, made.total));
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
    final (tone, icon) = switch (invoice.status) {
      'paid' => (Tone.brand, Icons.check_rounded),
      'void' || 'cancelled' => (Tone.neutral, Icons.block_rounded),
      'overdue' => (Tone.danger, Icons.error_outline_rounded),
      _ when invoice.proof?.status == 'pending' => (Tone.info, Icons.hourglass_top_rounded),
      _ => (Tone.attention, Icons.receipt_long_outlined),
    };
    final url = invoice.documentUrl;
    final row = Padding(
      padding: const EdgeInsets.fromLTRB(Space.l, Space.m, Space.m, Space.m),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon, tone: tone, size: 40),
          const Gap(Space.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(invoice.forWhom, style: context.type.cardTitle),
                Text(
                  [
                    invoice.number,
                    if (invoice.family) context.l10n.familyInvoice,
                    if (invoice.issuedAt != null) invoice.issuedAt!.day(context.locale),
                  ].join(' · '),
                  style: context.type.caption,
                ),
                for (final off in invoice.lines.where((x) => x.isDiscount))
                  Text('${off.description}  ${off.amount}', style: context.type.caption.copyWith(color: context.colors.brandDeep)),
                if (invoice.proof case final proof? when proof.status != 'rejected' || invoice.payable) ...[
                  const Gap(Space.s),
                  _ProofLine(proof: proof),
                ],
              ],
            ),
          ),
          const Gap(Space.s),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(invoice.total, style: context.type.cardTitle.copyWith(fontFeatures: const [FontFeature.tabularFigures()])),
              const Gap(Space.xs),
              StatusChip(invoice.statusLabel, tone: tone),
            ],
          ),
          if (url != null) ...[const Gap(Space.xs), Icon(Icons.chevron_right_rounded, color: context.colors.faint)],
        ],
      ),
    );
    if (url == null) return row;
    return InkWell(
      onTap: () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
      child: row,
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
    style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: context.colors.brandDeep),
    onPressed: _busy ? null : _pay,
    icon: const Icon(Icons.lock_outline_rounded),
    label: Text(context.l10n.payAmount(widget.invoice.outstanding), style: const TextStyle(fontWeight: FontWeight.w800)),
  );
}

/// Where to send the money by hand, in the academy's words (an account and
/// its IBAN, an InstaPay or wallet number): selectable, line breaks kept,
/// with a button that copies it as written.
class _TransferDetails extends StatefulWidget {
  const _TransferDetails({required this.text, this.titled = true});

  final String text;

  /// Its own title (in the receipt's sheet); the page gives it a heading instead.
  final bool titled;

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
    final copy = TextButton.icon(
      key: const Key('transfer-copy'),
      onPressed: _copy,
      style: TextButton.styleFrom(visualDensity: VisualDensity.compact, foregroundColor: c.brandDeep),
      icon: Icon(_copied ? Icons.check_rounded : Icons.copy_rounded, size: 18),
      label: Text(l.transferCopy, style: const TextStyle(fontWeight: FontWeight.w700)),
    );
    return Container(
      key: const Key('transfer-details'),
      width: double.infinity,
      padding: EdgeInsetsDirectional.fromSTEB(Space.l, widget.titled ? Space.xs : Space.m, Space.s, Space.m),
      decoration: BoxDecoration(
        color: widget.titled ? c.brandSoft : c.card,
        borderRadius: BorderRadius.circular(widget.titled ? context.shape.field : context.shape.card),
        border: widget.titled ? null : Border.all(color: c.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (widget.titled)
            Row(
              children: [
                Expanded(
                  child: Text(l.transferTitle, style: context.text.labelLarge?.copyWith(color: c.brandDeep)),
                ),
                copy,
              ],
            ),
          Padding(
            padding: const EdgeInsetsDirectional.only(end: Space.s),
            child: SelectableText(widget.text, style: context.type.body.copyWith(height: 1.7)),
          ),
          if (!widget.titled) Align(alignment: AlignmentDirectional.centerEnd, child: copy),
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
      DoneMoment.show(context, title: context.l10n.proofSent);
      unawaited(cubit.load());
    }
  }

  @override
  Widget build(BuildContext context) {
    final label = Text(context.l10n.paidByTransfer, style: const TextStyle(fontWeight: FontWeight.w700));
    const icon = Icon(Icons.upload_rounded);
    // On the brand's surface: white when it is the way to pay, outlined in white beside Pay.
    return primary
        ? FilledButton.icon(
            style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: context.colors.brandDeep),
            onPressed: () => _open(context),
            icon: icon,
            label: label,
          )
        : OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
              foregroundColor: Colors.white,
              side: BorderSide(color: Colors.white.withValues(alpha: 0.6)),
            ),
            onPressed: () => _open(context),
            icon: icon,
            label: label,
          );
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
