import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/training/cubit/exercise_detail_cubit.dart';
import 'package:octa_fit/presentation/training/exercise_media_resolver.dart';
import 'package:octa_fit/presentation/training/training_theme.dart';
import 'package:octa_fit/presentation/training/widgets/exercise_animation.dart';
import 'package:octa_fit/presentation/training/widgets/training_video_player.dart';

class ExerciseDetailsScreen extends StatelessWidget {
  const ExerciseDetailsScreen({required this.exerciseId, super.key});

  final int exerciseId;

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => ExerciseDetailCubit(sl<TrainingRepository>())..load(exerciseId),
      child: _ExerciseDetailsView(exerciseId: exerciseId),
    );
  }
}

class _ExerciseDetailsView extends StatelessWidget {
  const _ExerciseDetailsView({required this.exerciseId});

  final int exerciseId;

  Color _accentFor(Exercise exercise) {
    return trainingAccentFor(exercise.category?.type);
  }

  /// Prefer API-backed remote media (graduation demo uploads) over local assets.
  String? _remoteMediaUrl(Exercise exercise) {
    final video = exercise.primaryVideo;
    if (video == null || video.isPlaceholder) return null;
    final url = video.videoUrl.trim();
    if (url.startsWith('http://') || url.startsWith('https://')) {
      return url;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'training_details'.tr()),
      body: BlocBuilder<ExerciseDetailCubit, ExerciseDetailState>(
        builder: (context, state) {
          return switch (state) {
            ExerciseDetailLoading() || ExerciseDetailInitial() => const AppLoadingView(),
            ExerciseDetailError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                onRetry: () => context.read<ExerciseDetailCubit>().load(exerciseId),
              ),
            ExerciseDetailLoaded(:final exercise) => Builder(
                builder: (context) {
                  final accent = _accentFor(exercise);
                  final locale = context.locale.languageCode;
                  final remoteMedia = _remoteMediaUrl(exercise);
                  final animationAsset =
                      remoteMedia ?? ExerciseMediaResolver.assetForSlug(exercise.slug);

                  return ListView(
                    padding: EdgeInsets.zero,
                    children: [
                      OctaPage(
                        padding: const EdgeInsets.fromLTRB(
                          AppSpacing.lg,
                          AppSpacing.md,
                          AppSpacing.lg,
                          AppSpacing.xl,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (animationAsset == null)
                              _ExerciseHero(
                                accent: accent,
                                thumbnailUrl: exercise.thumbnailUrl,
                                hasVideo: exercise.primaryVideo != null,
                              ),
                            if (animationAsset == null) const SizedBox(height: AppSpacing.lg),
                            Text(
                              exercise.localizedName(locale),
                              style: Theme.of(context).textTheme.displaySmall,
                              softWrap: true,
                            ),
                            const SizedBox(height: AppSpacing.md),
                            Wrap(
                              spacing: AppSpacing.sm,
                              runSpacing: AppSpacing.sm,
                              children: [
                                OctaBadge(
                                  label: 'training_difficulty'.tr(namedArgs: {
                                    'level': 'difficulty_${exercise.difficulty}'.tr(),
                                  }),
                                  color: accent,
                                ),
                                if (exercise.equipment != null)
                                  OctaBadge(label: exercise.equipment!, color: AppColors.textSecondary),
                                if (exercise.prescriptionLabel.isNotEmpty)
                                  OctaBadge(
                                    label: exercise.prescriptionLabel,
                                    color: AppColors.primary,
                                  ),
                                if (exercise.target != null)
                                  OctaBadge(
                                    label: '${'training_target'.tr()}: ${exercise.target}',
                                    color: AppColors.textSecondary,
                                  ),
                              ],
                            ),
                            const SizedBox(height: AppSpacing.lg),
                            if (animationAsset != null) ...[
                              ExerciseAnimation(asset: animationAsset),
                              const SizedBox(height: AppSpacing.lg),
                            ],
                            FilledButton(
                              style: FilledButton.styleFrom(
                                backgroundColor: accent,
                                foregroundColor: AppColors.foregroundOn(accent),
                                minimumSize: const Size.fromHeight(48),
                              ),
                              onPressed: () => context.push(
                                '/training/workout/start?exerciseIds=${exercise.id}',
                              ),
                              child: Text('training_start_workout'.tr()),
                            ),
                            if (exercise.evidenceNotes != null &&
                                exercise.evidenceNotes!.trim().isNotEmpty) ...[
                              const SizedBox(height: AppSpacing.md),
                              OctaSurface(
                                borderColor: AppColors.border,
                                child: Text(
                                  exercise.evidenceNotes!,
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ),
                            ],
                            if (animationAsset == null && exercise.primaryVideo != null) ...[
                              const SizedBox(height: AppSpacing.xl),
                              OctaSurface(
                                elevated: true,
                                borderColor: accent.withValues(alpha: 0.28),
                                padding: EdgeInsets.zero,
                                child: ClipRRect(
                                  borderRadius: BorderRadius.circular(AppRadii.lg - 1),
                                  child: TrainingVideoPlayer(video: exercise.primaryVideo!),
                                ),
                              ),
                            ],
                            const SizedBox(height: AppSpacing.xl),
                            AppSectionHeader('training_description', accent: accent),
                            const SizedBox(height: AppSpacing.md),
                            OctaSurface(
                              child: Text(
                                exercise.localizedDescription(locale) ?? '—',
                                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                      height: 1.5,
                                    ),
                              ),
                            ),
                            const SizedBox(height: AppSpacing.xl),
                            AppSectionHeader('training_instructions', accent: accent),
                            const SizedBox(height: AppSpacing.md),
                            OctaSurface(
                              elevated: true,
                              borderColor: accent.withValues(alpha: 0.22),
                              child: Text(
                                exercise.localizedInstructions(locale) ?? '—',
                                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                      height: 1.5,
                                    ),
                              ),
                            ),
                            const SizedBox(height: AppSpacing.xl),
                            AppSectionHeader('training_safety', accent: accent),
                            const SizedBox(height: AppSpacing.md),
                            OctaSurface(
                              borderColor: AppColors.warning.withValues(alpha: 0.35),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Icon(
                                    Icons.health_and_safety_outlined,
                                    color: AppColors.warning,
                                    size: 20,
                                  ),
                                  const SizedBox(width: AppSpacing.sm),
                                  Expanded(
                                    child: Text(
                                      exercise.localizedSafetyNotes(locale) ?? '—',
                                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                            height: 1.5,
                                            color: AppColors.textSecondary,
                                          ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  );
                },
              ),
          };
        },
      ),
    );
  }
}

class _ExerciseHero extends StatelessWidget {
  const _ExerciseHero({
    required this.accent,
    required this.hasVideo,
    this.thumbnailUrl,
  });

  final Color accent;
  final String? thumbnailUrl;
  final bool hasVideo;

  @override
  Widget build(BuildContext context) {
    final hasThumb = thumbnailUrl != null && thumbnailUrl!.isNotEmpty;

    return AspectRatio(
      aspectRatio: 16 / 9,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.xl),
          border: Border.all(color: AppColors.border),
          boxShadow: AppShadows.soft,
          gradient: accent == AppColors.mma
              ? AppGradients.mmaCard
              : AppGradients.trainingCard,
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (hasThumb)
              Image.network(
                thumbnailUrl!,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => const SizedBox.shrink(),
              ),
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.transparent,
                    AppColors.background.withValues(alpha: 0.55),
                  ],
                ),
              ),
            ),
            Center(
              child: Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: 0.22),
                  shape: BoxShape.circle,
                  border: Border.all(color: accent.withValues(alpha: 0.5)),
                ),
                child: Icon(
                  hasVideo ? Icons.play_arrow_rounded : Icons.fitness_center_rounded,
                  color: accent,
                  size: 34,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
