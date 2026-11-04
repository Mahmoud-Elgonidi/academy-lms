import 'package:equatable/equatable.dart';

import '../../../core/time/local_time.dart';

/// A card the academy's gateway keeps for the payer (docs/architecture/02,
/// section 27, J5b): its brand, last four and expiry only, never its number.
class SavedCard extends Equatable {
  const SavedCard({
    required this.id,
    required this.brand,
    required this.last4,
    required this.label,
    this.brandLabel = '',
    this.expMonth = 0,
    this.expYear = 0,
    this.account = '',
    this.expiresSoon = false,
    this.savedAt,
  });

  factory SavedCard.fromJson(Map<String, dynamic> json) => SavedCard(
    id: json['id'] as String? ?? '',
    brand: json['brand'] as String? ?? 'card',
    brandLabel: json['brand_label'] as String? ?? '',
    last4: json['last4'] as String? ?? '',
    expMonth: (json['exp_month'] as num?)?.toInt() ?? 0,
    expYear: (json['exp_year'] as num?)?.toInt() ?? 0,
    label: json['label'] as String? ?? '',
    account: json['account'] as String? ?? '',
    expiresSoon: json['expires_soon'] == true,
    savedAt: LocalTime.fromJson(json['saved_at']),
  );

  final String id;

  /// visa, mastercard, mada, amex, … or card.
  final String brand;
  final String brandLabel;
  final String last4;
  final int expMonth;
  final int expYear;

  /// "Visa ****4242", as the API writes it.
  final String label;

  /// The gateway account it is kept at, by the name payers see.
  final String account;

  /// It expires within a month, or has: a new one is needed.
  final bool expiresSoon;
  final LocalTime? savedAt;

  /// "12/34": the month and year as printed on the card.
  String get expiry =>
      expMonth <= 0 || expYear <= 0 ? '' : '${expMonth.toString().padLeft(2, '0')}/${(expYear % 100).toString().padLeft(2, '0')}';

  @override
  List<Object?> get props => [id, brand, last4, expMonth, expYear, label, account, expiresSoon, savedAt];
}

/// One of the payer's accounts (one per currency, usually one): whether its
/// renewals are charged automatically, and to which card.
class RenewalAccount extends Equatable {
  const RenewalAccount({required this.id, required this.currency, this.autoRenew = false, this.cardId});

  factory RenewalAccount.fromJson(Map<String, dynamic> json) => RenewalAccount(
    id: json['id'] as String? ?? '',
    currency: json['currency'] as String? ?? '',
    autoRenew: json['auto_renew'] == true,
    cardId: json['card_id'] as String?,
  );

  final String id;
  final String currency;
  final bool autoRenew;

  /// The card a renewal in this currency is charged to; null when none can be.
  final String? cardId;

  @override
  List<Object?> get props => [id, currency, autoRenew, cardId];
}

/// Where an automatic charge stands.
enum ChargeStatus {
  scheduled,

  /// A soft decline: tried once more.
  retry,

  /// Under way: it can no longer be stopped.
  charging;

  static ChargeStatus of(Object? v) => switch (v) {
    'retry' => retry,
    'charging' => charging,
    _ => scheduled,
  };
}

/// An automatic charge coming (or under way): which invoice, how much and when, as the API wrote it.
class ComingCharge extends Equatable {
  const ComingCharge({required this.invoiceId, required this.number, required this.amount, this.status = ChargeStatus.scheduled, this.at});

  factory ComingCharge.fromJson(Map<String, dynamic> json) => ComingCharge(
    invoiceId: json['invoice_id'] as String? ?? '',
    number: json['number'] as String? ?? '',
    amount: json['amount'] as String? ?? '',
    status: ChargeStatus.of(json['status']),
    at: LocalTime.fromJson(json['at']),
  );

  final String invoiceId;
  final String number;
  final String amount;
  final ChargeStatus status;
  final LocalTime? at;

  @override
  List<Object?> get props => [invoiceId, number, amount, status, at];
}

/// The words a payer ticks to save a card and renew automatically, and their version (sent back with the checkout).
class Mandate extends Equatable {
  const Mandate({required this.version, required this.text});

  static Mandate? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final version = json['version'] as String? ?? '';
    if (version.isEmpty) return null;
    return Mandate(version: version, text: (json['text'] as String? ?? '').trim());
  }

  final String version;
  final String text;

  @override
  List<Object?> get props => [version, text];
}

/// The payer's saved cards and automatic renewal (`GET family/cards`).
class SavedCards extends Equatable {
  const SavedCards({this.cards = const [], this.accounts = const [], this.autoRenew = false, this.coming = const [], this.mandate});

  factory SavedCards.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic> ? json['data'] as Map<String, dynamic> : json;
    return SavedCards(
      cards: [for (final c in data['cards'] as List<dynamic>? ?? const []) SavedCard.fromJson(c as Map<String, dynamic>)],
      accounts: [for (final a in data['accounts'] as List<dynamic>? ?? const []) RenewalAccount.fromJson(a as Map<String, dynamic>)],
      autoRenew: data['auto_renew'] == true,
      coming: [for (final c in data['coming'] as List<dynamic>? ?? const []) ComingCharge.fromJson(c as Map<String, dynamic>)],
      mandate: Mandate.fromJson(data['mandate']),
    );
  }

  final List<SavedCard> cards;
  final List<RenewalAccount> accounts;

  /// Whether any of the payer's renewals are charged automatically.
  final bool autoRenew;
  final List<ComingCharge> coming;
  final Mandate? mandate;

  bool get hasCard => cards.isNotEmpty;

  /// The accounts that may be switched on their own (more than one currency); one switch otherwise.
  bool get perAccount => accounts.length > 1;

  /// Some charge still to come, which turning it off would stop.
  bool get stopsSomething => coming.any((c) => c.status != ChargeStatus.charging);

  SavedCard? card(String? id) => id == null ? null : cards.where((c) => c.id == id).firstOrNull;

  @override
  List<Object?> get props => [cards, accounts, autoRenew, coming, mandate];
}

/// Automatic renewal on one course's balance (the classic look, its payer only).
class CourseAutoRenew extends Equatable {
  const CourseAutoRenew({required this.on, required this.text, this.card, this.next});

  static CourseAutoRenew? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final card = json['card'];
    final next = json['next_charge'];
    return CourseAutoRenew(
      on: json['on'] == true,
      text: (json['text'] as String? ?? '').trim(),
      card: card is Map<String, dynamic> ? SavedCard.fromJson(card) : null,
      next: next is Map<String, dynamic> ? ComingCharge.fromJson(next) : null,
    );
  }

  final bool on;
  final SavedCard? card;

  /// The waiting invoice's automatic charge: when, and how much.
  final ComingCharge? next;

  /// The API's sentence on where it stands.
  final String text;

  @override
  List<Object?> get props => [on, card, next, text];
}

/// Saving the card while paying an invoice (`GET family/invoices/{id}`: `save_card`): whether this payer's look
/// allows it, and the mandate to tick.
class SaveCardOffer extends Equatable {
  const SaveCardOffer({required this.offered, required this.mandate});

  static SaveCardOffer? fromJson(Object? json) {
    final mandate = Mandate.fromJson(json);
    if (json is! Map<String, dynamic> || mandate == null) return null;
    return SaveCardOffer(offered: json['offered'] == true, mandate: mandate);
  }

  final bool offered;
  final Mandate mandate;

  @override
  List<Object?> get props => [offered, mandate];
}
