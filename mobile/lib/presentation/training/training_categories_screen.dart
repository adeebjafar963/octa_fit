import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';
import 'package:octa_fit/presentation/training/cubit/categories_cubit.dart';
import 'package:octa_fit/presentation/training/training_theme.dart';

class TrainingCategoriesScreen extends StatelessWidget {
  const TrainingCategoriesScreen({
    required this.type,
    required this.level,
    super.key,
  });

  final String type;
  final String level;

  @override
  Widget build(BuildContext context) {
    final accent = trainingAccentFor(type);
    return BlocProvider(
      create: (_) => CategoriesCubit(sl<TrainingRepository>())
        ..load(type: type, level: level),
      child: Scaffold(
        appBar: OctaAppBar(
          title: 'difficulty_$level'.tr(),
          accent: accent,
        ),
        body: BlocBuilder<CategoriesCubit, CategoriesState>(
          builder: (context, state) {
            return switch (state) {
              CategoriesLoading() => const AppLoadingView(),
              CategoriesError(:final messageKey) => AppEmptyState(
                  messageKey: messageKey,
                  accent: accent,
                  onRetry: () => context
                      .read<CategoriesCubit>()
                      .load(type: type, level: level),
                ),
              CategoriesLoaded(:final categories, :final programs) => OctaPage(
                  child: ListView(
                    padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
                    children: [
                      if (programs.isNotEmpty) ...[
                        Text(
                          'training_programs'.tr(),
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        ...programs.map((p) => Padding(
                              padding: const EdgeInsets.only(bottom: AppSpacing.md),
                              child: OctaPanel(
                                elevated: true,
                                borderColor: accent.withValues(alpha: 0.4),
                                onTap: () => context.push(
                                  '/training/workout/start?programId=${p.id}',
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      p.localizedName(context.locale.languageCode),
                                      style: Theme.of(context).textTheme.titleLarge,
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      'training_program_meta'.tr(namedArgs: {
                                        'count': '${p.programExercisesCount ?? p.exercises.length}',
                                      }),
                                      style: Theme.of(context).textTheme.bodySmall,
                                    ),
                                    const SizedBox(height: AppSpacing.sm),
                                    Text(
                                      'training_start_workout'.tr(),
                                      style: Theme.of(context)
                                          .textTheme
                                          .labelLarge
                                          ?.copyWith(color: accent),
                                    ),
                                  ],
                                ),
                              ),
                            )),
                        const SizedBox(height: AppSpacing.lg),
                      ],
                      Text(
                        'training_categories_title'.tr(),
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: AppSpacing.md),
                      if (categories.isEmpty)
                        Text('training_empty'.tr())
                      else
                        ...categories.map((c) => Padding(
                              padding: const EdgeInsets.only(bottom: AppSpacing.md),
                              child: OctaPanel(
                                onTap: () => context.push(
                                  '/training/$type/levels/$level/categories/${c.id}',
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            c.localizedName(context.locale.languageCode),
                                            style: Theme.of(context).textTheme.titleLarge,
                                          ),
                                          if (c.exercisesCount != null) ...[
                                            const SizedBox(height: 4),
                                            Text(
                                              '${c.exercisesCount} ${'training_exercises_count'.tr()}',
                                              style: Theme.of(context).textTheme.bodySmall,
                                            ),
                                          ],
                                        ],
                                      ),
                                    ),
                                    Icon(Icons.chevron_right_rounded, color: accent),
                                  ],
                                ),
                              ),
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
