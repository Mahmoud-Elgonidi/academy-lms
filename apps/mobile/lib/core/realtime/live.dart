import 'dart:async';

import 'pusher_socket.dart';

/// `GET realtime`: where to listen. [host] null: the API's own host.
class RealtimeConfig {
  const RealtimeConfig({required this.enabled, required this.channel, this.key, this.host, this.port, this.tls = true});

  factory RealtimeConfig.fromJson(Map<String, dynamic> json) => RealtimeConfig(
    enabled: json['enabled'] as bool? ?? false,
    channel: json['channel'] as String? ?? '',
    key: json['key'] as String?,
    host: json['host'] as String?,
    port: (json['port'] as num?)?.toInt(),
    tls: json['tls'] as bool? ?? true,
  );

  final bool enabled;
  final String channel;
  final String? key;
  final String? host;
  final int? port;
  final bool tls;
}

/// Where the API says to listen, and its signature for the person's channel.
abstract interface class LiveSource {
  Future<RealtimeConfig> where();
  Future<String> authorize(String socketId, String channel);
}

/// The person's own signals while the app is open, as the portal and the
/// panels have them: one socket (Reverb) on their private channel, for the
/// account open now, only while the app is in front. A screen reloads on
/// the signals it shows ([on]); coming back to the app, or the socket
/// coming back, reloads every screen once ([resync]). Without a socket, or
/// with the relay quiet for 2.5 minutes, every screen reloads each minute.
class Live {
  Live({
    required this._source,
    required String apiBaseUrl,
    this._connect,
    this.debounce = const Duration(milliseconds: 300),
    DateTime Function()? clock,
  }) : _apiHost = Uri.parse(apiBaseUrl).host,
       _clock = clock ?? DateTime.now;

  static const lessons = 'lessons.changed';
  static const homework = 'homework.changed';
  static const inbox = 'inbox.changed';
  static const payments = 'payments.changed';
  static const consents = 'consents.changed';
  static const tickets = 'tickets.changed';
  static const privacy = 'privacy.changed';
  static const concerns = 'concerns.changed';
  static const checks = 'checks.changed';
  static const messages = 'messages.changed';
  static const practice = 'practice.changed';

  /// A learner's hifz map: a page reviewed, marked or recited (and a recited page's check done).
  static const hifz = 'hifz.changed';

  /// Everything may have changed: after the app comes back, the socket comes back, or the relay restarts.
  static const resync = 'resync';

  static const quiet = Duration(seconds: 150);
  static const fallbackEvery = Duration(seconds: 60);

  final LiveSource _source;
  final String _apiHost;
  final Connect? _connect;
  final DateTime Function() _clock;
  final Duration debounce;

  final _signals = StreamController<String>.broadcast();
  final _subs = <StreamSubscription<Object?>>[];
  PusherSocket? _socket;
  Timer? _fallback;
  String? _account;
  bool _foreground = true;
  bool _connected = false;
  bool _connectedOnce = false;
  late DateTime _lastHeard = _clock();
  late DateTime _lastRefresh = _clock();

  /// Every signal heard (for tests and the bell).
  Stream<String> get signals => _signals.stream;

  /// Fires when one of [names] is heard (or [resync]); a burst of them fires once.
  Stream<void> on(Set<String> names) {
    late final StreamController<void> out;
    StreamSubscription<String>? sub;
    Timer? soon;
    out = StreamController<void>(
      onListen: () => sub = _signals.stream.where((s) => s == resync || names.contains(s)).listen((_) {
        soon?.cancel();
        soon = Timer(debounce, () {
          if (!out.isClosed) out.add(null);
        });
      }),
      onCancel: () async {
        soon?.cancel();
        await sub?.cancel();
      },
    );
    return out.stream;
  }

  /// The account open now (null: none). Another account gets its own socket.
  Future<void> follow(String? account) async {
    if (account == _account) return;
    _account = account;
    await _stop();
    if (account != null && _foreground) await _start(account);
  }

  /// The app came to the front (true) or went away (false). Back in front, every screen catches up.
  Future<void> foreground(bool front) async {
    if (front == _foreground) return;
    _foreground = front;
    if (!front) {
      await _stop();
      return;
    }
    final account = _account;
    if (account == null) return;
    await _start(account);
    _emit(resync);
  }

  /// Whether nothing is telling the screens about changes, and the last catch-up is a while ago.
  static bool needsFallback({required bool connected, required DateTime lastHeard, required DateTime lastRefresh, required DateTime now}) {
    final deaf = !connected || now.difference(lastHeard) > quiet;
    return deaf && now.difference(lastRefresh) >= fallbackEvery;
  }

  Future<void> close() async {
    _account = null;
    await _stop();
    await _signals.close();
  }

  void _emit(String signal) {
    if (signal == resync) _lastRefresh = _clock();
    if (!_signals.isClosed) _signals.add(signal);
  }

  Future<void> _start(String account) async {
    _lastHeard = _clock();
    _lastRefresh = _clock();
    _fallback?.cancel();
    _fallback = Timer.periodic(const Duration(seconds: 15), (_) {
      if (needsFallback(connected: _connected, lastHeard: _lastHeard, lastRefresh: _lastRefresh, now: _clock())) _emit(resync);
    });
    final RealtimeConfig where;
    try {
      where = await _source.where();
    } on Object {
      return;
    }
    // Still this account, in front, with no socket yet (a quick away-and-back may have started another).
    if (account != _account || !_foreground || _socket != null) return;
    if (!where.enabled || where.key == null || where.channel.isEmpty) return;
    final socket = PusherSocket(
      uri: PusherSocket.address(host: where.host ?? _apiHost, port: where.port ?? (where.tls ? 443 : 80), tls: where.tls, key: where.key!),
      authorize: _source.authorize,
      connect: _connect,
    );
    _socket = socket;
    _subs
      ..add(
        socket.heard.listen((h) {
          _lastHeard = _clock();
          if (h.event != 'alive') _emit(h.event);
        }),
      )
      ..add(
        socket.connected.listen((up) {
          _connected = up;
          if (!up) return;
          _lastHeard = _clock();
          if (_connectedOnce) _emit(resync);
          _connectedOnce = true;
        }),
      );
    socket
      ..subscribe('private-${where.channel}')
      ..subscribe('lms.live')
      ..open();
  }

  Future<void> _stop() async {
    _fallback?.cancel();
    _fallback = null;
    for (final s in _subs) {
      await s.cancel();
    }
    _subs.clear();
    final socket = _socket;
    _socket = null;
    _connected = false;
    _connectedOnce = false;
    await socket?.close();
  }
}
