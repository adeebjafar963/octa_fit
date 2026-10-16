import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/domain/repositories/physio_repository.dart';
import 'package:octa_fit/presentation/physio/cubit/physio_assessments_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class PhysioAssessmentsScreen extends StatelessWidget {
  const PhysioAssessmentsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => PhysioAssessmentsCubit(sl<PhysioRepository>())..load(),
      child: const _PhysioAssessmentsView(),
    );
  }
}

class _PhysioAssessmentsView extends StatelessWidget {
  const _PhysioAssessmentsView();

  String _bodyAreaLabel(String key) => 'physio_area_$key'.tr();

  String _riskLabel(String? risk) {
    if (risk == null || risk.isEmpty) return '';
    return 'physio_risk_$risk'.tr();
  }

  Color _riskColor(String? risk) {
    return switch (risk) {
      'urgent' => AppColors.danger,
      'high' => AppColors.warning,
      'moderate' => AppColors.info,
      'low' => AppColors.success,
      _ => AppColors.textSecondary,
    };
  }

  String _formatDate(String? iso, BuildContext context) {
    if (iso == null || iso.isEmpty) return '';
    final date = DateTime.tryParse(iso)?.toLocal();
    if (date == null) return iso;
    return DateFormat.yMMMd(context.locale.languageCode).format(date);
  }

  Future<void> _confirmDelete(
    BuildContext context,
    PhysioAssessment item,
  ) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('physio_delete_assessment'.tr()),
        content: Text('physio_delete_confirm'.tr()),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text('common_cancel'.tr()),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(
              'common_confirm'.tr(),
              style: const TextStyle(color: AppColors.danger),
            ),
          ),
        ],
      ),
    );
    if (ok == true && context.mounted) {
      await context.read<PhysioAssessmentsCubit>().delete(item.id);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'physio_title'.tr(), accent: AppColors.ai),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          await context.push('/physio/new');
          if (context.mounted) {
            context.read<PhysioAssessmentsCubit>().load();
          }
        },
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.onPrimary,
        child: const Icon(Icons.add_rounded),
      ),
      body: BlocConsumer<PhysioAssessmentsCubit, PhysioAssessmentsState>(
        listener: (context, state) {
          if (state is PhysioAssessmentsLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(
              context,
            ).showSnackBar(SnackBar(content: Text(state.feedbackKey!.tr())));
          }
        },
        builder: (context, state) {
          return switch (state) {
            PhysioAssessmentsLoading() ||
            PhysioAssessmentsInitial() => const AppLoadingView(),
            PhysioAssessmentsError(:final messageKey) => AppEmptyState(
              messageKey: messageKey,
              accent: AppColors.ai,
              icon: Icons.health_and_safety_outlined,
              onRetry: () => context.read<PhysioAssessmentsCubit>().load(),
            ),
            PhysioAssessmentsLoaded(:final assessments) =>
              assessments.isEmpty
                  ? AppEmptyState(
                      messageKey: 'physio_empty',
                      accent: AppColors.ai,
                      icon: Icons.health_and_safety_outlined,
                      onRetry: () async {
                        await context.push('/physio/new');
                        if (context.mounted) {
                          context.read<PhysioAssessmentsCubit>().load();
                        }
                      },
                    )
                  : RefreshIndicator(
                      color: AppColors.ai,
                      onRefresh: () =>
                          context.read<PhysioAssessmentsCubit>().load(),
                      child: OctaPage(
                        padding: EdgeInsets.zero,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(
                            AppSpacing.lg,
                            AppSpacing.md,
                            AppSpacing.lg,
                            100,
                          ),
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
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(height: 1.45),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: AppSpacing.lg),
                            ...assessments.map((item) {
                              final risk = item.riskLevel;
                              return Padding(
                                padding: const EdgeInsets.only(
                                  bottom: AppSpacing.sm,
                                ),
                                child: OctaSurface(
                                  onTap: () async {
                                    await context.push(
                                      '/physio/assessment/${item.id}',
                                    );
                                    if (context.mounted) {
                                      context
                                          .read<PhysioAssessmentsCubit>()
                                          .load();
                                    }
                                  },
                                  elevated: true,
                                  borderColor: risk != null
                                      ? _riskColor(risk).withValues(alpha: 0.35)
                                      : AppColors.ai.withValues(alpha: 0.10),
                                  padding: const EdgeInsets.all(AppSpacing.md),
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Container(
                                        width: 44,
                                        height: 44,
                                        decoration: BoxDecoration(
                                          color: AppColors.ai.withValues(
                                            alpha: 0.14,
                                          ),
                                          borderRadius: BorderRadius.circular(
                                            AppRadii.sm,
                                          ),
                                        ),
                                        child: const Icon(
                                          Icons.accessibility_new_rounded,
                                          color: AppColors.ai,
                                          size: 22,
                                        ),
                                      ),
                                      const SizedBox(width: AppSpacing.md),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Row(
                                              children: [
                                                Expanded(
                                                  child: Text(
                                                    _bodyAreaLabel(
                                                      item.bodyArea,
                                                    ),
                                                    style: Theme.of(
                                                      context,
                                                    ).textTheme.titleMedium,
                                                  ),
                                                ),
                                                if (risk != null)
                                                  OctaBadge(
                                                    label: _riskLabel(risk),
                                                    color: _riskColor(risk),
                                                  ),
                                              ],
                                            ),
                                            const SizedBox(height: 6),
                                            Text(
                                              item.shortSymptoms,
                                              maxLines: 2,
                                              overflow: TextOverflow.ellipsis,
                                              style: Theme.of(
                                                context,
                                              ).textTheme.bodyMedium,
                                            ),
                                            const SizedBox(height: 6),
                                            Text(
                                              _formatDate(
                                                item.updatedAt ??
                                                    item.createdAt,
                                                context,
                                              ),
                                              style: Theme.of(
                                                context,
                                              ).textTheme.labelSmall,
                                            ),
                                          ],
                                        ),
                                      ),
                                      IconButton(
                                        tooltip: 'physio_delete_assessment'
                                            .tr(),
                                        onPressed: () =>
                                            _confirmDelete(context, item),
                                        icon: const Icon(
                                          Icons.delete_outline_rounded,
                                          color: AppColors.danger,
                                          size: 20,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            }),
                          ],
                        ),
                      ),
                    ),
          };
        },
      ),
    );
  }
}
