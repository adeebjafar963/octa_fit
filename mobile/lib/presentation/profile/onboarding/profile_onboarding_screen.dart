import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/profile/onboarding/cubit/profile_onboarding_cubit.dart';
import 'package:octa_fit/presentation/profile/onboarding/widgets/onboarding_option_grid.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';

class ProfileOnboardingScreen extends StatelessWidget {
  const ProfileOnboardingScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => ProfileOnboardingCubit(context.read<AuthCubit>()),
      child: const _OnboardingView(),
    );
  }
}

class _OnboardingView extends StatelessWidget {
  const _OnboardingView();

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<ProfileOnboardingCubit, ProfileOnboardingState>(
      listenWhen: (p, c) => c.completed && !p.completed,
      listener: (context, state) {
        if (state.completed) context.go('/home');
      },
      builder: (context, state) {
        final cubit = context.read<ProfileOnboardingCubit>();
        final busy = state.saving || state.submitting;

        return Scaffold(
          appBar: OctaAppBar(
            title: 'onboarding_title'.tr(),
            subtitle: 'onboarding_step_of'.tr(
              namedArgs: {
                'current': '${state.stepIndex + 1}',
                'total': '${OnboardingStep.values.length}',
              },
            ),
            forceBack: !state.isFirst,
            onBack: state.isFirst
                ? null
                : () {
                    if (!busy) cubit.back();
                  },
          ),
          body: SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                return Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(
                        AppSpacing.lg,
                        AppSpacing.sm,
                        AppSpacing.lg,
                        0,
                      ),
                      child: _StepDots(
                        current: state.stepIndex,
                        total: OnboardingStep.values.length,
                      ),
                    ),
                    Expanded(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.all(AppSpacing.lg),
                        child: Center(
                          child: ConstrainedBox(
                            constraints: BoxConstraints(
                              minHeight: constraints.maxHeight - 160,
                              maxWidth: 920,
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Text(
                                  AppBrand.name,
                                  style: Theme.of(context)
                                      .textTheme
                                      .labelLarge
                                      ?.copyWith(
                                        color: AppColors.primaryHover,
                                        letterSpacing: 1.2,
                                        fontWeight: FontWeight.w700,
                                      ),
                                ),
                                const SizedBox(height: AppSpacing.xs),
                                Text(
                                  _stepTitleKey(state.step).tr(),
                                  style: Theme.of(context).textTheme.headlineMedium,
                                ),
                                const SizedBox(height: AppSpacing.sm),
                                Text(
                                  _stepSubtitleKey(state.step).tr(),
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium
                                      ?.copyWith(color: AppColors.textSecondary),
                                ),
                                const SizedBox(height: AppSpacing.lg),
                                OctaPanel(
                                  elevated: true,
                                  padding: const EdgeInsets.all(AppSpacing.lg),
                                  child: _StepBody(state: state, enabled: !busy),
                                ),
                                if (state.errorMessageKey != null ||
                                    state.errorMessage != null) ...[
                                  const SizedBox(height: AppSpacing.md),
                                  Text(
                                    state.errorMessageKey?.tr() ??
                                        state.errorMessage!,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodyMedium
                                        ?.copyWith(color: AppColors.danger),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(
                        AppSpacing.lg,
                        AppSpacing.sm,
                        AppSpacing.lg,
                        AppSpacing.md,
                      ),
                      child: Center(
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 920),
                          child: Row(
                            children: [
                              if (!state.isFirst)
                                Expanded(
                                  child: SizedBox(
                                    height: 54,
                                    child: OutlinedButton(
                                      onPressed: busy ? null : cubit.back,
                                      style: OutlinedButton.styleFrom(
                                        shape: RoundedRectangleBorder(
                                          borderRadius:
                                              BorderRadius.circular(AppRadii.md),
                                        ),
                                      ),
                                      child: Text('onboarding_back'.tr()),
                                    ),
                                  ),
                                ),
                              if (!state.isFirst)
                                const SizedBox(width: AppSpacing.md),
                              Expanded(
                                flex: 2,
                                child: OnboardingContinueButton(
                                  label: state.isLast
                                      ? 'onboarding_finish'.tr()
                                      : 'onboarding_next'.tr(),
                                  icon: state.isLast
                                      ? Icons.check_rounded
                                      : Icons.arrow_forward_rounded,
                                  isLoading: busy,
                                  onPressed: busy
                                      ? null
                                      : () async {
                                          if (state.isLast) {
                                            await cubit.submit();
                                          } else {
                                            await cubit.next();
                                          }
                                        },
                                ),
                              ),
                            ],
                          ),
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
    );
  }

  String _stepTitleKey(OnboardingStep step) => switch (step) {
        OnboardingStep.about => 'onboarding_about_title',
        OnboardingStep.body => 'onboarding_body_title',
        OnboardingStep.fitness => 'onboarding_fitness_title',
        OnboardingStep.goals => 'onboarding_goals_title',
        OnboardingStep.training => 'onboarding_training_title',
        OnboardingStep.injury => 'onboarding_injury_title',
        OnboardingStep.lifestyle => 'onboarding_lifestyle_title',
        OnboardingStep.review => 'onboarding_review_title',
      };

  String _stepSubtitleKey(OnboardingStep step) => switch (step) {
        OnboardingStep.about => 'onboarding_about_sub',
        OnboardingStep.body => 'onboarding_body_sub',
        OnboardingStep.fitness => 'onboarding_fitness_sub',
        OnboardingStep.goals => 'onboarding_goals_sub',
        OnboardingStep.training => 'onboarding_training_sub',
        OnboardingStep.injury => 'onboarding_injury_sub',
        OnboardingStep.lifestyle => 'onboarding_lifestyle_sub',
        OnboardingStep.review => 'onboarding_review_sub',
      };
}

class _StepDots extends StatelessWidget {
  const _StepDots({required this.current, required this.total});
  final int current;
  final int total;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var i = 0; i < total; i++) ...[
          if (i > 0) const SizedBox(width: 8),
          AnimatedContainer(
            duration: AppMotion.fast,
            width: i == current ? 18 : 8,
            height: 8,
            decoration: BoxDecoration(
              color: i <= current ? AppColors.primaryHover : AppColors.border,
              borderRadius: BorderRadius.circular(AppRadii.full),
            ),
          ),
        ],
      ],
    );
  }
}

class _StepBody extends StatelessWidget {
  const _StepBody({required this.state, required this.enabled});
  final ProfileOnboardingState state;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final cubit = context.read<ProfileOnboardingCubit>();
    return switch (state.step) {
      OnboardingStep.about => _AboutStep(state: state, enabled: enabled),
      OnboardingStep.body => _BodyStep(state: state, enabled: enabled),
      OnboardingStep.fitness => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Label('onboarding_fitness_level'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: const [
                ('beginner', 'onboarding_level_beginner', Icons.spa_rounded),
                ('intermediate', 'onboarding_level_intermediate', Icons.trending_up_rounded),
                ('advanced', 'onboarding_level_advanced', Icons.bolt_rounded),
              ],
              selected: state.fitnessLevel,
              enabled: enabled,
              onSelect: cubit.setFitnessLevel,
            ),
            const SizedBox(height: AppSpacing.lg),
            _Label('onboarding_experience'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: const [
                ('none', 'onboarding_exp_none', Icons.hourglass_empty_rounded),
                ('under_1_year', 'onboarding_exp_under_1', Icons.timelapse_rounded),
                ('1_3_years', 'onboarding_exp_1_3', Icons.calendar_month_rounded),
                ('3_plus_years', 'onboarding_exp_3_plus', Icons.military_tech_rounded),
              ],
              selected: state.trainingExperience,
              enabled: enabled,
              onSelect: cubit.setTrainingExperience,
            ),
          ],
        ),
      OnboardingStep.goals => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Label('profile_goal'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: const [
                ('muscle_gain', 'onboarding_goal_muscle_gain', Icons.fitness_center_rounded),
                ('fat_loss', 'onboarding_goal_fat_loss', Icons.local_fire_department_rounded),
                ('strength', 'onboarding_goal_strength', Icons.sports_gymnastics_rounded),
                ('endurance', 'onboarding_goal_endurance', Icons.directions_run_rounded),
                ('general_fitness', 'onboarding_goal_general', Icons.favorite_rounded),
                ('mma', 'onboarding_goal_mma', Icons.sports_mma_rounded),
                ('mobility', 'onboarding_goal_mobility', Icons.self_improvement_rounded),
                ('rehabilitation', 'onboarding_goal_rehab', Icons.health_and_safety_rounded),
              ],
              selected: state.trainingGoal,
              enabled: enabled,
              onSelect: cubit.setTrainingGoal,
            ),
            const SizedBox(height: AppSpacing.lg),
            _Label('onboarding_secondary_goals'),
            const SizedBox(height: AppSpacing.sm),
            _MultiOptionGrid(
              options: const [
                ('muscle_gain', 'onboarding_goal_muscle_gain', Icons.fitness_center_rounded),
                ('fat_loss', 'onboarding_goal_fat_loss', Icons.local_fire_department_rounded),
                ('strength', 'onboarding_goal_strength', Icons.sports_gymnastics_rounded),
                ('endurance', 'onboarding_goal_endurance', Icons.directions_run_rounded),
                ('mobility', 'onboarding_goal_mobility', Icons.self_improvement_rounded),
              ],
              selected: state.secondaryGoals,
              enabled: enabled,
              onToggle: cubit.toggleSecondaryGoal,
            ),
          ],
        ),
      OnboardingStep.training => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Label('onboarding_frequency'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: [
                for (var i = 1; i <= 7; i++)
                  (i.toString(), 'onboarding_days_n', Icons.event_repeat_rounded),
              ],
              selected: state.trainingFrequency?.toString(),
              enabled: enabled,
              onSelect: (v) => cubit.setTrainingFrequency(int.tryParse(v)),
              labelBuilder: (value, key) => key.tr(namedArgs: {'n': value}),
            ),
            const SizedBox(height: AppSpacing.lg),
            _Label('onboarding_duration'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: const [
                ('30', 'onboarding_min_n', Icons.timer_outlined),
                ('45', 'onboarding_min_n', Icons.timer_outlined),
                ('60', 'onboarding_min_n', Icons.timer_rounded),
                ('90', 'onboarding_min_n', Icons.hourglass_bottom_rounded),
              ],
              selected: state.sessionDurationMinutes?.toString(),
              enabled: enabled,
              onSelect: (v) => cubit.setSessionDuration(int.tryParse(v)),
              labelBuilder: (value, key) => key.tr(namedArgs: {'n': value}),
            ),
            const SizedBox(height: AppSpacing.lg),
            _Label('onboarding_location'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: const [
                ('gym', 'onboarding_loc_gym', Icons.fitness_center_rounded),
                ('home', 'onboarding_loc_home', Icons.home_rounded),
                ('outdoor', 'onboarding_loc_outdoor', Icons.park_rounded),
                ('mixed', 'onboarding_loc_mixed', Icons.swap_horiz_rounded),
              ],
              selected: state.trainingLocation,
              enabled: enabled,
              onSelect: cubit.setTrainingLocation,
            ),
            const SizedBox(height: AppSpacing.lg),
            _Label('onboarding_equipment'),
            const SizedBox(height: AppSpacing.sm),
            _MultiOptionGrid(
              options: const [
                ('dumbbells', 'onboarding_eq_dumbbells', Icons.fitness_center_rounded),
                ('barbell', 'onboarding_eq_barbell', Icons.sports_gymnastics_rounded),
                ('machines', 'onboarding_eq_machines', Icons.settings_rounded),
                ('bands', 'onboarding_eq_bands', Icons.linear_scale_rounded),
                ('bodyweight', 'onboarding_eq_bodyweight', Icons.accessibility_new_rounded),
                ('cardio', 'onboarding_eq_cardio', Icons.monitor_heart_rounded),
              ],
              selected: state.equipment,
              enabled: enabled,
              onToggle: cubit.toggleEquipment,
            ),
          ],
        ),
      OnboardingStep.injury => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Label('onboarding_injury_status'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: const [
                ('none', 'onboarding_injury_none', Icons.check_circle_outline_rounded),
                ('historical', 'onboarding_injury_historical', Icons.history_rounded),
                ('current', 'onboarding_injury_current', Icons.healing_rounded),
              ],
              selected: state.injuryStatus,
              enabled: enabled,
              onSelect: cubit.setInjuryStatus,
            ),
            if (state.injuryStatus != null &&
                state.injuryStatus != 'none') ...[
              const SizedBox(height: AppSpacing.lg),
              _Label('onboarding_injury_areas'),
              const SizedBox(height: AppSpacing.sm),
              _MultiOptionGrid(
                options: const [
                  ('shoulder', 'onboarding_area_shoulder', Icons.accessibility_new_rounded),
                  ('knee', 'onboarding_area_knee', Icons.airline_seat_legroom_extra_rounded),
                  ('back', 'onboarding_area_back', Icons.airline_seat_recline_normal_rounded),
                  ('hip', 'onboarding_area_hip', Icons.directions_walk_rounded),
                  ('ankle', 'onboarding_area_ankle', Icons.do_not_step_rounded),
                  ('wrist', 'onboarding_area_wrist', Icons.back_hand_rounded),
                ],
                selected: state.injuryAreas,
                enabled: enabled,
                onToggle: cubit.toggleInjuryArea,
              ),
              const SizedBox(height: AppSpacing.lg),
              _InjuryNotesField(
                initial: state.injuryDescription ?? '',
                enabled: enabled,
                onChanged: cubit.setInjuryDescription,
              ),
            ],
          ],
        ),
      OnboardingStep.lifestyle => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'onboarding_optional'.tr(),
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
            const SizedBox(height: AppSpacing.md),
            _Label('onboarding_activity'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: const [
                ('sedentary', 'onboarding_act_sedentary', Icons.weekend_rounded),
                ('light', 'onboarding_act_light', Icons.directions_walk_rounded),
                ('moderate', 'onboarding_act_moderate', Icons.directions_bike_rounded),
                ('very_active', 'onboarding_act_very', Icons.directions_run_rounded),
              ],
              selected: state.activityLevel,
              enabled: enabled,
              onSelect: cubit.setActivityLevel,
            ),
            const SizedBox(height: AppSpacing.lg),
            _Label('onboarding_diet'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: const [
                ('none', 'onboarding_diet_none', Icons.restaurant_rounded),
                ('vegetarian', 'onboarding_diet_veg', Icons.eco_rounded),
                ('vegan', 'onboarding_diet_vegan', Icons.grass_rounded),
                ('halal', 'onboarding_diet_halal', Icons.verified_rounded),
                ('other', 'onboarding_diet_other', Icons.more_horiz_rounded),
              ],
              selected: state.dietaryPreference,
              enabled: enabled,
              onSelect: cubit.setDietaryPreference,
            ),
            const SizedBox(height: AppSpacing.lg),
            _Label('onboarding_meals'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: [
                for (var i = 2; i <= 6; i++)
                  (i.toString(), 'onboarding_meals_n', Icons.dinner_dining_rounded),
              ],
              selected: state.mealsPerDay?.toString(),
              enabled: enabled,
              onSelect: (v) => cubit.setMealsPerDay(int.tryParse(v)),
              labelBuilder: (value, key) => key.tr(namedArgs: {'n': value}),
            ),
            const SizedBox(height: AppSpacing.lg),
            _Label('onboarding_sleep'),
            const SizedBox(height: AppSpacing.sm),
            _OptionGrid(
              options: const [
                ('5', 'onboarding_hours_n', Icons.bedtime_rounded),
                ('6', 'onboarding_hours_n', Icons.bedtime_rounded),
                ('7', 'onboarding_hours_n', Icons.bedtime_rounded),
                ('8', 'onboarding_hours_n', Icons.hotel_rounded),
                ('9', 'onboarding_hours_n', Icons.hotel_rounded),
              ],
              selected: state.sleepHours?.toInt().toString(),
              enabled: enabled,
              onSelect: (v) => cubit.setSleepHours(double.tryParse(v)),
              labelBuilder: (value, key) =>
                  key.tr(namedArgs: {'n': value}),
            ),
          ],
        ),
      OnboardingStep.review => _ReviewStep(state: state),
    };
  }
}

class _InjuryNotesField extends StatefulWidget {
  const _InjuryNotesField({
    required this.initial,
    required this.enabled,
    required this.onChanged,
  });

  final String initial;
  final bool enabled;
  final ValueChanged<String> onChanged;

  @override
  State<_InjuryNotesField> createState() => _InjuryNotesFieldState();
}

class _InjuryNotesFieldState extends State<_InjuryNotesField> {
  late final TextEditingController _c =
      TextEditingController(text: widget.initial);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: _c,
      enabled: widget.enabled,
      maxLines: 3,
      onChanged: widget.onChanged,
      decoration: InputDecoration(
        labelText: 'onboarding_injury_notes'.tr(),
        alignLabelWithHint: true,
      ),
    );
  }
}

class _AboutStep extends StatefulWidget {
  const _AboutStep({required this.state, required this.enabled});
  final ProfileOnboardingState state;
  final bool enabled;

  @override
  State<_AboutStep> createState() => _AboutStepState();
}

class _AboutStepState extends State<_AboutStep> {
  late final TextEditingController _age;

  @override
  void initState() {
    super.initState();
    _age = TextEditingController(
      text: widget.state.age?.toString() ?? '',
    );
  }

  @override
  void dispose() {
    _age.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cubit = context.read<ProfileOnboardingCubit>();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _age,
          enabled: widget.enabled,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: InputDecoration(labelText: 'profile_age'.tr()),
          onChanged: (v) => cubit.setAge(int.tryParse(v)),
        ),
        const SizedBox(height: AppSpacing.lg),
        _Label('profile_gender'),
        const SizedBox(height: AppSpacing.sm),
        GenderOptionRow(
          selected: widget.state.gender,
          enabled: widget.enabled,
          maleLabel: 'onboarding_gender_male'.tr(),
          femaleLabel: 'onboarding_gender_female'.tr(),
          onSelect: cubit.setGender,
        ),
      ],
    );
  }
}

class _BodyStep extends StatefulWidget {
  const _BodyStep({required this.state, required this.enabled});
  final ProfileOnboardingState state;
  final bool enabled;

  @override
  State<_BodyStep> createState() => _BodyStepState();
}

class _BodyStepState extends State<_BodyStep> {
  late final TextEditingController _height;
  late final TextEditingController _weight;

  @override
  void initState() {
    super.initState();
    final h = widget.state.heightCm;
    final w = widget.state.weightKg;
    _height = TextEditingController(
      text: h == null ? '' : h.toStringAsFixed(h % 1 == 0 ? 0 : 1),
    );
    _weight = TextEditingController(
      text: w == null ? '' : w.toStringAsFixed(w % 1 == 0 ? 0 : 1),
    );
  }

  @override
  void dispose() {
    _height.dispose();
    _weight.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cubit = context.read<ProfileOnboardingCubit>();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _height,
          enabled: widget.enabled,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(labelText: 'profile_height'.tr()),
          onChanged: (v) => cubit.setHeight(double.tryParse(v)),
        ),
        const SizedBox(height: AppSpacing.md),
        TextField(
          controller: _weight,
          enabled: widget.enabled,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(labelText: 'profile_weight'.tr()),
          onChanged: (v) => cubit.setWeight(double.tryParse(v)),
        ),
      ],
    );
  }
}

class _ReviewStep extends StatelessWidget {
  const _ReviewStep({required this.state});
  final ProfileOnboardingState state;

  @override
  Widget build(BuildContext context) {
    final rows = <(String, String)>[
      ('profile_age', state.age?.toString() ?? 'profile_not_set'.tr()),
            (
        'profile_gender',
        state.gender == null
            ? 'profile_not_set'.tr()
            : switch (state.gender) {
                'male' => 'onboarding_gender_male'.tr(),
                'female' => 'onboarding_gender_female'.tr(),
                _ => 'profile_not_set'.tr(),
              },
      ),
      (
        'profile_height',
        state.heightCm != null
            ? '${state.heightCm!.toStringAsFixed(0)} cm'
            : 'profile_not_set'.tr(),
      ),
      (
        'profile_weight',
        state.weightKg != null
            ? '${state.weightKg!.toStringAsFixed(0)} kg'
            : 'profile_not_set'.tr(),
      ),
      (
        'onboarding_fitness_level',
        state.fitnessLevel == null
            ? 'profile_not_set'.tr()
            : switch (state.fitnessLevel) {
                'beginner' => 'onboarding_level_beginner'.tr(),
                'intermediate' => 'onboarding_level_intermediate'.tr(),
                'advanced' => 'onboarding_level_advanced'.tr(),
                _ => state.fitnessLevel!,
              },
      ),
      (
        'profile_goal',
        state.trainingGoal == null
            ? 'profile_not_set'.tr()
            : _goalKey(state.trainingGoal!).tr(),
      ),
      (
        'onboarding_injury_status',
        state.injuryStatus == null
            ? 'profile_not_set'.tr()
            : switch (state.injuryStatus) {
                'none' => 'onboarding_injury_none'.tr(),
                'historical' => 'onboarding_injury_historical'.tr(),
                'current' => 'onboarding_injury_current'.tr(),
                _ => state.injuryStatus!,
              },
      ),
    ];

    return Column(
      children: [
        for (final row in rows) ...[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 2,
                child: Text(
                  row.$1.tr(),
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                ),
              ),
              Expanded(
                flex: 3,
                child: Text(
                  row.$2,
                  style: Theme.of(context).textTheme.titleSmall,
                  textAlign: TextAlign.end,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
        ],
      ],
    );
  }

  String _goalKey(String goal) => switch (goal) {
        'muscle_gain' => 'onboarding_goal_muscle_gain',
        'fat_loss' => 'onboarding_goal_fat_loss',
        'strength' => 'onboarding_goal_strength',
        'endurance' => 'onboarding_goal_endurance',
        'general_fitness' => 'onboarding_goal_general',
        'mma' => 'onboarding_goal_mma',
        'mobility' => 'onboarding_goal_mobility',
        'rehabilitation' => 'onboarding_goal_rehab',
        _ => 'profile_not_set',
      };
}

class _Label extends StatelessWidget {
  const _Label(this.keyName);
  final String keyName;

  @override
  Widget build(BuildContext context) {
    return Text(
      keyName.tr(),
      style: Theme.of(context).textTheme.labelMedium,
    );
  }
}

class _OptionGrid extends StatelessWidget {
  const _OptionGrid({
    required this.options,
    required this.selected,
    required this.enabled,
    required this.onSelect,
    this.labelBuilder,
  });

  /// (value, i18nKey, icon)
  final List<(String, String, IconData)> options;
  final String? selected;
  final bool enabled;
  final ValueChanged<String> onSelect;
  final String Function(String value, String key)? labelBuilder;

  @override
  Widget build(BuildContext context) {
    return OnboardingOptionGrid(
      enabled: enabled,
      selected: selected,
      onSelect: onSelect,
      options: [
        for (final (value, key, icon) in options)
          OnboardingOption(
            value: value,
            label: labelBuilder?.call(value, key) ?? key.tr(),
            icon: icon,
          ),
      ],
    );
  }
}

class _MultiOptionGrid extends StatelessWidget {
  const _MultiOptionGrid({
    required this.options,
    required this.selected,
    required this.enabled,
    required this.onToggle,
  });

  final List<(String, String, IconData)> options;
  final List<String> selected;
  final bool enabled;
  final ValueChanged<String> onToggle;

  @override
  Widget build(BuildContext context) {
    return OnboardingOptionGrid(
      multi: true,
      enabled: enabled,
      multiSelected: selected,
      onSelect: onToggle,
      options: [
        for (final (value, key, icon) in options)
          OnboardingOption(
            value: value,
            label: key.tr(),
            icon: icon,
          ),
      ],
    );
  }
}
