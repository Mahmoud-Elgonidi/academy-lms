import 'dart:async';
import 'dart:js_interop';
import 'dart:ui_web' as ui_web;

import 'package:flutter/widgets.dart';
import 'package:web/web.dart' as web;

import 'game_frame.dart';
import 'game_session.dart';

/// The web: the page in an `<iframe sandbox="allow-scripts">` (no same
/// origin, so it reaches nothing of the app's), talking by `postMessage`;
/// only messages from that frame are read.
class PlatformGameFrame extends StatefulWidget {
  const PlatformGameFrame({super.key, required this.frame});

  final GameFrame frame;

  @override
  State<PlatformGameFrame> createState() => _PlatformGameFrameState();
}

class _PlatformGameFrameState extends State<PlatformGameFrame> {
  static int _next = 0;
  late final String _type = 'lms-game-${_next++}';
  late final web.HTMLIFrameElement _iframe;
  StreamSubscription<web.MessageEvent>? _messages;

  @override
  void initState() {
    super.initState();
    _iframe = web.HTMLIFrameElement()
      ..src = widget.frame.url
      ..setAttribute('sandbox', 'allow-scripts')
      ..setAttribute('allow', 'autoplay')
      ..setAttribute('referrerpolicy', 'no-referrer')
      ..style.border = '0'
      ..style.width = '100%'
      ..style.height = '100%';
    ui_web.platformViewRegistry.registerViewFactory(_type, (int _) => _iframe);
    widget.frame.session.attach(_say);
    _messages = web.EventStreamProviders.messageEvent.forTarget(web.window).listen(_hear);
  }

  @override
  void dispose() {
    _messages?.cancel();
    widget.frame.session.detach();
    super.dispose();
  }

  void _say(String message) => _iframe.contentWindow?.postMessage(message.toJS, '*'.toJS);

  void _hear(web.MessageEvent event) {
    final from = event.source;
    final frame = _iframe.contentWindow;
    if (from == null || frame == null || !from.strictEquals(frame).toDart) return;
    final data = event.data;
    if (!data.isA<JSString>()) return;
    final result = widget.frame.session.handle((data as JSString).toDart);
    if (result == GameEvent.ready) widget.frame.session.sendStart();
    if (result != GameEvent.ignored) widget.frame.onEvent(result);
  }

  @override
  Widget build(BuildContext context) => HtmlElementView(viewType: _type);
}
