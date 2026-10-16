import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/training/cubit/disciplines_cubit.dart';
import 'package:octa_fit/presentation/training/training_theme.dart';

class TrainingHomeScreen extends StatelessWidget {
  const TrainingHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => DisciplinesCubit(sl<TrainingRepository>())..load(),
      child: const _TrainingHomeView(),
    );
  }
}

class _TrainingHomeView extends StatelessWidget {
  const _TrainingHomeView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'training_title'.tr(),
        actions: [
          IconButton(
            tooltip: 'training_history'.tr(),
            onPressed: () => context.push('/training/history'),
            icon: const Icon(Icons.history_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: BlocBuilder<DisciplinesCubit, DisciplinesState>(
          builder: (context, state) {
            return switch (state) {
              DisciplinesLoading() || DisciplinesInitial() => const AppLoadingView(),
              DisciplinesError(:final messageKey) => AppEmptyState(
                  messageKey: messageKey,
                  onRetry: () => context.read<DisciplinesCubit>().load(),
                ),
              DisciplinesLoaded(:final disciplines) => OctaPage(
                  child: ListView(
                    padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
                    children: [
                      Text(
                        'training_subtitle'.tr(),
                        style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                              color: AppColors.textSecondary,
                              height: 1.45,
                            ),
                      ),
                      const SizedBox(height: AppSpacing.xl),
                      ...disciplines.map((d) => Padding(
                            padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                            child: _DisciplineCard(discipline: d),
                          )),
                    ],
                  ),
                ),
            };
          },
        ),
      ),
    );
  }
}

class _DisciplineCard extends StatefulWidget {
  const _DisciplineCard({required this.discipline});
  final TrainingDiscipline discipline;

  @override
  State<_DisciplineCard> createState() => _DisciplineCardState();
}

class _DisciplineCardState extends State<_DisciplineCard> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final d = widget.discipline;
    final accent = trainingAccentFor(d.accent == 'primary' ? d.slug : d.accent);
    final locale = context.locale.languageCode;

    return AnimatedScale(
      scale: _pressed ? 0.985 : 1,
      duration: const Duration(milliseconds: 120),
      child: GestureDetector(
        onTapDown: (_) => setState(() => _pressed = true),
        onTapCancel: () => setState(() => _pressed = false),
        onTapUp: (_) => setState(() => _pressed = false),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: () => context.push('/training/${d.slug}/levels'),
            borderRadius: BorderRadius.circular(AppRadii.lg),
            child: Ink(
              decoration: BoxDecoration(
                color: AppColors.surface,
                gradient: accent == AppColors.mma
                    ? AppGradients.mmaCard
                    : AppGradients.trainingCard,
                borderRadius: BorderRadius.circular(AppRadii.lg),
                border: Border.all(color: AppColors.border),
              ),
              padding: const EdgeInsets.fromLTRB(22, 22, 22, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(_iconFor(d.slug), color: accent, size: 28),
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    d.localizedName(locale),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    d.localizedDescription(locale),
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    'training_levels_available'.tr(namedArgs: {
                      'count': '${d.levels.length}',
                    }),
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    'home_cta_train'.tr(),
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  IconData _iconFor(String slug) {
    return switch (slug) {
      'mma' => Icons.sports_mma_outlined,
      'home_calisthenics' => Icons.accessibility_new_outlined,
      _ => Icons.fitness_center_outlined,
    };
  }
}
