import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';

/// Overflow-safe page frame with max content width.
class OctaScaffoldBody extends StatelessWidget {
  const OctaScaffoldBody({
    required this.child,
    super.key,
    this.maxWidth = 1120,
    this.padding,
    this.scroll = true,
  });

  final Widget child;
  final double maxWidth;
  final EdgeInsetsGeometry? padding;
  final bool scroll;

  @override
  Widget build(BuildContext context) {
    final pad = padding ??
        EdgeInsets.symmetric(
          horizontal: AppBreakpoints.isCompact(context) ? AppSpacing.md : AppSpacing.lg,
          vertical: AppSpacing.md,
        );

    final content = Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: Padding(padding: pad, child: child),
      ),
    );

    if (!scroll) return content;
    return SingleChildScrollView(child: content);
  }
}

/// Responsive product/exercise grid — never overflows.
class OctaResponsiveGrid extends StatelessWidget {
  const OctaResponsiveGrid({
    required this.itemCount,
    required this.itemBuilder,
    super.key,
    this.minTileWidth = 280,
    this.spacing = AppSpacing.md,
    this.aspectRatio,
    this.shrinkWrap = true,
    this.physics = const NeverScrollableScrollPhysics(),
  });

  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;
  final double minTileWidth;
  final double spacing;
  final double? aspectRatio;
  final bool shrinkWrap;
  final ScrollPhysics? physics;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;
        final cols = math.max(1, (width / minTileWidth).floor());
        if (aspectRatio != null) {
          return GridView.builder(
            shrinkWrap: shrinkWrap,
            physics: physics,
            itemCount: itemCount,
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: cols,
              mainAxisSpacing: spacing,
              crossAxisSpacing: spacing,
              childAspectRatio: aspectRatio!,
            ),
            itemBuilder: itemBuilder,
          );
        }
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: List.generate(itemCount, (i) {
            final tileW = (width - spacing * (cols - 1)) / cols;
            return SizedBox(
              width: tileW.clamp(0, width),
              child: itemBuilder(context, i),
            );
          }),
        );
      },
    );
  }
}

/// Activity / progress ring (Whoop / Fitness+ inspired, original OCTA style).
class OctaProgressRing extends StatelessWidget {
  const OctaProgressRing({
    required this.progress,
    super.key,
    this.size = 88,
    this.stroke = 8,
    this.color = AppColors.primary,
    this.trackColor,
    this.child,
  });

  final double progress;
  final double size;
  final double stroke;
  final Color color;
  final Color? trackColor;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        painter: _RingPainter(
          progress: progress.clamp(0.0, 1.0),
          stroke: stroke,
          color: color,
          track: trackColor ?? AppColors.elevated,
        ),
        child: Center(child: child),
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  _RingPainter({
    required this.progress,
    required this.stroke,
    required this.color,
    required this.track,
  });

  final double progress;
  final double stroke;
  final Color color;
  final Color track;

  @override
  void paint(Canvas canvas, Size size) {
    final c = Offset(size.width / 2, size.height / 2);
    final r = (size.shortestSide - stroke) / 2;
    final trackPaint = Paint()
      ..color = track
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round;
    final progressPaint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round;

    canvas.drawCircle(c, r, trackPaint);
    canvas.drawArc(
      Rect.fromCircle(center: c, radius: r),
      -math.pi / 2,
      2 * math.pi * progress,
      false,
      progressPaint,
    );
  }

  @override
  bool shouldRepaint(covariant _RingPainter old) =>
      old.progress != progress || old.color != color;
}

/// Compact action chip — never overflows parent width.
class OctaQuickAction extends StatelessWidget {
  const OctaQuickAction({
    required this.label,
    required this.icon,
    required this.onTap,
    required this.accent,
    super.key,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.md),
        child: Ink(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            color: AppColors.elevated,
            borderRadius: BorderRadius.circular(AppRadii.full),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 18, color: accent),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(color: AppColors.textPrimary),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Soft surface — Level 1 or 2 elevation.
class OctaPanel extends StatelessWidget {
  const OctaPanel({
    required this.child,
    super.key,
    this.padding,
    this.onTap,
    this.elevated = false,
    this.borderColor,
    this.radius,
    this.clip = false,
  });

  final Widget child;
  final EdgeInsetsGeometry? padding;
  final VoidCallback? onTap;
  final bool elevated;
  final Color? borderColor;
  final double? radius;
  final bool clip;

  @override
  Widget build(BuildContext context) {
    final r = radius ?? AppRadii.md;
    final box = Container(
      width: double.infinity,
      padding: padding ?? const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: elevated ? AppColors.elevated : AppColors.surface,
        borderRadius: BorderRadius.circular(r),
        border: borderColor == null ? null : Border.all(color: borderColor!),
        boxShadow: elevated ? AppElevation.level2 : null,
      ),
      child: child,
    );
    final wrapped = clip ? ClipRRect(borderRadius: BorderRadius.circular(r), child: box) : box;
    if (onTap == null) return wrapped;
    return Material(
      color: Colors.transparent,
      child: InkWell(onTap: onTap, borderRadius: BorderRadius.circular(r), child: wrapped),
    );
  }
}
