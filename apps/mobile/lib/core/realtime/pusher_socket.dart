import 'dart:async';
import 'dart:convert';

import 'package:web_socket_channel/web_socket_channel.dart';

/// One text connection (a WebSocket), behind an interface so tests can talk
/// to [PusherSocket] without a network.
abstract interface class SocketLink {
  Stream<String> get incoming;
  void send(String text);
  Future<void> close();
}

typedef Connect = SocketLink Function(Uri uri);

class _WebSocketLink implements SocketLink {
  _WebSocketLink(Uri uri) : _channel = WebSocketChannel.connect(uri);

  final WebSocketChannel _channel;

  @override
  Stream<String> get incoming => _channel.stream.map((m) => m is String ? m : utf8.decode(m as List<int>));

  @override
  void send(String text) => _channel.sink.add(text);

  @override
  Future<void> close() => _channel.sink.close();
}

/// A broadcast heard on a channel: its name, nothing more (the API's signals carry no data).
typedef Heard = ({String channel, String event});

/// Signs a private channel for this socket (the API's `realtime/auth`).
typedef Authorize = Future<String> Function(String socketId, String channel);

/// The little of the Pusher protocol that Reverb speaks and the app needs:
/// connect, subscribe to public and private channels (signed by the API),
/// answer and send pings, and come back after the network drops, waiting a
/// little longer each time. Signals are names only; the screen asks the API
/// again for what it shows.
class PusherSocket {
  PusherSocket({required this.uri, required this._authorize, Connect? connect, Duration Function(int attempt)? backoff})
    : _connect = connect ?? _WebSocketLink.new,
      _backoff = backoff ?? _defaultBackoff;

  /// `ws(s)://{host}:{port}/app/{key}?protocol=7&…`.
  final Uri uri;
  final Authorize _authorize;
  final Connect _connect;
  final Duration Function(int attempt) _backoff;

  final _heard = StreamController<Heard>.broadcast();
  final _connected = StreamController<bool>.broadcast();
  final _channels = <String>{};
  SocketLink? _link;
  StreamSubscription<String>? _reading;
  Timer? _again;
  Timer? _idle;
  String? _socketId;
  Duration _activity = const Duration(seconds: 120);
  int _attempt = 0;
  bool _closed = false;
  bool _waitingForPong = false;

  static Duration _defaultBackoff(int attempt) => Duration(seconds: [1, 2, 4, 8, 16, 30][attempt.clamp(0, 5)]);

  static Uri address({required String host, required int port, required bool tls, required String key}) => Uri(
    scheme: tls ? 'wss' : 'ws',
    host: host,
    port: port,
    path: '/app/$key',
    queryParameters: {'protocol': '7', 'client': 'lms-app', 'version': '1.0', 'flash': 'false'},
  );

  Stream<Heard> get heard => _heard.stream;

  /// true once the server greeted this connection, false when it is gone.
  Stream<bool> get connected => _connected.stream;

  bool get isConnected => _socketId != null;

  void open() {
    _closed = false;
    _dial();
  }

  /// Listens on a channel ("private-…" ones are signed by the API).
  void subscribe(String channel) {
    if (!_channels.add(channel)) return;
    if (_socketId != null) unawaited(_join(channel));
  }

  Future<void> close() async {
    _closed = true;
    _again?.cancel();
    _idle?.cancel();
    await _drop();
    await _heard.close();
    await _connected.close();
  }

  void _dial() {
    if (_closed) return;
    try {
      final link = _connect(uri);
      _link = link;
      _reading = link.incoming.listen(_read, onError: (Object _) => _lost(), onDone: _lost);
    } on Object {
      _lost();
    }
  }

  Future<void> _drop() async {
    final wasConnected = _socketId != null;
    _socketId = null;
    _waitingForPong = false;
    await _reading?.cancel();
    _reading = null;
    final link = _link;
    _link = null;
    if (link != null) {
      try {
        await link.close();
      } on Object {
        // Already gone.
      }
    }
    if (wasConnected && !_connected.isClosed) _connected.add(false);
  }

  void _lost() {
    if (_closed) return;
    _idle?.cancel();
    unawaited(_drop());
    _again?.cancel();
    _again = Timer(_backoff(_attempt++), _dial);
  }

  void _send(String event, Map<String, dynamic> data) => _link?.send(jsonEncode({'event': event, 'data': data}));

  void _read(String text) {
    _awake();
    final Map<String, dynamic> message;
    try {
      message = jsonDecode(text) as Map<String, dynamic>;
    } on Object {
      return;
    }
    final event = message['event'] as String? ?? '';
    final data = message['data'];
    switch (event) {
      case 'pusher:connection_established':
        final hello = (data is String ? jsonDecode(data) : data) as Map<String, dynamic>;
        _socketId = hello['socket_id'] as String?;
        final timeout = (hello['activity_timeout'] as num?)?.toInt();
        if (timeout != null && timeout > 0) _activity = Duration(seconds: timeout);
        _attempt = 0;
        _connected.add(true);
        for (final channel in _channels) {
          unawaited(_join(channel));
        }
        _awake();
      case 'pusher:ping':
        _send('pusher:pong', {});
      case 'pusher:pong':
        _waitingForPong = false;
      case 'pusher:error':
        final error = (data is String ? jsonDecode(data) : data) as Map<String, dynamic>?;
        final code = (error?['code'] as num?)?.toInt() ?? 0;
        // 4000–4099: do not come back as is (a wrong key, an app over its limits).
        if (code >= 4000 && code < 4100) {
          _closed = true;
          unawaited(_drop());
        }
      default:
        final channel = message['channel'] as String?;
        if (channel != null && !event.startsWith('pusher')) _heard.add((channel: channel, event: event));
    }
  }

  Future<void> _join(String channel) async {
    final socketId = _socketId;
    if (socketId == null) return;
    if (!channel.startsWith('private-')) {
      _send('pusher:subscribe', {'channel': channel});
      return;
    }
    try {
      final auth = await _authorize(socketId, channel);
      // Still the same connection (it may have dropped while the API answered).
      if (_socketId == socketId) _send('pusher:subscribe', {'channel': channel, 'auth': auth});
    } on Object {
      // Not signed: no signals on it; the screens still refresh by themselves.
    }
  }

  /// Quiet for the server's activity timeout: ask if it is still there, and give up waiting after 30 seconds.
  void _awake() {
    _idle?.cancel();
    if (_socketId == null) return;
    _idle = Timer(_activity, () {
      if (_waitingForPong) {
        _lost();
        return;
      }
      _waitingForPong = true;
      _send('pusher:ping', {});
      _idle = Timer(const Duration(seconds: 30), () {
        if (_waitingForPong) _lost();
      });
    });
  }
}
