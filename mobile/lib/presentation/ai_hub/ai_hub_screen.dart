import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/features/feature_flags.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/ai_hub.dart';
import 'package:octa_fit/domain/repositories/ai_hub_repository.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';
import 'package:octa_fit/presentation/ai_hub/cubit/ai_hub_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class AiHubScreen extends StatelessWidget {
  const AiHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) =>
          AiHubCubit(sl<AiHubRepository>(), sl<CoachRepository>())..load(),
      child: const _AiHubView(),
    );
  }
}

class _AiHubView extends StatefulWidget {
  const _AiHubView();

  @override
  State<_AiHubView> createState() => _AiHubViewState();
}

class _AiHubViewState extends State<_AiHubView> {
  final _input = TextEditingController();
  final _focus = FocusNode();

  @override
  void dispose() {
    _input.dispose();
    _focus.dispose();
    super.dispose();
  }

  Future<void> _submit(String text) async {
    final lang = context.locale.languageCode.startsWith('ar') ? 'ar' : 'en';
    final nav = await context.read<AiHubCubit>().ask(text, language: lang);
    if (!mounted || nav == null) return;
    _input.clear();
    context.push(nav.path);
  }

  @override
  Widget build(BuildContext context) {
    final flags = context.watch<FeatureFlagsCubit>().state;

    return Scaffold(
      appBar: OctaAppBar(
        title: 'ai_hub_title'.tr(),
        subtitle: 'ai_hub_subtitle'.tr(),
        accent: AppColors.ai,
      ),
      body: BlocBuilder<AiHubCubit, AiHubState>(
        builder: (context, state) {
          return switch (state) {
            AiHubInitial() || AiHubLoading() => const AppLoadingView(),
            AiHubError(:final messageKey) => AppEmptyState(
              messageKey: messageKey ?? 'common_error',
              accent: AppColors.ai,
              onRetry: () => context.read<AiHubCubit>().load(),
            ),
            AiHubLoaded(
              :final snapshot,
              :final routing,
              :final errorKey,
              :final errorMessage,
            ) =>
              _HubBody(
                snapshot: snapshot,
                flags: flags,
                routing: routing,
                errorKey: errorKey,
                errorMessage: errorMessage,
                input: _input,
                focus: _focus,
                onSubmit: _submit,
                onQuick: (prompt) {
                  _input.text = prompt;
                  _submit(prompt);
                },
              ),
          };
        },
      ),
    );
  }
}

class _HubBody extends StatelessWidget {
  const _HubBody({
    required this.snapshot,
    required this.flags,
    required this.routing,
    required this.input,
    required this.focus,
    required this.onSubmit,
    required this.onQuick,
    this.errorKey,
    this.errorMessage,
  });

  final AiHubSnapshot snapshot;
  final FeatureFlags flags;
  final bool routing;
  final String? errorKey;
  final String? errorMessage;
  final TextEditingController input;
  final FocusNode focus;
  final ValueChanged<String> onSubmit;
  final ValueChanged<String> onQuick;

  @override
  Widget build(BuildContext context) {
    final caps = snapshot.capabilities;
    final coachOn = caps.coach && flags.coachEnabled;
    final physioOn = caps.physio && flags.physioEnabled;
    final motionOn = caps.motion && flags.motionEnabled;

    return SafeArea(
      child: OctaPage(
        padding: EdgeInsets.zero,
        child: Column(
          children: [
            Expanded(
              child: LayoutBuilder(
                builder: (context, constraints) {
                  return SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.lg,
                      AppSpacing.md,
                      AppSpacing.lg,
                      AppSpacing.md,
                    ),
                    child: ConstrainedBox(
                      constraints: BoxConstraints(
                        minHeight: constraints.maxHeight - 24,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _HeroHeader(greeting: snapshot.greeting),
                          const SizedBox(height: AppSpacing.lg),
                          _QuickActions(
                            coachOn: coachOn,
                            physioOn: physioOn,
                            motionOn: motionOn,
                            enabled: !routing,
                            onQuick: onQuick,
                            onOpenCoach: () => context.push('/coach'),
                            onOpenPhysio: () => context.push('/physio/new'),
                            onOpenMotion: () => context.push('/motion'),
                            onOpenHydration: () => context.push('/hydration'),
                          ),
                          const SizedBox(height: AppSpacing.xl),
                          Text(
                            'ai_hub_capabilities'.tr(),
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          Text(
                            'ai_hub_capabilities_sub'.tr(),
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(color: AppColors.textSecondary),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          _CapabilityGrid(
                            coachOn: coachOn,
                            physioOn: physioOn,
                            motionOn: motionOn,
                          ),
                          if (snapshot.conversations.isNotEmpty ||
                              snapshot.assessments.isNotEmpty) ...[
                            const SizedBox(height: AppSpacing.xl),
                            Text(
                              'ai_hub_recent'.tr(),
                              style: Theme.of(context).textTheme.titleLarge,
                            ),
                            const SizedBox(height: AppSpacing.md),
                            _RecentActivity(
                              conversations: snapshot.conversations,
                              assessments: snapshot.assessments,
                            ),
                          ],
                          if (errorKey != null || errorMessage != null) ...[
                            const SizedBox(height: AppSpacing.md),
                            Text(
                              errorKey?.tr() ?? errorMessage!,
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(color: AppColors.danger),
                            ),
                          ],
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
            _AskBar(
              controller: input,
              focusNode: focus,
              enabled: !routing && (coachOn || physioOn || motionOn),
              loading: routing,
              onSubmit: onSubmit,
            ),
          ],
        ),
      ),
    );
  }
}

class _HeroHeader extends StatelessWidget {
  const _HeroHeader({required this.greeting});
  final AiHubGreeting greeting;

  @override
  Widget build(BuildContext context) {
    final name = greeting.name.trim();
    final goal = greeting.trainingGoal;

    String subtitle;
    if (goal != null && goal.isNotEmpty) {
      subtitle = 'ai_hub_hello_goal'.tr(
        namedArgs: {
          'name': name.isEmpty ? '—' : name,
          'goal': _goalLabel(goal),
        },
      );
    } else if (name.isNotEmpty) {
      subtitle = 'ai_hub_hello_name'.tr(namedArgs: {'name': name});
    } else {
      subtitle = 'ai_hub_hello'.tr();
    }

    return DecoratedBox(
      decoration: AppFeatureCardSurface.ai(
        borderRadius: BorderRadius.circular(AppRadii.lg),
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: AppColors.ai.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(AppRadii.md),
                  border: Border.all(
                    color: AppColors.ai.withValues(alpha: 0.14),
                  ),
                ),
                child: const Icon(
                  Icons.auto_awesome,
                  color: AppColors.aiAccent,
                  size: 24,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      AppBrand.name,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.1,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'ai_hub_tagline'.tr(),
                      style: Theme.of(context).textTheme.titleMedium,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            subtitle,
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              color: AppColors.textSecondary,
              height: 1.45,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'ai_hub_prompt'.tr(),
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
        ),
      ),
    );
  }

  String _goalLabel(String goal) => switch (goal) {
    'muscle_gain' => 'onboarding_goal_muscle_gain'.tr(),
    'fat_loss' => 'onboarding_goal_fat_loss'.tr(),
    'strength' => 'onboarding_goal_strength'.tr(),
    'endurance' => 'onboarding_goal_endurance'.tr(),
    'general_fitness' => 'onboarding_goal_general'.tr(),
    'mma' => 'onboarding_goal_mma'.tr(),
    'mobility' => 'onboarding_goal_mobility'.tr(),
    'rehabilitation' => 'onboarding_goal_rehab'.tr(),
    _ => goal,
  };
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({
    required this.coachOn,
    required this.physioOn,
    required this.motionOn,
    required this.enabled,
    required this.onQuick,
    required this.onOpenCoach,
    required this.onOpenPhysio,
    required this.onOpenMotion,
    required this.onOpenHydration,
  });

  final bool coachOn;
  final bool physioOn;
  final bool motionOn;
  final bool enabled;
  final ValueChanged<String> onQuick;
  final VoidCallback onOpenCoach;
  final VoidCallback onOpenPhysio;
  final VoidCallback onOpenMotion;
  final VoidCallback onOpenHydration;

  @override
  Widget build(BuildContext context) {
    final items = <(String, String, IconData, VoidCallback?)>[
      if (coachOn) ...[
        (
          'ai_hub_qa_workout',
          'ai_hub_qa_workout_prompt',
          Icons.fitness_center_rounded,
          () => onQuick('ai_hub_qa_workout_prompt'.tr()),
        ),
        (
          'ai_hub_qa_nutrition',
          'ai_hub_qa_nutrition_prompt',
          Icons.restaurant_rounded,
          () => onQuick('ai_hub_qa_nutrition_prompt'.tr()),
        ),
        (
          'ai_hub_qa_plan',
          'ai_hub_qa_plan_prompt',
          Icons.calendar_month_rounded,
          () => onQuick('ai_hub_qa_plan_prompt'.tr()),
        ),
      ],
      (
        'ai_hub_qa_hydration',
        'ai_hub_qa_hydration_prompt',
        Icons.water_drop_outlined,
        onOpenHydration,
      ),
      if (physioOn)
        (
          'ai_hub_qa_pain',
          'ai_hub_qa_pain_prompt',
          Icons.healing_rounded,
          onOpenPhysio,
        ),
      if (motionOn)
        (
          'ai_hub_qa_form',
          'ai_hub_qa_form_prompt',
          Icons.videocam_rounded,
          onOpenMotion,
        ),
      if (coachOn)
        (
          'ai_hub_qa_ask',
          'ai_hub_qa_ask_prompt',
          Icons.chat_bubble_outline_rounded,
          onOpenCoach,
        ),
    ];

    if (items.isEmpty) {
      return Text(
        'ai_hub_all_disabled'.tr(),
        style: Theme.of(
          context,
        ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
      );
    }

    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        for (final item in items)
          _QuickChip(
            label: item.$1.tr(),
            icon: item.$3,
            enabled: enabled,
            onTap: item.$4,
          ),
      ],
    );
  }
}

class _QuickChip extends StatefulWidget {
  const _QuickChip({
    required this.label,
    required this.icon,
    required this.enabled,
    this.onTap,
  });

  final String label;
  final IconData icon;
  final bool enabled;
  final VoidCallback? onTap;

  @override
  State<_QuickChip> createState() => _QuickChipState();
}

class _QuickChipState extends State<_QuickChip> {
  bool _hover = false;

  @override
  Widget build(BuildContext context) {
    final active = widget.enabled && widget.onTap != null;
    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_) => setState(() => _hover = false),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: active ? widget.onTap : null,
          borderRadius: BorderRadius.circular(AppRadii.md),
          child: AnimatedContainer(
            duration: AppMotion.fast,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: _hover && active ? AppColors.elevated : AppColors.surface,
              borderRadius: BorderRadius.circular(AppRadii.md),
              border: Border.all(
                color: _hover && active
                    ? AppColors.ai.withValues(alpha: 0.18)
                    : AppColors.border,
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  widget.icon,
                  size: 18,
                  color: active ? AppColors.aiHover : AppColors.textDisabled,
                ),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    widget.label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: active
                          ? AppColors.textPrimary
                          : AppColors.textDisabled,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CapabilityGrid extends StatelessWidget {
  const _CapabilityGrid({
    required this.coachOn,
    required this.physioOn,
    required this.motionOn,
  });

  final bool coachOn;
  final bool physioOn;
  final bool motionOn;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, c) {
        final cols = c.maxWidth >= 860 ? 3 : (c.maxWidth >= 520 ? 2 : 1);
        const gap = 12.0;
        final cards = [
          _CapabilityCard(
            title: 'coach_title'.tr(),
            subtitle: 'ai_hub_card_coach_sub'.tr(),
            icon: Icons.psychology_alt_rounded,
            enabled: coachOn,
            onTap: coachOn ? () => context.push('/coach') : null,
          ),
          _CapabilityCard(
            title: 'physio_title'.tr(),
            subtitle: 'ai_hub_card_physio_sub'.tr(),
            icon: Icons.health_and_safety_outlined,
            enabled: physioOn,
            onTap: physioOn ? () => context.push('/physio') : null,
          ),
          _CapabilityCard(
            title: 'motion_title'.tr(),
            subtitle: 'ai_hub_card_motion_sub'.tr(),
            icon: Icons.motion_photos_on_outlined,
            enabled: motionOn,
            onTap: motionOn ? () => context.push('/motion') : null,
          ),
        ];

        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [
            for (final card in cards)
              SizedBox(
                width: cols == 1
                    ? c.maxWidth
                    : (c.maxWidth - gap * (cols - 1)) / cols,
                child: card,
              ),
          ],
        );
      },
    );
  }
}

class _CapabilityCard extends StatefulWidget {
  const _CapabilityCard({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.enabled,
    this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final bool enabled;
  final VoidCallback? onTap;

  @override
  State<_CapabilityCard> createState() => _CapabilityCardState();
}

class _CapabilityCardState extends State<_CapabilityCard> {
  bool _hover = false;
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final enabled = widget.enabled;
    final border = enabled
        ? (_hover
              ? AppColors.ai.withValues(alpha: 0.22)
              : AppColors.ai.withValues(alpha: 0.12))
        : AppColors.border;
    final bg = !enabled
        ? AppColors.surface.withValues(alpha: 0.55)
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
        onTapDown: enabled ? (_) => setState(() => _pressed = true) : null,
        onTapUp: enabled
            ? (_) {
                setState(() => _pressed = false);
                widget.onTap?.call();
              }
            : null,
        onTapCancel: () => setState(() => _pressed = false),
        child: AnimatedContainer(
          duration: AppMotion.fast,
          curve: AppMotion.ease,
          constraints: const BoxConstraints(minHeight: 148),
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: bg,
            gradient: enabled ? AppGradients.aiCard : null,
            borderRadius: BorderRadius.circular(AppRadii.lg),
            border: Border.all(
              color: border,
              width: 1,
            ),
            boxShadow: enabled && _hover ? AppElevation.level2 : null,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                widget.icon,
                size: 28,
                color: enabled ? AppColors.aiAccent : AppColors.textDisabled,
              ),
              const SizedBox(height: 14),
              Text(
                widget.title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: enabled
                      ? AppColors.textPrimary
                      : AppColors.textDisabled,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                enabled ? widget.subtitle : 'ai_hub_capability_disabled'.tr(),
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RecentActivity extends StatelessWidget {
  const _RecentActivity({
    required this.conversations,
    required this.assessments,
  });

  final List<AiRecentConversation> conversations;
  final List<AiRecentAssessment> assessments;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (final c in conversations)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppRadii.md),
                side: const BorderSide(color: AppColors.border),
              ),
              tileColor: AppColors.surface,
              leading: const Icon(Icons.chat_rounded, color: AppColors.aiHover),
              title: Text(
                c.title?.trim().isNotEmpty == true
                    ? c.title!
                    : 'coach_untitled'.tr(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              trailing: const Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textSecondary,
              ),
              onTap: () => context.push('/coach/chat/${c.id}'),
            ),
          ),
        for (final a in assessments)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppRadii.md),
                side: const BorderSide(color: AppColors.border),
              ),
              tileColor: AppColors.surface,
              leading: Icon(
                Icons.healing_rounded,
                color: a.requiresUrgentCare
                    ? AppColors.danger
                    : AppColors.aiHover,
              ),
              title: Text(
                a.bodyArea ?? 'physio_title'.tr(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              subtitle: a.riskLevel != null
                  ? Text('physio_risk_${a.riskLevel}'.tr(), maxLines: 1)
                  : null,
              trailing: const Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textSecondary,
              ),
              onTap: () => context.push('/physio/assessment/${a.id}'),
            ),
          ),
      ],
    );
  }
}

class _AskBar extends StatelessWidget {
  const _AskBar({
    required this.controller,
    required this.focusNode,
    required this.enabled,
    required this.loading,
    required this.onSubmit,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final bool enabled;
  final bool loading;
  final ValueChanged<String> onSubmit;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      elevation: 8,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.md,
            AppSpacing.sm,
            AppSpacing.md,
            AppSpacing.md,
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: TextField(
                  controller: controller,
                  focusNode: focusNode,
                  enabled: enabled && !loading,
                  minLines: 1,
                  maxLines: 4,
                  textInputAction: TextInputAction.send,
                  onSubmitted: enabled && !loading ? onSubmit : null,
                  decoration: InputDecoration(
                    hintText: 'ai_hub_input_hint'.tr(),
                    filled: true,
                    fillColor: AppColors.elevated,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadii.md),
                      borderSide: const BorderSide(color: AppColors.border),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadii.md),
                      borderSide: const BorderSide(color: AppColors.border),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadii.md),
                      borderSide: const BorderSide(
                        color: AppColors.primary,
                        width: 1.5,
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              SizedBox(
                height: 48,
                width: 48,
                child: FilledButton(
                  onPressed: enabled && !loading
                      ? () => onSubmit(controller.text)
                      : null,
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: AppColors.onPrimary,
                    disabledBackgroundColor: AppColors.elevated,
                    padding: EdgeInsets.zero,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(AppRadii.md),
                    ),
                  ),
                  child: loading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.2,
                            color: AppColors.onPrimary,
                          ),
                        )
                      : const Icon(Icons.arrow_upward_rounded, size: 22),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
