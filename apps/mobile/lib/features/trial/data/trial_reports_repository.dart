import '../../../core/network/api_client.dart';
import '../../../core/time/local_time.dart';
import '../domain/trial.dart';
import '../domain/trial_report.dart';

/// A signed-in family's free trials (docs/architecture/02, section 27, J2):
/// the learner's trials, a trial's report, and one more trial from it (the
/// free second trial with another teacher, or a new time after one that did
/// not take place). The API checks each one again: a learner the account
/// follows, and booking only in the classic look.
class TrialReportsRepository {
  TrialReportsRepository(this._api);

  final ApiClient _api;

  /// The learner's trials, latest first ([learnerId] null: the account's first learner, or the student themself).
  Future<FamilyTrials> trials({String? learnerId}) async =>
      FamilyTrials.fromJson(await _api.get('family/trials', query: {'learner_id': ?learnerId}));

  /// A trial's report; not found until it took place and the teacher wrote it.
  Future<TrialReport> report(String session) async => TrialReport.fromJson(await _api.get('family/trials/$session'));

  /// Times for one more trial from [session], soonest first, on the viewer's clock (422 with why it cannot be booked).
  Future<List<TrialSlot>> slots(String session) async {
    final json = await _api.get('family/trials/$session/slots');
    return [
      for (final row in json['data'] as List<dynamic>? ?? const [])
        if (LocalTime.fromJson((row as Map<String, dynamic>)['starts_at']) case final at?) TrialSlot(at),
    ];
  }

  /// Books it at [startsAt]. Moves a lesson: sent once, whatever the network does.
  Future<NextTrialBooked> book(String session, LocalTime startsAt) async => NextTrialBooked.fromJson(
    await _api.post('family/trials/$session/book', idempotent: true, body: {'starts_at': startsAt.utc.toIso8601String()}),
  );
}
