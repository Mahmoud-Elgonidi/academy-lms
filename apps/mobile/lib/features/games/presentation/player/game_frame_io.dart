import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import 'game_frame.dart';
import 'game_session.dart';

/// A phone: the page in a WebView that runs its scripts, talks through the
/// `LmsHost` channel, hears through `LMS.receive`, and never leaves its address.
class PlatformGameFrame extends StatefulWidget {
  const PlatformGameFrame({super.key, required this.frame});

  final GameFrame frame;

  @override
  State<PlatformGameFrame> createState() => _PlatformGameFrameState();
}

class _PlatformGameFrameState extends State<PlatformGameFrame> {
  late final WebViewController _web;

  @override
  void initState() {
    super.initState();
    final page = Uri.parse(widget.frame.url);
    _web = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFF13294B))
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            final to = Uri.tryParse(request.url);
            final same = to != null && to.scheme == page.scheme && to.host == page.host && to.path == page.path;
            return same ? NavigationDecision.navigate : NavigationDecision.prevent;
          },
        ),
      )
      ..addJavaScriptChannel('LmsHost', onMessageReceived: (message) => _hear(message.message));
    widget.frame.session.attach(_say);
    _web.loadRequest(page);
  }

  @override
  void dispose() {
    widget.frame.session.detach();
    super.dispose();
  }

  void _say(String message) {
    _web.runJavaScript('window.LMS && window.LMS.receive(${_literal(message)});').catchError((Object _) {});
  }

  // A string literal for the page: JSON escapes everything JavaScript needs.
  static String _literal(String text) => jsonEncode(text);

  void _hear(String text) {
    final event = widget.frame.session.handle(text);
    if (event == GameEvent.ready) widget.frame.session.sendStart();
    if (event != GameEvent.ignored) widget.frame.onEvent(event);
  }

  @override
  Widget build(BuildContext context) => WebViewWidget(controller: _web);
}
