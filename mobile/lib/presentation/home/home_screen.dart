import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/features/feature_flags.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/hydration_repository.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/home/widgets/home_training_hero.dart';
import 'package:octa_fit/presentation/hydration/cubit/hydration_cubit.dart';
import 'package:octa_fit/presentation/hydration/widgets/home_hydration_card.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => HydrationCubit(sl<HydrationRepository>())..load(),
      child: const _HomeView(),
    );
  }
}

class _HomeView extends StatelessWidget {
  const _HomeView();

  @override
  Widget build(BuildContext context) {
    final authState = context.watch<AuthCubit>().state;
    final flags = context.watch<FeatureFlagsCubit>().state;
    final user = authState is AuthAuthenticated
        ? authState.user
        : (authState is AuthError ? authState.sessionUser : null);
    final userName = user?.name ?? '';
    final wide = !AppBreakpoints.isCompact(context);

    return Scaffold(
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Align(
                alignment: Alignment.topCenter,
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 1180),
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(
                      AppBreakpoints.isCompact(context)
                          ? AppSpacing.md
                          : AppSpacing.lg,
                      AppSpacing.md,
                      AppBreakpoints.isCompact(context)
                          ? AppSpacing.md
                          : AppSpacing.lg,
                      AppSpacing.xl,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _TopBar(
                          userName: userName,
                          trainingGoal: user?.profile?.trainingGoal,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        HomeTrainingHero(
                          onStartTraining: () => context.go('/training'),
                        ),
                        const SizedBox(height: AppSpacing.xl),
                        HomeHydrationCard(wide: wide),
                        const SizedBox(height: AppSpacing.xl),
                        Text(
                          'home_section_quick'.tr(),
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Wrap(
                          spacing: AppSpacing.sm,
                          runSpacing: AppSpacing.sm,
                          children: [
                            OctaQuickAction(
                              label: 'hydration_title'.tr(),
                              icon: Icons.water_drop_outlined,
                              accent: AppColors.primary,
                              onTap: () => context.push('/hydration'),
                            ),
                            OctaQuickAction(
                              label: 'home_cta_train'.tr(),
                              icon: Icons.fitness_center,
                              accent: AppColors.primary,
                              onTap: () => context.go('/training'),
                            ),
                            OctaQuickAction(
                              label: 'training_history'.tr(),
                              icon: Icons.history_rounded,
                              accent: AppColors.textSecondary,
                              onTap: () => context.push('/training/history'),
                            ),
                            if (flags.coachEnabled)
                              OctaQuickAction(
                                label: 'nav_ai'.tr(),
                                icon: Icons.auto_awesome,
                                accent: AppColors.ai,
                                onTap: () => context.go('/ai'),
                              ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.xl),
                        _HomeAccess(
                          onStore: () => context.go('/store'),
                          onCommunity: () => context.go('/community'),
                        ),
                        if (user?.isAdmin == true)
                          _HomeLink(
                            titleKey: 'admin_dashboard',
                            subtitleKey: 'home_admin_sub',
                            onTap: () => context.push('/admin'),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.userName, this.trainingGoal});
  final String userName;
  final String? trainingGoal;

  String? _goalLabel() {
    final goal = trainingGoal;
    if (goal == null || goal.isEmpty) return null;
    return switch (goal) {
      'muscle_gain' => 'onboarding_goal_muscle_gain',
      'fat_loss' => 'onboarding_goal_fat_loss',
      'strength' => 'onboarding_goal_strength',
      'endurance' => 'onboarding_goal_endurance',
      'general_fitness' => 'onboarding_goal_general',
      'mma' => 'onboarding_goal_mma',
      'mobility' => 'onboarding_goal_mobility',
      'rehabilitation' => 'onboarding_goal_rehab',
      _ => null,
    };
  }

  @override
  Widget build(BuildContext context) {
    final hour = DateTime.now().hour;
    final greetingKey = hour < 12
        ? 'home_greeting_morning'
        : hour < 18
        ? 'home_greeting_afternoon'
        : 'home_greeting_evening';
    final goalKey = _goalLabel();
    final subtitle = goalKey == null
        ? 'home_greeting_sub'.tr()
        : 'home_greeting_goal'.tr(namedArgs: {'goal': goalKey.tr()});

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                AppBrand.name,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: AppColors.primaryHover,
                  letterSpacing: 2.2,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                userName.isEmpty
                    ? 'home_welcome'.tr()
                    : greetingKey.tr(namedArgs: {'name': userName}),
                style: Theme.of(context).textTheme.headlineLarge,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: Theme.of(context).textTheme.bodyMedium,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        IconButton(
          tooltip: 'nav_settings'.tr(),
          onPressed: () => context.push('/settings'),
          style: IconButton.styleFrom(backgroundColor: AppColors.elevated),
          icon: const Icon(Icons.settings_outlined),
        ),
        const SizedBox(width: 8),
        InkWell(
          onTap: () => context.push('/profile'),
          borderRadius: BorderRadius.circular(AppRadii.full),
          child: CircleAvatar(
            radius: 20,
            backgroundColor: AppColors.primaryTint,
            child: Text(
              userName.trim().isEmpty ? 'O' : userName.trim()[0].toUpperCase(),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(color: AppColors.primaryHover),
            ),
          ),
        ),
      ],
    );
  }
}

class _HomeAccess extends StatelessWidget {
  const _HomeAccess({required this.onStore, required this.onCommunity});

  final VoidCallback onStore;
  final VoidCallback onCommunity;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final store = _AccessCard(
          titleKey: 'store_title',
          bodyKey: 'home_store_card_body',
          icon: Icons.storefront_outlined,
          onTap: onStore,
        );
        final community = _AccessCard(
          titleKey: 'community_title',
          bodyKey: 'home_community_card_body',
          icon: Icons.groups_outlined,
          onTap: onCommunity,
        );
        if (constraints.maxWidth >= 560) {
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: store),
              const SizedBox(width: AppSpacing.md12),
              Expanded(child: community),
            ],
          );
        }
        return Column(
          children: [
            store,
            const SizedBox(height: AppSpacing.md12),
            community,
          ],
        );
      },
    );
  }
}

class _AccessCard extends StatefulWidget {
  const _AccessCard({
    required this.titleKey,
    required this.bodyKey,
    required this.icon,
    required this.onTap,
  });

  final String titleKey;
  final String bodyKey;
  final IconData icon;
  final VoidCallback onTap;

  @override
  State<_AccessCard> createState() => _AccessCardState();
}

class _AccessCardState extends State<_AccessCard> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final title = widget.titleKey.tr();
    final body = widget.bodyKey.tr();
    final radius = BorderRadius.circular(AppRadii.xl);

    return Semantics(
      button: true,
      label: title,
      child: GestureDetector(
        onTapDown: (_) => setState(() => _pressed = true),
        onTapCancel: () => setState(() => _pressed = false),
        onTapUp: (_) => setState(() => _pressed = false),
        onTap: widget.onTap,
        child: AnimatedScale(
          scale: _pressed ? 0.985 : 1,
          duration: AppMotion.fast,
          curve: AppMotion.ease,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 104),
            child: DecoratedBox(
              decoration: AppFeatureCardSurface.decoration(
                borderRadius: radius,
              ),
              child: ClipRRect(
                borderRadius: radius,
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: AppColors.overlay,
                          borderRadius: BorderRadius.circular(AppRadii.sm),
                          border: Border.all(color: AppColors.border),
                        ),
                        child: Icon(
                          widget.icon,
                          color: AppColors.primary,
                          size: 22,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.md12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              AppBrand.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.labelSmall
                                  ?.copyWith(
                                    color: AppColors.primaryHover,
                                    letterSpacing: 1.1,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w700,
                                  ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(
                                    color: AppColors.textPrimary,
                                    fontWeight: FontWeight.w700,
                                  ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              body,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: AppColors.textSecondary,
                                    height: 1.35,
                                  ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _HomeLink extends StatelessWidget {
  const _HomeLink({
    required this.titleKey,
    required this.subtitleKey,
    required this.onTap,
  });

  final String titleKey;
  final String subtitleKey;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    titleKey.tr(),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitleKey.tr(),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.arrow_forward_rounded,
              size: 18,
              color: AppColors.textSecondary,
            ),
          ],
        ),
      ),
    );
  }
}
