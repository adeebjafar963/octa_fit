import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/coach.dart';
import 'package:octa_fit/domain/entities/coach_structured.dart';

class CoachTrainingPlanCard extends StatelessWidget {
  const CoachTrainingPlanCard({
    required this.plan,
    this.groundedExercises = const [],
    this.onRegenerate,
    super.key,
  });

  final Map<String, dynamic> plan;
  final List<CoachGroundedExercise> groundedExercises;
  final VoidCallback? onRegenerate;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final goal = CoachPlanParsing.asString(plan['goal']);
    final days = CoachPlanParsing.asInt(plan['days_per_week']);
    final level = CoachPlanParsing.asString(plan['level']);
    final discipline = CoachPlanParsing.asString(plan['discipline']);
    final title = CoachPlanParsing.asString(plan['title']) ??
        'coach_training_plan_title'.tr();
    final sessions = CoachPlanParsing.asMapList(plan['sessions']);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.elevated,
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: AppColors.ai.withValues(alpha: 0.12)),
        boxShadow: AppShadows.lift,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.fitness_center, size: 18, color: AppColors.ai),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              if (goal != null) _MetaPill(label: 'coach_plan_goal'.tr(namedArgs: {'value': goal})),
              if (days != null)
                _MetaPill(label: 'coach_plan_days'.tr(namedArgs: {'value': '$days'})),
              if (level != null)
                _MetaPill(label: 'coach_plan_level'.tr(namedArgs: {'value': level})),
              if (discipline != null)
                _MetaPill(
                  label: 'coach_plan_discipline'.tr(namedArgs: {'value': discipline}),
                ),
            ],
          ),
          if (sessions.isNotEmpty) ...[
            const SizedBox(height: 12),
            ...sessions.map((session) => _SessionBlock(session: session)),
          ],
          if (onRegenerate != null) ...[
            const SizedBox(height: 10),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                onPressed: onRegenerate,
                icon: const Icon(Icons.refresh, size: 18),
                label: Text('coach_action_regenerate_plan'.tr()),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SessionBlock extends StatelessWidget {
  const _SessionBlock({required this.session});

  final Map<String, dynamic> session;

  @override
  Widget build(BuildContext context) {
    final day = CoachPlanParsing.asInt(session['day'] ?? session['day_number']);
    final title = CoachPlanParsing.asString(session['title'] ?? session['name']) ??
        'coach_session_fallback'.tr();
    final exercises = CoachPlanParsing.asMapList(session['exercises']);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            day == null
                ? title
                : 'coach_session_day'.tr(namedArgs: {'day': '$day', 'title': title}),
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 6),
          ...exercises.map((ex) => _ExerciseTile(exercise: ex)),
        ],
      ),
    );
  }
}

class _ExerciseTile extends StatelessWidget {
  const _ExerciseTile({required this.exercise});

  final Map<String, dynamic> exercise;

  @override
  Widget build(BuildContext context) {
    final id = CoachPlanParsing.asInt(exercise['exercise_id'] ?? exercise['id']) ?? 0;
    final name = CoachPlanParsing.asString(exercise['name'] ?? exercise['name_en']) ??
        'coach_exercise_unknown'.tr();
    final sets = CoachPlanParsing.asString(exercise['sets']);
    final reps = CoachPlanParsing.asString(exercise['reps']);
    final rounds = CoachPlanParsing.asString(exercise['rounds']);
    final duration = CoachPlanParsing.asString(exercise['duration_seconds']);
    final rest = CoachPlanParsing.asString(exercise['rest_seconds']);
    final route = CoachPlanParsing.asString(exercise['flutter_route']) ??
        (id > 0 ? '/training/exercises/$id' : null);

    final rxParts = <String>[];
    if (sets != null) rxParts.add('coach_sets'.tr(namedArgs: {'value': sets}));
    if (reps != null) {
      rxParts.add('coach_reps'.tr(namedArgs: {'value': reps}));
    } else if (rounds != null) {
      rxParts.add('coach_rounds'.tr(namedArgs: {'value': rounds}));
    } else if (duration != null) {
      rxParts.add('coach_duration_sec'.tr(namedArgs: {'value': duration}));
    }
    if (rest != null) {
      rxParts.add('coach_rest_sec'.tr(namedArgs: {'value': rest}));
    }

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.6),
        borderRadius: BorderRadius.circular(AppRadii.sm),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            name,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
          if (rxParts.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              rxParts.join(' · '),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ],
          const SizedBox(height: 8),
              if (id > 0 && route != null)
            SizedBox(
              height: 36,
              child: OutlinedButton.icon(
                key: ValueKey('open_exercise_$id'),
                onPressed: () => context.push(route),
                icon: const Icon(Icons.open_in_new, size: 16),
                label: Text('coach_open_exercise'.tr()),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.textPrimary,
                  side: BorderSide(color: AppColors.ai.withValues(alpha: 0.10)),
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                ),
              ),
            )
          else
            Text(
              key: const ValueKey('exercise_unavailable'),
              'coach_exercise_unavailable'.tr(),
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
        ],
      ),
    );
  }
}

class CoachGroundedExercisesStrip extends StatelessWidget {
  const CoachGroundedExercisesStrip({required this.exercises, super.key});

  final List<CoachGroundedExercise> exercises;

  @override
  Widget build(BuildContext context) {
    final valid = exercises.where((e) => e.exerciseId > 0).toList();
    if (valid.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'coach_related_exercises'.tr(),
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w700,
              ),
        ),
        const SizedBox(height: 8),
        ...valid.map(
          (ex) => Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: ListTile(
              dense: true,
              contentPadding: const EdgeInsets.symmetric(horizontal: 10),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppRadii.sm),
                side: BorderSide(color: AppColors.border),
              ),
              tileColor: AppColors.elevated,
              title: Text(ex.name, maxLines: 2, overflow: TextOverflow.ellipsis),
              subtitle: ex.level != null || ex.discipline != null
                  ? Text(
                      [ex.discipline, ex.level].whereType<String>().join(' · '),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    )
                  : null,
              trailing: TextButton(
                onPressed: () => context.push(ex.detailRoute),
                child: Text('coach_open_exercise'.tr()),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _MetaPill extends StatelessWidget {
  const _MetaPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.aiTint.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(AppRadii.full),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              fontWeight: FontWeight.w600,
            ),
      ),
    );
  }
}
