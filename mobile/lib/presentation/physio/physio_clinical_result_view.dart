import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

/// Premium structured clinical assessment result for AI Physiotherapist demos.
class PhysioClinicalResultView extends StatelessWidget {
  const PhysioClinicalResultView({super.key, required this.assessment});

  final PhysioAssessment assessment;

  Color _severityColor(String? level) {
    final value = (level ?? assessment.riskLevel ?? '').toLowerCase();
    return switch (value) {
      'urgent' => AppColors.danger,
      'high' => AppColors.warning,
      'moderate' => AppColors.info,
      'low' => AppColors.success,
      _ => AppColors.ai,
    };
  }

  String _severityLabel(String? level) {
    final raw = (level ?? assessment.riskLevel ?? 'LOW').toUpperCase();
    final key = 'physio_escalation_$raw';
    final translated = key.tr();
    return translated == key ? raw : translated;
  }

  IconData _findingIcon(String? icon) {
    return switch (icon) {
      'duration' => Icons.schedule_rounded,
      'pattern' => Icons.timeline_rounded,
      'trigger' => Icons.bolt_rounded,
      'severity' => Icons.thermostat_rounded,
      'neuro' => Icons.psychology_alt_rounded,
      'load' => Icons.fitness_center_rounded,
      'recovery' => Icons.trending_up_rounded,
      'safety' => Icons.health_and_safety_rounded,
      _ => Icons.check_circle_outline_rounded,
    };
  }

  @override
  Widget build(BuildContext context) {
    final result = assessment.result;
    final severity = (result.escalationLevel ?? assessment.riskLevel ?? 'LOW')
        .toUpperCase();
    final isUrgent = severity == 'URGENT' || assessment.requiresUrgentCare;
    final color = _severityColor(severity);
    final impression = (result.initialClinicalImpression ?? '').trim();
    final avoid =
        result.whatToAvoid.isNotEmpty ? result.whatToAvoid : result.avoid;
    final next =
        (result.nextStep ?? result.followUp ?? result.professionalCare ?? '')
            .trim();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _HeroHeader(
          bodyAreaLabel: 'physio_area_${assessment.bodyArea}'.tr(),
          assessmentLabel: 'physio_section_clinical_assessment'.tr(),
          severityLabel: _severityLabel(severity),
          severityColor: color,
          subtitle: (result.subtitle ?? '').trim().isNotEmpty
              ? result.subtitle!
              : 'physio_result_subtitle'.tr(),
        ),
        if (isUrgent &&
            (result.medicalPriorityMessage ?? '').trim().isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          _PriorityBanner(message: result.medicalPriorityMessage!),
        ],
        const SizedBox(height: AppSpacing.md),
        if (impression.isNotEmpty)
          _SectionCard(
            icon: Icons.biotech_outlined,
            title: 'physio_section_clinical_summary'.tr(),
            child: Text(
              impression,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    height: 1.55,
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w500,
                  ),
            ),
          ),
        if (result.keyFindings.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          _SectionCard(
            icon: Icons.manage_search_rounded,
            title: 'physio_section_why_assessment'.tr(),
            child: Column(
              children: [
                for (final finding in result.keyFindings)
                  _FindingTile(
                    icon: _findingIcon(finding.icon),
                    title: finding.title,
                    detail: finding.detail,
                  ),
              ],
            ),
          ),
        ],
        if (!isUrgent && result.recommendedExercises.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          _SectionCard(
            icon: Icons.fitness_center_rounded,
            title: 'physio_section_exercises'.tr(),
            child: Column(
              children: [
                for (final exercise in result.recommendedExercises)
                  _PremiumExerciseCard(exercise: exercise),
              ],
            ),
          ),
        ],
        if (!result.selfManagement.isEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          _SectionCard(
            icon: Icons.task_alt_rounded,
            title: 'physio_section_what_now'.tr(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (result.selfManagement.today.isNotEmpty) ...[
                  Text(
                    'physio_self_today'.tr(),
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const SizedBox(height: 8),
                  _BulletList(items: result.selfManagement.today),
                  const SizedBox(height: AppSpacing.sm),
                ],
                if (result.selfManagement.nextDays.isNotEmpty) ...[
                  Text(
                    'physio_self_next_days'.tr(),
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const SizedBox(height: 8),
                  _BulletList(items: result.selfManagement.nextDays),
                ],
              ],
            ),
          ),
        ],
        if (avoid.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          _SectionCard(
            icon: Icons.do_not_disturb_alt_rounded,
            title: 'physio_section_avoid_now'.tr(),
            child: _BulletList(items: avoid),
          ),
        ],
        if (result.redFlags.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          _SectionCard(
            icon: Icons.warning_amber_rounded,
            title: 'physio_section_attention_signs'.tr(),
            accent: AppColors.danger,
            child: _BulletList(items: result.redFlags, accent: AppColors.danger),
          ),
        ],
        if (next.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          _SectionCard(
            icon: Icons.flag_rounded,
            title: 'physio_section_next_step'.tr(),
            accent: color,
            child: Text(
              next,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    height: 1.5,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
            ),
          ),
        ],
      ],
    );
  }
}

class _HeroHeader extends StatelessWidget {
  const _HeroHeader({
    required this.bodyAreaLabel,
    required this.assessmentLabel,
    required this.severityLabel,
    required this.severityColor,
    required this.subtitle,
  });

  final String bodyAreaLabel;
  final String assessmentLabel;
  final String severityLabel;
  final Color severityColor;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return OctaSurface(
      elevated: true,
      borderColor: severityColor.withValues(alpha: 0.22),
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      bodyAreaLabel,
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      assessmentLabel,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: AppColors.ai,
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                  ],
                ),
              ),
              OctaBadge(label: severityLabel, color: severityColor),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            subtitle,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                  height: 1.45,
                ),
          ),
        ],
      ),
    );
  }
}

class _PriorityBanner extends StatelessWidget {
  const _PriorityBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return OctaSurface(
      borderColor: AppColors.danger.withValues(alpha: 0.45),
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.emergency_rounded, color: AppColors.danger),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'physio_medical_priority_title'.tr(),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.danger,
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 6),
                Text(
                  message,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        height: 1.45,
                        color: AppColors.textPrimary,
                      ),
                ),
              ],
            ),
          ),
        ],
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
    return OctaSurface(
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

class _FindingTile extends StatelessWidget {
  const _FindingTile({
    required this.icon,
    required this.title,
    required this.detail,
  });

  final IconData icon;
  final String title;
  final String detail;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: AppColors.ai.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(AppRadii.sm),
            ),
            child: Icon(icon, size: 18, color: AppColors.ai),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 2),
                Text(
                  detail,
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
    );
  }
}

class _PremiumExerciseCard extends StatelessWidget {
  const _PremiumExerciseCard({required this.exercise});

  final PhysioExerciseRecommendation exercise;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.elevated.withValues(alpha: 0.65),
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: AppColors.ai.withValues(alpha: 0.1)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  exercise.name,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
              if ((exercise.difficulty ?? '').isNotEmpty)
                OctaBadge(
                  label: exercise.difficulty!,
                  color: AppColors.info,
                ),
            ],
          ),
          if ((exercise.purpose ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              '${'physio_exercise_purpose'.tr()}: ${exercise.purpose}',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    height: 1.4,
                    color: AppColors.textSecondary,
                  ),
            ),
          ],
          if (exercise.instructions.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(
              'physio_exercise_howto'.tr(),
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 6),
            for (var i = 0; i < exercise.instructions.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(
                  '${i + 1}. ${exercise.instructions[i]}',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        height: 1.4,
                      ),
                ),
              ),
          ],
          const SizedBox(height: AppSpacing.sm),
          Text(
            'physio_section_dosage'.tr(),
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if ((exercise.sets ?? '').isNotEmpty)
                _DoseChip(
                  label: '${'physio_exercise_sets'.tr()}: ${exercise.sets}',
                ),
              if (exercise.doseLabel.isNotEmpty)
                _DoseChip(
                  label: '${'physio_exercise_dose'.tr()}: ${exercise.doseLabel}',
                ),
              if ((exercise.rest ?? '').isNotEmpty)
                _DoseChip(
                  label: '${'physio_exercise_rest'.tr()}: ${exercise.rest}',
                ),
              if ((exercise.frequency ?? '').isNotEmpty)
                _DoseChip(
                  label:
                      '${'physio_exercise_frequency'.tr()}: ${exercise.frequency}',
                ),
            ],
          ),
          if ((exercise.safetyNote ?? exercise.caution ?? '').isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(
              '${'physio_exercise_caution'.tr()}: ${exercise.safetyNote ?? exercise.caution}',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.warning,
                    height: 1.4,
                  ),
            ),
          ],
        ],
      ),
    );
  }
}

class _DoseChip extends StatelessWidget {
  const _DoseChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.ai.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(AppRadii.sm),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: AppColors.textPrimary,
            ),
      ),
    );
  }
}

class _BulletList extends StatelessWidget {
  const _BulletList({required this.items, this.accent});

  final List<String> items;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final color = accent ?? AppColors.ai;
    return Column(
      children: [
        for (final item in items)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(top: 7),
                  child: Container(
                    width: 5,
                    height: 5,
                    decoration: BoxDecoration(
                      color: color,
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    item,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          height: 1.45,
                          color: AppColors.textPrimary,
                        ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
