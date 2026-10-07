import 'package:flutter/material.dart';
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

class _Money {
  const _Money(this.invoices, this.balance);

  final Invoices invoices;
  final List<Balance> balance;
}

/// What is owed and paying it on the academy's own gateway page, the
/// lessons left, and every invoice (each opens its printable document with
/// the tax QR). Coming back to the app reloads it.
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
          })..load();
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
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: _PayButton(invoice: due),
                ),
                const SizedBox(height: 6),
                Text(l.payNote, style: context.text.bodySmall),
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
                    child: Row(
                      children: [
                        Expanded(child: Text(b.subject, style: context.text.titleSmall)),
                        Pill(l.lessonsCount(b.sessions), tone: b.sessions <= 1 ? Tone.attention : Tone.brand),
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
                    '${invoice.number}${invoice.issuedAt == null ? '' : ' · ${invoice.issuedAt!.day(context.locale)}'}',
                    style: context.text.bodySmall,
                  ),
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
