import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

import 'gen/app_localizations.dart';

/// The app's words for the open academy: an academy for adults reads the
/// training words (tool/strings.py writes them to `app_{ar,en}_ZZ.arb`: only
/// what differs, the rest falls back to the language's own), everyone else
/// the base. The app's locale stays the language alone (ar, en), so dates,
/// Material's own words and the API's `Accept-Language` never change.
AppLocalizations wordsFor(String language, {bool adults = false}) =>
    lookupAppLocalizations(adults ? Locale(language, adultsRegion) : Locale(language));

/// The region code of the adults' wording (a private-use one: no country has it).
const adultsRegion = 'ZZ';

/// The languages the app speaks (the adults' wording is not one of its own).
final appLocales = [
  for (final l in AppLocalizations.supportedLocales)
    if (l.countryCode == null) l,
];

/// [AppLocalizations.delegate] that picks the adults' wording when [adults].
class AppWords extends LocalizationsDelegate<AppLocalizations> {
  const AppWords({this.adults = false});

  final bool adults;

  @override
  bool isSupported(Locale locale) => AppLocalizations.delegate.isSupported(Locale(locale.languageCode));

  @override
  Future<AppLocalizations> load(Locale locale) => SynchronousFuture(wordsFor(locale.languageCode, adults: adults));

  @override
  bool shouldReload(AppWords old) => old.adults != adults;
}
