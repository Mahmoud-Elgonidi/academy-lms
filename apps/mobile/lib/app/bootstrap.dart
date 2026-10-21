import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:intl/intl.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/auth/account_store.dart';
import '../core/auth/session_cubit.dart';
import '../core/auth/session_holder.dart';
import '../core/auth/session_repository.dart';
import '../core/config.dart';
import '../core/network/api_client.dart';
import '../core/prefs/app_prefs.dart';
import '../core/push/push.dart';
import '../core/time/device_timezone.dart';
import 'app.dart';
import 'di/services.dart';

/// Builds the app's parts and runs it: the API client reads the open
/// account from the session on every request.
Future<void> bootstrap({AppConfig? config, Secrets? secrets}) async {
  WidgetsFlutterBinding.ensureInitialized();
  LicenseRegistry.addLicense(() async* {
    final text = await rootBundle.loadString('assets/fonts/OFL.txt');
    yield LicenseEntryWithLineBreaks(const ['Readex Pro', 'IBM Plex Sans Arabic', 'Baloo Bhaijaan 2', 'Rubik'], text);
    yield LicenseEntryWithLineBreaks(const ['Amiri Quran'], await rootBundle.loadString('assets/fonts/OFL-AmiriQuran.txt'));
  });
  // Western digits in dates, as the API writes its labels.
  DateFormat.useNativeDigitsByDefaultFor('ar', false);

  final version = await PackageInfo.fromPlatform().then<String?>((p) => p.version, onError: (Object _) => null);
  await Push.instance.start();
  final holder = SessionHolder();
  final api = ApiClient(config: config ?? AppConfig.fromEnvironment(), context: holder);
  final session = SessionCubit(
    holder: holder,
    store: AccountStore(secrets ?? const DeviceSecrets()),
    prefs: AppPrefs(SharedPreferencesAsync()),
    repository: SessionRepository(api),
    deviceTimezone: await deviceTimezone(),
    deviceLocale: PlatformDispatcher.instance.locale.languageCode,
    appVersion: version,
    push: Push.instance,
  );
  unawaited(session.start());
  runApp(AcademyApp(services: Services(api), session: session));
}
