import '../../../core/network/api_client.dart';
import '../domain/start.dart';

/// The family starts a course and pays on its own (docs/architecture/02,
/// section 27, J3): what may be started, a teacher's weekly times or the
/// open groups, starting it, calling it off before paying, and the invoice
/// as its payer reads it (its coupon, paying it on the academy's gateway).
/// The API checks each one again: the classic look only, the payer's own.
class StartRepository {
  StartRepository(this._api);

  final ApiClient _api;

  /// The learners, subjects, plans priced for the payer and, with [subjectId], the teachers and the trial's offer.
  Future<StartOptions> options({String? learnerId, String? subjectId}) async =>
      StartOptions.fromJson(await _api.get('family/start/options', query: {'learner_id': ?learnerId, 'subject_id': ?subjectId}));

  /// A teacher's weekly times on the viewer's clock, or (a group plan) the open groups.
  Future<StartSlots> slots({required String subjectId, required String planId, String? learnerId, String? teacherId}) async =>
      StartSlots.fromJson(
        await _api.get(
          'family/start/slots',
          query: {'learner_id': ?learnerId, 'subject_id': subjectId, 'plan_id': planId, 'teacher_id': ?teacherId},
        ),
      );

  /// Starts it at the price the family saw. Moves lessons and money: sent once, whatever the network does.
  Future<StartedCourse> start({
    required String subjectId,
    required String planId,
    required Amount price,
    String? learnerId,
    String? teacherId,
    List<String> times = const [],
    String? groupId,
  }) async => StartedCourse.fromJson(
    await _api.post(
      'family/courses',
      idempotent: true,
      body: {
        'learner_id': ?learnerId,
        'subject_id': subjectId,
        'plan_id': planId,
        'price_minor': price.minor,
        'currency': price.currency,
        'teacher_id': ?teacherId,
        if (times.isNotEmpty) 'times': times,
        'group_id': ?groupId,
      },
    ),
  );

  /// Calls off a course the family started, before paying; the API's sentence on what became of it.
  Future<String> cancel(String enrollmentId) async {
    final json = await _api.post('family/courses/$enrollmentId/cancel', idempotent: true);
    return (json['data'] as Map<String, dynamic>?)?['message'] as String? ?? '';
  }

  /// The invoice as its payer reads it, with the online payment [checkoutId] (else the latest).
  Future<InvoiceView> invoice(String invoiceId, {String? checkoutId}) async =>
      InvoiceView.fromJson(await _api.get('family/invoices/$invoiceId', query: {'checkout': ?checkoutId}));

  /// Puts a coupon on it: the invoice is issued again, so the answer is a new one.
  Future<PayerInvoice> applyCoupon(String invoiceId, String code) async {
    final json = await _api.post('family/invoices/$invoiceId/coupon', idempotent: true, body: {'code': code});
    return PayerInvoice.fromJson(json['data'] as Map<String, dynamic>);
  }

  /// Takes it off: again a new invoice.
  Future<PayerInvoice> removeCoupon(String invoiceId) async {
    final json = await _api.delete('family/invoices/$invoiceId/coupon', idempotent: true);
    return PayerInvoice.fromJson(json['data'] as Map<String, dynamic>);
  }

  /// The gateway's page, coming back through the portal to the app. With [mandateVersion] (the words the payer
  /// ticked), the gateway also keeps the card and renewals are charged to it (J5b).
  Future<({String id, Uri url})> checkout(String invoiceId, {String? accountId, String? mandateVersion}) async {
    final json = await _api.post(
      'family/invoices/$invoiceId/checkout',
      idempotent: true,
      body: {
        'account_id': ?accountId,
        'return': 'app',
        if (mandateVersion != null) ...{'save_card': true, 'mandate_version': mandateVersion},
      },
    );
    return (id: json['checkout_id'] as String? ?? '', url: Uri.parse(json['url'] as String));
  }
}
