import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';

class OnboardingOption {
  const OnboardingOption({
    required this.value,
    required this.label,
    this.description,
    this.icon,
  });

  final String value;
  final String label;
  final String? description;
  final IconData? icon;
}

/// Responsive option grid: 3 columns on desktop, 2/1 on narrower widths.
/// Cards in the same row share equal width and height.
class OnboardingOptionGrid extends StatelessWidget {
  const OnboardingOptionGrid({
    required this.options,
    required this.onSelect,
    super.key,
    this.selected,
    this.multiSelected,
    this.multi = false,
    this.enabled = true,
    this.minTileWidth = 160,
    this.forceColumns,
  });

  final List<OnboardingOption> options;
  final String? selected;
  final List<String>? multiSelected;
  final bool multi;
  final bool enabled;
  final ValueChanged<String> onSelect;
  final double minTileWidth;
  final int? forceColumns;

  static int columnsForWidth(double width, {double minTileWidth = 160}) {
    if (width >= 720) return 3;
    if (width >= 420) return 2;
    // Prefer 2 cols when a tile would still fit reasonably.
    if (width >= minTileWidth * 2 + 12) return 2;
    return 1;
  }

  @override
  Widget build(BuildContext context) {
    if (options.isEmpty) return const SizedBox.shrink();

    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : MediaQuery.sizeOf(context).width;
        var cols =
            forceColumns ?? columnsForWidth(width, minTileWidth: minTileWidth);
        cols = cols.clamp(1, options.length);

        const spacing = 12.0;
        final rows = <Widget>[];

        for (var i = 0; i < options.length; i += cols) {
          final slice = options.sublist(i, (i + cols).clamp(0, options.length));
          rows.add(
            Padding(
              padding: EdgeInsets.only(
                bottom: i + cols < options.length ? spacing : 0,
              ),
              child: IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (var j = 0; j < cols; j++) ...[
                      if (j > 0) const SizedBox(width: spacing),
                      Expanded(
                        child: j < slice.length
                            ? _OptionTile(
                                option: slice[j],
                                selected: multi
                                    ? (multiSelected?.contains(
                                            slice[j].value,
                                          ) ??
                                          false)
                                    : selected == slice[j].value,
                                enabled: enabled,
                                onTap: () => onSelect(slice[j].value),
                              )
                            : const SizedBox.shrink(),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          );
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: rows,
        );
      },
    );
  }
}

class _OptionTile extends StatefulWidget {
  const _OptionTile({
    required this.option,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  final OnboardingOption option;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  State<_OptionTile> createState() => _OptionTileState();
}

class _OptionTileState extends State<_OptionTile> {
  bool _hover = false;
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final selected = widget.selected;
    final border = selected
        ? AppColors.primary
        : (_hover
              ? AppColors.primaryHover.withValues(alpha: 0.55)
              : AppColors.border);
    final bg = selected
        ? AppColors.primaryTint
        : (_pressed
              ? AppColors.elevated
              : (_hover ? AppColors.elevated : AppColors.surface));

    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_) => setState(() {
        _hover = false;
        _pressed = false;
      }),
      child: GestureDetector(
        onTapDown: widget.enabled
            ? (_) => setState(() => _pressed = true)
            : null,
        onTapUp: widget.enabled
            ? (_) {
                setState(() => _pressed = false);
                widget.onTap();
              }
            : null,
        onTapCancel: () => setState(() => _pressed = false),
        child: AnimatedContainer(
          duration: AppMotion.fast,
          curve: AppMotion.ease,
          constraints: const BoxConstraints(minHeight: 108),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 18),
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(AppRadii.md),
            border: Border.all(color: border, width: selected ? 1.8 : 1),
            boxShadow: selected ? AppElevation.level2 : null,
          ),
          child: Stack(
            children: [
              Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (widget.option.icon != null) ...[
                      Icon(
                        widget.option.icon,
                        size: 28,
                        color: selected
                            ? AppColors.primaryHover
                            : AppColors.textSecondary,
                      ),
                      const SizedBox(height: 10),
                    ],
                    Text(
                      widget.option.label,
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: selected
                            ? FontWeight.w700
                            : FontWeight.w600,
                        height: 1.25,
                      ),
                    ),
                    if (widget.option.description != null) ...[
                      const SizedBox(height: 6),
                      Text(
                        widget.option.description!,
                        textAlign: TextAlign.center,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                          height: 1.3,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (selected)
                PositionedDirectional(
                  top: 0,
                  end: 0,
                  child: Icon(
                    Icons.check_circle_rounded,
                    size: 20,
                    color: AppColors.primaryHover,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Equal dual tiles for Male / Female.
class GenderOptionRow extends StatelessWidget {
  const GenderOptionRow({
    required this.selected,
    required this.onSelect,
    required this.maleLabel,
    required this.femaleLabel,
    super.key,
    this.enabled = true,
  });

  final String? selected;
  final ValueChanged<String> onSelect;
  final String maleLabel;
  final String femaleLabel;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, c) {
        return OnboardingOptionGrid(
          forceColumns: c.maxWidth < 360 ? 1 : 2,
          enabled: enabled,
          selected: selected,
          onSelect: onSelect,
          options: [
            OnboardingOption(
              value: 'male',
              label: maleLabel,
              icon: Icons.male_rounded,
            ),
            OnboardingOption(
              value: 'female',
              label: femaleLabel,
              icon: Icons.female_rounded,
            ),
          ],
        );
      },
    );
  }
}

class OnboardingContinueButton extends StatelessWidget {
  const OnboardingContinueButton({
    required this.label,
    required this.onPressed,
    super.key,
    this.isLoading = false,
    this.icon,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool isLoading;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !isLoading;
    return AnimatedOpacity(
      duration: AppMotion.fast,
      opacity: enabled || isLoading ? 1 : 0.55,
      child: SizedBox(
        width: double.infinity,
        height: 54,
        child: ElevatedButton(
          onPressed: enabled ? onPressed : null,
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            foregroundColor: AppColors.onPrimary,
            disabledBackgroundColor: AppColors.elevated,
            disabledForegroundColor: AppColors.textSecondary,
            elevation: 0,
            shadowColor: Colors.transparent,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadii.md),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 28),
          ),
          child: isLoading
              ? const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.4,
                    color: AppColors.onPrimary,
                  ),
                )
              : Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Flexible(
                      child: Text(
                        label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: AppColors.onPrimary,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.2,
                        ),
                      ),
                    ),
                    if (icon != null) ...[
                      const SizedBox(width: 10),
                      Icon(icon, size: 20),
                    ],
                  ],
                ),
        ),
      ),
    );
  }
}
