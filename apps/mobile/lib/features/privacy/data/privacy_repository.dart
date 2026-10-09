import '../../../core/network/api_client.dart';
import '../domain/my_data.dart';

/// One's own data, the same as "My data" in /my, /teach and the portal: a
/// copy, erasure of an account (one's own, or a followed learner's), and
/// taking an erasure back. The API checks each step again.
class PrivacyRepository {
  PrivacyRepository(this._api);

  final ApiClient _api;

  Future<MyData> load() async => MyData.fromJson(await _api.get('me/data-requests'));

  /// A copy of [subject]'s data, prepared in a few minutes (one a day).
  Future<void> askCopy(String subject) => _api.post('me/data-requests', body: {'kind': 'export', 'subject_id': subject}, idempotent: true);

  /// Asks the academy to erase [subject]'s account; it answers within 30 days.
  Future<void> askErasure(String subject, String reason) => _api.post(
    'me/data-requests',
    body: {'kind': 'erasure', 'subject_id': subject, 'reason': reason.trim().isEmpty ? null : reason.trim()},
    idempotent: true,
  );

  /// Takes an erasure back while it waits, or in the day after the academy approved it.
  Future<void> cancel(String id) => _api.post('me/data-requests/$id/cancel');
}
