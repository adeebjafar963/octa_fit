import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';
import 'package:octa_fit/presentation/training/cubit/levels_cubit.dart';
import 'package:octa_fit/presentation/training/training_theme.dart';

class TrainingLevelsScreen extends StatelessWidget {
  const TrainingLevelsScreen({required this.type, super.key});

  final String type;

  @override
  Widget build(BuildContext context) {
    final accent = trainingAccentFor(type);
    return BlocProvider(
      create: (_) => LevelsCubit(sl<TrainingRepository>())..load(type),
      child: Scaffold(
        appBar: OctaAppBar(
          title: 'training_choose_level'.tr(),
          accent: accent,
        ),
        body: BlocBuilder<LevelsCubit, LevelsState>(
          builder: (context, state) {
            return switch (state) {
              LevelsLoading() => const AppLoadingView(),
              LevelsError(:final messageKey) => AppEmptyState(
                  messageKey: messageKey,
                  accent: accent,
                  onRetry: () => context.read<LevelsCubit>().load(type),
                ),
              LevelsLoaded(:final levels) => OctaPage(
                  child: ListView.separated(
                    padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
                    itemCount: levels.length,
                    separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.md),
                    itemBuilder: (context, i) {
                      final level = levels[i];
                      return OctaPanel(
                        elevated: true,
                        borderColor: accent.withValues(alpha: 0.35),
                        onTap: () => context.push(
                          '/training/$type/levels/${level.slug}/categories',
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: accent.withValues(alpha: 0.16),
                                borderRadius: BorderRadius.circular(AppRadii.md),
                              ),
                              child: Text(
                                '${i + 1}',
                                style: Theme.of(context)
                                    .textTheme
                                    .titleLarge
                                    ?.copyWith(color: accent),
                              ),
                            ),
                            const SizedBox(width: AppSpacing.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    level.localizedName(context.locale.languageCode),
                                    style: Theme.of(context).textTheme.titleLarge,
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'training_level_meta'.tr(namedArgs: {
                                      'categories': '${level.categoriesCount}',
                                      'exercises': '${level.exercisesCount}',
                                    }),
                                    style: Theme.of(context).textTheme.bodySmall,
                                  ),
                                ],
                              ),
                            ),
                            Icon(Icons.chevron_right_rounded, color: accent),
                          ],
                        ),
                      );
                    },
                  ),
                ),
            };
          },
        ),
      ),
    );
  }
}
