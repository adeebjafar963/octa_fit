import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/domain/repositories/physio_repository.dart';
import 'package:octa_fit/presentation/physio/cubit/physio_assessment_cubit.dart';
import 'package:octa_fit/presentation/physio/physio_clinical_result_view.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class PhysioAssessmentDetailScreen extends StatelessWidget {
  const PhysioAssessmentDetailScreen({super.key, required this.assessmentId});

  final int assessmentId;

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) =>
          PhysioAssessmentCubit(sl<PhysioRepository>())..load(assessmentId),
      child: _PhysioAssessmentDetailView(assessmentId: assessmentId),
    );
  }
}

class _PhysioAssessmentDetailView extends StatelessWidget {
  const _PhysioAssessmentDetailView({required this.assessmentId});

  final int assessmentId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'physio_assessment_detail'.tr(),
        accent: AppColors.ai,
      ),
      body: BlocBuilder<PhysioAssessmentCubit, PhysioAssessmentState>(
        builder: (context, state) {
          return switch (state) {
            PhysioAssessmentLoading() ||
            PhysioAssessmentInitial() ||
            PhysioAssessmentFormState() => const AppLoadingView(),
            PhysioAssessmentError(:final messageKey) => AppEmptyState(
              messageKey: messageKey,
              accent: AppColors.ai,
              onRetry: () =>
                  context.read<PhysioAssessmentCubit>().load(assessmentId),
            ),
            PhysioAssessmentLoaded(:final assessment) => ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                OctaSurface(
                  borderColor: AppColors.ai.withValues(alpha: 0.12),
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(
                        Icons.info_outline_rounded,
                        color: AppColors.ai,
                        size: 18,
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          'physio_disclaimer'.tr(),
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                height: 1.45,
                              ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                if (assessment.requiresUrgentCare) ...[
                  const _UrgentBanner(),
                  const SizedBox(height: AppSpacing.md),
                ],
                if (assessment.result.hasStructuredClinicalOutput)
                  PhysioClinicalResultView(assessment: assessment)
                else
                  _LegacyResultCard(assessment: assessment),
              ],
            ),
          };
        },
      ),
    );
  }
}

class _UrgentBanner extends StatelessWidget {
  const _UrgentBanner();

  @override
  Widget build(BuildContext context) {
    return OctaSurface(
      borderColor: AppColors.danger.withValues(alpha: 0.5),
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.warning_amber_rounded,
                color: AppColors.danger,
                size: 20,
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  'physio_urgent_title'.tr(),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.danger,
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'physio_urgent_body'.tr(),
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textPrimary,
                  height: 1.45,
                ),
          ),
        ],
      ),
    );
  }
}

class _LegacyResultCard extends StatelessWidget {
  const _LegacyResultCard({required this.assessment});

  final PhysioAssessment assessment;

  Color _riskColor(String? risk) {
    return switch (risk) {
      'urgent' => AppColors.danger,
      'high' => AppColors.warning,
      'moderate' => AppColors.info,
      'low' => AppColors.success,
      _ => AppColors.ai,
    };
  }

  @override
  Widget build(BuildContext context) {
    final result = assessment.result;
    return OctaSurface(
      elevated: true,
      borderColor: AppColors.ai.withValues(alpha: 0.12),
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'physio_area_${assessment.bodyArea}'.tr(),
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
              ),
              if (assessment.riskLevel != null)
                OctaBadge(
                  label: 'physio_risk_${assessment.riskLevel}'.tr(),
                  color: _riskColor(assessment.riskLevel),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          _Section(
            title: 'physio_section_summary'.tr(),
            body: result.summary ?? assessment.symptoms,
          ),
          if (result.possibleExplanations.isNotEmpty)
            _BulletSection(
              title: 'physio_section_explanations'.tr(),
              items: result.possibleExplanations,
            ),
          if (result.selfCare.isNotEmpty)
            _BulletSection(
              title: 'physio_section_self_care'.tr(),
              items: result.selfCare,
            ),
          if (result.avoid.isNotEmpty)
            _BulletSection(
              title: 'physio_section_avoid'.tr(),
              items: result.avoid,
            ),
          if ((result.professionalCare ?? '').isNotEmpty)
            _Section(
              title: 'physio_section_professional'.tr(),
              body: result.professionalCare!,
            ),
          if (result.redFlags.isNotEmpty)
            _BulletSection(
              title: 'physio_section_red_flags'.tr(),
              items: result.redFlags,
              accent: AppColors.danger,
            ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 6),
          Text(
            body,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textPrimary,
                  height: 1.5,
                ),
          ),
        ],
      ),
    );
  }
}

class _BulletSection extends StatelessWidget {
  const _BulletSection({required this.title, required this.items, this.accent});

  final String title;
  final List<String> items;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final color = accent ?? AppColors.ai;
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          ...items.map(
            (item) => Padding(
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
                            color: AppColors.textPrimary,
                            height: 1.45,
                          ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
