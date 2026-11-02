import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/api_problem.dart';
import '../../../billing/data/billing_repository.dart';
import '../../data/start_repository.dart';
import '../../domain/start.dart';

/// Where paying an invoice stands, as the screen shows it.
enum PayPhase { loading, failed, pay, waiting, paid, cancelled, nothing }

class PayState extends Equatable {
  const PayState({
    this.invoice,
    this.checkout,
    this.loading = true,
    this.problem,
    this.payWith,
    this.canSendProof = false,
    this.couponBusy = false,
    this.couponProblem,
    this.couponMessage,
    this.paying = false,
    this.payProblem,
    this.awaiting = false,
    this.justPaid = false,
    this.cancelling = false,
    this.cancelled,
    this.cancelProblem,
  });

  final PayerInvoice? invoice;
  final Checkout? checkout;
  final bool loading;
  final ApiProblem? problem;

  /// The academy's gateways that take it (null until read: the first is used then).
  final List<({String id, String label})>? payWith;

  /// It may be paid by hand, with the receipt sent from Payments.
  final bool canSendProof;
  final bool couponBusy;
  final ApiProblem? couponProblem;
  final String? couponMessage;
  final bool paying;
  final ApiProblem? payProblem;

  /// The gateway's page was opened from here: the next answer is what came of it.
  final bool awaiting;

  /// Paid while this screen was open (the celebration plays once).
  final bool justPaid;
  final bool cancelling;

  /// The course was called off: the API's sentence.
  final String? cancelled;
  final ApiProblem? cancelProblem;

  PayPhase get phase {
    if (cancelled != null) return PayPhase.cancelled;
    final i = invoice;
    if (i == null) return loading ? PayPhase.loading : (problem != null ? PayPhase.failed : PayPhase.nothing);
    if (i.isVoid) return PayPhase.nothing;
    if (i.isPaid) return PayPhase.paid;
    if (checkout?.status == CheckoutStatus.open && awaiting) return PayPhase.waiting;
    if (checkout?.status == CheckoutStatus.paid) return PayPhase.waiting;
    return PayPhase.pay;
  }

  /// The last online payment did not go through: said, with the way to try again.
  bool get retry => checkout != null && (checkout!.status == CheckoutStatus.failed || checkout!.status == CheckoutStatus.expired);

  PayState copyWith({
    PayerInvoice? Function()? invoice,
    Checkout? Function()? checkout,
    bool? loading,
    ApiProblem? Function()? problem,
    List<({String id, String label})>? Function()? payWith,
    bool? canSendProof,
    bool? couponBusy,
    ApiProblem? Function()? couponProblem,
    String? Function()? couponMessage,
    bool? paying,
    ApiProblem? Function()? payProblem,
    bool? awaiting,
    bool? justPaid,
    bool? cancelling,
    String? Function()? cancelled,
    ApiProblem? Function()? cancelProblem,
  }) => PayState(
    invoice: invoice == null ? this.invoice : invoice(),
    checkout: checkout == null ? this.checkout : checkout(),
    loading: loading ?? this.loading,
    problem: problem == null ? this.problem : problem(),
    payWith: payWith == null ? this.payWith : payWith(),
    canSendProof: canSendProof ?? this.canSendProof,
    couponBusy: couponBusy ?? this.couponBusy,
    couponProblem: couponProblem == null ? this.couponProblem : couponProblem(),
    couponMessage: couponMessage == null ? this.couponMessage : couponMessage(),
    paying: paying ?? this.paying,
    payProblem: payProblem == null ? this.payProblem : payProblem(),
    awaiting: awaiting ?? this.awaiting,
    justPaid: justPaid ?? this.justPaid,
    cancelling: cancelling ?? this.cancelling,
    cancelled: cancelled == null ? this.cancelled : cancelled(),
    cancelProblem: cancelProblem == null ? this.cancelProblem : cancelProblem(),
  );

  @override
  List<Object?> get props => [
    invoice,
    checkout,
    loading,
    problem,
    payWith,
    canSendProof,
    couponBusy,
    couponProblem,
    couponMessage,
    paying,
    payProblem,
    awaiting,
    justPaid,
    cancelling,
    cancelled,
    cancelProblem,
  ];
}

/// Paying a course's first invoice (J3): the invoice as its payer reads it,
/// a coupon put on or taken off (each issues it again, so its id changes),
/// the academy's gateway in the browser coming back to the app, and what
/// came of it, read again when the app comes back to the front and on the
/// `payments` signal: paid, still being confirmed (a calm wait), or failed.
/// A course the family started may be called off here before paying.
class PayCubit extends Cubit<PayState> {
  PayCubit(this._repo, this._billing, this._invoiceId, {PayerInvoice? invoice, this.enrollmentId, Stream<void>? refreshOn})
    : super(PayState(invoice: invoice, loading: true)) {
    _live = refreshOn?.listen((_) => load());
  }

  final StartRepository _repo;
  final BillingRepository _billing;

  /// The course started with it, when the family started it here.
  final String? enrollmentId;
  String _invoiceId;
  String? _checkoutId;
  StreamSubscription<void>? _live;

  String get invoiceId => _invoiceId;

  @override
  Future<void> close() async {
    await _live?.cancel();
    return super.close();
  }

  Future<void> load() async {
    if (_invoiceId == 'none' || state.cancelled != null) {
      emit(state.copyWith(loading: false));
      return;
    }
    final wasPaid = state.invoice?.isPaid ?? false;
    final gateways = state.payWith == null ? _gateways() : null;
    emit(state.copyWith(loading: true));
    try {
      final view = await _repo.invoice(_invoiceId, checkoutId: _checkoutId);
      if (isClosed) return;
      final paidNow = view.invoice.isPaid && !wasPaid && (state.invoice != null || state.awaiting);
      emit(
        state.copyWith(
          invoice: () => view.invoice,
          checkout: () => view.checkout,
          loading: false,
          problem: () => null,
          justPaid: paidNow ? true : null,
          // Back from the gateway with an answer: the wait is over.
          awaiting: view.checkout?.status == CheckoutStatus.open ? null : false,
        ),
      );
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(loading: false, problem: () => p));
    }
    await gateways;
  }

  /// The gateways that take it, from the family's invoices (none: it is paid by hand).
  Future<void> _gateways() async {
    try {
      final list = await _billing.invoices();
      final mine = list.list.where((i) => i.id == _invoiceId).firstOrNull;
      if (!isClosed && mine != null) emit(state.copyWith(payWith: () => mine.payWith, canSendProof: mine.canSendProof));
    } on ApiProblem {
      // The first gateway is used; the API says when none takes it.
    }
  }

  Future<bool> applyCoupon(String code) async {
    final typed = code.trim();
    if (typed.isEmpty || state.couponBusy) return false;
    emit(state.copyWith(couponBusy: true, couponProblem: () => null, couponMessage: () => null));
    try {
      final next = await _repo.applyCoupon(_invoiceId, typed);
      if (isClosed) return false;
      _invoiceId = next.id;
      emit(state.copyWith(couponBusy: false, invoice: () => next, checkout: () => null, payWith: () => null));
      unawaited(_gateways());
      return true;
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(couponBusy: false, couponProblem: () => p));
      return false;
    }
  }

  Future<void> removeCoupon() async {
    if (state.couponBusy) return;
    emit(state.copyWith(couponBusy: true, couponProblem: () => null));
    try {
      final next = await _repo.removeCoupon(_invoiceId);
      if (isClosed) return;
      _invoiceId = next.id;
      emit(state.copyWith(couponBusy: false, invoice: () => next, checkout: () => null, payWith: () => null));
      unawaited(_gateways());
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(couponBusy: false, couponProblem: () => p));
    }
  }

  /// Opens a checkout at [accountId] (else the academy's first); the page to open in the browser.
  Future<Uri?> checkout({String? accountId}) async {
    if (state.paying) return null;
    emit(state.copyWith(paying: true, payProblem: () => null));
    try {
      final made = await _repo.checkout(_invoiceId, accountId: accountId);
      if (isClosed) return null;
      _checkoutId = made.id;
      emit(
        state.copyWith(
          paying: false,
          awaiting: true,
          checkout: () => Checkout(id: made.id, status: CheckoutStatus.open),
        ),
      );
      return made.url;
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(paying: false, payProblem: () => p));
      return null;
    }
  }

  /// The browser could not be opened: nothing to wait for.
  void notOpened() => emit(state.copyWith(awaiting: false));

  void celebrated() => emit(state.copyWith(justPaid: false));

  Future<void> cancelCourse() async {
    final id = enrollmentId;
    if (id == null || state.cancelling) return;
    emit(state.copyWith(cancelling: true, cancelProblem: () => null));
    try {
      final message = await _repo.cancel(id);
      if (!isClosed) emit(state.copyWith(cancelling: false, cancelled: () => message));
    } on ApiProblem catch (p) {
      if (!isClosed) emit(state.copyWith(cancelling: false, cancelProblem: () => p));
    }
  }
}
