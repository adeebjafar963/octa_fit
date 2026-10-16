import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/motion.dart';
import 'package:octa_fit/presentation/motion/widgets/motion_video_preview.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';

class MotionResultScreen extends StatelessWidget {
  const MotionResultScreen({
    super.key,
    required this.result,
    this.videoPath,
  });

  final MotionAnalysisResult result;
  final String? videoPath;

  @override
  Widget build(BuildContext context) {
    if (result.hasPremiumStructure) {
      return _PremiumMotionResult(result: result, videoPath: videoPath);
    }
    return _LegacyMotionResult(result: result, videoPath: videoPath);
  }
}

class _PremiumMotionResult extends StatelessWidget {
  const _PremiumMotionResult({required this.result, this.videoPath});

  final MotionAnalysisResult result;
  final String? videoPath;

  Color _statusColor(String status) {
    return switch (status.toLowerCase()) {
      'good' => AppColors.success,
      'attention' => AppColors.warning,
      'improve' => AppColors.info,
      _ => AppColors.ai,
    };
  }

  @override
  Widget build(BuildContext context) {
    final title = (result.title ?? '').trim().isNotEmpty
        ? result.title!
        : 'motion_exercise_${result.exercise}'.tr();
    final analysisLabel = (result.analysisLabel ?? '').trim().isNotEmpty
        ? result.analysisLabel!
        : 'motion_analysis_label'.tr();
    final disclaimer = (result.disclaimer ?? '').trim().isNotEmpty
        ? result.disclaimer!
        : 'motion_disclaimer_short'.tr();
    final issues = result.movementPoints
        .where((p) => p.status.toLowerCase() != 'good')
        .toList();

    return Scaffold(
      appBar: OctaAppBar(
        title: 'motion_results'.tr(),
        accent: AppColors.ai,
      ),
      body: OctaPage(
        padding: EdgeInsets.zero,
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          children: [
            OctaPanel(
              elevated: true,
              borderColor: AppColors.ai.withValues(alpha: 0.14),
              radius: AppRadii.xl,
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    analysisLabel,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: AppColors.ai,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  if ((result.overallStatus ?? '').trim().isNotEmpty) ...[
                    const SizedBox(height: AppSpacing.md),
                    OctaBadge(
                      label: result.overallStatus!,
                      color: AppColors.warning,
                    ),
                  ],
                ],
              ),
            ),
            if ((videoPath ?? '').trim().isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.videocam_outlined,
                title: 'motion_video_preview'.tr(),
                child: MotionVideoPreview(videoPath: videoPath!, compact: true),
              ),
            ],
            if ((result.overallSummary ?? '').trim().isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.analytics_outlined,
                title: 'motion_overall_assessment'.tr(),
                child: Text(
                  result.overallSummary!,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        height: 1.55,
                        fontWeight: FontWeight.w500,
                      ),
                ),
              ),
            ],
            if (result.positivePoints.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.check_circle_outline_rounded,
                title: 'motion_positive_points'.tr(),
                accent: AppColors.success,
                child: Column(
                  children: [
                    for (final point in result.positivePoints)
                      Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(
                              Icons.check_rounded,
                              size: 18,
                              color: AppColors.success,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    point.title,
                                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                          fontWeight: FontWeight.w700,
                                        ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    point.explanation,
                                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                          color: AppColors.textSecondary,
                                          height: 1.4,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
            if (issues.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.tune_rounded,
                title: 'motion_needs_improvement'.tr(),
                child: Column(
                  children: [
                    for (final point in issues)
                      _IssueCard(
                        point: point,
                        color: _statusColor(point.status),
                      ),
                  ],
                ),
              ),
            ],
            if (result.coachingCues.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.record_voice_over_outlined,
                title: 'motion_coaching_cues'.tr(),
                child: _BulletList(items: result.coachingCues),
              ),
            ],
            if (result.improvementDrills.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.fitness_center_rounded,
                title: 'motion_improvement_drills'.tr(),
                child: Column(
                  children: [
                    for (final drill in result.improvementDrills)
                      _DrillCard(drill: drill),
                  ],
                ),
              ),
            ],
            if (result.commonMistakes.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.report_gmailerrorred_outlined,
                title: 'motion_common_mistakes'.tr(),
                child: Column(
                  children: [
                    for (final mistake in result.commonMistakes)
                      Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(Icons.close_rounded, size: 18, color: AppColors.warning),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    mistake.title,
                                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                          fontWeight: FontWeight.w700,
                                        ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    mistake.explanation,
                                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                          color: AppColors.textSecondary,
                                          height: 1.4,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
            if (result.nextAttempt.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.flag_rounded,
                title: 'motion_next_attempt'.tr(),
                accent: AppColors.ai,
                child: Column(
                  children: [
                    for (var i = 0; i < result.nextAttempt.length; i++)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 24,
                              height: 24,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: AppColors.ai.withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(AppRadii.sm),
                              ),
                              child: Text(
                                '${i + 1}',
                                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                                      fontWeight: FontWeight.w800,
                                    ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                result.nextAttempt[i],
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                      height: 1.4,
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
            const SizedBox(height: AppSpacing.md),
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
                      disclaimer,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(height: 1.45),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            AppPrimaryButton(
              labelKey: 'motion_try_again',
              accent: OctaAccent.ai,
              icon: Icons.refresh_rounded,
              onPressed: () => context.go('/motion'),
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.icon,
    required this.title,
    required this.child,
    this.accent,
  });

  final IconData icon;
  final String title;
  final Widget child;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final color = accent ?? AppColors.ai;
    return OctaPanel(
      elevated: true,
      borderColor: color.withValues(alpha: 0.14),
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 18, color: color),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          child,
        ],
      ),
    );
  }
}

class _IssueCard extends StatelessWidget {
  const _IssueCard({required this.point, required this.color});

  final MotionPoint point;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.elevated.withValues(alpha: 0.6),
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: color.withValues(alpha: 0.18)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                point.status == 'attention'
                    ? Icons.priority_high_rounded
                    : Icons.tune_rounded,
                size: 18,
                color: color,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  point.title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
              OctaBadge(
                label: point.statusLabel ?? point.status,
                color: color,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'motion_observed'.tr(),
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            point.explanation,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  height: 1.45,
                  color: AppColors.textSecondary,
                ),
          ),
          if ((point.correction ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              'motion_how_to_improve'.tr(),
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 4),
            Text(
              point.correction!,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    height: 1.45,
                  ),
            ),
          ],
        ],
      ),
    );
  }
}

class _DrillCard extends StatelessWidget {
  const _DrillCard({required this.drill});

  final MotionDrill drill;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.elevated.withValues(alpha: 0.6),
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: AppColors.ai.withValues(alpha: 0.1)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            drill.name,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            '${'motion_drill_purpose'.tr()}: ${drill.purpose}',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                  height: 1.4,
                ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if ((drill.difficulty ?? '').isNotEmpty)
                _Chip('${'motion_drill_level'.tr()}: ${drill.difficulty}'),
              if ((drill.sets ?? '').isNotEmpty)
                _Chip('${'motion_drill_sets'.tr()}: ${drill.sets}'),
              if ((drill.reps ?? '').isNotEmpty)
                _Chip('${'motion_drill_reps'.tr()}: ${drill.reps}'),
              if ((drill.rest ?? '').isNotEmpty)
                _Chip('${'motion_drill_rest'.tr()}: ${drill.rest}'),
            ],
          ),
          if (drill.instructions.isNotEmpty) ...[
            const SizedBox(height: 8),
            for (var i = 0; i < drill.instructions.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(
                  '${i + 1}. ${drill.instructions[i]}',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(height: 1.4),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.ai.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(AppRadii.sm),
      ),
      child: Text(label, style: Theme.of(context).textTheme.labelMedium),
    );
  }
}

class _BulletList extends StatelessWidget {
  const _BulletList({required this.items});

  final List<String> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (final item in items)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(top: 7),
                  child: Container(
                    width: 5,
                    height: 5,
                    decoration: const BoxDecoration(
                      color: AppColors.ai,
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    item,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.45),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _LegacyMotionResult extends StatelessWidget {
  const _LegacyMotionResult({required this.result, this.videoPath});

  final MotionAnalysisResult result;
  final String? videoPath;

  @override
  Widget build(BuildContext context) {
    final qualityKey = 'motion_quality_${result.movementQuality}';
    final qualityLabel = qualityKey.tr();
    final displayQuality =
        qualityLabel == qualityKey ? result.movementQuality : qualityLabel;

    return Scaffold(
      appBar: OctaAppBar(
        title: 'motion_results'.tr(),
        accent: AppColors.ai,
      ),
      body: OctaPage(
        padding: EdgeInsets.zero,
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          children: [
            OctaPanel(
              elevated: true,
              borderColor: AppColors.ai.withValues(alpha: 0.12),
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'motion_exercise_${result.exercise}'.tr(),
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  OctaBadge(label: displayQuality, color: AppColors.ai),
                ],
              ),
            ),
            if ((videoPath ?? '').trim().isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.videocam_outlined,
                title: 'motion_video_preview'.tr(),
                child: MotionVideoPreview(videoPath: videoPath!, compact: true),
              ),
            ],
            if (result.observations.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.visibility_outlined,
                title: 'motion_observations'.tr(),
                child: _BulletList(items: result.observations),
              ),
            ],
            if (result.tips.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                icon: Icons.lightbulb_outline,
                title: 'motion_tips'.tr(),
                child: _BulletList(items: result.tips),
              ),
            ],
            const SizedBox(height: AppSpacing.xl),
            AppPrimaryButton(
              labelKey: 'motion_try_again',
              accent: OctaAccent.ai,
              icon: Icons.refresh_rounded,
              onPressed: () => context.go('/motion'),
            ),
          ],
        ),
      ),
    );
  }
}
