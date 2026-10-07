import 'package:flutter/widgets.dart';

import '../network/api_problem.dart';
import 'gen/app_localizations.dart';

export 'gen/app_localizations.dart';

extension L10nContext on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);

  /// The language the app is showing (ar or en).
  String get locale => Localizations.localeOf(this).languageCode;

  /// One line for an error: the API's own words (already in the reader's
  /// language), or ours when the network is down or the API said nothing.
  String problemText(Object error) {
    if (error is ApiProblem) {
      if (error.isOffline) return l10n.offline;
      return error.message ?? l10n.problem;
    }
    return l10n.problem;
  }
}
