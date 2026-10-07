import '../../../core/network/api_client.dart';
import '../domain/billing.dart';

/// The family's balance and invoices. Paying opens the academy's own
/// gateway page; the invoice counts as paid only when the gateway says so.
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
}
