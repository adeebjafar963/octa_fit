import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/theme/app_theme.dart';

enum OctaAccent { fit, mma, ai }

Color accentColor(OctaAccent accent) => switch (accent) {
  // AI uses the global primary button system — burgundy is accent-only.
  OctaAccent.fit => AppColors.primary,
  OctaAccent.mma => AppColors.mma,
  OctaAccent.ai => AppColors.primary,
};

class OctaAppBar extends StatelessWidget implements PreferredSizeWidget {
  const OctaAppBar({
    required this.title,
    super.key,
    this.actions,
    this.forceBack = false,
    this.onBack,
    this.accent,
    this.centerTitle = false,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final List<Widget>? actions;
  final bool forceBack;
  final VoidCallback? onBack;
  final Color? accent;
  final bool centerTitle;

  @override
  Size get preferredSize =>
      Size.fromHeight(subtitle == null ? kToolbarHeight : 72);

  static const _rootPaths = {
    '/splash',
    '/login',
    '/home',
    '/training',
    '/store',
    '/community',
    '/ai',
  };

  bool _showBack(BuildContext context) {
    if (forceBack) return true;
    if (Navigator.of(context).canPop()) return true;
    try {
      final path = GoRouterState.of(context).uri.path;
      return !_rootPaths.contains(path);
    } catch (_) {
      return false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final showBack = _showBack(context);
    return AppBar(
      centerTitle: centerTitle,
      title: subtitle == null
          ? Text(title)
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title),
                Text(subtitle!, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
      leading: showBack
          ? IconButton(
              tooltip: MaterialLocalizations.of(context).backButtonTooltip,
              onPressed:
                  onBack ??
                  () {
                    if (context.canPop()) {
                      context.pop();
                    } else {
                      context.go('/home');
                    }
                  },
              icon: Icon(
                Icons.arrow_back_rounded,
                color: accent ?? AppColors.textPrimary,
              ),
            )
          : null,
      automaticallyImplyLeading: false,
      actions: actions,
    );
  }
}

class AppPrimaryButton extends StatelessWidget {
  const AppPrimaryButton({
    required this.labelKey,
    required this.onPressed,
    super.key,
    this.isLoading = false,
    this.accent = OctaAccent.fit,
    this.icon,
    this.expand = true,
  });

  final String labelKey;
  final VoidCallback? onPressed;
  final bool isLoading;
  final OctaAccent accent;
  final IconData? icon;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final color = accentColor(accent);
    final onFill = AppColors.foregroundOn(color);
    final child = isLoading
        ? SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(strokeWidth: 2.4, color: onFill),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 18),
                const SizedBox(width: 8),
              ],
              Flexible(child: Text(labelKey.tr())),
            ],
          );

    final button = DecoratedBox(
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(AppRadii.md),
        // Matte depth only — solid Indigo / AI / MMA fills, no glow.
        boxShadow: onPressed != null ? AppShadows.matte : null,
      ),
      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: color,
          shadowColor: Colors.transparent,
          foregroundColor: onFill,
        ),
        child: child,
      ),
    );
    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}

class AppSectionHeader extends StatelessWidget {
  const AppSectionHeader(
    this.titleKey, {
    super.key,
    this.subtitleKey,
    this.actionLabelKey,
    this.onAction,
    this.accent,
  });

  final String titleKey;
  final String? subtitleKey;
  final String? actionLabelKey;
  final VoidCallback? onAction;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                titleKey.tr(),
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              if (subtitleKey != null) ...[
                const SizedBox(height: 4),
                Text(
                  subtitleKey!.tr(),
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
            ],
          ),
        ),
        if (actionLabelKey != null && onAction != null)
          TextButton(
            onPressed: onAction,
            style: accent == null
                ? null
                : TextButton.styleFrom(foregroundColor: accent),
            child: Text(actionLabelKey!.tr()),
          ),
      ],
    );
  }
}

class AppEmptyState extends StatelessWidget {
  const AppEmptyState({
    required this.messageKey,
    super.key,
    this.onRetry,
    this.icon = Icons.inbox_outlined,
    this.accent,
    this.actionKey,
  });

  final String messageKey;
  final VoidCallback? onRetry;
  final IconData icon;
  final Color? accent;
  final String? actionKey;

  @override
  Widget build(BuildContext context) {
    final color = accent ?? AppColors.primary;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color.withValues(alpha: 0.85), size: 36),
            const SizedBox(height: AppSpacing.lg),
            Text(
              messageKey.tr(),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            if (onRetry != null) ...[
              const SizedBox(height: AppSpacing.lg),
              AppPrimaryButton(
                labelKey: actionKey ?? 'common_retry',
                onPressed: onRetry,
                expand: false,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class AppLoadingView extends StatelessWidget {
  const AppLoadingView({super.key, this.messageKey});

  final String? messageKey;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(
            width: 34,
            height: 34,
            child: CircularProgressIndicator(strokeWidth: 2.8),
          ),
          if (messageKey != null) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              messageKey!.tr(),
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ],
      ),
    );
  }
}

class OctaSurface extends StatelessWidget {
  const OctaSurface({
    required this.child,
    super.key,
    this.onTap,
    this.padding,
    this.elevated = false,
    this.borderColor,
    this.radius,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry? padding;
  final bool elevated;
  final Color? borderColor;
  final double? radius;

  @override
  Widget build(BuildContext context) {
    final r = radius ?? AppRadii.lg;
    final box = Container(
      width: double.infinity,
      padding: padding ?? const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: elevated ? AppColors.elevated : AppColors.surface,
        borderRadius: BorderRadius.circular(r),
        border: borderColor == null ? null : Border.all(color: borderColor!),
        boxShadow: elevated ? AppShadows.lift : null,
      ),
      child: child,
    );
    if (onTap == null) return box;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(r),
        child: box,
      ),
    );
  }
}

/// Backward-compatible alias.
class OctaCard extends OctaSurface {
  const OctaCard({
    required super.child,
    super.key,
    super.onTap,
    super.padding,
    Color? accentBorder,
    Gradient? gradient,
  }) : super(borderColor: accentBorder, elevated: false);
}

class OctaMetricTile extends StatelessWidget {
  const OctaMetricTile({
    required this.label,
    required this.value,
    super.key,
    this.icon,
    this.accent,
  });

  final String label;
  final String value;
  final IconData? icon;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final color = accent ?? AppColors.primary;
    return OctaSurface(
      elevated: true,
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icon != null) Icon(icon, color: color, size: 18),
          if (icon != null) const SizedBox(height: 10),
          Text(value, style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: 2),
          Text(label, style: Theme.of(context).textTheme.labelMedium),
        ],
      ),
    );
  }
}

class OctaRailItem extends StatelessWidget {
  const OctaRailItem({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
    required this.accent,
    super.key,
    this.width = 176,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onTap;
  final Color accent;
  final double width;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: width,
      child: OctaSurface(
        onTap: onTap,
        elevated: true,
        borderColor: null,
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(AppRadii.sm),
              ),
              child: Icon(icon, color: accent, size: 18),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    letterSpacing: 0,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class OctaFeatureTile extends StatelessWidget {
  const OctaFeatureTile({
    required this.titleKey,
    required this.subtitleKey,
    required this.icon,
    required this.onTap,
    required this.accent,
    super.key,
  });

  final String titleKey;
  final String subtitleKey;
  final IconData icon;
  final VoidCallback onTap;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return OctaSurface(
      onTap: onTap,
      elevated: true,
      borderColor: null,
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(AppRadii.md),
            ),
            child: Icon(icon, color: accent),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  titleKey.tr(),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 3),
                Text(
                  subtitleKey.tr(),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          Icon(Icons.arrow_forward_ios_rounded, size: 14, color: accent),
        ],
      ),
    );
  }
}

class OctaBadge extends StatelessWidget {
  const OctaBadge({
    required this.label,
    super.key,
    this.color = AppColors.primary,
  });

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(AppRadii.full),
      ),
      child: Text(
        label,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: color,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class OctaPage extends StatelessWidget {
  const OctaPage({
    required this.child,
    super.key,
    this.maxWidth = 980,
    this.padding,
  });

  final Widget child;
  final double maxWidth;
  final EdgeInsetsGeometry? padding;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: Padding(
          padding:
              padding ?? const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          child: child,
        ),
      ),
    );
  }
}

class OctaSkeleton extends StatefulWidget {
  const OctaSkeleton({super.key, this.height = 16, this.width, this.radius});

  final double height;
  final double? width;
  final double? radius;

  @override
  State<OctaSkeleton> createState() => _OctaSkeletonState();
}

class _OctaSkeletonState extends State<OctaSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      builder: (_, _) => Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: Color.lerp(AppColors.elevated, AppColors.border, _c.value),
          borderRadius: BorderRadius.circular(widget.radius ?? AppRadii.sm),
        ),
      ),
    );
  }
}
