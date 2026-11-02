import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/time/formats.dart';
import '../../../../core/realtime/live.dart';
import '../../../../shared/motion/celebration.dart';
import '../../../../shared/motion/done_moment.dart';
import '../../../../shared/motion/juice.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../billing/data/billing_repository.dart';
import '../../data/start_repository.dart';
import '../../domain/start.dart';
import '../start_page.dart';
import '../widgets.dart';
import 'pay_cubit.dart';

/// Paying a course's first invoice (docs/architecture/02, section 27, J3):
/// right after starting it (what was made, when to pay by, what became of
/// the offer), or from anywhere with the invoice's id. The invoice line by
/// line with its discounts and VAT, a coupon, and the academy's own payment
/// page in the browser; coming back to the app reads it again: paid (a
/// celebration and the welcome: the teacher, the weekly times, the first
/// lesson and how to join), still being confirmed (a calm wait that follows
/// the payments signal), or not paid (said, with the way to try again).
class PayPage extends StatefulWidget {
  const PayPage({required this.invoiceId, this.started, super.key});

  /// `none` when the course joined a shared package (nothing to pay).
  final String invoiceId;
  final StartedCourse? started;

  @override
  State<PayPage> createState() => _PayPageState();
}

class _PayPageState extends State<PayPage> {
  late final AppLifecycleListener _resume;
  PayCubit? _cubit;

  /// Paid while this screen was open: thanks, rather than a plain "paid".
  bool _paidHere = false;

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
    final l = context.l10n;
    return BlocProvider(
      create: (c) => _cubit = PayCubit(
        c.read<StartRepository>(),
        c.read<BillingRepository>(),
        widget.invoiceId,
        invoice: widget.started?.invoice,
        enrollmentId: widget.started?.canCancel ?? false ? widget.started!.enrollmentId : null,
        refreshOn: c.read<Live>().on({Live.payments}),
      )..load(),
      child: Scaffold(
        appBar: AppBar(
          title: Text(l.payTitle),
          // Opened from a notification or a link, with nothing under it: back to Payments.
          leading: context.canPop()
              ? null
              : IconButton(
                  key: const Key('pay-to-money'),
                  tooltip: l.payOpenPayments,
                  onPressed: () => context.go('/family/money'),
                  icon: const Icon(Icons.close_rounded),
                ),
        ),
        body: BlocConsumer<PayCubit, PayState>(
          listenWhen: (a, b) => !a.justPaid && b.justPaid,
          listener: (context, state) {
            _paidHere = true;
            Juice.done();
            Celebration.show(context, from: MediaQuery.sizeOf(context).center(const Offset(0, -120)));
            context.read<PayCubit>().celebrated();
          },
          builder: (context, state) {
            final started = widget.started;
            final Widget body = switch (state.phase) {
              PayPhase.loading => const Center(child: CircularProgressIndicator.adaptive()),
              PayPhase.failed => ErrorState.of(state.problem!, onRetry: context.read<PayCubit>().load),
              PayPhase.nothing when widget.invoiceId == 'none' && started != null => _Package(started: started),
              PayPhase.nothing => EmptyState(key: const Key('pay-void'), icon: Icons.block_rounded, title: l.payVoid),
              PayPhase.cancelled => _Cancelled(message: state.cancelled!, started: started),
              PayPhase.paid when started == null => _Settled(invoice: state.invoice!, fresh: _paidHere || state.justPaid),
              PayPhase.paid => _Welcome(started: started),
              PayPhase.waiting => _Waiting(checkout: state.checkout),
              PayPhase.pay => _Pay(state: state, started: started),
            };
            return AnimatedSwitcher(
              duration: context.calm ? Duration.zero : const Duration(milliseconds: 360),
              child: KeyedSubtree(key: ValueKey(state.phase), child: body),
            );
          },
        ),
      ),
    );
  }
}

/// The invoice waiting to be paid, and paying it.
class _Pay extends StatelessWidget {
  const _Pay({required this.state, this.started});

  final PayState state;
  final StartedCourse? started;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final invoice = state.invoice!;
    final s = started;
    final checkout = state.checkout;
    final noGateway = state.payWith != null && state.payWith!.isEmpty;
    return RefreshIndicator.adaptive(
      onRefresh: context.read<PayCubit>().load,
      child: ListView(
        key: const Key('pay-view'),
        padding: pagePadding.copyWith(top: Space.m, bottom: Space.xxl),
        physics: const AlwaysScrollableScrollPhysics(),
        children: staggered(context, [
          _Hero(invoice: invoice, started: s),
          // The offer on the invoice is said by the coupon's own line; what else became of it (ended, the sibling discount) is said here.
          if (s?.offerMessage case final message? when !(s!.offerState == 'applied' && invoice.couponPersonal)) ...[
            const Gap(Space.m),
            Note(
              message,
              key: const Key('pay-offer'),
              tone: s.offerState == 'applied' ? Tone.brand : Tone.attention,
              icon: Icons.local_offer_outlined,
            ),
          ],
          if (state.retry) ...[
            const Gap(Space.m),
            Note(
              [?checkout!.text, ?checkout.hint].join(' '),
              key: const Key('pay-retry'),
              tone: Tone.danger,
              icon: Icons.error_outline_rounded,
            ),
          ] else if (checkout?.status == CheckoutStatus.open && checkout?.text != null) ...[
            const Gap(Space.m),
            Note([?checkout!.text, ?checkout.hint].join(' '), key: const Key('pay-open'), icon: Icons.hourglass_top_rounded),
          ],
          const Gap(Space.l),
          _Lines(invoice: invoice),
          const Gap(Space.m),
          _Coupon(state: state),
          const Gap(Space.l),
          if (noGateway) ...[
            Note(l.payNoGateway, key: const Key('pay-no-gateway'), icon: Icons.account_balance_outlined),
            const Gap(Space.m),
            SizedBox(
              height: 54,
              child: PrimaryAction(l.payTransferInstead, icon: Icons.upload_rounded, onPressed: () => context.go('/family/money')),
            ),
          ] else ...[
            if (state.payProblem != null) ...[
              Note(
                context.problemText(state.payProblem!),
                key: const Key('pay-problem'),
                tone: Tone.danger,
                icon: Icons.error_outline_rounded,
              ),
              const Gap(Space.m),
            ],
            SizedBox(
              height: 56,
              child: PrimaryAction(
                state.retry ? l.payTryAgain : l.payAmount(invoice.outstanding.text),
                key: const Key('pay-now'),
                icon: Icons.lock_outline_rounded,
                busy: state.paying,
                onPressed: state.couponBusy ? null : () => _pay(context),
              ),
            ),
            const Gap(Space.s),
            Text(l.payNote, textAlign: TextAlign.center, style: context.type.caption),
            if (state.canSendProof) ...[
              const Gap(Space.s),
              TextButton.icon(
                onPressed: () => context.go('/family/money'),
                icon: const Icon(Icons.upload_rounded, size: 18),
                label: Text(l.payTransferInstead),
              ),
            ],
          ],
          if (s?.renews case final renews?) ...[const Gap(Space.l), _Line(icon: Icons.autorenew_rounded, text: renews)],
          if (invoice.documentUrl case final url?) ...[
            const Gap(Space.s),
            Align(
              child: TextButton.icon(
                key: const Key('pay-document'),
                onPressed: () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
                icon: const Icon(Icons.description_outlined, size: 18),
                label: Text(l.payReadInvoice),
              ),
            ),
          ],
          if (context.read<PayCubit>().enrollmentId != null) ...[const Gap(Space.l), _CancelCourse(state: state)],
        ]),
      ),
    );
  }

  Future<void> _pay(BuildContext context) async {
    final l = context.l10n;
    final cubit = context.read<PayCubit>();
    final gateways = state.payWith ?? const [];
    String? gateway = gateways.firstOrNull?.id;
    if (gateways.length > 1) {
      gateway = await showActionSheet<String>(
        context,
        title: l.pickGateway,
        builder: (c) => Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final g in gateways)
              ListTile(leading: const Icon(Icons.credit_card_rounded), title: Text(g.label), onTap: () => Navigator.pop(c, g.id)),
          ],
        ),
      );
      if (gateway == null) return;
    }
    final url = await cubit.checkout(accountId: gateway);
    if (url == null) return;
    final opened = await launchUrl(url, mode: LaunchMode.externalApplication);
    if (!opened) cubit.notOpened();
  }
}

/// What is owed, the one surface in the academy's colour: the amount, the invoice, and when to pay by.
class _Hero extends StatelessWidget {
  const _Hero({required this.invoice, this.started});

  final PayerInvoice invoice;
  final StartedCourse? started;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    const ink = Colors.white;
    final s = started;
    return Container(
      key: const Key('pay-hero'),
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
          if (s != null) ...[
            Row(
              children: [
                const Icon(Icons.check_circle_rounded, color: ink, size: 20),
                const Gap(Space.s),
                Expanded(
                  child: Text(
                    l.payCreatedTitle,
                    style: const TextStyle(color: ink, fontWeight: FontWeight.w800, fontSize: 16),
                  ),
                ),
              ],
            ),
            const Gap(Space.xs),
            Text(
              l.payCreatedLead(s.subject, givenName(s.learner)),
              style: TextStyle(color: ink.withValues(alpha: 0.9), fontSize: 14, height: 1.5),
            ),
            const Gap(Space.m),
          ],
          Text(
            l.payInvoiceNumber(invoice.number),
            style: TextStyle(color: ink.withValues(alpha: 0.85), fontWeight: FontWeight.w700, fontSize: 13),
          ),
          const Gap(Space.xs),
          Text(
            invoice.outstanding.text,
            key: const Key('pay-amount'),
            style: context.type.number.copyWith(color: ink, fontSize: 34),
          ),
          // An invoice opened on its own (a renewal, extra lessons): by when to pay it.
          if (s == null && invoice.dueAt != null) ...[
            const Gap(Space.xs),
            Text(
              l.payDueOn('${invoice.dueAt!.day(context.locale)} · ${invoice.dueAt!.clock(context.locale)}'),
              key: const Key('pay-due'),
              style: TextStyle(color: ink.withValues(alpha: 0.9), fontSize: 14),
            ),
          ],
          if (s?.payText case final text?) ...[
            const Gap(Space.m),
            Container(
              key: const Key('pay-by'),
              padding: const EdgeInsets.all(Space.m),
              decoration: BoxDecoration(color: ink.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(context.shape.field)),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.schedule_rounded, color: ink, size: 18),
                  const Gap(Space.s),
                  Expanded(
                    child: Text(text, style: const TextStyle(color: ink, fontSize: 13.5, height: 1.55)),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// The invoice line by line: the plan's block, its discounts (negative), what it comes to before VAT, the VAT and the total.
class _Lines extends StatelessWidget {
  const _Lines({required this.invoice});

  final PayerInvoice invoice;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final i = invoice;
    return LmsCard(
      key: const Key('pay-lines'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final line in i.lines)
            MoneyRow(
              label: line.quantity > 1 && !line.isDiscount ? '${line.description} × ${line.quantity}' : line.description,
              amount: line.amount.text,
              discount: line.isDiscount,
            ),
          Divider(height: Space.l, color: c.line),
          if (i.hasVat && !i.vatIncluded) ...[
            MoneyRow(label: l.paySubtotal, amount: i.subtotal.text, muted: true),
            MoneyRow(label: l.payVat(i.vatRateText), amount: i.vat!.text, muted: true),
          ],
          MoneyRow(label: l.payTotal, amount: i.total.text, total: true),
          if (i.hasVat && i.vatIncluded) MoneyRow(label: l.payVatIncluded(i.vatRateText), amount: i.vat!.text, muted: true),
          if ((i.paid?.minor ?? 0) > 0) ...[
            MoneyRow(label: l.payPaidSoFar, amount: i.paid!.text, muted: true),
            MoneyRow(label: l.payLeft, amount: i.outstanding.text, total: true),
          ],
        ],
      ),
    );
  }
}

/// A coupon: typed and applied (the invoice comes again with it), taken off,
/// or why not now in the API's words. The trial's offer stays on.
class _Coupon extends StatefulWidget {
  const _Coupon({required this.state});

  final PayState state;

  @override
  State<_Coupon> createState() => _CouponState();
}

class _CouponState extends State<_Coupon> {
  final _code = TextEditingController();

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _apply() async {
    final ok = await context.read<PayCubit>().applyCoupon(_code.text);
    if (ok && mounted) {
      _code.clear();
      FocusScope.of(context).unfocus();
      DoneMoment.show(context, title: context.l10n.payCouponOn(widget.state.invoice?.couponCode ?? ''));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final s = widget.state;
    final i = s.invoice!;
    final code = i.couponCode;
    final problem = s.couponProblem;
    final Widget body;
    if (code != null) {
      body = Row(
        children: [
          Icon(i.couponPersonal ? Icons.local_offer_rounded : Icons.confirmation_number_outlined, color: c.brandDeep, size: 20),
          const Gap(Space.s),
          Expanded(
            child: Text(
              i.couponPersonal ? l.payOfferStays : l.payCouponOn(code),
              key: const Key('pay-coupon-on'),
              style: context.type.body.copyWith(color: c.brandDeep, fontWeight: FontWeight.w600),
            ),
          ),
          if (!i.couponPersonal && i.couponOpen)
            TextButton(
              key: const Key('pay-coupon-remove'),
              onPressed: s.couponBusy ? null : context.read<PayCubit>().removeCoupon,
              child: Text(l.payCouponRemove),
            ),
        ],
      );
    } else if (i.couponOpen) {
      body = Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.payCouponTitle, style: context.type.label),
          const Gap(Space.s),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: TextField(
                  key: const Key('pay-coupon-field'),
                  controller: _code,
                  textCapitalization: TextCapitalization.characters,
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => _apply(),
                  decoration: InputDecoration(labelText: l.payCouponField, isDense: true),
                ),
              ),
              const Gap(Space.s),
              SizedBox(
                height: 48,
                child: FilledButton.tonal(
                  key: const Key('pay-coupon-apply'),
                  onPressed: s.couponBusy ? null : _apply,
                  child: s.couponBusy
                      ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(l.payCouponApply),
                ),
              ),
            ],
          ),
        ],
      );
    } else if (i.couponWhyNot != null) {
      body = Text(i.couponWhyNot!, key: const Key('pay-coupon-why'), style: context.type.caption);
    } else {
      return const SizedBox.shrink();
    }
    return Container(
      padding: const EdgeInsets.all(Space.m + 2),
      decoration: BoxDecoration(
        color: code != null ? c.brandSoft : c.card,
        borderRadius: BorderRadius.circular(context.shape.card),
        border: Border.all(color: code != null ? c.brandSoft : c.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          body,
          if (problem != null) ...[
            const Gap(Space.s),
            Semantics(
              liveRegion: true,
              child: Text(
                context.problemText(problem),
                key: const Key('pay-coupon-problem'),
                style: context.type.caption.copyWith(color: c.dangerInk),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Calling off the course before paying, asked first.
class _CancelCourse extends StatelessWidget {
  const _CancelCourse({required this.state});

  final PayState state;

  Future<void> _cancel(BuildContext context) async {
    final l = context.l10n;
    final sure = await confirmDestructive(
      context,
      title: l.startCancelTitle,
      body: l.startCancelBody,
      confirm: l.startCancelYes,
      keep: l.startCancelKeep,
    );
    if (sure && context.mounted) await context.read<PayCubit>().cancelCourse();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (state.cancelProblem != null) ...[
          Note(context.problemText(state.cancelProblem!), key: const Key('pay-cancel-problem'), tone: Tone.danger),
          const Gap(Space.s),
        ],
        Align(
          child: TextButton.icon(
            key: const Key('pay-cancel'),
            style: TextButton.styleFrom(foregroundColor: c.dangerInk),
            onPressed: state.cancelling ? null : () => _cancel(context),
            icon: state.cancelling
                ? const SizedBox.square(dimension: 16, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.event_busy_outlined, size: 18),
            label: Text(context.l10n.payCancelCourse),
          ),
        ),
      ],
    );
  }
}

/// The gateway is still confirming: a calm wait that follows the payments signal, and a way to look now.
class _Waiting extends StatelessWidget {
  const _Waiting({this.checkout});

  final Checkout? checkout;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final loading = context.select<PayCubit, bool>((x) => x.state.loading);
    return Center(
      key: const Key('pay-waiting'),
      child: Padding(
        padding: const EdgeInsets.all(Space.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(color: c.infoSoft, shape: BoxShape.circle),
              child: Icon(Icons.hourglass_top_rounded, size: 40, color: c.infoInk),
            ).pulse(context, to: 1.06),
            const Gap(Space.l),
            Semantics(
              liveRegion: true,
              child: Text(checkout?.text ?? l.payWaitingTitle, textAlign: TextAlign.center, style: context.type.title),
            ),
            if (checkout?.hint case final hint?) ...[
              const Gap(Space.s),
              Text(
                hint,
                textAlign: TextAlign.center,
                style: context.type.body.copyWith(color: c.muted, height: 1.6),
              ),
            ],
            const Gap(Space.xl),
            OutlinedButton.icon(
              key: const Key('pay-check'),
              onPressed: loading ? null : context.read<PayCubit>().load,
              icon: loading
                  ? const SizedBox.square(dimension: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.refresh_rounded),
              label: Text(l.payCheckNow),
            ),
          ],
        ),
      ),
    );
  }
}

/// Paid: the welcome to the course: the teacher, the weekly times, the first lesson and how to join.
class _Welcome extends StatelessWidget {
  const _Welcome({this.started});

  final StartedCourse? started;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final s = started;
    return ListView(
      key: const Key('pay-paid'),
      padding: pagePadding.copyWith(top: Space.xl, bottom: Space.xxl),
      children: staggered(context, [
        Center(
          child: Container(
            width: 92,
            height: 92,
            decoration: BoxDecoration(color: c.brandSoft, shape: BoxShape.circle),
            child: Icon(Icons.celebration_rounded, size: 46, color: c.brandDeep),
          ).bob(context),
        ),
        const Gap(Space.l),
        Text(l.paidTitle, textAlign: TextAlign.center, style: context.type.display),
        const Gap(Space.s),
        Text(
          s == null ? l.paidLeadPlain : l.paidLead(givenName(s.learner), s.subject),
          textAlign: TextAlign.center,
          style: context.type.body.copyWith(color: c.muted, height: 1.6),
        ),
        if (s != null) ...[const Gap(Space.xl), _CourseCard(started: s)],
        const Gap(Space.xl),
        SizedBox(
          height: 54,
          child: PrimaryAction(
            l.startOpenLessons,
            key: const Key('paid-lessons'),
            icon: Icons.calendar_month_rounded,
            onPressed: () => context.go('/family/lessons'),
          ),
        ),
      ]),
    );
  }
}

/// An invoice paid (just now, or opened again once paid): said plainly, its printable copy, and the lessons.
class _Settled extends StatelessWidget {
  const _Settled({required this.invoice, required this.fresh});

  final PayerInvoice invoice;

  /// Paid while this screen was open: thanks rather than a plain "paid".
  final bool fresh;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final url = invoice.documentUrl;
    return ListView(
      key: const Key('pay-settled'),
      padding: pagePadding.copyWith(top: Space.xl, bottom: Space.xxl),
      children: staggered(context, [
        Center(
          child: Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(color: c.brandSoft, shape: BoxShape.circle),
            child: Icon(fresh ? Icons.celebration_rounded : Icons.check_rounded, size: 44, color: c.brandDeep),
          ),
        ),
        const Gap(Space.l),
        Text(fresh ? l.paidJustTitle : l.paidPlainTitle, textAlign: TextAlign.center, style: context.type.display),
        const Gap(Space.s),
        Text(
          '${l.payInvoiceNumber(invoice.number)} · ${invoice.total.text}',
          textAlign: TextAlign.center,
          style: context.type.cardTitle.copyWith(color: c.muted),
        ),
        const Gap(Space.s),
        Text(
          fresh ? l.paidLeadPlain : l.paidPlainLead,
          textAlign: TextAlign.center,
          style: context.type.body.copyWith(color: c.muted, height: 1.6),
        ),
        const Gap(Space.l),
        _Lines(invoice: invoice),
        const Gap(Space.xl),
        if (url != null) ...[
          SizedBox(
            height: 54,
            child: PrimaryAction(
              l.payReadInvoice,
              key: const Key('pay-document'),
              icon: Icons.description_outlined,
              onPressed: () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
            ),
          ),
          const Gap(Space.s),
        ],
        SecondaryAction(l.startOpenLessons, icon: Icons.calendar_month_rounded, onPressed: () => context.go('/family/lessons')),
      ]),
    );
  }
}

/// The course at a glance: its teacher, weekly times and first lesson, and how to join.
class _CourseCard extends StatelessWidget {
  const _CourseCard({required this.started});

  final StartedCourse started;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = started;
    return LmsCard(
      key: const Key('paid-course'),
      padding: const EdgeInsets.symmetric(horizontal: Space.l, vertical: Space.s),
      child: Column(
        children: [
          if (s.teacher.isNotEmpty) SummaryRow(label: l.paidTeacher, value: s.teacher, icon: Icons.school_outlined),
          if (s.weeklyTimes.isNotEmpty) SummaryRow(label: l.paidTimes, value: s.weeklyTimes.join('\n'), icon: Icons.event_repeat_rounded),
          SummaryRow(label: l.paidFirst, value: s.firstLesson?.label ?? l.paidFirstLater, icon: Icons.flag_outlined),
          if (s.skipped.isNotEmpty)
            SummaryRow(
              key: const Key('paid-skips'),
              label: l.startSkippedTitle,
              value: s.skipped.map((w) => w.text).join('\n'),
              detail: l.startSkippedNote,
              icon: Icons.event_busy_rounded,
            ),
          SummaryRow(label: l.paidJoin, value: l.paidJoinHow, icon: Icons.videocam_outlined),
        ],
      ),
    );
  }
}

/// The course joined the learner's shared package: nothing to pay, and where it stands.
class _Package extends StatelessWidget {
  const _Package({required this.started});

  final StartedCourse started;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = started;
    return ListView(
      key: const Key('pay-package'),
      padding: pagePadding.copyWith(top: Space.xl, bottom: Space.xxl),
      children: staggered(context, [
        const Align(
          alignment: AlignmentDirectional.centerStart,
          child: IconBadge(Icons.inventory_2_outlined, size: 64, tone: Tone.info),
        ),
        const Gap(Space.l),
        Text(l.packageTitle, style: context.type.display),
        if (s.payText case final text?) ...[const Gap(Space.s), Text(text, style: context.type.body.copyWith(height: 1.6))],
        if (s.offerMessage case final m?) ...[const Gap(Space.m), Note(m)],
        const Gap(Space.xl),
        _CourseCard(started: s),
        const Gap(Space.xl),
        SizedBox(
          height: 54,
          child: PrimaryAction(l.startOpenLessons, icon: Icons.calendar_month_rounded, onPressed: () => context.go('/family/lessons')),
        ),
      ]),
    );
  }
}

/// Called off: the API's sentence, and starting again.
class _Cancelled extends StatelessWidget {
  const _Cancelled({required this.message, this.started});

  final String message;
  final StartedCourse? started;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return EmptyState(
      key: const Key('pay-cancelled'),
      icon: Icons.event_busy_outlined,
      title: message,
      action: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: 260,
            child: PrimaryAction(
              l.startBlockedOver,
              key: const Key('pay-start-over'),
              icon: Icons.refresh_rounded,
              onPressed: () => context.pushReplacement(StartPage.location()),
            ),
          ),
          const Gap(Space.s),
          TextButton(onPressed: () => context.go('/family/home'), child: Text(l.close)),
        ],
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Icon(icon, size: 16, color: context.colors.muted),
      ),
      const Gap(Space.s),
      Expanded(child: Text(text, style: context.type.caption)),
    ],
  );
}
