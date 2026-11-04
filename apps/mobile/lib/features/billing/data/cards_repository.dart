import '../../../core/network/api_client.dart';
import '../domain/cards.dart';

/// The payer's saved cards and automatic renewal (docs/architecture/02,
/// section 27, J5b), in the classic look only (the API answers 403
/// `cards-refused` otherwise). A card is saved only by ticking "save my card
/// and renew automatically" when paying on the gateway's page; here it is
/// read, switched on or off and removed. Each write answers the whole
/// picture again, with the API's sentence on what was done.
class CardsRepository {
  CardsRepository(this._api);

  final ApiClient _api;

  Future<SavedCards> cards() async => SavedCards.fromJson(await _api.get('family/cards'));

  /// Turns automatic renewal on or off (for [accountId] alone, else every account). On needs a live card: 422 with why not.
  Future<({SavedCards cards, String message})> autoRenew({required bool on, String? accountId}) async {
    final json = await _api.put('family/cards/auto-renew', idempotent: true, body: {'on': on, 'billing_account_id': ?accountId});
    return (cards: SavedCards.fromJson(json), message: json['message'] as String? ?? '');
  }

  /// Lets a card go at once; with none left, automatic renewal stops and the charges still to come are cancelled.
  Future<({SavedCards cards, String message})> remove(String cardId) async {
    final json = await _api.delete('family/cards/$cardId', idempotent: true);
    return (cards: SavedCards.fromJson(json), message: json['message'] as String? ?? '');
  }
}
