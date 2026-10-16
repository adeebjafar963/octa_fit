import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/hydration.dart';
import 'package:octa_fit/presentation/hydration/cubit/hydration_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';

/// Compact Home hydration area — progress + quick-add + today's log.
/// Uses real [HydrationCubit] / API data only.
class HomeHydrationCard extends StatelessWidget {
  const HomeHydrationCard({super.key, this.wide = false});

  final bool wide;

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<HydrationCubit, HydrationState>(
      builder: (context, state) {
        return switch (state) {
          HydrationInitial() || HydrationLoading() => _HydrationShell(
            child: const SizedBox(
              height: 140,
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            ),
          ),
          HydrationError(:final messageKey) => _HydrationShell(
            child: AppEmptyState(
              messageKey: messageKey ?? 'common_error',
              accent: AppColors.primary,
              onRetry: () => context.read<HydrationCubit>().load(),
            ),
          ),
          HydrationLoaded(:final summary, :final busy) => _HydrationSections(
            summary: summary,
            busy: busy,
            wide: wide,
          ),
        };
      },
    );
  }
}

class _HydrationShell extends StatelessWidget {
  const _HydrationShell({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: AppFeatureCardSurface.decoration(
        borderRadius: BorderRadius.circular(AppRadii.lg),
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: child,
      ),
    );
  }
}

class _HydrationSections extends StatelessWidget {
  const _HydrationSections({
    required this.summary,
    required this.busy,
    required this.wide,
  });

  final HydrationSummary summary;
  final bool busy;
  final bool wide;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final sideBySide = wide && constraints.maxWidth >= 560;

        final progress = _ProgressCard(summary: summary);
        final quickAdd = _QuickAddCard(busy: busy);

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (sideBySide)
              IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(child: progress),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(child: quickAdd),
                  ],
                ),
              )
            else ...[
              progress,
              const SizedBox(height: AppSpacing.md),
              quickAdd,
            ],
            const SizedBox(height: AppSpacing.md),
            _TodayLogCard(intakes: summary.intakes),
          ],
        );
      },
    );
  }
}

class _ProgressCard extends StatelessWidget {
  const _ProgressCard({required this.summary});

  final HydrationSummary summary;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final liters = summary.consumedMl / 1000;
    final goalLiters = summary.goalMl / 1000;
    final remainingLiters = summary.remainingMl / 1000;
    final pct = (summary.progressClamped * 100).round();
    final accent = summary.goalMet ? AppColors.success : AppColors.primary;

    return DecoratedBox(
      decoration: AppFeatureCardSurface.decoration(
        borderRadius: BorderRadius.circular(AppRadii.lg),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.push('/hydration'),
          borderRadius: BorderRadius.circular(AppRadii.lg),
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'hydration_today_title'.tr(),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Icon(
                      Icons.water_drop_outlined,
                      size: 18,
                      color: AppColors.primary.withValues(alpha: 0.85),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.md),
                Row(
                  children: [
                    OctaProgressRing(
                      progress: summary.progressClamped,
                      color: accent,
                      size: 72,
                      stroke: 7,
                      child: Text(
                        '$pct%',
                        style: theme.textTheme.labelLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          AnimatedSwitcher(
                            duration: AppMotion.standard,
                            child: Text(
                              '${liters.toStringAsFixed(2)} ${'hydration_l'.tr()}',
                              key: ValueKey(summary.consumedMl),
                              style: theme.textTheme.headlineSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                                color: AppColors.textPrimary,
                                height: 1.1,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'hydration_of_goal'.tr(
                              namedArgs: {
                                'n': goalLiters.toStringAsFixed(2),
                                'unit': 'hydration_l'.tr(),
                              },
                            ),
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: AppColors.textSecondary,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 6),
                          Text(
                            summary.goalMet
                                ? 'hydration_complete'.tr()
                                : 'hydration_remaining_liters'.tr(
                                    namedArgs: {
                                      'n': remainingLiters.toStringAsFixed(2),
                                      'unit': 'hydration_l'.tr(),
                                    },
                                  ),
                            style: theme.textTheme.labelLarge?.copyWith(
                              color: accent,
                              fontWeight: FontWeight.w600,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _QuickAddCard extends StatelessWidget {
  const _QuickAddCard({required this.busy});

  final bool busy;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cubit = context.read<HydrationCubit>();

    return DecoratedBox(
      decoration: AppFeatureCardSurface.decoration(
        borderRadius: BorderRadius.circular(AppRadii.lg),
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'hydration_add'.tr(),
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 2),
            Text(
              'hydration_choose_amount'.tr(),
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final ml in const [250, 500, 750, 1000])
                  _QuickAddChip(
                    label: '+$ml ${'hydration_ml'.tr()}',
                    enabled: !busy,
                    onTap: () => cubit.addWater(ml),
                  ),
                _QuickAddChip(
                  label: 'hydration_custom'.tr(),
                  enabled: !busy,
                  outlined: true,
                  onTap: () => _showCustomSheet(context),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _showCustomSheet(BuildContext context) async {
    final controller = TextEditingController();
    final cubit = context.read<HydrationCubit>();
    final amount = await showModalBottomSheet<int>(
      context: context,
      backgroundColor: AppColors.surface,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadii.lg)),
      ),
      builder: (ctx) {
        return Padding(
          padding: EdgeInsets.only(
            left: AppSpacing.lg,
            right: AppSpacing.lg,
            top: AppSpacing.lg,
            bottom: MediaQuery.viewInsetsOf(ctx).bottom + AppSpacing.lg,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'hydration_custom'.tr(),
                style: Theme.of(ctx).textTheme.titleLarge,
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                'hydration_choose_amount'.tr(),
                style: Theme.of(ctx).textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: controller,
                autofocus: true,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(
                  labelText: 'hydration_custom_hint'.tr(),
                  suffixText: 'hydration_ml'.tr(),
                ),
                onSubmitted: (v) {
                  final n = int.tryParse(v);
                  if (n != null && n >= 1 && n <= 5000) {
                    Navigator.pop(ctx, n);
                  }
                },
              ),
              const SizedBox(height: AppSpacing.md),
              FilledButton(
                onPressed: () {
                  final n = int.tryParse(controller.text);
                  if (n != null && n >= 1 && n <= 5000) {
                    Navigator.pop(ctx, n);
                  } else {
                    ScaffoldMessenger.of(ctx).showSnackBar(
                      SnackBar(content: Text('hydration_invalid_amount'.tr())),
                    );
                  }
                },
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: AppColors.onPrimary,
                ),
                child: Text('hydration_add'.tr()),
              ),
            ],
          ),
        );
      },
    );
    if (amount != null) {
      await cubit.addWater(amount);
    }
  }
}

class _TodayLogCard extends StatelessWidget {
  const _TodayLogCard({required this.intakes});

  final List<WaterIntake> intakes;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return DecoratedBox(
      decoration: AppFeatureCardSurface.decoration(
        borderRadius: BorderRadius.circular(AppRadii.lg),
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'hydration_today_log'.tr(),
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            if (intakes.isEmpty)
              Text(
                'hydration_log_empty'.tr(),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                ),
              )
            else
              ...intakes.reversed.take(8).map(
                (intake) => Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    children: [
                      SizedBox(
                        width: 52,
                        child: Text(
                          _formatTime(intake),
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: AppColors.textSecondary,
                            fontFeatures: const [FontFeature.tabularFigures()],
                          ),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          '${intake.amountMl} ${'hydration_ml'.tr()}',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  String _formatTime(WaterIntake intake) {
    final raw = intake.consumedAtLocal ?? intake.consumedAt;
    if (raw == null || raw.isEmpty) return '--:--';
    final dt = DateTime.tryParse(raw);
    if (dt == null) return '--:--';
    final local = dt.isUtc ? dt.toLocal() : dt;
    final hh = local.hour.toString().padLeft(2, '0');
    final mm = local.minute.toString().padLeft(2, '0');
    return '$hh:$mm';
  }
}

class _QuickAddChip extends StatelessWidget {
  const _QuickAddChip({
    required this.label,
    required this.enabled,
    required this.onTap,
    this.outlined = false,
  });

  final String label;
  final bool enabled;
  final VoidCallback onTap;
  final bool outlined;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: enabled ? onTap : null,
        borderRadius: BorderRadius.circular(AppRadii.md),
        child: Ink(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: outlined
                ? Colors.transparent
                : AppColors.primary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(AppRadii.md),
            border: Border.all(
              color: outlined
                  ? AppColors.border
                  : AppColors.primary.withValues(alpha: 0.35),
            ),
          ),
          child: Text(
            label,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: enabled ? AppColors.textPrimary : AppColors.textDisabled,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}
