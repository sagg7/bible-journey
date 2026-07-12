import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ─────────────────────────────────────────────
// Design tokens — Bible Journey Visual System v1.0
// ─────────────────────────────────────────────

class BjColors {
  // Modern Deep
  static const surfacePrimary = Color(0xFF0B1220);
  static const surfaceRaised = Color(0xFF111A2E);
  static const surfaceCard = Color(0xFF18243A);
  static const surfaceBorder = Color(0xFF243050);

  // Accents
  static const accentPrimary = Color(0xFF6D4AFF); // violet — acciones + Ezra
  static const accentPrimaryMid = Color(0xFF8B6EFF);
  static const accentBronze = Color(0xFFBB8B14);
  static const accentBronzeLight = Color(0xFFD9A441);

  // Text on dark
  static const textPrimaryDark = Color(0xFFF1EDE6); // bone white
  static const textSecondaryDark = Color(0xFFB0B8CC);
  static const textMutedDark = Color(0xFF6B7A99);

  // Editorial Light
  static const surfaceReaderLight = Color(0xFFF8F6F0);
  static const surfaceReaderRaised = Color(0xFFEFEBE3);
  static const surfaceReaderPapyrus = Color(0xFFF4E8D0);
  static const surfaceReaderPapyrusRaised = Color(0xFFEADAB9);
  static const textPrimaryLight = Color(0xFF1A1612); // warm black
  static const textSecondaryLight = Color(0xFF4A3F34);
  static const textMutedLight = Color(0xFF8A7B6E);

  // Certainty
  static const certaintyHigh = Color(0xFF34A87A); // green
  static const certaintyProbable = Color(0xFFA76BF8); // lavender
  static const certaintyDebated = Color(0xFFD9A441); // amber
  static const certaintyUnresolved = Color(0xFF8892A4); // grey-blue
  static const certaintyTraditional = Color(0xFF8B6E5A); // warm brown

  // Status
  static const statusSuccess = Color(0xFF34A87A);
  static const statusWarning = Color(0xFFD9A441);
  static const statusDanger = Color(0xFFD94141);
}

const kReaderBackgroundDark = 'dark';
const kReaderBackgroundPapyrus = 'papyrus';

String resolveReaderBackground(String? saved, bool isSystemDark) {
  if (saved == kReaderBackgroundDark || saved == kReaderBackgroundPapyrus) {
    return saved!;
  }

  return isSystemDark ? kReaderBackgroundDark : kReaderBackgroundPapyrus;
}

bool isReaderBackgroundDark(String background) =>
    background == kReaderBackgroundDark;

Color readerBackgroundColor(String background) =>
    isReaderBackgroundDark(background)
    ? BjColors.surfacePrimary
    : BjColors.surfaceReaderPapyrus;

Color readerRaisedColor(String background) => isReaderBackgroundDark(background)
    ? BjColors.surfaceCard
    : BjColors.surfaceReaderPapyrusRaised;

Color readerTextColor(String background) => isReaderBackgroundDark(background)
    ? BjColors.textPrimaryDark
    : BjColors.textPrimaryLight;

// ─────────────────────────────────────────────
// Theme mode provider
// ─────────────────────────────────────────────

const _themePrefKey = 'bible_journey_theme';

class ThemeModeNotifier extends StateNotifier<ThemeMode> {
  ThemeModeNotifier() : super(ThemeMode.dark) {
    _load();
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    final val = prefs.getString(_themePrefKey);
    if (val == 'light') state = ThemeMode.light;
  }

  Future<void> toggle() async {
    state = state == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _themePrefKey,
      state == ThemeMode.dark ? 'dark' : 'light',
    );
  }

  Future<void> set(ThemeMode mode) async {
    state = mode;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _themePrefKey,
      mode == ThemeMode.dark ? 'dark' : 'light',
    );
  }
}

final themeModeProvider = StateNotifierProvider<ThemeModeNotifier, ThemeMode>(
  (ref) => ThemeModeNotifier(),
);

// ─────────────────────────────────────────────
// Modern Deep theme (dark)
// ─────────────────────────────────────────────

ThemeData get modernDeepTheme {
  final base = ThemeData.dark(useMaterial3: true);
  final textTheme = GoogleFonts.interTextTheme(base.textTheme).copyWith(
    bodyLarge: GoogleFonts.inter(
      color: BjColors.textPrimaryDark,
      fontSize: 16,
      height: 1.5,
    ),
    bodyMedium: GoogleFonts.inter(
      color: BjColors.textPrimaryDark,
      fontSize: 14,
      height: 1.5,
    ),
    bodySmall: GoogleFonts.inter(
      color: BjColors.textSecondaryDark,
      fontSize: 12,
    ),
    labelSmall: GoogleFonts.inter(
      color: BjColors.textMutedDark,
      fontSize: 11,
      letterSpacing: 0.5,
    ),
    titleLarge: GoogleFonts.inter(
      color: BjColors.textPrimaryDark,
      fontSize: 20,
      fontWeight: FontWeight.w600,
    ),
    titleMedium: GoogleFonts.inter(
      color: BjColors.textPrimaryDark,
      fontSize: 16,
      fontWeight: FontWeight.w600,
    ),
    titleSmall: GoogleFonts.inter(
      color: BjColors.textPrimaryDark,
      fontSize: 14,
      fontWeight: FontWeight.w600,
    ),
    headlineMedium: GoogleFonts.inter(
      color: BjColors.textPrimaryDark,
      fontSize: 24,
      fontWeight: FontWeight.w700,
    ),
    headlineSmall: GoogleFonts.inter(
      color: BjColors.textPrimaryDark,
      fontSize: 20,
      fontWeight: FontWeight.w700,
    ),
    labelLarge: GoogleFonts.inter(
      color: BjColors.textPrimaryDark,
      fontSize: 14,
      fontWeight: FontWeight.w500,
    ),
  );

  return base.copyWith(
    textTheme: textTheme,
    scaffoldBackgroundColor: BjColors.surfacePrimary,
    colorScheme: const ColorScheme.dark(
      surface: BjColors.surfacePrimary,
      surfaceContainer: BjColors.surfaceRaised,
      surfaceContainerHigh: BjColors.surfaceCard,
      primary: BjColors.accentPrimary,
      primaryContainer: Color(0xFF2A1F5E),
      onPrimary: Colors.white,
      onSurface: BjColors.textPrimaryDark,
      onSurfaceVariant: BjColors.textSecondaryDark,
      secondary: BjColors.accentBronze,
      tertiary: BjColors.certaintyHigh,
      outline: BjColors.surfaceBorder,
      outlineVariant: Color(0xFF1D2840),
      error: BjColors.statusDanger,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: BjColors.surfacePrimary,
      foregroundColor: BjColors.textPrimaryDark,
      elevation: 0,
      scrolledUnderElevation: 0,
      systemOverlayStyle: SystemUiOverlayStyle.light,
      titleTextStyle: GoogleFonts.inter(
        color: BjColors.textPrimaryDark,
        fontSize: 17,
        fontWeight: FontWeight.w600,
      ),
      iconTheme: const IconThemeData(
        color: BjColors.textSecondaryDark,
        size: 22,
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: BjColors.surfaceRaised,
      indicatorColor: BjColors.accentPrimary.withValues(alpha: 0.15),
      iconTheme: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) {
          return const IconThemeData(color: BjColors.accentPrimary, size: 22);
        }
        return const IconThemeData(color: BjColors.textMutedDark, size: 22);
      }),
      labelTextStyle: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return GoogleFonts.inter(
          fontSize: 11,
          fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
          color: selected ? BjColors.accentPrimary : BjColors.textMutedDark,
        );
      }),
      elevation: 0,
      shadowColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      height: 64,
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
    ),
    cardTheme: CardThemeData(
      color: BjColors.surfaceCard,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: BjColors.surfaceBorder, width: 0.5),
      ),
    ),
    dividerTheme: const DividerThemeData(
      color: BjColors.surfaceBorder,
      thickness: 0.5,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: BjColors.surfaceCard,
      hintStyle: GoogleFonts.inter(color: BjColors.textMutedDark, fontSize: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: BjColors.surfaceBorder),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: BjColors.surfaceBorder),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: BjColors.accentPrimary, width: 1.5),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: BjColors.accentPrimary,
        foregroundColor: Colors.white,
        textStyle: GoogleFonts.inter(fontWeight: FontWeight.w600, fontSize: 15),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        minimumSize: const Size(double.infinity, 48),
        elevation: 0,
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: BjColors.textPrimaryDark,
        side: const BorderSide(color: BjColors.surfaceBorder),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        textStyle: GoogleFonts.inter(fontWeight: FontWeight.w500, fontSize: 14),
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: BjColors.surfaceCard,
      side: const BorderSide(color: BjColors.surfaceBorder, width: 0.5),
      labelStyle: GoogleFonts.inter(
        color: BjColors.textSecondaryDark,
        fontSize: 12,
      ),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(
      color: BjColors.accentPrimary,
      linearTrackColor: BjColors.surfaceBorder,
    ),
  );
}

// ─────────────────────────────────────────────
// Editorial Light theme (light reader)
// ─────────────────────────────────────────────

ThemeData get editorialLightTheme {
  final base = ThemeData.light(useMaterial3: true);
  final textTheme = GoogleFonts.interTextTheme(base.textTheme).copyWith(
    bodyLarge: GoogleFonts.inter(
      color: BjColors.textPrimaryLight,
      fontSize: 16,
      height: 1.6,
    ),
    bodyMedium: GoogleFonts.inter(
      color: BjColors.textPrimaryLight,
      fontSize: 14,
      height: 1.6,
    ),
    bodySmall: GoogleFonts.inter(
      color: BjColors.textSecondaryLight,
      fontSize: 12,
    ),
    labelSmall: GoogleFonts.inter(
      color: BjColors.textMutedLight,
      fontSize: 11,
      letterSpacing: 0.5,
    ),
    titleLarge: GoogleFonts.inter(
      color: BjColors.textPrimaryLight,
      fontSize: 20,
      fontWeight: FontWeight.w600,
    ),
    titleMedium: GoogleFonts.inter(
      color: BjColors.textPrimaryLight,
      fontSize: 16,
      fontWeight: FontWeight.w600,
    ),
    titleSmall: GoogleFonts.inter(
      color: BjColors.textPrimaryLight,
      fontSize: 14,
      fontWeight: FontWeight.w600,
    ),
    headlineMedium: GoogleFonts.inter(
      color: BjColors.textPrimaryLight,
      fontSize: 24,
      fontWeight: FontWeight.w700,
    ),
    headlineSmall: GoogleFonts.inter(
      color: BjColors.textPrimaryLight,
      fontSize: 20,
      fontWeight: FontWeight.w700,
    ),
    labelLarge: GoogleFonts.inter(
      color: BjColors.textPrimaryLight,
      fontSize: 14,
      fontWeight: FontWeight.w500,
    ),
  );

  return base.copyWith(
    textTheme: textTheme,
    scaffoldBackgroundColor: BjColors.surfaceReaderLight,
    colorScheme: const ColorScheme.light(
      surface: BjColors.surfaceReaderLight,
      surfaceContainer: BjColors.surfaceReaderRaised,
      surfaceContainerHigh: Color(0xFFE8E2D8),
      primary: BjColors.accentPrimary,
      primaryContainer: Color(0xFFEDE8FF),
      onPrimary: Colors.white,
      onSurface: BjColors.textPrimaryLight,
      onSurfaceVariant: BjColors.textSecondaryLight,
      secondary: BjColors.accentBronze,
      tertiary: BjColors.certaintyHigh,
      outline: Color(0xFFD4CFC8),
      outlineVariant: Color(0xFFE8E2D8),
      error: BjColors.statusDanger,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: BjColors.surfaceReaderLight,
      foregroundColor: BjColors.textPrimaryLight,
      elevation: 0,
      scrolledUnderElevation: 0.5,
      shadowColor: const Color(0x14000000),
      systemOverlayStyle: SystemUiOverlayStyle.dark,
      titleTextStyle: GoogleFonts.inter(
        color: BjColors.textPrimaryLight,
        fontSize: 17,
        fontWeight: FontWeight.w600,
      ),
      iconTheme: const IconThemeData(
        color: BjColors.textSecondaryLight,
        size: 22,
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: BjColors.surfaceReaderLight,
      indicatorColor: BjColors.accentPrimary.withValues(alpha: 0.10),
      iconTheme: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) {
          return const IconThemeData(color: BjColors.accentPrimary, size: 22);
        }
        return const IconThemeData(color: BjColors.textMutedLight, size: 22);
      }),
      labelTextStyle: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return GoogleFonts.inter(
          fontSize: 11,
          fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
          color: selected ? BjColors.accentPrimary : BjColors.textMutedLight,
        );
      }),
      elevation: 0,
      surfaceTintColor: Colors.transparent,
      height: 64,
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
    ),
    cardTheme: CardThemeData(
      color: BjColors.surfaceReaderRaised,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: Color(0xFFD4CFC8), width: 0.5),
      ),
    ),
    dividerTheme: const DividerThemeData(
      color: Color(0xFFE0DAD0),
      thickness: 0.5,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: BjColors.surfaceReaderRaised,
      hintStyle: GoogleFonts.inter(
        color: BjColors.textMutedLight,
        fontSize: 14,
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFD4CFC8)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFD4CFC8)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: BjColors.accentPrimary, width: 1.5),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: BjColors.accentPrimary,
        foregroundColor: Colors.white,
        textStyle: GoogleFonts.inter(fontWeight: FontWeight.w600, fontSize: 15),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        minimumSize: const Size(double.infinity, 48),
        elevation: 0,
      ),
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(
      color: BjColors.accentPrimary,
      linearTrackColor: Color(0xFFD4CFC8),
    ),
  );
}

// ─────────────────────────────────────────────
// Scripture reading fonts
// ─────────────────────────────────────────────

/// Font choices offered in the text-display sheet, ordered as shown to the
/// user. Keys are persisted in [LocalProgress.fontFamily].
const kScriptureFonts = <String, String>{
  'lora': 'Lora (serif clásica)',
  'merriweather': 'Merriweather (serif)',
  'inter': 'Inter (sans)',
  'atkinson': 'Atkinson Hyperlegible (alta legibilidad)',
};

const kDefaultScriptureFont = 'lora';

// Alias for reader typography — font family is user-selectable (see
// kScriptureFonts); Lora (serif) remains the default to match the Editorial
// Light visual system.
TextStyle scriptureTextStyle({
  double fontSize = 17,
  double height = 1.75,
  String fontFamily = kDefaultScriptureFont,
}) {
  final base = TextStyle(
    fontSize: fontSize,
    height: height,
    color: BjColors.textPrimaryLight,
    fontWeight: FontWeight.w400,
  );
  switch (fontFamily) {
    case 'merriweather':
      return GoogleFonts.merriweather(textStyle: base);
    case 'inter':
      return GoogleFonts.inter(textStyle: base);
    case 'atkinson':
      return GoogleFonts.atkinsonHyperlegible(textStyle: base);
    case 'lora':
    default:
      return GoogleFonts.lora(textStyle: base);
  }
}

TextStyle scriptureVerseStyle() => GoogleFonts.inter(
  fontSize: 11,
  fontWeight: FontWeight.w600,
  color: BjColors.textMutedLight,
  letterSpacing: 0.3,
);

// ─────────────────────────────────────────────
// CertaintyBadge — shared component
// ─────────────────────────────────────────────

String translateCertainty(String? label) {
  if (label == null) return '';
  const map = {
    'high': 'Alta confianza',
    'probable': 'Probable',
    'debated': 'Debatido',
    'unresolved': 'Sin resolver',
    'traditional': 'Tradicional',
    'speculative': 'Especulativo',
    'alta': 'Alta confianza',
    'alta confianza': 'Alta confianza',
    'debatida': 'Debatido',
    'sin resolver': 'Sin resolver',
  };
  return map[label.toLowerCase()] ?? label;
}

Color certaintyColor(String? label) {
  if (label == null) return BjColors.certaintyUnresolved;
  final l = label.toLowerCase();
  if (l.contains('alta') || l.contains('high')) return BjColors.certaintyHigh;
  if (l.contains('probable')) return BjColors.certaintyProbable;
  if (l.contains('debat')) return BjColors.certaintyDebated;
  if (l.contains('tradici') || l.contains('popular')) {
    return BjColors.certaintyTraditional;
  }
  if (l.contains('especulat') || l.contains('speculat')) {
    return BjColors.statusDanger;
  }
  return BjColors.certaintyUnresolved;
}

IconData certaintyIcon(String? label) {
  if (label == null) return Icons.help_outline;
  final l = label.toLowerCase();
  if (l.contains('alta') || l.contains('high')) {
    return Icons.check_circle_outline;
  }
  if (l.contains('probable')) return Icons.radio_button_checked;
  if (l.contains('debat')) return Icons.balance;
  if (l.contains('tradici') || l.contains('popular')) return Icons.history_edu;
  return Icons.help_outline;
}

class CertaintyBadge extends StatelessWidget {
  final String? label;
  final bool compact;
  const CertaintyBadge({super.key, required this.label, this.compact = false});

  @override
  Widget build(BuildContext context) {
    if (label == null) return const SizedBox.shrink();
    final displayLabel = translateCertainty(label);
    final color = certaintyColor(label);
    final icon = certaintyIcon(label);
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 6 : 8,
        vertical: compact ? 2 : 3,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.35), width: 0.5),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: compact ? 10 : 12, color: color),
          const SizedBox(width: 4),
          Text(
            displayLabel,
            style: TextStyle(
              color: color,
              fontSize: compact ? 10 : 11,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────
// Section label (uppercase small)
// ─────────────────────────────────────────────

class BjSectionLabel extends StatelessWidget {
  final String text;
  const BjSectionLabel(this.text, {super.key});

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: GoogleFonts.inter(
        fontSize: 11,
        fontWeight: FontWeight.w700,
        letterSpacing: 1.2,
        color: Theme.of(context).brightness == Brightness.dark
            ? BjColors.textMutedDark
            : BjColors.textMutedLight,
      ),
    );
  }
}
