import 'package:easy_localization/easy_localization.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/motion_repository.dart';
import 'package:octa_fit/presentation/motion/cubit/motion_analyzer_cubit.dart';
import 'package:octa_fit/presentation/motion/motion_video_file_service.dart';
import 'package:octa_fit/presentation/motion/widgets/motion_video_preview.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';

class MotionHomeScreen extends StatelessWidget {
  const MotionHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => MotionAnalyzerCubit(sl<MotionRepository>()),
      child: const _MotionHomeView(),
    );
  }
}

class _MotionHomeView extends StatelessWidget {
  const _MotionHomeView();

  Future<void> _pickVideo(BuildContext context) async {
    final cubit = context.read<MotionAnalyzerCubit>();
    final files = await FilePicker.pickFiles(type: FileType.video);
    if (files.isEmpty) return;
    final file = files.first;
    final path = file.path;
    if (path == null || path.isEmpty) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('motion_video_play_failed'.tr())),
        );
      }
      return;
    }

    cubit.beginVideoStaging();
    try {
      final staged = await MotionVideoFileService().prepareLocalVideo(
        sourcePath: path,
        displayName: file.name,
      );
      if (!context.mounted) return;
      cubit.setVideo(path: staged, name: file.name);
    } on MotionVideoFileException {
      if (!context.mounted) return;
      cubit.clearVideo();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('motion_video_play_failed_hint'.tr())),
      );
    } catch (_) {
      if (!context.mounted) return;
      cubit.clearVideo();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('motion_video_play_failed'.tr())),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'motion_title'.tr(),
        accent: AppColors.ai,
      ),
      body: BlocConsumer<MotionAnalyzerCubit, MotionAnalyzerState>(
        listener: (context, state) {
          if (state is MotionAnalyzerSuccess) {
            context.push(
              '/motion/result',
              extra: {
                'result': state.result,
                'videoPath': state.videoPath,
              },
            );
          } else if (state is MotionAnalyzerFailure) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.messageKey.tr())),
            );
          }
        },
        builder: (context, state) {
          if (state is MotionAnalyzerUploading || state is MotionAnalyzerAnalyzing) {
            final label = state is MotionAnalyzerUploading
                ? 'motion_uploading'.tr()
                : 'motion_analyzing'.tr();
            final progress = state is MotionAnalyzerUploading ? 0.42 : 0.78;
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: OctaPanel(
                  elevated: true,
                  borderColor: AppColors.ai.withValues(alpha: 0.10),
                  radius: AppRadii.xl,
                  padding: const EdgeInsets.all(AppSpacing.xl),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      OctaProgressRing(
                        progress: progress,
                        color: AppColors.ai,
                        size: 96,
                        stroke: 9,
                        child: Icon(
                          state is MotionAnalyzerUploading
                              ? Icons.cloud_upload_outlined
                              : Icons.analytics_outlined,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      Text(
                        label,
                        textAlign: TextAlign.center,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        'motion_preparing'.tr(),
                        textAlign: TextAlign.center,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ),
            );
          }

          final selecting = state is MotionAnalyzerSelecting
              ? state
              : state is MotionAnalyzerFailure
                  ? MotionAnalyzerSelecting(
                      selectedExercise: state.selectedExercise,
                      videoPath: state.videoPath,
                      videoReady: (state.videoPath ?? '').isNotEmpty,
                    )
                  : const MotionAnalyzerSelecting();

          final hasVideo = (selecting.videoPath ?? '').trim().isNotEmpty;
          final canAnalyze = hasVideo && selecting.videoReady && !selecting.videoStaging;
          final exerciseLabel = selecting.selectedExercise == null
              ? null
              : 'motion_exercise_${selecting.selectedExercise}'.tr();

          return OctaPage(
            padding: EdgeInsets.zero,
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                OctaPanel(
                  borderColor: AppColors.ai.withValues(alpha: 0.12),
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.info_outline_rounded, color: AppColors.ai, size: 18),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          'motion_disclaimer'.tr(),
                          maxLines: 4,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(height: 1.45),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                OctaPanel(
                  elevated: true,
                  borderColor: AppColors.ai.withValues(alpha: 0.12),
                  radius: AppRadii.lg,
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 40,
                            height: 40,
                            decoration: BoxDecoration(
                              color: AppColors.ai.withValues(alpha: 0.10),
                              borderRadius: BorderRadius.circular(AppRadii.sm),
                            ),
                            child: const Icon(
                              Icons.motion_photos_on_outlined,
                              color: AppColors.ai,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.md),
                          Expanded(
                            child: Text(
                              'motion_title'.tr(),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.headlineSmall,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        'motion_intro'.tr(),
                        maxLines: 5,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.5),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                AppSectionHeader(
                  'motion_select_exercise',
                  accent: AppColors.ai,
                ),
                const SizedBox(height: AppSpacing.md),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: selecting.exercises.map((ex) {
                    final selected = selecting.selectedExercise == ex.id;
                    return FilterChip(
                      label: Text(
                        ex.localizationKey.tr(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      selected: selected,
                      showCheckmark: false,
                      selectedColor: AppColors.primary.withValues(alpha: 0.22),
                      side: BorderSide(
                        color: selected ? AppColors.primary : AppColors.border,
                      ),
                      labelStyle: TextStyle(
                        color: selected
                            ? AppColors.textPrimary
                            : AppColors.textSecondary,
                        fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                      ),
                      onSelected: (_) => context
                          .read<MotionAnalyzerCubit>()
                          .selectExercise(ex.id),
                    );
                  }).toList(),
                ),
                const SizedBox(height: AppSpacing.xl),
                AppSectionHeader(
                  'motion_choose_video',
                  subtitleKey: hasVideo ? 'motion_watch_video' : 'motion_video_upload_hint',
                  accent: AppColors.ai,
                ),
                const SizedBox(height: AppSpacing.md),
                if (!hasVideo && !selecting.videoStaging)
                  OctaPanel(
                    elevated: true,
                    borderColor: AppColors.border,
                    radius: AppRadii.lg,
                    padding: const EdgeInsets.all(AppSpacing.xl),
                    onTap: () => _pickVideo(context),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 64,
                          height: 64,
                          decoration: BoxDecoration(
                            color: AppColors.ai.withValues(alpha: 0.10),
                            borderRadius: BorderRadius.circular(AppRadii.md),
                            border: Border.all(
                              color: AppColors.ai.withValues(alpha: 0.12),
                            ),
                          ),
                          child: const Icon(
                            Icons.video_library_outlined,
                            color: AppColors.ai,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Text(
                          'motion_pick_video'.tr(),
                          textAlign: TextAlign.center,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'motion_video_upload_hint'.tr(),
                          textAlign: TextAlign.center,
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                height: 1.4,
                              ),
                        ),
                      ],
                    ),
                  )
                else if (selecting.videoStaging && !hasVideo)
                  OctaPanel(
                    elevated: true,
                    borderColor: AppColors.ai.withValues(alpha: 0.12),
                    radius: AppRadii.lg,
                    padding: const EdgeInsets.all(AppSpacing.xl),
                    child: Column(
                      children: [
                        const CircularProgressIndicator(color: AppColors.ai),
                        const SizedBox(height: AppSpacing.md),
                        Text(
                          'motion_video_loading'.tr(),
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                      ],
                    ),
                  )
                else
                  OctaPanel(
                    elevated: true,
                    borderColor: AppColors.ai.withValues(alpha: 0.14),
                    radius: AppRadii.xl,
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (exerciseLabel != null) ...[
                          Text(
                            exerciseLabel,
                            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                          const SizedBox(height: 4),
                        ],
                        Text(
                          'motion_video_preview'.tr(),
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                color: AppColors.ai,
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        MotionVideoPreview(
                          videoPath: selecting.videoPath!,
                          onReadyChanged: (ready) => context
                              .read<MotionAnalyzerCubit>()
                              .setVideoReady(ready),
                          onRetry: () => _pickVideo(context),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: TextButton.icon(
                            onPressed: () => _pickVideo(context),
                            icon: const Icon(Icons.swap_horiz_rounded, size: 18),
                            label: Text('motion_change_video'.tr()),
                          ),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: AppSpacing.xl),
                AppPrimaryButton(
                  labelKey: 'motion_analyze',
                  accent: OctaAccent.ai,
                  icon: Icons.analytics_outlined,
                  onPressed: canAnalyze
                      ? () => context.read<MotionAnalyzerCubit>().analyze(
                            language:
                                context.locale.languageCode == 'ar' ? 'ar' : 'en',
                          )
                      : null,
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
