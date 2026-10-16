import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

/// OCTA FIT color tokens — final global identity.
/// Primary Indigo #5B6CFF · AI muted Burgundy accent (~10%) · MMA Brick #A9473D
/// Dark graphite surfaces. Matte, no neon, no glow, no pink/magenta.

class SurfaceColors {
  SurfaceColors._();
  static const Color background = Color(0xFF0B0C10);
  static const Color backgroundSecondary = Color(0xFF101116);
  static const Color card = Color(0xFF15161D);
  static const Color elevated = Color(0xFF1B1C24);
  static const Color floating = Color(0xFF22232C);
  static const Color navigation = Color(0xFF0D0E13);
  /// AI surfaces stay graphite — only a whisper of warmth (~10%).
  static const Color aiPanel = Color(0xFF15161D);
  static const Color aiElevated = Color(0xFF1B171A);
  static const Color border = Color(0x14FFFFFF);
  static const Color borderSubtle = Color(0x14FFFFFF);
  static const Color borderStrong = Color(0x24FFFFFF);
  static const Color divider = Color(0x14FFFFFF);
}

class TextColors {
  TextColors._();
  static const Color primary = Color(0xFFF3F4F6);
  static const Color secondary = Color(0xFFA7A9B2);
  static const Color muted = Color(0xFF777A84);
  static const Color disabled = Color(0xFF555861);
  /// Light label on filled Indigo / AI / MMA accents.
  static const Color inverse = Color(0xFFF3F4F6);
  static const Color navInactive = Color(0xFF777A84);
}

class BrandColors {
  BrandColors._();
  /// Main OCTA FIT accent — Indigo.
  static const Color primary = Color(0xFF5B6CFF);
  static const Color primaryHover = Color(0xFF7B88FF);
  static const Color primaryPressed = Color(0xFF4656D9);
  static const Color primaryDark = Color(0xFF222A4A);
  static const Color primarySoft = Color(0x335B6CFF);
  static const Color primaryTint = Color(0x285B6CFF);
  /// Legacy alias → Indigo (no separate tech-blue identity).
  static const Color accentBlue = primary;
  static const Color accentBlueBright = primaryHover;
}

class SectionColors {
  SectionColors._();
  static const Color fit = BrandColors.primary;
  /// AI accent only — muted burgundy for icons / tiny accents (~10% influence).
  /// Never use these for body text or large fills.
  static const Color ai = Color(0xFF8E403A);
  static const Color aiAccent = Color(0xFF8E403A);
  static const Color aiHover = Color(0xFF8E403A);
  static const Color aiDark = Color(0xFF1B171A);
  static const Color aiPressed = Color(0xFF17181E);
  static const Color aiSoft = Color(0x1A8E403A);
  static const Color aiTint = Color(0x148E403A);
  /// Dedicated MMA accent — Brick.
  static const Color mma = Color(0xFFA9473D);
  static const Color mmaHover = Color(0xFFC45A4E);
  static const Color mmaDark = Color(0xFF5C2822);
  static const Color mmaPressed = mmaDark;
  static const Color mmaSoft = Color(0x33A9473D);
  static const Color mmaTint = Color(0x29A9473D);
}

class StateColors {
  StateColors._();
  static const Color success = Color(0xFF3DAB7E);
  static const Color warning = Color(0xFFD4A24A);
  static const Color error = Color(0xFFD45454);
  static const Color info = BrandColors.primary;
}

/// Semantic aliases for the OCTA FIT design system.
class OCTAColors {
  OCTAColors._();
  static const Color background = SurfaceColors.background;
  static const Color backgroundSecondary = SurfaceColors.backgroundSecondary;
  static const Color navigation = SurfaceColors.navigation;
  static const Color surface = SurfaceColors.card;
  static const Color surfaceElevated = SurfaceColors.elevated;
  static const Color border = SurfaceColors.border;
  static const Color divider = SurfaceColors.divider;
  static const Color primary = BrandColors.primary;
  static const Color primaryBright = BrandColors.primaryHover;
  static const Color primarySoft = BrandColors.primarySoft;
  static const Color primaryDark = BrandColors.primaryDark;
  static const Color primaryTint = BrandColors.primaryTint;
  static const Color accentBlue = BrandColors.accentBlue;
  static const Color accentBlueBright = BrandColors.accentBlueBright;
  static const Color ai = SectionColors.ai;
  static const Color aiAccent = SectionColors.aiAccent;
  static const Color aiBright = SectionColors.aiHover;
  static const Color aiSoft = SectionColors.aiSoft;
  static const Color aiDark = SectionColors.aiDark;
  static const Color aiTint = SectionColors.aiTint;
  static const Color aiSurface = SurfaceColors.aiPanel;
  static const Color aiElevated = SurfaceColors.aiElevated;
  static const Color mma = SectionColors.mma;
  static const Color mmaBright = SectionColors.mmaHover;
  static const Color mmaSoft = SectionColors.mmaSoft;
  static const Color mmaDark = SectionColors.mmaDark;
  static const Color mmaTint = SectionColors.mmaTint;
  static const Color textPrimary = TextColors.primary;
  static const Color textSecondary = TextColors.secondary;
  static const Color textMuted = TextColors.muted;
  static const Color navInactive = TextColors.navInactive;
  static const Color success = StateColors.success;
  static const Color warning = StateColors.warning;
  static const Color error = StateColors.error;
  static const Color info = StateColors.info;
  static const Color onPrimary = TextColors.inverse;
}

/// Compatibility facade used across existing widgets.
class AppColors {
  AppColors._();

  static const Color background = SurfaceColors.background;
  static const Color backgroundSecondary = SurfaceColors.backgroundSecondary;
  static const Color navigation = SurfaceColors.navigation;
  static const Color surface = SurfaceColors.card;
  static const Color elevated = SurfaceColors.elevated;
  static const Color overlay = SurfaceColors.floating;
  static const Color border = SurfaceColors.border;
  static const Color borderStrong = SurfaceColors.borderStrong;
  static const Color divider = SurfaceColors.divider;
  static const Color surfaceAlt = SurfaceColors.elevated;
  static const Color secondary = SurfaceColors.elevated;

  static const Color primary = BrandColors.primary;
  static const Color primaryHover = BrandColors.primaryHover;
  static const Color primaryPressed = BrandColors.primaryPressed;
  static const Color primarySoft = BrandColors.primarySoft;
  static const Color primaryTint = BrandColors.primaryTint;
  static const Color primaryDeep = BrandColors.primaryDark;
  static const Color primaryDark = BrandColors.primaryDark;
  static const Color onPrimary = TextColors.inverse;
  static const Color accentBlue = BrandColors.accentBlue;
  static const Color accentBlueBright = BrandColors.accentBlueBright;
  static const Color aiSurface = SurfaceColors.aiPanel;
  static const Color aiElevated = SurfaceColors.aiElevated;

  static const Color ai = SectionColors.ai;
  static const Color aiAccent = SectionColors.aiAccent;
  static const Color aiHover = SectionColors.aiHover;
  static const Color aiPressed = SectionColors.aiPressed;
  static const Color aiSoft = SectionColors.aiSoft;
  static const Color aiDark = SectionColors.aiDark;
  static const Color aiTint = SectionColors.aiTint;

  static const Color mma = SectionColors.mma;
  static const Color mmaHover = SectionColors.mmaHover;
  static const Color mmaPressed = SectionColors.mmaPressed;
  static const Color mmaSoft = SectionColors.mmaSoft;
  static const Color mmaDark = SectionColors.mmaDark;
  static const Color mmaTint = SectionColors.mmaTint;

  static const Color textPrimary = TextColors.primary;
  static const Color textSecondary = TextColors.secondary;
  static const Color textMuted = TextColors.muted;
  static const Color textDisabled = TextColors.disabled;
  static const Color navInactive = TextColors.navInactive;

  static const Color success = StateColors.success;
  static const Color warning = StateColors.warning;
  static const Color danger = StateColors.error;
  static const Color info = StateColors.info;

  /// Label color for filled accents — always light on Indigo / AI / MMA.
  static Color foregroundOn(Color fill) {
    return textPrimary;
  }
}

class AppSpacing {
  AppSpacing._();
  static const double xxs = 4;
  static const double xs = 4;
  static const double sm = 8;
  static const double md12 = 12;
  static const double md = 16;
  static const double lg = 24;
  static const double xl = 32;
  static const double xxl = 48;
  static const double huge = 64;
  static const double section = 48;
  static const double page = 16;
}

class AppRadii {
  AppRadii._();
  static const double xs = 8;
  static const double sm = 8;
  static const double md = 14;
  static const double lg = 20;
  static const double xl = 20;
  static const double full = 999;
}

class AppElevation {
  AppElevation._();

  /// Level 0 — canvas
  /// Level 1 — card (color only)
  /// Level 2 — elevated
  /// Level 3 — floating (dialog/sheet)
  static List<BoxShadow> level1 = const [];
  static List<BoxShadow> level2 = [
    BoxShadow(color: Color(0x33000000), blurRadius: 18, offset: Offset(0, 8)),
  ];
  /// Neutral matte depth only — no colored glow emission.
  static List<BoxShadow> matte = [
    BoxShadow(color: Color(0x2E000000), blurRadius: 18, offset: Offset(0, 8)),
  ];
  static List<BoxShadow> brand = matte;
  static List<BoxShadow> ai = matte;
  static List<BoxShadow> coral = matte;
  static List<BoxShadow> level3 = [
    BoxShadow(color: Color(0x4D000000), blurRadius: 32, offset: Offset(0, 16)),
  ];
}

class AppShadows {
  AppShadows._();
  static List<BoxShadow> soft = AppElevation.level3;
  static List<BoxShadow> lift = AppElevation.level2;
  static List<BoxShadow> matte = AppElevation.matte;
  static List<BoxShadow> brand = AppElevation.matte;
  static List<BoxShadow> coral = AppElevation.matte;
  static List<BoxShadow> ai = AppElevation.matte;
}

/// OCTA FIT card gradient system.
///
/// Intensity rules (do not mix):
/// - **AI Center** (Hub / Coach / Physio / Motion): ~**10%** accent influence.
/// - **All other feature cards**: ~**30%** feature-color influence.
///
/// Cards must stay primarily dark graphite. Never fully colored rectangles.
/// Never neon, glow, or bloom. Typography stays white/gray — accents are for
/// icons / tiny interactive states only.
///
/// Future feature cards MUST reuse these semantic gradients (or
/// [AppFeatureCardSurface]) instead of inventing local LinearGradients.
class AppGradients {
  AppGradients._();

  /// Solid Indigo CTA fill (buttons only — not card backgrounds).
  static const LinearGradient brand = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF5B6CFF), Color(0xFF4656D9)],
  );

  /// Progress-bar fill (Indigo). Not a card surface.
  static const LinearGradient hydration = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: [Color(0xFF5B6CFF), Color(0xFF4656D9)],
  );

  // ---------------------------------------------------------------------------
  // Non-AI feature cards — ~30% feature-color influence
  // ---------------------------------------------------------------------------

  /// Primary OCTA FIT / Training / Store / Community / Hydration cards (~30% Indigo).
  /// Dark graphite → muted Indigo-tinted graphite. Remains dark & matte.
  static const LinearGradient primaryCard = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF15161D), Color(0xFF1C2035), Color(0xFF252C55)],
    stops: [0.0, 0.55, 1.0],
  );

  static const LinearGradient trainingCard = primaryCard;
  static const LinearGradient storeCard = primaryCard;
  static const LinearGradient communityCard = primaryCard;
  static const LinearGradient hydrationCard = primaryCard;

  /// MMA cards (~30% Brick). Dark graphite → muted brick-tinted graphite.
  static const LinearGradient mmaCard = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF15161D), Color(0xFF241816), Color(0xFF3A221E)],
    stops: [0.0, 0.55, 1.0],
  );

  // ---------------------------------------------------------------------------
  // AI Center — ~10% Burgundy influence ONLY
  // ---------------------------------------------------------------------------

  /// AI Hub / Coach / Physio / Motion cards (~10% Burgundy). Nearly graphite.
  static const LinearGradient aiCard = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF15161D), Color(0xFF17181E), Color(0xFF1B171A)],
    stops: [0.0, 0.55, 1.0],
  );

  // ---------------------------------------------------------------------------
  // Compatibility aliases (prefer semantic names above in new code)
  // ---------------------------------------------------------------------------

  /// @Deprecated Prefer [primaryCard].
  static const LinearGradient coralHero = primaryCard;

  /// @Deprecated Prefer [primaryCard].
  static const LinearGradient featureCard = primaryCard;

  /// @Deprecated Prefer [aiCard].
  static const LinearGradient aiHero = aiCard;

  /// @Deprecated Prefer [mmaCard].
  static const LinearGradient mmaHero = mmaCard;
}

class AppFeatureCardSurface {
  AppFeatureCardSurface._();

  /// Non-AI feature card (~30% Indigo) — default for Home / Store / Community / Hydration.
  static BoxDecoration decoration({
    required BorderRadius borderRadius,
    LinearGradient? gradient,
  }) {
    return BoxDecoration(
      color: AppColors.surface,
      borderRadius: borderRadius,
      border: Border.all(color: AppColors.border),
      boxShadow: AppShadows.matte,
      gradient: gradient ?? AppGradients.primaryCard,
    );
  }

  /// Explicit primary / Training / Store / Community / Hydration card (~30% Indigo).
  static BoxDecoration primary({required BorderRadius borderRadius}) =>
      decoration(borderRadius: borderRadius, gradient: AppGradients.primaryCard);

  /// MMA card (~30% Brick).
  static BoxDecoration mma({required BorderRadius borderRadius}) =>
      decoration(borderRadius: borderRadius, gradient: AppGradients.mmaCard);

  /// AI Center card (~10% Burgundy). Never use for non-AI surfaces.
  static BoxDecoration ai({required BorderRadius borderRadius}) =>
      decoration(borderRadius: borderRadius, gradient: AppGradients.aiCard);
}

class AppBreakpoints {
  AppBreakpoints._();
  static const double compact = 720;
  static const double medium = 1024;
  static const double expanded = 1280;
  static const double wide = 1600;

  static bool isCompact(BuildContext context) =>
      MediaQuery.sizeOf(context).width < compact;
  static bool isMedium(BuildContext context) {
    final w = MediaQuery.sizeOf(context).width;
    return w >= compact && w < expanded;
  }

  static bool isExpanded(BuildContext context) =>
      MediaQuery.sizeOf(context).width >= expanded;
  static bool useSidebar(BuildContext context) =>
      MediaQuery.sizeOf(context).width >= medium;
}

class AppMotion {
  AppMotion._();
  static const Duration fast = Duration(milliseconds: 150);
  static const Duration standard = Duration(milliseconds: 250);
  static const Duration emphasize = Duration(milliseconds: 400);
  static const Curve ease = Cubic(0.4, 0.0, 0.2, 1.0);
  static const Curve decelerate = Cubic(0.0, 0.0, 0.2, 1.0);
  static const Curve accelerate = Cubic(0.4, 0.0, 1.0, 1.0);
}

class AppIconSizes {
  AppIconSizes._();
  static const double sm = 16;
  static const double md = 20;
  static const double lg = 24;
  static const double xl = 32;
}

class AppText {
  AppText._();

  static TextTheme theme() {
    final display = GoogleFonts.manropeTextTheme();
    final body = GoogleFonts.interTextTheme();

    TextStyle d(
      TextStyle? s, {
      double? size,
      FontWeight? w,
      Color? c,
      double? ls,
      double? h,
    }) => (s ?? const TextStyle()).copyWith(
      fontSize: size,
      fontWeight: w,
      color: c ?? TextColors.primary,
      letterSpacing: ls,
      height: h,
    );

    return body.copyWith(
      displayLarge: d(
        display.displayLarge,
        size: 40,
        w: FontWeight.w800,
        ls: -1.2,
        h: 1.1,
      ),
      displayMedium: d(
        display.displayMedium,
        size: 32,
        w: FontWeight.w800,
        ls: -0.8,
        h: 1.15,
      ),
      displaySmall: d(
        display.displaySmall,
        size: 28,
        w: FontWeight.w700,
        ls: -0.5,
        h: 1.2,
      ),
      headlineLarge: d(
        display.headlineLarge,
        size: 24,
        w: FontWeight.w700,
        ls: -0.3,
      ),
      headlineMedium: d(display.headlineMedium, size: 20, w: FontWeight.w700),
      headlineSmall: d(display.headlineSmall, size: 18, w: FontWeight.w600),
      titleLarge: d(body.titleLarge, size: 17, w: FontWeight.w600),
      titleMedium: d(body.titleMedium, size: 15, w: FontWeight.w600),
      titleSmall: d(
        body.titleSmall,
        size: 12,
        w: FontWeight.w700,
        c: TextColors.secondary,
        ls: 0.6,
      ),
      bodyLarge: d(body.bodyLarge, size: 16, w: FontWeight.w400, h: 1.5),
      bodyMedium: d(
        body.bodyMedium,
        size: 14,
        w: FontWeight.w400,
        c: TextColors.secondary,
        h: 1.45,
      ),
      bodySmall: d(
        body.bodySmall,
        size: 13,
        w: FontWeight.w400,
        c: TextColors.secondary,
        h: 1.4,
      ),
      labelLarge: d(body.labelLarge, size: 14, w: FontWeight.w700),
      labelMedium: d(
        body.labelMedium,
        size: 12,
        w: FontWeight.w600,
        c: TextColors.secondary,
      ),
      labelSmall: d(
        body.labelSmall,
        size: 11,
        w: FontWeight.w600,
        c: TextColors.secondary,
        ls: 0.3,
      ),
    );
  }

  static TextStyle statistic(
    BuildContext context, {
    double size = 28,
    Color? color,
  }) {
    return GoogleFonts.jetBrainsMono(
      fontSize: size,
      fontWeight: FontWeight.w700,
      color: color ?? TextColors.primary,
      height: 1.1,
      letterSpacing: -0.6,
      fontFeatures: const [FontFeature.tabularFigures()],
    );
  }
}

Color _primaryButtonFill(Set<WidgetState> states) {
  if (states.contains(WidgetState.disabled)) {
    return SurfaceColors.elevated;
  }
  if (states.contains(WidgetState.pressed)) {
    return BrandColors.primaryPressed;
  }
  if (states.contains(WidgetState.hovered)) {
    return BrandColors.primaryHover;
  }
  return BrandColors.primary;
}

Color _primaryButtonLabel(Set<WidgetState> states) {
  if (states.contains(WidgetState.disabled)) {
    return TextColors.disabled;
  }
  return TextColors.inverse;
}

class AppTheme {
  AppTheme._();

  static ThemeData dark() {
    final text = AppText.theme();
    const scheme = ColorScheme.dark(
      primary: BrandColors.primary,
      onPrimary: TextColors.inverse,
      secondary: SectionColors.ai,
      onSecondary: TextColors.inverse,
      tertiary: SectionColors.mma,
      onTertiary: TextColors.inverse,
      surface: SurfaceColors.card,
      onSurface: TextColors.primary,
      error: StateColors.error,
      onError: TextColors.primary,
      outline: SurfaceColors.border,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: scheme,
      scaffoldBackgroundColor: SurfaceColors.background,
      textTheme: text,
      primaryColor: BrandColors.primary,
      dividerColor: SurfaceColors.border,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: SurfaceColors.background,
        foregroundColor: TextColors.primary,
        systemOverlayStyle: SystemUiOverlayStyle.light,
        titleTextStyle: text.titleLarge,
        iconTheme: const IconThemeData(color: TextColors.primary, size: 22),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style:
            ElevatedButton.styleFrom(
              backgroundColor: BrandColors.primary,
              foregroundColor: TextColors.inverse,
              disabledBackgroundColor: SurfaceColors.elevated,
              disabledForegroundColor: TextColors.disabled,
              minimumSize: const Size(48, 48),
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppRadii.md),
              ),
              textStyle: text.labelLarge,
            ).copyWith(
              backgroundColor: WidgetStateProperty.resolveWith(
                _primaryButtonFill,
              ),
              foregroundColor: WidgetStateProperty.resolveWith(
                _primaryButtonLabel,
              ),
            ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style:
            FilledButton.styleFrom(
              backgroundColor: BrandColors.primary,
              foregroundColor: TextColors.inverse,
              minimumSize: const Size(48, 48),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppRadii.md),
              ),
            ).copyWith(
              backgroundColor: WidgetStateProperty.resolveWith(
                _primaryButtonFill,
              ),
              foregroundColor: WidgetStateProperty.resolveWith(
                _primaryButtonLabel,
              ),
            ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: TextColors.primary,
          minimumSize: const Size(48, 48),
          side: const BorderSide(color: SurfaceColors.border),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.md),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: BrandColors.primaryHover,
          textStyle: text.labelLarge,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: SurfaceColors.card,
        hintStyle: text.bodyMedium,
        labelStyle: text.bodyMedium,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
          borderSide: const BorderSide(color: SurfaceColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
          borderSide: const BorderSide(color: SurfaceColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
          borderSide: const BorderSide(color: BrandColors.primary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
          borderSide: const BorderSide(color: StateColors.error),
        ),
      ),
      cardTheme: CardThemeData(
        color: SurfaceColors.card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: SurfaceColors.elevated,
        selectedColor: BrandColors.primaryTint,
        labelStyle: text.labelMedium!,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.full),
          side: const BorderSide(color: SurfaceColors.border),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: SurfaceColors.elevated,
        contentTextStyle: text.bodyMedium?.copyWith(color: TextColors.primary),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: SurfaceColors.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.lg),
        ),
        titleTextStyle: text.headlineSmall,
        contentTextStyle: text.bodyMedium,
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: SurfaceColors.floating,
        modalBackgroundColor: SurfaceColors.floating,
        showDragHandle: true,
        dragHandleColor: SurfaceColors.border,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: SurfaceColors.navigation,
        indicatorColor: BrandColors.primaryTint,
        elevation: 0,
        height: 68,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final on = states.contains(WidgetState.selected);
          return text.labelSmall?.copyWith(
            color: on ? BrandColors.primary : TextColors.navInactive,
            fontWeight: on ? FontWeight.w700 : FontWeight.w500,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final on = states.contains(WidgetState.selected);
          return IconThemeData(
            color: on ? BrandColors.primary : TextColors.navInactive,
            size: 22,
          );
        }),
      ),
      navigationRailTheme: NavigationRailThemeData(
        backgroundColor: SurfaceColors.navigation,
        indicatorColor: BrandColors.primaryTint,
        selectedIconTheme: const IconThemeData(
          color: BrandColors.primary,
          size: 22,
        ),
        unselectedIconTheme: const IconThemeData(
          color: TextColors.navInactive,
          size: 22,
        ),
        selectedLabelTextStyle: text.labelMedium?.copyWith(
          color: BrandColors.primary,
        ),
        unselectedLabelTextStyle: text.labelMedium?.copyWith(
          color: TextColors.navInactive,
        ),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: BrandColors.primary,
        circularTrackColor: SurfaceColors.elevated,
      ),
      dividerTheme: const DividerThemeData(
        color: Colors.transparent,
        thickness: 0,
        space: 0,
      ),
      listTileTheme: ListTileThemeData(
        iconColor: TextColors.secondary,
        textColor: TextColors.primary,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
        ),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: BrandColors.primary,
        foregroundColor: TextColors.inverse,
        elevation: 0,
      ),
      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: SurfaceColors.floating,
          borderRadius: BorderRadius.circular(AppRadii.sm),
          border: Border.all(color: SurfaceColors.border),
        ),
        textStyle: text.bodySmall?.copyWith(color: TextColors.primary),
      ),
    );
  }

  static ThemeData light() => dark();
}
