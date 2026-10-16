import 'dart:math' as math;

import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_body_viewer.dart';

/// Premium Training gateway Hero for Home.
///
/// The sculptural athlete figure is ALWAYS visible and scales with width.
/// Never hidden below a breakpoint.
class HomeTrainingHero extends StatefulWidget {
  const HomeTrainingHero({required this.onStartTraining, super.key});

  final VoidCallback onStartTraining;

  @override
  State<HomeTrainingHero> createState() => _HomeTrainingHeroState();
}

class _HeroMetrics {
  const _HeroMetrics({
    required this.figureSize,
    required this.figureFlex,
    required this.contentFlex,
    required this.gap,
    required this.padding,
    required this.compact,
    required this.showDepthLine,
    required this.floatAmplitude,
  });

  final double figureSize;
  final int figureFlex;
  final int contentFlex;
  final double gap;
  final EdgeInsets padding;
  final bool compact;
  final bool showDepthLine;
  final double floatAmplitude;

  /// Responsive sizing — figure never drops to zero / invisible.
  factory _HeroMetrics.fromWidth(double width) {
    // Figure size: ratio of available width, clamped to a recognizable min/max.
    final raw =
        width *
        (width >= 1100
            ? 0.22
            : width >= 900
            ? 0.24
            : width >= 720
            ? 0.26
            : width >= 480
            ? 0.28
            : 0.26);
    final figureSize = raw.clamp(64.0, 230.0);

    if (width >= 1100) {
      return _HeroMetrics(
        figureSize: figureSize,
        figureFlex: 38,
        contentFlex: 62,
        gap: AppSpacing.lg,
        padding: const EdgeInsets.all(AppSpacing.xl),
        compact: false,
        showDepthLine: false,
        floatAmplitude: 5,
      );
    }
    if (width >= 720) {
      return _HeroMetrics(
        figureSize: figureSize,
        figureFlex: 32,
        contentFlex: 68,
        gap: AppSpacing.md,
        padding: const EdgeInsets.all(AppSpacing.lg),
        compact: false,
        showDepthLine: false,
        floatAmplitude: 4,
      );
    }
    if (width >= 480) {
      return _HeroMetrics(
        figureSize: figureSize,
        figureFlex: 28,
        contentFlex: 72,
        gap: AppSpacing.sm,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.lg,
        ),
        compact: true,
        showDepthLine: false,
        floatAmplitude: 2.5,
      );
    }
    // Very narrow (incl. ~320–430)
    return _HeroMetrics(
      figureSize: figureSize.clamp(64.0, 90.0),
      figureFlex: 26,
      contentFlex: 74,
      gap: AppSpacing.sm,
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.md,
      ),
      compact: true,
      showDepthLine: false,
      floatAmplitude: 0,
    );
  }
}

class _HomeTrainingHeroState extends State<HomeTrainingHero>
    with SingleTickerProviderStateMixin {
  late final AnimationController _enter = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 720),
  );

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final reduce = MediaQuery.disableAnimationsOf(context);
      if (reduce) {
        _enter.value = 1;
      } else {
        _enter.forward();
      }
    });
  }

  @override
  void dispose() {
    _enter.dispose();
    super.dispose();
  }

  TextStyle? _headlineStyle(BuildContext context, double width) {
    final theme = Theme.of(context).textTheme;
    if (width < 380) return theme.titleLarge;
    if (width < 480) return theme.headlineSmall;
    if (width < 720) return theme.headlineMedium;
    if (width < 1100) return theme.displaySmall;
    return theme.displayMedium;
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : MediaQuery.sizeOf(context).width;
        final metrics = _HeroMetrics.fromWidth(width);

        final content = FadeTransition(
          opacity: CurvedAnimation(parent: _enter, curve: Curves.easeOutCubic),
          child: SlideTransition(
            position:
                Tween<Offset>(
                  begin: const Offset(0, 0.04),
                  end: Offset.zero,
                ).animate(
                  CurvedAnimation(parent: _enter, curve: Curves.easeOutCubic),
                ),
            // Content first, figure second → LTR: content left / figure right
            // RTL: content right / figure left (Directionality-aware).
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  flex: metrics.contentFlex,
                  child: _HeroCopy(
                    headlineStyle: _headlineStyle(context, width),
                    onStartTraining: widget.onStartTraining,
                    compact: metrics.compact,
                    showSupporting: width >= 360,
                    secondaryStyle: width < 400
                        ? Theme.of(context).textTheme.bodyMedium
                        : Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                SizedBox(width: metrics.gap),
                Expanded(
                  flex: metrics.figureFlex,
                  child: Align(
                    alignment: Alignment.center,
                    child: _HeroAthleteVisual(
                      size: metrics.figureSize,
                      floatAmplitude: metrics.floatAmplitude,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );

        return DecoratedBox(
          decoration: BoxDecoration(
            color: AppColors.elevated,
            borderRadius: BorderRadius.circular(AppRadii.xl),
            border: Border.all(color: AppColors.border),
            boxShadow: AppShadows.brand,
            gradient: AppGradients.primaryCard,
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(AppRadii.xl),
            child: Stack(
              clipBehavior: Clip.hardEdge,
              children: [
                if (metrics.showDepthLine)
                  Positioned.fill(
                    child: IgnorePointer(
                      child: CustomPaint(painter: _HeroDepthPainter()),
                    ),
                  ),
                Padding(padding: metrics.padding, child: content),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _HeroCopy extends StatelessWidget {
  const _HeroCopy({
    required this.headlineStyle,
    required this.onStartTraining,
    required this.compact,
    required this.showSupporting,
    required this.secondaryStyle,
  });

  final TextStyle? headlineStyle;
  final VoidCallback onStartTraining;
  final bool compact;
  final bool showSupporting;
  final TextStyle? secondaryStyle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'home_hero_eyebrow'.tr(namedArgs: {'brand': AppBrand.name}),
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
            color: AppColors.primaryHover,
            letterSpacing: compact ? 1.1 : 1.6,
            fontWeight: FontWeight.w700,
            fontSize: compact ? 11 : null,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        SizedBox(height: compact ? AppSpacing.sm : AppSpacing.lg),
        Text(
          'home_hero_title'.tr(),
          style: headlineStyle?.copyWith(
            height: 1.15,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'home_hero_secondary'.tr(),
          style: secondaryStyle?.copyWith(
            color: AppColors.textSecondary,
            height: 1.35,
          ),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        if (showSupporting) ...[
          const SizedBox(height: AppSpacing.sm),
          Text(
            'home_hero_supporting'.tr(),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary.withValues(alpha: 0.9),
              height: 1.4,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ],
        SizedBox(height: compact ? AppSpacing.md : AppSpacing.xl),
        Semantics(
          button: true,
          label: 'home_cta_train'.tr(),
          child: AppPrimaryButton(
            labelKey: 'home_cta_train',
            onPressed: onStartTraining,
            icon: Icons.fitness_center_rounded,
            expand: false,
          ),
        ),
      ],
    );
  }
}

class _HeroAthleteVisual extends StatefulWidget {
  const _HeroAthleteVisual({
    required this.size,
    required this.floatAmplitude,
  });

  final double size;
  final double floatAmplitude;

  @override
  State<_HeroAthleteVisual> createState() => _HeroAthleteVisualState();
}

class _HeroAthleteVisualState extends State<_HeroAthleteVisual>
    with SingleTickerProviderStateMixin {
  late final AnimationController _float = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 4200),
  );

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (MediaQuery.disableAnimationsOf(context) ||
          widget.floatAmplitude <= 0) {
        _float.value = 0.5;
      } else {
        _float.repeat(reverse: true);
      }
    });
  }

  @override
  void didUpdateWidget(covariant _HeroAthleteVisual oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.floatAmplitude <= 0 && _float.isAnimating) {
      _float.stop();
      _float.value = 0.5;
    } else if (widget.floatAmplitude > 0 &&
        !_float.isAnimating &&
        !MediaQuery.disableAnimationsOf(context)) {
      _float.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _float.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Hard floor so the figure never becomes effectively invisible.
    final size = widget.size.clamp(64.0, 240.0);

    return AnimatedBuilder(
      animation: _float,
      builder: (context, child) {
        final dy = widget.floatAmplitude <= 0
            ? 0.0
            : math.sin(_float.value * math.pi) * widget.floatAmplitude;
        return Transform.translate(offset: Offset(0, dy), child: child);
      },
      child: SizedBox(
        width: size,
        height: size * 1.35,
        child: Stack(
          alignment: Alignment.center,
          clipBehavior: Clip.none,
          children: [
            // Matte depth only — no luminous glow halo.
            OctaBodyHeroSilhouette(
              size: size * 0.92,
              accent: AppColors.primary,
              rimAccent: AppColors.primary,
            ),
          ],
        ),
      ),
    );
  }
}

class _HeroDepthPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final indigoWash = Paint()
      ..shader = RadialGradient(
        center: const Alignment(0.85, -0.2),
        radius: 0.85,
        colors: [AppColors.primary.withValues(alpha: 0.08), Colors.transparent],
      ).createShader(Offset.zero & size);
    canvas.drawRect(Offset.zero & size, indigoWash);

    final softDepth = Paint()
      ..shader = RadialGradient(
        center: const Alignment(-0.9, 1.0),
        radius: 0.9,
        colors: [AppColors.primary.withValues(alpha: 0.04), Colors.transparent],
      ).createShader(Offset.zero & size);
    canvas.drawRect(Offset.zero & size, softDepth);

    final line = Paint()
      ..color = AppColors.border.withValues(alpha: 0.45)
      ..strokeWidth = 1;
    canvas.drawLine(
      Offset(size.width * 0.08, size.height * 0.18),
      Offset(size.width * 0.42, size.height * 0.18),
      line,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
