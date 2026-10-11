import 'package:flutter/widgets.dart';

import 'game_frame_io.dart' if (dart.library.js_interop) 'game_frame_web.dart' as platform;
import 'game_session.dart';

/// Shows a game's page and carries the bridge's messages: a WebView on a
/// phone (the page calls `LmsHost.postMessage`), a sandboxed frame on the
/// web (`postMessage`). The page may not leave its own address.
class GameFrame extends StatelessWidget {
  const GameFrame({super.key, required this.url, required this.session, required this.onEvent});

  final String url;
  final GameSession session;
  final ValueChanged<GameEvent> onEvent;

  /// Tests put a stand-in page here (no WebView in a widget test).
  static Widget Function(GameFrame frame)? stand;

  @override
  Widget build(BuildContext context) => stand?.call(this) ?? platform.PlatformGameFrame(frame: this);
}
