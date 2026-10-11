import 'dart:convert';

import '../../domain/games.dart';

/// One round between the app and the game's page, whatever shows the page
/// (a WebView on a phone, a sandboxed frame on the web). The page speaks the
/// platform's bridge: it says `ready`, is handed the round, reports every try
/// (`answer`), and says `finish`. Only tries at the round's own items count,
/// and nothing counts after the finish.
class GameSession {
  GameSession(this.round, {this.muted = false});

  final StartedRound round;
  bool muted;

  final List<GameAnswer> _answers = [];
  bool _started = false;
  bool _finished = false;
  String? _error;

  List<GameAnswer> get answers => List.unmodifiable(_answers);
  bool get started => _started;
  bool get finished => _finished;

  /// The last error the page reported (shown only if the round cannot go on).
  String? get error => _error;

  /// What to hand the page once it is ready.
  String startMessage() => jsonEncode({
    'lms': 1,
    'type': 'start',
    'round': {...round.round, 'muted': muted},
  });

  void Function(String message)? _sender;

  /// The frame showing the page says how to reach it.
  void attach(void Function(String message) sender) => _sender = sender;

  void detach() => _sender = null;

  /// Sound on or off in the page too.
  void mute(bool on) {
    muted = on;
    _sender?.call(jsonEncode({'lms': 1, 'type': 'mute', 'muted': on}));
  }

  /// Hands the page its round (after `ready`).
  void sendStart() => _sender?.call(startMessage());

  /// Reads one message from the page and says what it was.
  GameEvent handle(Object? data) {
    Map<String, dynamic>? message;
    if (data is String) {
      try {
        final decoded = jsonDecode(data);
        if (decoded is Map<String, dynamic>) message = decoded;
      } on FormatException {
        return GameEvent.ignored;
      }
    } else if (data is Map<String, dynamic>) {
      message = data;
    }
    if (message == null || message['lms'] != 1) return GameEvent.ignored;
    switch (message['type']) {
      case 'ready':
        if (_started) return GameEvent.ignored;
        _started = true;
        return GameEvent.ready;
      case 'answer':
        final item = '${message['item']}';
        if (_finished || !round.itemIds.contains(item)) return GameEvent.ignored;
        _answers.add(GameAnswer(item, message['correct'] == true));
        return GameEvent.answer;
      case 'finish':
        if (_finished || !_started) return GameEvent.ignored;
        _finished = true;
        return GameEvent.finish;
      case 'error':
        _error = '${message['message'] ?? ''}';
        return GameEvent.error;
      default:
        return GameEvent.ignored;
    }
  }
}

enum GameEvent { ready, answer, finish, error, ignored }
