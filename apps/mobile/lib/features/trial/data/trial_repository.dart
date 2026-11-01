import '../../../core/network/api_client.dart';
import '../../../core/time/local_time.dart';
import '../domain/trial.dart';

/// A family asks for, or books, its own free trial before anyone signs in
/// (docs/architecture/02, section 27): the academy's page, the times on
/// offer, a code to the family's number and the booking with it; or the
/// request alone, which the academy answers. Every call names the academy
/// itself (`X-Academy`) and carries no account.
class TrialRepository {
  TrialRepository(this._api);

  final ApiClient _api;

  /// The academy's page; not found while the academy keeps it off (or no academy has the code).
  Future<AcademyPage> page(String academy, {DateTime? now}) async =>
      AcademyPage.fromJson(academy, await _api.get('site', academy: academy, anonymous: true), now ?? DateTime.now());

  /// The academy's colours, logo and name (null when they cannot be read: the app's own look stays).
  Future<Map<String, dynamic>?> branding(String academy) async {
    try {
      final json = await _api.get('branding', academy: academy, anonymous: true);
      return json['data'] as Map<String, dynamic>?;
    } on Object {
      return null;
    }
  }

  /// Times the trial may start at, soonest first, over the next two weeks, on [timezone]'s clock.
  Future<List<TrialSlot>> slots(
    String academy, {
    required String subjectId,
    int? birthYear,
    String? teacherGender,
    String? timezone,
  }) async {
    final json = await _api.get(
      'site/trial-slots',
      academy: academy,
      anonymous: true,
      query: {'subject_id': subjectId, 'birth_year': ?birthYear, 'teacher_gender': ?teacherGender, 'timezone': ?timezone},
    );
    return [
      for (final row in json['data'] as List<dynamic>? ?? const [])
        if (LocalTime.fromJson((row as Map<String, dynamic>)['starts_at']) case final at?) TrialSlot(at),
    ];
  }

  /// A code to the family's number, by WhatsApp (or a text message).
  Future<TrialCode> sendCode(String academy, String phone, {DateTime? now}) async => TrialCode.fromJson(
    await _api.post('site/trials/code', academy: academy, anonymous: true, body: {'phone': phone}),
    now ?? DateTime.now(),
  );

  /// Books the trial at [startsAt] with the code sent to the request's number. Moves a lesson: sent once, whatever the network does.
  Future<BookedTrial> book(
    String academy,
    Map<String, Object?> request, {
    required String challengeId,
    required String code,
    required LocalTime startsAt,
  }) async => BookedTrial.fromJson(
    await _api.post(
      'site/trials',
      academy: academy,
      anonymous: true,
      idempotent: true,
      body: {...request, 'challenge_id': challengeId, 'code': code, 'starts_at': startsAt.utc.toIso8601String()},
    ),
  );

  /// Sends the request without a time: the academy answers it (and, while families book, the reply carries a link to pick one).
  Future<void> request(String academy, Map<String, Object?> request, {required String formToken}) =>
      _api.post('site/requests', academy: academy, anonymous: true, body: {...request, 'kind': 'trial', 'form_token': formToken});
}
