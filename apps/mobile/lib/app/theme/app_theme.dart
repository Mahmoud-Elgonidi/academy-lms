import 'package:flutter/material.dart';

import 'branding.dart';
import 'stage.dart';

/// Colours every screen draws with, from the academy's branding: the brand
/// and its soft and deep shades, the three signal colours each with a soft
/// background and a readable ink, and the neutrals of the stage.
@immutable
class LmsColors extends ThemeExtension<LmsColors> {
  const LmsColors({
    required this.brand,
    required this.brandSoft,
    required this.brandDeep,
    required this.attention,
    required this.attentionSoft,
    required this.attentionInk,
    required this.danger,
    required this.dangerSoft,
    required this.dangerInk,
    required this.info,
    required this.infoSoft,
    required this.infoInk,
    required this.page,
    required this.card,
    required this.line,
    required this.ink,
    required this.muted,
    required this.faint,
    required this.chip,
    required this.reward,
    required this.rewardSoft,
  });

  factory LmsColors.of(Branding b, Stage stage) {
    final neutrals = switch (stage) {
      Stage.kids => (page: const Color(0xFFF3F7FB), chip: const Color(0xFFEAF0F6)),
      Stage.middle => (page: const Color(0xFFF4F4F6), chip: const Color(0xFFEDEDF0)),
      Stage.teen => (page: const Color(0xFFF8FAFC), chip: const Color(0xFFF1F5F9)),
      Stage.classic => (page: const Color(0xFFF7F6F4), chip: const Color(0xFFF0EEEB)),
    };
    return LmsColors(
      brand: b.primary,
      brandSoft: mix(b.primary, Colors.white, 0.88),
      brandDeep: mix(b.primary, Colors.black, 0.35),
      attention: b.attention,
      attentionSoft: mix(b.attention, Colors.white, 0.88),
      attentionInk: mix(b.attention, Colors.black, 0.5),
      danger: b.danger,
      dangerSoft: mix(b.danger, Colors.white, 0.9),
      dangerInk: mix(b.danger, Colors.black, 0.2),
      info: b.info,
      infoSoft: mix(b.info, Colors.white, 0.9),
      infoInk: mix(b.info, Colors.black, 0.35),
      page: neutrals.page,
      card: Colors.white,
      line: stage == Stage.teen ? const Color(0xFFE2E8F0) : const Color(0xFFECEAE7),
      ink: stage == Stage.teen ? const Color(0xFF0F172A) : const Color(0xFF1C1917),
      muted: stage == Stage.teen ? const Color(0xFF475569) : const Color(0xFF57534E),
      faint: const Color(0xFFA8A29E),
      chip: neutrals.chip,
      reward: const Color(0xFFF2B53A),
      rewardSoft: const Color(0xFFFFF1CC),
    );
  }

  final Color brand;
  final Color brandSoft;
  final Color brandDeep;
  final Color attention;
  final Color attentionSoft;
  final Color attentionInk;
  final Color danger;
  final Color dangerSoft;
  final Color dangerInk;
  final Color info;
  final Color infoSoft;
  final Color infoInk;
  final Color page;
  final Color card;
  final Color line;
  final Color ink;
  final Color muted;
  final Color faint;
  final Color chip;

  /// Stars, points and streaks in the students' looks.
  final Color reward;
  final Color rewardSoft;

  static Color mix(Color a, Color b, double t) => Color.lerp(a, b, t)!;

  @override
  LmsColors copyWith() => this;

  @override
  LmsColors lerp(LmsColors? other, double t) {
    if (other == null) return this;
    Color l(Color x, Color y) => Color.lerp(x, y, t)!;
    return LmsColors(
      brand: l(brand, other.brand),
      brandSoft: l(brandSoft, other.brandSoft),
      brandDeep: l(brandDeep, other.brandDeep),
      attention: l(attention, other.attention),
      attentionSoft: l(attentionSoft, other.attentionSoft),
      attentionInk: l(attentionInk, other.attentionInk),
      danger: l(danger, other.danger),
      dangerSoft: l(dangerSoft, other.dangerSoft),
      dangerInk: l(dangerInk, other.dangerInk),
      info: l(info, other.info),
      infoSoft: l(infoSoft, other.infoSoft),
      infoInk: l(infoInk, other.infoInk),
      page: l(page, other.page),
      card: l(card, other.card),
      line: l(line, other.line),
      ink: l(ink, other.ink),
      muted: l(muted, other.muted),
      faint: l(faint, other.faint),
      chip: l(chip, other.chip),
      reward: l(reward, other.reward),
      rewardSoft: l(rewardSoft, other.rewardSoft),
    );
  }
}

/// Corners and type by stage: round and bold for kids, the academy's own
/// corner style (sharp, soft, round) for everyone in the classic look.
@immutable
class LmsShape extends ThemeExtension<LmsShape> {
  const LmsShape({required this.stage, required this.card, required this.button, required this.field, required this.headingFont});

  factory LmsShape.of(Branding b, Stage stage) => switch (stage) {
    Stage.kids => const LmsShape(stage: Stage.kids, card: 26, button: 999, field: 18, headingFont: _kids),
    Stage.middle => const LmsShape(stage: Stage.middle, card: 18, button: 14, field: 14, headingFont: _middle),
    Stage.teen => const LmsShape(stage: Stage.teen, card: 10, button: 10, field: 10, headingFont: _body),
    Stage.classic => switch (b.radius) {
      'sharp' => LmsShape(stage: stage, card: 10, button: 10, field: 10, headingFont: _heading(b)),
      'round' => LmsShape(stage: stage, card: 24, button: 18, field: 16, headingFont: _heading(b)),
      _ => LmsShape(stage: stage, card: 18, button: 16, field: 14, headingFont: _heading(b)),
    },
  };

  static const _kids = 'BalooBhaijaan2';
  static const _middle = 'Rubik';
  static const _body = 'IBMPlexSansArabic';

  /// The academy's heading font when the app carries it, else Readex Pro.
  static String _heading(Branding b) => switch (b.headingFont) {
    'IBM Plex Sans Arabic' => _body,
    'Rubik' => _middle,
    _ => 'ReadexPro',
  };

  final Stage stage;
  final double card;
  final double button;
  final double field;
  final String headingFont;

  BorderRadius get cardRadius => BorderRadius.circular(card);

  @override
  LmsShape copyWith() => this;

  @override
  LmsShape lerp(LmsShape? other, double t) => t < 0.5 ? this : (other ?? this);
}

extension LmsTheme on BuildContext {
  LmsColors get colors => Theme.of(this).extension<LmsColors>()!;
  LmsShape get shape => Theme.of(this).extension<LmsShape>()!;
  TextTheme get text => Theme.of(this).textTheme;
}

/// The whole Material theme for an academy and a stage.
ThemeData buildTheme(Branding branding, Stage stage) {
  final c = LmsColors.of(branding, stage);
  final s = LmsShape.of(branding, stage);
  final body = stage == Stage.kids ? 'BalooBhaijaan2' : (stage == Stage.middle ? 'Rubik' : 'IBMPlexSansArabic');
  final heavy = stage == Stage.kids ? FontWeight.w800 : FontWeight.w700;
  final kids = stage == Stage.kids;

  final scheme = ColorScheme.fromSeed(seedColor: branding.primary).copyWith(
    primary: branding.primary,
    onPrimary: Colors.white,
    primaryContainer: c.brandSoft,
    onPrimaryContainer: c.brandDeep,
    error: branding.danger,
    surface: c.card,
    onSurface: c.ink,
    onSurfaceVariant: c.muted,
    outline: c.line,
    outlineVariant: c.line,
  );

  final base = ThemeData(useMaterial3: true, colorScheme: scheme, fontFamily: body);
  TextStyle h(double size, [FontWeight? w]) =>
      TextStyle(fontFamily: s.headingFont, fontSize: size, fontWeight: w ?? heavy, color: c.ink, height: 1.25);

  final text = base.textTheme.copyWith(
    headlineMedium: h(26),
    headlineSmall: h(22),
    titleLarge: h(19),
    titleMedium: TextStyle(fontFamily: body, fontSize: 15, fontWeight: heavy, color: c.ink),
    titleSmall: TextStyle(fontFamily: body, fontSize: 14, fontWeight: FontWeight.w600, color: c.ink),
    bodyLarge: TextStyle(fontFamily: body, fontSize: 15, color: c.ink, height: 1.5),
    bodyMedium: TextStyle(fontFamily: body, fontSize: 14, color: c.ink, height: 1.5),
    bodySmall: TextStyle(fontFamily: body, fontSize: 12.5, color: c.muted, height: 1.45),
    labelLarge: TextStyle(fontFamily: body, fontSize: 15, fontWeight: heavy),
    labelMedium: TextStyle(fontFamily: body, fontSize: 12, fontWeight: FontWeight.w600, color: c.muted),
    labelSmall: TextStyle(fontFamily: body, fontSize: 11, fontWeight: FontWeight.w700),
  );

  final buttonShape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(s.button));
  const tall = Size(64, 52);

  return base.copyWith(
    scaffoldBackgroundColor: c.page,
    textTheme: text,
    extensions: [c, s],
    appBarTheme: AppBarTheme(
      backgroundColor: c.page,
      foregroundColor: c.ink,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: h(20),
    ),
    cardTheme: CardThemeData(
      color: c.card,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: s.cardRadius,
        side: BorderSide(color: c.line),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(minimumSize: tall, shape: buttonShape, textStyle: text.labelLarge),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: tall,
        shape: buttonShape,
        foregroundColor: c.ink,
        side: BorderSide(color: c.line, width: 1.2),
        textStyle: text.labelLarge,
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(minimumSize: const Size(44, 44), textStyle: text.titleSmall),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: c.card,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(s.field),
        borderSide: const BorderSide(color: Color(0xFFD6D3D1), width: 1.5),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(s.field),
        borderSide: const BorderSide(color: Color(0xFFD6D3D1), width: 1.5),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(s.field),
        borderSide: BorderSide(color: branding.primary, width: 2),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(s.field),
        borderSide: BorderSide(color: branding.danger, width: 1.5),
      ),
      labelStyle: TextStyle(color: c.muted, fontFamily: body),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: c.card,
      indicatorColor: c.brandSoft,
      elevation: 0,
      height: 70,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (st) => TextStyle(
          fontFamily: body,
          fontSize: stage == Stage.kids ? 13 : 11.5,
          fontWeight: st.contains(WidgetState.selected) ? FontWeight.w800 : FontWeight.w600,
          color: st.contains(WidgetState.selected) ? c.brandDeep : c.muted,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (st) => IconThemeData(color: st.contains(WidgetState.selected) ? c.brandDeep : c.muted, size: stage == Stage.kids ? 28 : 24),
      ),
    ),
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: c.card,
      showDragHandle: true,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(s.card + 4))),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: c.card,
      selectedColor: c.brandSoft,
      side: BorderSide(color: c.line, width: 1.5),
      shape: const StadiumBorder(),
      labelStyle: TextStyle(fontFamily: body, fontWeight: FontWeight.w600, color: c.ink),
    ),
    dividerTheme: DividerThemeData(color: c.line, space: 1, thickness: 1),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: c.ink,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      contentTextStyle: TextStyle(fontFamily: body, color: Colors.white),
    ),
    // The kids' look keeps Material's own for these; the others share one finish.
    listTileTheme: kids
        ? null
        : ListTileThemeData(
            iconColor: c.muted,
            minVerticalPadding: 10,
            titleTextStyle: text.bodyLarge?.copyWith(fontWeight: FontWeight.w500),
            subtitleTextStyle: text.bodySmall,
          ),
    dialogTheme: kids
        ? null
        : DialogThemeData(
            backgroundColor: c.card,
            surfaceTintColor: Colors.transparent,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s.card)),
            titleTextStyle: h(20),
            contentTextStyle: text.bodyMedium?.copyWith(color: c.muted),
          ),
    segmentedButtonTheme: kids
        ? null
        : SegmentedButtonThemeData(
            style: SegmentedButton.styleFrom(
              selectedBackgroundColor: c.brandSoft,
              selectedForegroundColor: c.brandDeep,
              foregroundColor: c.muted,
              side: BorderSide(color: c.line),
              minimumSize: const Size(0, 44),
              textStyle: text.titleSmall,
            ),
          ),
    badgeTheme: kids
        ? null
        : BadgeThemeData(
            backgroundColor: c.danger,
            textColor: Colors.white,
            smallSize: 8,
            largeSize: 17,
            padding: const EdgeInsets.symmetric(horizontal: 5),
            textStyle: TextStyle(fontFamily: body, fontSize: 10.5, fontWeight: FontWeight.w700),
          ),
    iconButtonTheme: kids ? null : IconButtonThemeData(style: IconButton.styleFrom(minimumSize: const Size(48, 48))),
  );
}
