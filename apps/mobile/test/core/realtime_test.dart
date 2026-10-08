import 'dart:async';
import 'dart:convert';

import 'package:academy_app/core/realtime/live.dart';
import 'package:academy_app/core/realtime/pusher_socket.dart';
import 'package:academy_app/core/state/load_cubit.dart';
import 'package:flutter_test/flutter_test.dart';

/// A WebSocket for tests: what the server says, and what the app sent.
class FakeLink implements SocketLink {
  FakeLink(this.uri);

  final Uri uri;
  final _incoming = StreamController<String>();
  final sent = <Map<String, dynamic>>[];
  bool closed = false;

  @override
  Stream<String> get incoming => _incoming.stream;

  @override
  void send(String text) => sent.add(jsonDecode(text) as Map<String, dynamic>);

  @override
  Future<void> close() async {
    closed = true;
    if (!_incoming.isClosed) await _incoming.close();
  }

  void hello([String socketId = '123.456']) => _say({
    'event': 'pusher:connection_established',
    'data': jsonEncode({'socket_id': socketId, 'activity_timeout': 120}),
  });

  void signal(String channel, String event) => _say({'event': event, 'channel': channel, 'data': '{}'});

  void ping() => _say({'event': 'pusher:ping', 'data': '{}'});

  void error(int code) => _say({
    'event': 'pusher:error',
    'data': {'code': code, 'message': 'no'},
  });

  /// The server drops the connection.
  Future<void> drop() => _incoming.close();

  void _say(Map<String, dynamic> m) => _incoming.add(jsonEncode(m));

  List<String> get subscribed => [
    for (final m in sent)
      if (m['event'] == 'pusher:subscribe') (m['data'] as Map<String, dynamic>)['channel'] as String,
  ];
}

Future<void> settle() async {
  for (var i = 0; i < 5; i++) {
    await Future<void>.delayed(Duration.zero);
  }
}

class FakeSource implements LiveSource {
  FakeSource({this.enabled = true});

  final bool enabled;
  final authorized = <(String, String)>[];
  int asked = 0;

  @override
  Future<RealtimeConfig> where() async {
    asked++;
    return RealtimeConfig(enabled: enabled, channel: 'user.7', key: 'k', port: 8080, tls: false);
  }

  @override
  Future<String> authorize(String socketId, String channel) async {
    authorized.add((socketId, channel));
    return 'k:signed';
  }
}

void main() {
  group('the socket', () {
    late List<FakeLink> links;
    late List<(String, String)> asked;
    late PusherSocket socket;

    setUp(() {
      links = [];
      asked = [];
      socket = PusherSocket(
        uri: PusherSocket.address(host: 'api.test', port: 443, tls: true, key: 'k'),
        authorize: (id, channel) async {
          asked.add((id, channel));
          return 'k:signed';
        },
        connect: (uri) => FakeLink(uri)..let(links.add),
        backoff: (_) => Duration.zero,
      );
    });

    tearDown(() => socket.close());

    test('goes to the app key on Reverb, protocol 7', () {
      expect(socket.uri.toString(), startsWith('wss://api.test:443/app/k?protocol=7'));
    });

    test('subscribes once greeted, a private channel with the API\'s signature, and hears its signals', () async {
      final heard = <Heard>[];
      socket.heard.listen(heard.add);
      socket
        ..subscribe('private-user.7')
        ..subscribe('lms.live')
        ..open();
      await settle();
      expect(links.single.subscribed, isEmpty); // not before the server says hello

      links.single.hello();
      await settle();
      expect(asked, [('123.456', 'private-user.7')]);
      expect(links.single.subscribed, containsAll(['private-user.7', 'lms.live']));
      final private = links.single.sent.firstWhere((m) => (m['data'] as Map)['channel'] == 'private-user.7');
      expect((private['data'] as Map)['auth'], 'k:signed');

      links.single
        ..signal('private-user.7', 'homework.changed')
        ..signal('private-user.7', 'pusher_internal:subscription_succeeded')
        ..ping();
      await settle();
      expect(heard, [(channel: 'private-user.7', event: 'homework.changed')]);
      expect(links.single.sent.last['event'], 'pusher:pong');
    });

    test('comes back after the network drops, and subscribes again', () async {
      final states = <bool>[];
      socket.connected.listen(states.add);
      socket
        ..subscribe('private-user.7')
        ..open();
      await settle();
      links.single.hello();
      await settle();

      await links.single.drop();
      await settle();
      expect(links, hasLength(2));
      links.last.hello('999.1');
      await settle();
      expect(states, [true, false, true]);
      expect(asked.last, ('999.1', 'private-user.7'));
      expect(links.last.subscribed, ['private-user.7']);
    });

    test('stops for good on an error the server says not to retry (4000–4099)', () async {
      socket.open();
      await settle();
      links.single
        ..hello()
        ..error(4001);
      await settle();
      expect(links.single.closed, isTrue);
      await settle();
      expect(links, hasLength(1));
    });

    test('a channel the API will not sign is left alone', () async {
      final refusing = PusherSocket(
        uri: socket.uri,
        authorize: (_, _) async => throw Exception('403'),
        connect: (uri) => FakeLink(uri)..let(links.add),
      );
      refusing
        ..subscribe('private-user.8')
        ..open();
      await settle();
      links.last.hello();
      await settle();
      expect(links.last.subscribed, isEmpty);
      await refusing.close();
    });
  });

  group('the live service', () {
    late List<FakeLink> links;
    late FakeSource source;
    late Live live;

    setUp(() {
      links = [];
      source = FakeSource();
      live = Live(
        source: source,
        apiBaseUrl: 'http://api.test/api/v1',
        connect: (uri) => FakeLink(uri)..let(links.add),
        debounce: const Duration(milliseconds: 20),
      );
    });

    tearDown(() => live.close());

    test('listens on the open account\'s own channel, on the API\'s host when it names none', () async {
      await live.follow('demo:7');
      await settle();
      expect(links.single.uri.toString(), startsWith('ws://api.test:8080/app/k'));
      links.single.hello();
      await settle();
      expect(source.authorized.single.$2, 'private-user.7');
      expect(links.single.subscribed, containsAll(['private-user.7', 'lms.live']));
    });

    test('a screen reloads once for a burst of its own signals, and on a resync, not for others', () async {
      var reloads = 0;
      final sub = live.on({Live.homework}).listen((_) => reloads++);
      await live.follow('demo:7');
      await settle();
      links.single.hello();
      await settle();

      links.single
        ..signal('private-user.7', Live.homework)
        ..signal('private-user.7', Live.homework)
        ..signal('private-user.7', Live.homework);
      await Future<void>.delayed(const Duration(milliseconds: 60));
      expect(reloads, 1);

      links.single.signal('private-user.7', Live.lessons);
      await Future<void>.delayed(const Duration(milliseconds: 60));
      expect(reloads, 1);

      links.single.signal('lms.live', 'alive'); // the relay's heartbeat reloads nothing
      await Future<void>.delayed(const Duration(milliseconds: 60));
      expect(reloads, 1);

      links.single.signal('lms.live', Live.resync);
      await Future<void>.delayed(const Duration(milliseconds: 60));
      expect(reloads, 2);
      await sub.cancel();
    });

    test('drops the socket in the background, and catches every screen up when back', () async {
      final heard = <String>[];
      live.signals.listen(heard.add);
      await live.follow('demo:7');
      await settle();
      await live.foreground(false);
      expect(links.single.closed, isTrue);

      await live.foreground(true);
      await settle();
      expect(links, hasLength(2));
      expect(heard, contains(Live.resync));
    });

    test('another account gets its own socket; signing out closes it', () async {
      await live.follow('demo:7');
      await settle();
      await live.follow('rawda:3');
      await settle();
      expect(links.first.closed, isTrue);
      expect(links, hasLength(2));
      await live.follow(null);
      expect(links.last.closed, isTrue);
    });

    test('opens nothing when the server does not broadcast', () async {
      final quiet = Live(
        source: FakeSource(enabled: false),
        apiBaseUrl: 'http://api.test/api/v1',
        connect: (uri) => FakeLink(uri)..let(links.add),
      );
      await quiet.follow('demo:7');
      await settle();
      expect(links, isEmpty);
      await quiet.close();
    });

    test('falls back to a reload a minute while nothing tells the screens about changes', () {
      final now = DateTime.utc(2026, 10, 8, 17);
      expect(
        Live.needsFallback(
          connected: true,
          lastHeard: now.subtract(const Duration(minutes: 1)),
          lastRefresh: now.subtract(const Duration(minutes: 10)),
          now: now,
        ),
        isFalse,
      );
      expect(
        Live.needsFallback(connected: false, lastHeard: now, lastRefresh: now.subtract(const Duration(seconds: 61)), now: now),
        isTrue,
      );
      expect(
        Live.needsFallback(connected: false, lastHeard: now, lastRefresh: now.subtract(const Duration(seconds: 30)), now: now),
        isFalse,
      );
      expect(
        Live.needsFallback(
          connected: true,
          lastHeard: now.subtract(const Duration(seconds: 151)),
          lastRefresh: now.subtract(const Duration(seconds: 61)),
          now: now,
        ),
        isTrue,
      );
    });
  });

  test('a screen\'s data reloads by itself on its signals, and stops when the screen goes', () async {
    final signals = StreamController<void>.broadcast();
    var fetched = 0;
    final cubit = LoadCubit<int>(() async => ++fetched, refreshOn: signals.stream);
    await cubit.load();
    expect(cubit.state.data, 1);

    signals.add(null);
    await settle();
    expect(cubit.state.data, 2);

    await cubit.close();
    signals.add(null);
    await settle();
    expect(fetched, 2);
    await signals.close();
  });

  test('waits for the soonest moment still ahead, a breath after it, and never more than six hours', () {
    final now = DateTime.utc(2026, 10, 8, 17);
    expect(
      LoadCubit.waitUntilNext([DateTime.utc(2026, 10, 8, 16), DateTime.utc(2026, 10, 8, 17, 50), DateTime.utc(2026, 10, 8, 17, 30)], now),
      const Duration(minutes: 30, seconds: 2),
    );
    expect(LoadCubit.waitUntilNext([DateTime.utc(2026, 10, 8, 16)], now), isNull);
    expect(LoadCubit.waitUntilNext([DateTime.utc(2026, 10, 12)], now), LoadCubit.longestWait);
  });

  test('a screen reloads by itself when the clock alone changes it (a room opening)', () async {
    var fetched = 0;
    // The room opens in a moment; the screen asks again a breath (2 s) after it.
    final opens = DateTime.now().toUtc().add(const Duration(milliseconds: 100));
    final cubit = LoadCubit<int>(() async => ++fetched, refreshAt: (_) => [opens]);
    await cubit.load();
    expect(fetched, 1);
    await Future<void>.delayed(const Duration(milliseconds: 1500));
    expect(fetched, 1);
    await Future<void>.delayed(const Duration(milliseconds: 1000));
    expect(fetched, 2);
    await cubit.close();
  });
}

extension<T> on T {
  T let(void Function(T) f) {
    f(this);
    return this;
  }
}
