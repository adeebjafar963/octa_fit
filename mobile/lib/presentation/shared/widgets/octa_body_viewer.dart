import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';

/// Body region keys used by physio assessment UX.
enum OctaBodyRegion {
  head,
  shoulders,
  chest,
  upperBack,
  lowerBack,
  arms,
  core,
  hips,
  legs,
  knees,
  ankles,
}

extension OctaBodyRegionX on OctaBodyRegion {
  String get labelKey => switch (this) {
    OctaBodyRegion.head => 'body_region_head',
    OctaBodyRegion.shoulders => 'body_region_shoulders',
    OctaBodyRegion.chest => 'body_region_chest',
    OctaBodyRegion.upperBack => 'body_region_upper_back',
    OctaBodyRegion.lowerBack => 'body_region_lower_back',
    OctaBodyRegion.arms => 'body_region_arms',
    OctaBodyRegion.core => 'body_region_core',
    OctaBodyRegion.hips => 'body_region_hips',
    OctaBodyRegion.legs => 'body_region_legs',
    OctaBodyRegion.knees => 'body_region_knees',
    OctaBodyRegion.ankles => 'body_region_ankles',
  };
}

/// Premium interactive anatomical viewer.
///
/// Architecture (Flutter Windows–safe):
/// - Primary: CustomPainter interactive body map (rotate / select regions)
/// - No WebView / Three.js dependency — never crashes if 3D unavailable
/// - API designed so a future glTF/WebView renderer can swap in behind the same interface
class OctaBodyViewer extends StatefulWidget {
  const OctaBodyViewer({
    super.key,
    this.selected,
    this.onRegionSelected,
    this.height = 320,
    this.interactive = true,
    this.accent = AppColors.primary,
  });

  final OctaBodyRegion? selected;
  final ValueChanged<OctaBodyRegion>? onRegionSelected;
  final double height;
  final bool interactive;
  final Color accent;

  @override
  State<OctaBodyViewer> createState() => _OctaBodyViewerState();
}

class _OctaBodyViewerState extends State<OctaBodyViewer>
    with SingleTickerProviderStateMixin {
  double _yaw = 0.18;
  late final AnimationController _idle = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 8),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _idle.dispose();
    super.dispose();
  }

  void _onDrag(DragUpdateDetails d) {
    if (!widget.interactive) return;
    setState(() => _yaw = (_yaw + d.delta.dx * 0.008).clamp(-0.55, 0.55));
  }

  @override
  Widget build(BuildContext context) {
    return OctaPanel(
      elevated: true,
      borderColor: widget.accent.withValues(alpha: 0.35),
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.view_in_ar_outlined, color: widget.accent, size: 18),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'physio_body_viewer_title'.tr(),
                  style: Theme.of(context).textTheme.titleMedium,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              Text(
                'physio_body_viewer_hint'.tr(),
                style: Theme.of(context).textTheme.labelSmall,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          SizedBox(
            height: widget.height,
            child: AnimatedBuilder(
              animation: _idle,
              builder: (context, _) {
                final breathe = 1.0 + (_idle.value - 0.5) * 0.012;
                return GestureDetector(
                  onHorizontalDragUpdate: _onDrag,
                  child: CustomPaint(
                    painter: _BodyPainter(
                      yaw: _yaw,
                      scale: breathe,
                      selected: widget.selected,
                      accent: widget.accent,
                    ),
                    child: LayoutBuilder(
                      builder: (context, constraints) {
                        return Stack(
                          children: [
                            for (final hit in _hits(constraints.biggest))
                              Positioned(
                                left: hit.rect.left,
                                top: hit.rect.top,
                                width: hit.rect.width,
                                height: hit.rect.height,
                                child: Tooltip(
                                  message: hit.region.labelKey.tr(),
                                  child: InkWell(
                                    onTap: widget.onRegionSelected == null
                                        ? null
                                        : () => widget.onRegionSelected!(
                                            hit.region,
                                          ),
                                    borderRadius: BorderRadius.circular(8),
                                    child: const SizedBox.expand(),
                                  ),
                                ),
                              ),
                          ],
                        );
                      },
                    ),
                  ),
                );
              },
            ),
          ),
          if (widget.selected != null) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(
              'physio_body_selected'.tr(
                namedArgs: {'region': widget.selected!.labelKey.tr()},
              ),
              textAlign: TextAlign.center,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: widget.accent),
            ),
          ],
        ],
      ),
    );
  }

  List<_Hit> _hits(Size size) {
    final cx = size.width / 2;
    final cy = size.height / 2;
    final s = size.shortestSide * 0.42;
    Offset o(double x, double y) =>
        Offset(cx + x * s + _yaw * s * 0.35, cy + y * s);
    Rect box(Offset c, double w, double h) =>
        Rect.fromCenter(center: c, width: w * s, height: h * s);

    return [
      _Hit(OctaBodyRegion.head, box(o(0, -0.72), 0.28, 0.22)),
      _Hit(OctaBodyRegion.shoulders, box(o(0, -0.48), 0.62, 0.14)),
      _Hit(OctaBodyRegion.chest, box(o(0, -0.28), 0.42, 0.2)),
      _Hit(OctaBodyRegion.upperBack, box(o(0, -0.18), 0.38, 0.14)),
      _Hit(OctaBodyRegion.arms, box(o(-0.42, -0.18), 0.18, 0.42)),
      _Hit(OctaBodyRegion.arms, box(o(0.42, -0.18), 0.18, 0.42)),
      _Hit(OctaBodyRegion.core, box(o(0, 0.02), 0.34, 0.22)),
      _Hit(OctaBodyRegion.lowerBack, box(o(0, 0.12), 0.3, 0.14)),
      _Hit(OctaBodyRegion.hips, box(o(0, 0.28), 0.38, 0.14)),
      _Hit(OctaBodyRegion.legs, box(o(-0.14, 0.55), 0.16, 0.42)),
      _Hit(OctaBodyRegion.legs, box(o(0.14, 0.55), 0.16, 0.42)),
      _Hit(OctaBodyRegion.knees, box(o(-0.14, 0.62), 0.16, 0.1)),
      _Hit(OctaBodyRegion.knees, box(o(0.14, 0.62), 0.16, 0.1)),
      _Hit(OctaBodyRegion.ankles, box(o(-0.14, 0.88), 0.14, 0.08)),
      _Hit(OctaBodyRegion.ankles, box(o(0.14, 0.88), 0.14, 0.08)),
    ];
  }
}

class _Hit {
  const _Hit(this.region, this.rect);
  final OctaBodyRegion region;
  final Rect rect;
}

class _BodyPainter extends CustomPainter {
  _BodyPainter({
    required this.yaw,
    required this.scale,
    required this.selected,
    required this.accent,
    this.rimAccent,
  });

  final double yaw;
  final double scale;
  final OctaBodyRegion? selected;
  final Color accent;
  final Color? rimAccent;

  @override
  void paint(Canvas canvas, Size size) {
    final cx = size.width / 2;
    final cy = size.height / 2;
    canvas.save();
    canvas.translate(cx, cy);
    canvas.scale(scale);
    canvas.translate(yaw * size.shortestSide * 0.08, 0);

    final base = Paint()
      ..color = AppColors.elevated
      ..style = PaintingStyle.fill;
    final fillTint = Paint()
      ..color = accent.withValues(alpha: 0.16)
      ..style = PaintingStyle.fill;
    final outline = Paint()
      ..color = AppColors.border
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.4;
    final rim = Paint()
      ..color = (rimAccent ?? accent).withValues(alpha: 0.55)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.8;
    final glow = Paint()
      ..color = accent.withValues(alpha: 0.55)
      ..style = PaintingStyle.fill;

    final s = size.shortestSide * 0.42;

    RRect rr(double x, double y, double w, double h) => RRect.fromRectAndRadius(
      Rect.fromCenter(
        center: Offset(x * s, y * s),
        width: w * s,
        height: h * s,
      ),
      const Radius.circular(10),
    );

    void part(RRect r, {bool hot = false}) {
      canvas.drawRRect(r, hot ? glow : base);
      if (!hot) canvas.drawRRect(r, fillTint);
      canvas.drawRRect(r, outline);
      canvas.drawRRect(r.deflate(1.2), rim);
    }

    bool hot(OctaBodyRegion r) => selected == r;

    part(rr(0, -0.72, 0.28, 0.22), hot: hot(OctaBodyRegion.head));
    part(rr(0, -0.48, 0.62, 0.14), hot: hot(OctaBodyRegion.shoulders));
    part(rr(0, -0.28, 0.42, 0.2), hot: hot(OctaBodyRegion.chest));
    part(rr(0, -0.12, 0.36, 0.14), hot: hot(OctaBodyRegion.upperBack));
    part(rr(-0.42, -0.18, 0.16, 0.42), hot: hot(OctaBodyRegion.arms));
    part(rr(0.42, -0.18, 0.16, 0.42), hot: hot(OctaBodyRegion.arms));
    part(rr(0, 0.05, 0.34, 0.24), hot: hot(OctaBodyRegion.core));
    part(rr(0, 0.16, 0.3, 0.12), hot: hot(OctaBodyRegion.lowerBack));
    part(rr(0, 0.28, 0.38, 0.14), hot: hot(OctaBodyRegion.hips));
    part(rr(-0.14, 0.55, 0.16, 0.42), hot: hot(OctaBodyRegion.legs));
    part(rr(0.14, 0.55, 0.16, 0.42), hot: hot(OctaBodyRegion.legs));
    part(rr(-0.14, 0.62, 0.16, 0.1), hot: hot(OctaBodyRegion.knees));
    part(rr(0.14, 0.62, 0.16, 0.1), hot: hot(OctaBodyRegion.knees));
    part(rr(-0.14, 0.88, 0.14, 0.08), hot: hot(OctaBodyRegion.ankles));
    part(rr(0.14, 0.88, 0.14, 0.08), hot: hot(OctaBodyRegion.ankles));

    // Soft floor ellipse for depth
    final floor = Paint()
      ..color = AppColors.background.withValues(alpha: 0.55)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 12);
    canvas.drawOval(
      Rect.fromCenter(
        center: Offset(0, 1.05 * s),
        width: 0.7 * s,
        height: 0.12 * s,
      ),
      floor,
    );

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _BodyPainter old) =>
      old.yaw != yaw ||
      old.scale != scale ||
      old.selected != selected ||
      old.accent != accent ||
      old.rimAccent != rimAccent;
}

/// Compact hero body silhouette for Home (non-interactive).
/// Sculptural figure with Coral Rose fill and rim light.
class OctaBodyHeroSilhouette extends StatelessWidget {
  const OctaBodyHeroSilhouette({
    super.key,
    this.size = 160,
    this.accent = AppColors.primary,
    this.rimAccent = AppColors.primary,
  });

  final double size;
  final Color accent;
  final Color rimAccent;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size * 1.35,
      child: CustomPaint(
        painter: _BodyPainter(
          yaw: 0.14,
          scale: 1,
          selected: null,
          accent: accent,
          rimAccent: rimAccent,
        ),
      ),
    );
  }
}
