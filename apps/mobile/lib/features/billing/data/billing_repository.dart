import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../domain/billing.dart';

/// A receipt picked on the phone: a photo, a screenshot or the bank's PDF.
class Receipt {
  const Receipt({required this.bytes, required this.name, required this.mime});

  final Uint8List bytes;
  final String name;
  final String mime;
}

/// The family's balance and invoices. Paying opens the academy's own
/// gateway page; the invoice counts as paid only when the gateway says so.
/// Paid by hand, the payer sends the receipt and the academy confirms it.
class BillingRepository {
  BillingRepository(this._api);

  final ApiClient _api;

  Future<List<Balance>> balance({String? learnerId}) async => [
    for (final b in (await _api.get('family/balance', query: {'learner_id': ?learnerId}))['data'] as List<dynamic>)
      Balance.fromJson(b as Map<String, dynamic>),
  ];

  Future<Invoices> invoices() async => Invoices.fromJson(await _api.get('family/invoices'));

  /// The gateway page to pay on.
  Future<Uri> checkout(String invoiceId, {String? accountId}) async {
    final json = await _api.post('family/invoices/$invoiceId/checkout', idempotent: true, body: {'account_id': ?accountId});
    return Uri.parse(json['url'] as String);
  }

  /// Sends proof of paying by hand (multipart, with the receipt); it waits for the academy.
  /// [paidOn] is the payer's own day, as picked ("2026-10-05").
  Future<ProofState> sendProof(
    String invoiceId, {
    required String method,
    required String amount,
    required String paidOn,
    required Receipt receipt,
    String? reference,
    String? note,
  }) async {
    final form = FormData.fromMap({
      'method': method,
      'amount': amount,
      'paid_on': paidOn,
      'reference': ?reference,
      'note': ?note,
      'file': MultipartFile.fromBytes(receipt.bytes, filename: receipt.name, contentType: DioMediaType.parse(receipt.mime)),
    });
    final json = await _api.post('family/invoices/$invoiceId/proofs', idempotent: true, body: form);
    return ProofState.fromJson(json['data'] as Map<String, dynamic>);
  }
}
