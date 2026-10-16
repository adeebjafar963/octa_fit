import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';
import 'package:octa_fit/presentation/admin/cubit/admin_dashboard_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';

class AdminDashboardScreen extends StatelessWidget {
  const AdminDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => AdminDashboardCubit(sl<AdminRepository>())..load(),
      child: const _AdminDashboardView(),
    );
  }
}

class _AdminDashboardView extends StatelessWidget {
  const _AdminDashboardView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'admin_dashboard'.tr(),
        accent: AppColors.warning,
      ),
      body: BlocBuilder<AdminDashboardCubit, AdminDashboardState>(
        builder: (context, state) {
          return switch (state) {
            AdminDashboardLoading() ||
            AdminDashboardInitial() => const AppLoadingView(),
            AdminDashboardError(:final messageKey) => AppEmptyState(
              messageKey: messageKey,
              accent: AppColors.warning,
              onRetry: () => context.read<AdminDashboardCubit>().load(),
            ),
            AdminDashboardLoaded(:final stats) => RefreshIndicator(
              color: AppColors.warning,
              onRefresh: () => context.read<AdminDashboardCubit>().load(),
              child: OctaPage(
                maxWidth: 1120,
                padding: EdgeInsets.zero,
                child: ListView(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  children: [
                    OctaPanel(
                      borderColor: AppColors.mma.withValues(alpha: 0.35),
                      padding: const EdgeInsets.all(AppSpacing.md),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.security_rounded,
                            color: AppColors.mmaHover,
                            size: 18,
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Text(
                              'admin_security_note'.tr(),
                              maxLines: 4,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(
                                context,
                              ).textTheme.bodySmall?.copyWith(height: 1.45),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xl),
                    AppSectionHeader(
                      'admin_dashboard',
                      accent: AppColors.warning,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final w = constraints.maxWidth;
                        final cols = w >= AppBreakpoints.compact
                            ? 3
                            : w >= 420
                            ? 2
                            : 1;
                        final kpis = <_KpiData>[
                          _KpiData(
                            'admin_stat_users'.tr(),
                            '${stats.totalUsers}',
                            Icons.people_outline,
                            AppColors.primary,
                          ),
                          _KpiData(
                            'admin_stat_admins'.tr(),
                            '${stats.totalAdmins}',
                            Icons.admin_panel_settings_outlined,
                            AppColors.mma,
                          ),
                          _KpiData(
                            'admin_stat_exercises'.tr(),
                            '${stats.totalExercises}',
                            Icons.fitness_center,
                            AppColors.primaryHover,
                          ),
                          _KpiData(
                            'admin_stat_products'.tr(),
                            '${stats.totalProducts}',
                            Icons.storefront_outlined,
                            AppColors.info,
                          ),
                          _KpiData(
                            'admin_stat_active_products'.tr(),
                            '${stats.activeProducts}',
                            Icons.check_circle_outline,
                            AppColors.success,
                          ),
                          _KpiData(
                            'admin_stat_orders'.tr(),
                            '${stats.totalOrders}',
                            Icons.receipt_long_outlined,
                            AppColors.warning,
                          ),
                          _KpiData(
                            'admin_stat_posts'.tr(),
                            '${stats.totalPosts}',
                            Icons.forum_outlined,
                            AppColors.primary,
                          ),
                          _KpiData(
                            'admin_stat_coach'.tr(),
                            '${stats.totalCoachConversations}',
                            Icons.smart_toy_outlined,
                            AppColors.ai,
                          ),
                          _KpiData(
                            'admin_stat_physio'.tr(),
                            '${stats.totalPhysioAssessments}',
                            Icons.health_and_safety_outlined,
                            AppColors.ai,
                          ),
                          _KpiData(
                            'admin_stat_hydration_users'.tr(),
                            '${stats.hydrationActiveUsers}',
                            Icons.water_drop_outlined,
                            AppColors.info,
                          ),
                          _KpiData(
                            'admin_stat_hydration_intakes'.tr(),
                            '${stats.hydrationIntakes7d}',
                            Icons.opacity,
                            AppColors.info,
                          ),
                        ];
                        final tileW = (w - AppSpacing.sm * (cols - 1)) / cols;
                        return Wrap(
                          spacing: AppSpacing.sm,
                          runSpacing: AppSpacing.sm,
                          children: [
                            for (final kpi in kpis)
                              SizedBox(
                                width: tileW.clamp(0, w),
                                child: _KpiTile(data: kpi),
                              ),
                          ],
                        );
                      },
                    ),
                    const SizedBox(height: AppSpacing.xl),
                    AppSectionHeader(
                      'admin_sections',
                      accent: AppColors.warning,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    OctaPanel(
                      elevated: true,
                      borderColor: AppColors.border,
                      padding: EdgeInsets.zero,
                      clip: true,
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          _NavTile(
                            'admin_users'.tr(),
                            Icons.people_outline,
                            '/admin/users',
                            AppColors.primary,
                          ),
                          const Divider(height: 1),
                          _NavTile(
                            'admin_training'.tr(),
                            Icons.fitness_center,
                            '/admin/training',
                            AppColors.primary,
                          ),
                          const Divider(height: 1),
                          _NavTile(
                            'admin_products'.tr(),
                            Icons.storefront_outlined,
                            '/admin/products',
                            AppColors.info,
                          ),
                          const Divider(height: 1),
                          _NavTile(
                            'admin_orders'.tr(),
                            Icons.receipt_long_outlined,
                            '/admin/orders',
                            AppColors.warning,
                          ),
                          const Divider(height: 1),
                          _NavTile(
                            'admin_community'.tr(),
                            Icons.groups_outlined,
                            '/admin/community',
                            AppColors.primary,
                          ),
                          const Divider(height: 1),
                          _NavTile(
                            'admin_settings'.tr(),
                            Icons.settings_outlined,
                            '/admin/settings',
                            AppColors.textSecondary,
                            isLast: true,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          };
        },
      ),
    );
  }
}

class _KpiData {
  const _KpiData(this.label, this.value, this.icon, this.accent);
  final String label;
  final String value;
  final IconData icon;
  final Color accent;
}

class _KpiTile extends StatelessWidget {
  const _KpiTile({required this.data});
  final _KpiData data;

  @override
  Widget build(BuildContext context) {
    return OctaPanel(
      elevated: true,
      borderColor: data.accent.withValues(alpha: 0.28),
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: data.accent.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadii.sm),
                ),
                child: Icon(data.icon, color: data.accent, size: 16),
              ),
              const Spacer(),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            data.value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppText.statistic(
              context,
              size: 26,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            data.label,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelMedium,
          ),
        ],
      ),
    );
  }
}

class _NavTile extends StatelessWidget {
  const _NavTile(
    this.label,
    this.icon,
    this.path,
    this.accent, {
    this.isLast = false,
  });

  final String label;
  final IconData icon;
  final String path;
  final Color accent;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => context.push(path),
        borderRadius: isLast
            ? const BorderRadius.vertical(bottom: Radius.circular(AppRadii.md))
            : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: 14,
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadii.sm),
                ),
                child: Icon(icon, color: accent, size: 18),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              const Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textSecondary,
                size: 20,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
