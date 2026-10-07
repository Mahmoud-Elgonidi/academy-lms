import '../../../core/time/local_time.dart';

/// What is left of a learner's balance for one subject.
class Balance {
  const Balance({required this.subject, required this.sessions, required this.minutes});

  factory Balance.fromJson(Map<String, dynamic> json) => Balance(
    subject: json['subject'] as String? ?? '',
    sessions: (json['sessions'] as num?)?.toInt() ?? 0,
    minutes: (json['minutes'] as num?)?.toInt() ?? 0,
  );

  final String subject;
  final int sessions;
  final int minutes;
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

  bool get payable => outstandingMinor > 0 && status != 'void';
}

class Invoices {
  const Invoices({required this.list, this.owed});

  factory Invoices.fromJson(Map<String, dynamic> json) => Invoices(
    owed: json['owed'] as String?,
    list: [for (final i in json['data'] as List<dynamic>) Invoice.fromJson(i as Map<String, dynamic>)],
  );

  /// The total still to pay, formatted ("40 US$"); null when nothing is.
  final String? owed;
  final List<Invoice> list;

  List<Invoice> get payable => list.where((i) => i.payable).toList();
}
