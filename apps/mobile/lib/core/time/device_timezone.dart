import 'package:flutter_timezone/flutter_timezone.dart';

/// The phone's own zone as an IANA name ("Asia/Riyadh"), sent with every
/// request as `X-Timezone` so times come back on the clock the person sees.
Future<String?> deviceTimezone() async {
  try {
    final zone = await FlutterTimezone.getLocalTimezone();
    final id = zone.identifier;
    return id.contains('/') || id == 'UTC' ? id : null;
  } on Exception {
    return null;
  }
}
