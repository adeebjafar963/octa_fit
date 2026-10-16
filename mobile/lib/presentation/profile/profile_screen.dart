import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'profile_title'.tr(),
        accent: AppColors.primary,
      ),
      body: BlocConsumer<AuthCubit, AuthState>(
        listener: (context, state) {
          if (state is AuthUnauthenticated) {
            context.go('/login');
          }
        },
        builder: (context, state) {
          if (state is AuthLoading || state is AuthInitial) {
            return const AppLoadingView();
          }
          final user = context.read<AuthCubit>().currentUser;
          if (user == null) {
            return AppEmptyState(
              messageKey: 'error_unauthorized',
              accent: AppColors.primary,
              onRetry: () => context.go('/login'),
            );
          }

          final profile = user.profile;
          final initial = user.name.isNotEmpty
              ? user.name.characters.first.toUpperCase()
              : '?';

          return OctaPage(
            padding: EdgeInsets.zero,
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                _IdentityHero(
                  name: user.name,
                  email: user.email,
                  initial: initial,
                  roleLabel: user.role?.displayName ?? user.role?.name,
                  isAdmin: user.isAdmin,
                ),
                const SizedBox(height: AppSpacing.xl),
                AppSectionHeader('profile_fitness', accent: AppColors.primary),
                const SizedBox(height: AppSpacing.md),
                OctaPanel(
                  elevated: true,
                  padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.md,
                    vertical: AppSpacing.sm,
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      _InfoRow(label: 'profile_age'.tr(), value: profile?.age?.toString()),
                      _InfoRow(
                        label: 'profile_gender'.tr(),
                        value: switch (profile?.gender) {
                          'male' => 'onboarding_gender_male'.tr(),
                          'female' => 'onboarding_gender_female'.tr(),
                          _ => null,
                        },
                      ),
                      _InfoRow(
                        label: 'profile_height'.tr(),
                        value: profile?.heightCm != null ? '${profile!.heightCm}' : null,
                      ),
                      _InfoRow(
                        label: 'profile_weight'.tr(),
                        value: profile?.weightKg != null ? '${profile!.weightKg}' : null,
                      ),
                      _InfoRow(
                        label: 'profile_goal'.tr(),
                        value: profile?.trainingGoal,
                        isLast: true,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                AppSectionHeader('nav_settings', accent: AppColors.primary),
                const SizedBox(height: AppSpacing.md),
                OctaPanel(
                  elevated: true,
                  padding: EdgeInsets.zero,
                  clip: true,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      _SettingsTile(
                        icon: Icons.edit_outlined,
                        label: 'profile_edit'.tr(),
                        onTap: () => context.push('/profile/edit'),
                      ),
                      _SettingsTile(
                        icon: Icons.lock_outline_rounded,
                        label: 'profile_change_password'.tr(),
                        onTap: () => context.push('/profile/password'),
                      ),
                      _SettingsTile(
                        icon: Icons.settings_outlined,
                        label: 'nav_settings'.tr(),
                        onTap: () => context.push('/settings'),
                      ),
                      if (user.isAdmin) ...[
                        _SettingsTile(
                          icon: Icons.admin_panel_settings_outlined,
                          label: 'admin_dashboard'.tr(),
                          accent: AppColors.mma,
                          onTap: () => context.push('/admin'),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () => context.read<AuthCubit>().logout(),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.danger,
                      side: BorderSide(color: AppColors.danger.withValues(alpha: 0.45)),
                      minimumSize: const Size(48, 48),
                    ),
                    icon: const Icon(Icons.logout_rounded),
                    label: Text('auth_logout'.tr()),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _IdentityHero extends StatelessWidget {
  const _IdentityHero({
    required this.name,
    required this.email,
    required this.initial,
    required this.isAdmin,
    this.roleLabel,
  });

  final String name;
  final String email;
  final String initial;
  final String? roleLabel;
  final bool isAdmin;

  @override
  Widget build(BuildContext context) {
    return OctaPanel(
      elevated: true,
      borderColor: AppColors.primary.withValues(alpha: 0.35),
      radius: AppRadii.xl,
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [AppColors.primaryTint, AppColors.elevated],
                  ),
                  border: Border.all(
                    color: AppColors.primary.withValues(alpha: 0.5),
                    width: 2,
                  ),
                ),
                alignment: Alignment.center,
                child: Text(
                  initial,
                  style: Theme.of(context).textTheme.displaySmall?.copyWith(
                        color: AppColors.primaryHover,
                      ),
                ),
              ),
              const SizedBox(width: AppSpacing.lg),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.headlineMedium,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    if (roleLabel != null) ...[
                      const SizedBox(height: 10),
                      OctaBadge(
                        label: roleLabel!,
                        color: isAdmin ? AppColors.mma : AppColors.primary,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Container(
            width: double.infinity,
            height: 1,
            color: AppColors.border,
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            'profile_title'.tr().toUpperCase(),
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: AppColors.primaryHover,
                  letterSpacing: 1.6,
                ),
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.label,
    required this.value,
    this.isLast = false,
  });

  final String label;
  final String? value;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final hasValue = value?.isNotEmpty == true;
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(
        border: isLast
            ? null
            : const Border(bottom: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 2,
            child: Text(
              label,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Flexible(
            flex: 3,
            child: Text(
              hasValue ? value! : 'profile_not_set'.tr(),
              textAlign: TextAlign.end,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: hasValue ? AppColors.textPrimary : AppColors.textDisabled,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SettingsTile extends StatelessWidget {
  const _SettingsTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.accent,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final color = accent ?? AppColors.primary;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
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
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadii.sm),
                ),
                child: Icon(icon, color: color, size: 18),
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
              const Icon(Icons.chevron_right_rounded, color: AppColors.textSecondary, size: 20),
            ],
          ),
        ),
      ),
    );
  }
}
