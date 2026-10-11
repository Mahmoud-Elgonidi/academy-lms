import 'package:flutter/widgets.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/l10n/l10n.dart';
import 'lms.dart';

/// Opens [url] in the browser over the app (or, with [mode], in its own
/// app); says so when nothing on the phone can open it. True once opened.
Future<bool> openLink(BuildContext context, Uri url, {LaunchMode mode = LaunchMode.inAppBrowserView}) async {
  var opened = false;
  try {
    opened = await launchUrl(url, mode: mode);
  } on Object {
    // No browser, or no app for this kind of link.
  }
  if (!opened && context.mounted) showSnack(context, context.l10n.linkNotOpened);
  return opened;
}
