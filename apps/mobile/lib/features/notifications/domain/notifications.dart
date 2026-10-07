import '../../../core/time/local_time.dart';

/// One message of the inbox; [screen] and [targetId] say where it opens.
class InboxItem {
  const InboxItem({
    required this.id,
    required this.category,
    required this.title,
    required this.body,
    required this.read,
    required this.urgent,
    this.screen,
    this.targetId,
    this.at,
  });

  factory InboxItem.fromJson(Map<String, dynamic> json) {
    final action = json['action'] as Map<String, dynamic>? ?? const {};
    return InboxItem(
      id: json['id'] as String,
      category: json['category'] as String? ?? '',
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      read: json['read'] == true,
      urgent: json['urgent'] == true,
      screen: action['screen'] as String?,
      targetId: action['id'] as String?,
      at: LocalTime.fromJson(json['created_at']),
    );
  }

  final String id;
  final String category;
  final String title;
  final String body;
  final bool read;
  final bool urgent;
  final String? screen;
  final String? targetId;
  final LocalTime? at;

  InboxItem readNow() => InboxItem(
    id: id,
    category: category,
    title: title,
    body: body,
    read: true,
    urgent: urgent,
    screen: screen,
    targetId: targetId,
    at: at,
  );
}

class InboxPage {
  const InboxPage({required this.items, required this.unread, this.nextCursor});

  final List<InboxItem> items;
  final int unread;
  final String? nextCursor;
}

/// One notification choice on "My settings" (`notifications.*`).
class NotificationChoice {
  const NotificationChoice({required this.key, required this.label, required this.value, required this.options, this.hint});

  factory NotificationChoice.fromJson(Map<String, dynamic> json) => NotificationChoice(
    key: json['key'] as String,
    label: json['label'] as String? ?? '',
    hint: json['hint'] as String?,
    value: '${json['value']}',
    options: [
      for (final o in json['options'] as List<dynamic>? ?? const [])
        (value: '${(o as Map<String, dynamic>)['value']}', label: o['label'] as String? ?? ''),
    ],
  );

  final String key;
  final String label;
  final String? hint;
  final String value;
  final List<({String value, String label})> options;
}
