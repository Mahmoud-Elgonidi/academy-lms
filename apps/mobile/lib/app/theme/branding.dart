import 'dart:ui';

import 'package:equatable/equatable.dart';

/// An academy's look as `GET /branding` gives it: its four colours, the
/// corner style and its logo. The app draws every screen from these, so one
/// build serves every academy.
class Branding extends Equatable {
  const Branding({
    required this.primary,
    required this.attention,
    required this.danger,
    required this.info,
    required this.radius,
    this.headingFont = 'Readex Pro',
    this.bodyFont = 'IBM Plex Sans Arabic',
    this.logoUrl,
    this.nameAr,
    this.nameEn,
  });

  /// The product's own colours, until the academy's arrive.
  static const fallback = Branding(
    primary: Color(0xFF0D735E),
    attention: Color(0xFFB7791F),
    danger: Color(0xFFB42318),
    info: Color(0xFF1D5FB8),
    radius: 'soft',
  );

  /// From `data` of `GET /branding`; anything missing keeps [fallback]'s value.
  factory Branding.fromJson(Map<String, dynamic> json) {
    final tokens = json['tokens'] as Map<String, dynamic>? ?? const {};
    final name = json['name'] as Map<String, dynamic>? ?? const {};
    return Branding(
      primary: parseColour(tokens['primary']) ?? fallback.primary,
      attention: parseColour(tokens['attention']) ?? fallback.attention,
      danger: parseColour(tokens['danger']) ?? fallback.danger,
      info: parseColour(tokens['info']) ?? fallback.info,
      radius: tokens['radius'] as String? ?? fallback.radius,
      headingFont: tokens['heading_font'] as String? ?? fallback.headingFont,
      bodyFont: tokens['body_font'] as String? ?? fallback.bodyFont,
      logoUrl: json['logo_url'] as String?,
      nameAr: name['ar'] as String?,
      nameEn: name['en'] as String?,
    );
  }

  /// The colour a discovered account carries, before its branding loads.
  factory Branding.fromColour(String? hex) => Branding(
    primary: parseColour(hex) ?? fallback.primary,
    attention: fallback.attention,
    danger: fallback.danger,
    info: fallback.info,
    radius: fallback.radius,
  );

  final Color primary;
  final Color attention;
  final Color danger;
  final Color info;

  /// sharp, soft or round.
  final String radius;
  final String headingFont;
  final String bodyFont;
  final String? logoUrl;
  final String? nameAr;
  final String? nameEn;

  Map<String, dynamic> toJson() => {
    'tokens': {
      'primary': hex(primary),
      'attention': hex(attention),
      'danger': hex(danger),
      'info': hex(info),
      'radius': radius,
      'heading_font': headingFont,
      'body_font': bodyFont,
    },
    'logo_url': logoUrl,
    'name': {'ar': nameAr, 'en': nameEn},
  };

  static Color? parseColour(Object? value) {
    if (value is! String) return null;
    final h = value.replaceFirst('#', '');
    if (h.length != 6) return null;
    final v = int.tryParse(h, radix: 16);
    return v == null ? null : Color(0xFF000000 | v);
  }

  static String hex(Color c) => '#${(c.toARGB32() & 0xFFFFFF).toRadixString(16).padLeft(6, '0').toUpperCase()}';

  @override
  List<Object?> get props => [primary, attention, danger, info, radius, headingFont, bodyFont, logoUrl];
}
