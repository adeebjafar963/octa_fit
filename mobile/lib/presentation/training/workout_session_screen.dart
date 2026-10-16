import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';
import 'package:octa_fit/presentation/training/cubit/workout_session_cubit.dart';
import 'package:octa_fit/presentation/training/training_theme.dart';

class WorkoutSessionScreen extends StatelessWidget {
  const WorkoutSessionScreen({
    this.programId,
    this.exerciseIds,
    this.sessionId,
    super.key,
  });

  final int? programId;
  final List<int>? exerciseIds;
  final int? sessionId;

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) {
        final cubit = WorkoutSessionCubit(sl<TrainingRepository>());
        if (sessionId != null) {
          cubit.load(sessionId!);
        } else {
          cubit.start(programId: programId, exerciseIds: exerciseIds);
        }
        return cubit;
      },
      child: const _WorkoutSessionView(),
    );
  }
}

class _WorkoutSessionView extends StatelessWidget {
  const _WorkoutSessionView();

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<WorkoutSessionCubit, WorkoutSessionState>(
      listener: (context, state) {
        if (state is WorkoutSessionReady && state.messageKey != null) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(state.messageKey!.tr())),
          );
        }
      },
      builder: (context, state) {
        return switch (state) {
          WorkoutSessionLoading() => const Scaffold(body: AppLoadingView()),
          WorkoutSessionError(:final messageKey) => Scaffold(
              appBar: OctaAppBar(title: 'training_workout'.tr()),
              body: AppEmptyState(
                messageKey: messageKey,
                onRetry: () => context.pop(),
              ),
            ),
          WorkoutSessionReady(
            :final session,
            :final elapsedSeconds,
            :final restRemaining,
            :final restTotal,
          ) =>
            session.status == 'completed'
                ? _CompletionView(session: session)
                : _ActiveConsole(
                    session: session,
                    elapsedSeconds: elapsedSeconds,
                    restRemaining: restRemaining,
                    restTotal: restTotal,
                  ),
        };
      },
    );
  }
}

class _ActiveConsole extends StatelessWidget {
  const _ActiveConsole({
    required this.session,
    required this.elapsedSeconds,
    this.restRemaining,
    this.restTotal,
  });

  final WorkoutSession session;
  final int elapsedSeconds;
  final int? restRemaining;
  final int? restTotal;

  @override
  Widget build(BuildContext context) {
    final accent = trainingAccentFor(session.type);
    final locale = context.locale.languageCode;
    final active = session.activeExercise;
    final exercise = active?.exercise;
    final totalEx = session.sessionExercises.length;
    final doneEx = session.exercisesCompleted;
    final progress = totalEx == 0 ? 0.0 : (doneEx / totalEx).clamp(0.0, 1.0);
    final isPaused = session.status == 'paused';
    final isResting = restRemaining != null;

    return Scaffold(
      appBar: OctaAppBar(
        title: session.localizedTitle(locale),
        accent: accent,
        actions: [
          TextButton(
            onPressed: () async {
              final ok = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: Text('training_end_confirm_title'.tr()),
                  content: Text('training_end_confirm_body'.tr()),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text('common_cancel'.tr())),
                    TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text('training_end_workout'.tr())),
                  ],
                ),
              );
              if (ok == true && context.mounted) {
                await context.read<WorkoutSessionCubit>().completeWorkout();
              }
            },
            child: Text('training_end_workout'.tr()),
          ),
        ],
      ),
      body: OctaPage(
        child: ListView(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
          children: [
            Row(
              children: [
                Text(
                  formatDuration(elapsedSeconds),
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: accent),
                ),
                const Spacer(),
                Text(
                  'training_progress_exercises'.tr(namedArgs: {
                    'current': '${doneEx + (active != null ? 1 : 0)}',
                    'total': '$totalEx',
                  }),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 8,
                backgroundColor: AppColors.border,
                color: accent,
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            if (isResting) ...[
              OctaPanel(
                elevated: true,
                borderColor: AppColors.warning.withValues(alpha: 0.5),
                child: Column(
                  children: [
                    Text('training_rest'.tr(), style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      formatDuration(restRemaining!),
                      style: Theme.of(context).textTheme.displaySmall?.copyWith(
                            color: AppColors.warning,
                          ),
                    ),
                    if (restTotal != null) ...[
                      const SizedBox(height: AppSpacing.sm),
                      LinearProgressIndicator(
                        value: 1 - (restRemaining! / restTotal!),
                        color: AppColors.warning,
                        backgroundColor: AppColors.border,
                      ),
                    ],
                    const SizedBox(height: AppSpacing.lg),
                    Wrap(
                      spacing: AppSpacing.sm,
                      runSpacing: AppSpacing.sm,
                      alignment: WrapAlignment.center,
                      children: [
                        OutlinedButton(
                          onPressed: () => context.read<WorkoutSessionCubit>().adjustRest(-15),
                          child: const Text('-15'),
                        ),
                        OutlinedButton(
                          onPressed: () => context.read<WorkoutSessionCubit>().adjustRest(15),
                          child: const Text('+15'),
                        ),
                        FilledButton(
                          onPressed: () => context.read<WorkoutSessionCubit>().skipRest(),
                          child: Text('training_skip_rest'.tr()),
                        ),
                      ],
                    ),
                    if (active != null && exercise != null) ...[
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        '${'training_next'.tr()}: ${exercise.localizedName(locale)}',
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ],
                ),
              ),
            ] else if (exercise != null && active != null) ...[
              Text(
                exercise.localizedName(locale),
                style: Theme.of(context).textTheme.displaySmall,
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                [
                  if (exercise.primaryMuscles.isNotEmpty) exercise.primaryMuscles.join(', '),
                  'difficulty_${exercise.difficulty}'.tr(),
                ].where((e) => e.isNotEmpty).join(' • '),
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: AppSpacing.lg),
              OctaPanel(
                elevated: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'training_set_of'.tr(namedArgs: {
                        'current': '${active.setsCompleted + 1}',
                        'total': '${active.targetSetCount}',
                      }),
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    if (active.targetRepsMin != null)
                      Text(
                        'training_target_reps'.tr(namedArgs: {
                          'reps': active.targetRepsMax != null &&
                                  active.targetRepsMax != active.targetRepsMin
                              ? '${active.targetRepsMin}-${active.targetRepsMax}'
                              : '${active.targetRepsMin}',
                        }),
                      ),
                    if (active.targetDurationSeconds != null)
                      Text(
                        'training_target_duration'.tr(namedArgs: {
                          'sec': '${active.targetDurationSeconds}',
                        }),
                      ),
                    if (active.rounds != null)
                      Text(
                        'training_rounds'.tr(namedArgs: {
                          'current': '${active.setsCompleted + 1}',
                          'total': '${active.rounds}',
                        }),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.xl),
              FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  foregroundColor: AppColors.foregroundOn(accent),
                  minimumSize: const Size.fromHeight(52),
                ),
                onPressed: isPaused
                    ? null
                    : () => context.read<WorkoutSessionCubit>().completeSet(),
                child: Text('training_complete_set'.tr()),
              ),
            ] else ...[
              Text('training_no_active_exercise'.tr()),
              const SizedBox(height: AppSpacing.lg),
              FilledButton(
                onPressed: () => context.read<WorkoutSessionCubit>().completeWorkout(),
                child: Text('training_finish'.tr()),
              ),
            ],
            const SizedBox(height: AppSpacing.lg),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {
                      final cubit = context.read<WorkoutSessionCubit>();
                      if (isPaused) {
                        cubit.resume();
                      } else {
                        cubit.pause();
                      }
                    },
                    child: Text(
                      isPaused ? 'training_resume'.tr() : 'training_pause'.tr(),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CompletionView extends StatelessWidget {
  const _CompletionView({required this.session});
  final WorkoutSession session;

  @override
  Widget build(BuildContext context) {
    final locale = context.locale.languageCode;
    final accent = trainingAccentFor(session.type);
    return Scaffold(
      appBar: OctaAppBar(title: 'training_complete_title'.tr(), accent: accent),
      body: OctaPage(
        child: ListView(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.xl),
          children: [
            Icon(Icons.check_circle_outline_rounded, size: 72, color: accent),
            const SizedBox(height: AppSpacing.lg),
            Text(
              session.localizedTitle(locale),
              style: Theme.of(context).textTheme.headlineMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.xl),
            _StatRow(label: 'training_duration'.tr(), value: formatDuration(session.durationSeconds ?? session.elapsedSeconds)),
            _StatRow(label: 'training_exercises_done'.tr(), value: '${session.exercisesCompleted}'),
            _StatRow(label: 'training_sets_done'.tr(), value: '${session.setsCompleted}'),
            _StatRow(label: 'training_total_reps'.tr(), value: '${session.totalReps}'),
            const SizedBox(height: AppSpacing.xl),
            FilledButton(
              onPressed: () => context.go('/training/history'),
              child: Text('training_view_history'.tr()),
            ),
            const SizedBox(height: AppSpacing.md),
            OutlinedButton(
              onPressed: () => context.go('/training'),
              child: Text('training_done'.tr()),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatRow extends StatelessWidget {
  const _StatRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Expanded(child: Text(label)),
          Text(value, style: Theme.of(context).textTheme.titleMedium),
        ],
      ),
    );
  }
}
