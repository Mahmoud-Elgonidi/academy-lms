import 'package:equatable/equatable.dart';

/// An area of the academy that may be off: by its SaaS plan (a paid one) or
/// by the academy itself (an optional one). The core is never listed.
enum Feature {
  crm('crm'),
  onlinePayments('online_payments'),
  dataImport('import'),
  customBranding('custom_branding'),
  payroll('payroll'),
  ai('ai'),
  quran('quran'),
  groups('groups'),
  curriculum('curriculum'),
  games('games'),
  messaging('messaging'),
  announcements('announcements'),
  support('support'),
  evaluation('evaluation'),
  site('site');

  const Feature(this.value);

  /// As the API writes it (`GET me`'s `modules`, `feature` on a 403 `feature-not-in-plan`).
  final String value;
}

/// The areas that are on for the open account's academy, as `GET me`
/// (`data.modules`) or `GET branding` (`modules`) list them. Anything not
/// listed is off; an older server that lists nothing has everything on.
class Modules extends Equatable {
  const Modules._(this._on);

  factory Modules.fromJson(Object? json) => json is List ? Modules._({for (final x in json) '$x'}) : all;

  /// Only [features] on (for tests and a known list).
  factory Modules.only(Iterable<Feature> features) => Modules._({for (final f in features) f.value});

  /// Everything on: no academy open yet, or a server from before academy modules.
  static const all = Modules._(null);

  final Set<String>? _on;

  bool has(Feature feature) => _on == null || _on.contains(feature.value);

  /// Whether [location] (an app path) may open: a place of an area that is off goes home instead.
  bool allows(String location) {
    final f = featureAt(location);
    return f == null || has(f);
  }

  /// The list as the API writes it (null: from an older server).
  List<String>? toJson() => _on?.toList();

  @override
  List<Object?> get props => [_on == null ? null : (_on.toList()..sort()).join(',')];
}

/// The optional area a place in the app belongs to (null: the core, or the
/// tabs that stay whatever is off and show a calm page instead).
Feature? featureAt(String location) {
  final path = Uri.parse(location).path;
  final parts = path.split('/').where((p) => p.isNotEmpty).toList();
  bool has(String part) => parts.contains(part);
  if (has('messages')) return Feature.messaging;
  if (has('help')) return Feature.support;
  // The creatures' album is the games'; the kids' sticker album and a course's levels are the curriculum's journey.
  if (parts.length >= 3 && parts[1] == 'journey' && parts[2] == 'album') return Feature.games;
  if (parts.length >= 3 && parts[1] == 'more' && parts[2] == 'stickers') return Feature.curriculum;
  if (parts.length >= 3 && parts[1] == 'journey' && parts[2] == 'levels') return Feature.curriculum;
  if (path == '/teach/more/performance') return Feature.evaluation;
  if (path == '/teach/more/card') return Feature.site;
  // Everything of the Quran: the hifz map and the Quran under a journey, a lesson's mushaf and portion, the exams.
  if (has('hifz') || has('mushaf') || has('quran') || has('quran-exams')) return Feature.quran;
  return null;
}
