import '../../../core/network/api_client.dart';
import '../domain/notifications.dart';

/// The signed-in person's inbox and notification choices.
class NotificationsRepository {
  NotificationsRepository(this._api);

  final ApiClient _api;

  Future<InboxPage> inbox({String? cursor, bool unreadOnly = false, int perPage = 20}) async {
    final json = await _api.get('notifications', query: {'cursor': ?cursor, if (unreadOnly) 'unread': 1, 'per_page': perPage});
    final meta = json['meta'] as Map<String, dynamic>? ?? const {};
    return InboxPage(
      items: [for (final n in json['data'] as List<dynamic>? ?? const []) InboxItem.fromJson(n as Map<String, dynamic>)],
      unread: (meta['unread'] as num?)?.toInt() ?? 0,
      nextCursor: meta['next_cursor'] as String?,
    );
  }

  Future<int> unread() async => (await inbox(unreadOnly: true, perPage: 1)).unread;

  /// Marks [ids] read, or everything when null.
  Future<void> markRead([List<String>? ids]) => _api.post('notifications/read', body: {'ids': ?ids});

  Future<List<NotificationChoice>> choices() async => [
    for (final c in (await _api.get('notifications/preferences'))['data'] as List<dynamic>)
      NotificationChoice.fromJson(c as Map<String, dynamic>),
  ];

  Future<List<NotificationChoice>> choose(String key, String value) async => [
    for (final c
        in (await _api.put(
              'notifications/preferences',
              body: {
                'preferences': {key: value},
              },
            ))['data']
            as List<dynamic>)
      NotificationChoice.fromJson(c as Map<String, dynamic>),
  ];
}
