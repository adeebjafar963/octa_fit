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
import 'package:octa_fit/presentation/training/cubit/training_list_cubit.dart';
import 'package:octa_fit/presentation/training/training_theme.dart';

class TrainingListScreen extends StatelessWidget {
  const TrainingListScreen({
    required this.type,
    this.level,
    this.categoryId,
    this.title,
    super.key,
  });

  final String type;
  final String? level;
  final int? categoryId;
  final String? title;

  @override
  Widget build(BuildContext context) {
    final accent = trainingAccentFor(type);

    return BlocProvider(
      create: (_) => TrainingListCubit(
        sl<TrainingRepository>(),
        type: type,
        level: level,
        categoryId: categoryId,
      )..load(),
      child: _TrainingListView(
        title: title ?? 'training_title'.tr(),
        accent: accent,
        type: type,
      ),
    );
  }
}

class _TrainingListView extends StatefulWidget {
  const _TrainingListView({
    required this.title,
    required this.accent,
    required this.type,
  });

  final String title;
  final Color accent;
  final String type;

  @override
  State<_TrainingListView> createState() => _TrainingListViewState();
}

class _TrainingListViewState extends State<_TrainingListView> {
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: widget.title, accent: widget.accent),
      floatingActionButton: BlocBuilder<TrainingListCubit, TrainingListState>(
        builder: (context, state) {
          if (state is! TrainingListLoaded || state.page.items.isEmpty) {
            return const SizedBox.shrink();
          }
          return FloatingActionButton.extended(
            backgroundColor: widget.accent,
            onPressed: () {
              final ids = state.page.items.map((e) => e.id).toList();
              context.push('/training/workout/start?exerciseIds=${ids.join(',')}');
            },
            icon: const Icon(Icons.play_arrow_rounded),
            label: Text('training_start_workout'.tr()),
          );
        },
      ),
      body: Column(
        children: [
          OctaPage(
            padding: const EdgeInsets.fromLTRB(AppSpacing.lg, AppSpacing.md, AppSpacing.lg, 0),
            child: TextField(
              controller: _search,
              decoration: InputDecoration(
                hintText: 'training_search_hint'.tr(),
                prefixIcon: Icon(Icons.search, color: widget.accent),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadii.md),
                  borderSide: BorderSide(color: widget.accent, width: 1.5),
                ),
                suffixIcon: IconButton(
                  onPressed: () {
                    _search.clear();
                    context.read<TrainingListCubit>().load();
                  },
                  icon: const Icon(Icons.clear),
                ),
              ),
              textInputAction: TextInputAction.search,
              onSubmitted: (value) =>
                  context.read<TrainingListCubit>().load(search: value.trim()),
            ),
          ),
          Expanded(
            child: BlocBuilder<TrainingListCubit, TrainingListState>(
              builder: (context, state) {
                return switch (state) {
                  TrainingListLoading() || TrainingListInitial() => const AppLoadingView(),
                  TrainingListError(:final messageKey) => AppEmptyState(
                      messageKey: messageKey,
                      accent: widget.accent,
                      onRetry: () => context
                          .read<TrainingListCubit>()
                          .load(search: _search.text.trim()),
                    ),
                  TrainingListLoaded(:final page) => page.items.isEmpty
                      ? AppEmptyState(
                          messageKey: 'training_empty',
                          accent: widget.accent,
                        )
                      : ListView.separated(
                          padding: const EdgeInsets.all(AppSpacing.lg),
                          itemCount: page.items.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: AppSpacing.md),
                          itemBuilder: (context, index) {
                            final exercise = page.items[index];
                            return _ExerciseCard(
                              exercise: exercise,
                              accent: widget.accent,
                              onTap: () =>
                                  context.push('/training/exercises/${exercise.id}'),
                            );
                          },
                        ),
                };
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _ExerciseCard extends StatelessWidget {
  const _ExerciseCard({
    required this.exercise,
    required this.accent,
    required this.onTap,
  });

  final Exercise exercise;
  final Color accent;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final locale = context.locale.languageCode;
    return OctaPanel(
      onTap: onTap,
      elevated: true,
      borderColor: accent.withValues(alpha: 0.28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            exercise.localizedName(locale),
            style: Theme.of(context).textTheme.titleLarge,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              OctaBadge(
                label: 'difficulty_${exercise.difficulty}'.tr(),
                color: accent,
              ),
              if (exercise.equipment != null)
                OctaBadge(label: exercise.equipment!, color: AppColors.textSecondary),
              if (exercise.prescriptionLabel.isNotEmpty)
                OctaBadge(label: exercise.prescriptionLabel, color: AppColors.primary),
              if (exercise.restSeconds != null)
                OctaBadge(
                  label: 'training_rest_sec'.tr(namedArgs: {
                    'sec': '${exercise.restSeconds}',
                  }),
                  color: AppColors.warning,
                ),
            ],
          ),
        ],
      ),
    );
  }
}
