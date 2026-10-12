import '../../../core/time/local_time.dart';

/// What is left of a learner's balance for one subject (or a shared
/// package's subjects together), and extra lessons the person can buy on it
/// when they pay for it.
class Balance {
  const Balance({
    required this.subject,
    required this.sessions,
    required this.minutes,
    this.enrollmentId = '',
    this.extra,
    this.shared = false,
  });

  factory Balance.fromJson(Map<String, dynamic> json) => Balance(
    enrollmentId: json['enrollment_id'] as String? ?? '',
    subject: json['subject'] as String? ?? '',
    sessions: (json['sessions'] as num?)?.toInt() ?? 0,
    minutes: (json['minutes'] as num?)?.toInt() ?? 0,
    extra: json['extra'] == null ? null : ExtraOffer.fromJson(json['extra'] as Map<String, dynamic>),
    shared: json['shared'] as bool? ?? false,
  );

  final String enrollmentId;

  /// A shared package ("12 lessons in any subject"): one balance for all its subjects.
  final bool shared;
  final String subject;
  final int sessions;
  final int minutes;
  final ExtraOffer? extra;
}

/// Extra lessons on a course: a lesson's price and length, at most how many,
/// and the invoice for them waiting to be paid (one at a time).
class ExtraOffer {
  const ExtraOffer({required this.price, required this.minutes, required this.max, this.waitingNumber});

  factory ExtraOffer.fromJson(Map<String, dynamic> json) => ExtraOffer(
    price: json['price'] as String? ?? '',
    minutes: (json['minutes'] as num?)?.toInt() ?? 0,
    max: (json['max'] as num?)?.toInt() ?? 1,
    waitingNumber: (json['waiting_invoice'] as Map<String, dynamic>?)?['number'] as String?,
  );

  final String price;
  final int minutes;
  final int max;
  final String? waitingNumber;
}

/// A line of an invoice; a discount (the family discount, a coupon) is negative.
class InvoiceLine {
  const InvoiceLine({required this.kind, required this.description, required this.amount});

  factory InvoiceLine.fromJson(Map<String, dynamic> json) => InvoiceLine(
    kind: json['kind'] as String? ?? '',
    description: json['description'] as String? ?? '',
    amount: json['amount'] as String? ?? '',
  );

  final String kind;
  final String description;
  final String amount;

  bool get isDiscount => kind == 'discount';
}

/// The latest proof of paying an invoice by hand: waiting for the academy,
/// approved, or turned down with the academy's reason.
class ProofState {
  const ProofState({required this.status, required this.text, this.reason});

  factory ProofState.fromJson(Map<String, dynamic> json) =>
      ProofState(status: json['status'] as String? ?? '', text: json['text'] as String? ?? '', reason: json['reason'] as String?);

  /// pending, approved, rejected
  final String status;

  /// Where it stands, in the API's words.
  final String text;
  final String? reason;
}

/// Paying by hand: the academy's own words on how, and whether a proof can be sent.
class ManualPayment {
  const ManualPayment({this.note, this.takesProofs = false, this.methods = const []});

  factory ManualPayment.fromJson(Map<String, dynamic>? json) => json == null
      ? const ManualPayment()
      : ManualPayment(
          note: json['note'] as String?,
          takesProofs: json['takes_proofs'] as bool? ?? false,
          methods: [
            for (final m in json['methods'] as List<dynamic>? ?? const [])
              (value: (m as Map<String, dynamic>)['value'] as String, label: m['label'] as String? ?? ''),
          ],
        );

  final String? note;
  final bool takesProofs;
  final List<({String value, String label})> methods;
}

/// An invoice the person pays, with the academy's gateways that take it.
class Invoice {
  const Invoice({
    required this.id,
    required this.number,
    required this.forWhom,
    required this.total,
    required this.outstanding,
    required this.outstandingMinor,
    required this.status,
    required this.statusLabel,
    required this.payWith,
    this.issuedAt,
    this.dueAt,
    this.documentUrl,
    this.outstandingDecimal = '',
    this.proof,
    this.canSendProof = false,
    this.lines = const [],
    this.family = false,
    this.transferDetails,
  });

  factory Invoice.fromJson(Map<String, dynamic> json) => Invoice(
    id: json['id'] as String,
    number: json['number'] as String? ?? '',
    forWhom: json['for'] as String? ?? '',
    total: json['total'] as String? ?? '',
    outstanding: json['outstanding'] as String? ?? '',
    outstandingMinor: (json['outstanding_minor'] as num?)?.toInt() ?? 0,
    status: json['status'] as String? ?? '',
    statusLabel: json['status_label'] as String? ?? '',
    issuedAt: LocalTime.fromJson(json['issued_at']),
    dueAt: LocalTime.fromJson(json['due_at']),
    documentUrl: json['document_url'] as String?,
    outstandingDecimal: json['outstanding_decimal'] as String? ?? '',
    proof: json['proof'] == null ? null : ProofState.fromJson(json['proof'] as Map<String, dynamic>),
    canSendProof: json['can_send_proof'] as bool? ?? false,
    family: json['family'] as bool? ?? false,
    transferDetails: _text(json['transfer_details']),
    lines: [for (final l in json['lines'] as List<dynamic>? ?? const []) InvoiceLine.fromJson(l as Map<String, dynamic>)],
    payWith: [
      for (final g in json['pay_with'] as List<dynamic>? ?? const [])
        (id: (g as Map<String, dynamic>)['account_id'] as String, label: g['label'] as String? ?? ''),
    ],
  );

  final String id;
  final String number;
  final String forWhom;
  final String total;
  final String outstanding;
  final int outstandingMinor;

  /// issued, partly_paid, paid, overdue, void, …
  final String status;
  final String statusLabel;
  final LocalTime? issuedAt;
  final LocalTime? dueAt;
  final String? documentUrl;
  final List<({String id, String label})> payWith;

  /// What is left as a number to type ("400.00").
  final String outstandingDecimal;
  final ProofState? proof;
  final bool canSendProof;

  /// What it is for and what came off it.
  final List<InvoiceLine> lines;

  /// One invoice for several children's renewals that fell due in the same week.
  final bool family;

  /// Where to send the money by hand for this invoice, in the academy's words
  /// for the payer's region (several lines): only while something is left to pay.
  final String? transferDetails;

  bool get payable => outstandingMinor > 0 && status != 'void';

  /// The transfer details to show: none once nothing is left to pay.
  String? get transferText => payable ? transferDetails : null;
}

/// A text from the API with its blank edges gone; none when nothing is left.
String? _text(Object? value) {
  final text = value is String ? value.trim() : '';
  return text.isEmpty ? null : text;
}

class Invoices {
  const Invoices({required this.list, this.owed, this.manual = const ManualPayment()});

  factory Invoices.fromJson(Map<String, dynamic> json) => Invoices(
    owed: json['owed'] as String?,
    manual: ManualPayment.fromJson(json['manual'] as Map<String, dynamic>?),
    list: [for (final i in json['data'] as List<dynamic>) Invoice.fromJson(i as Map<String, dynamic>)],
  );

  /// The total still to pay, formatted ("40 US$"); null when nothing is.
  final String? owed;
  final List<Invoice> list;
  final ManualPayment manual;

  List<Invoice> get payable => list.where((i) => i.payable).toList();
}
