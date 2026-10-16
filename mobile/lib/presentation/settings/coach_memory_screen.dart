import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/coach_memory.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';
import 'package:octa_fit/presentation/settings/cubit/coach_memory_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class CoachMemoryScreen extends StatelessWidget {
  const CoachMemoryScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => CoachMemoryCubit(sl<CoachRepository>())..load(),
      child: const _CoachMemoryView(),
    );
  }
}

class _CoachMemoryView extends StatelessWidget {
  const _CoachMemoryView();

  String _categoryKey(String category) {
    switch (category) {
      case 'training_preference':
        return 'coach_memory_cat_training';
      case 'nutrition_preference':
        return 'coach_memory_cat_nutrition';
      case 'food_preference':
        return 'coach_memory_cat_food';
      case 'equipment':
        return 'coach_memory_cat_equipment';
      case 'goal':
        return 'coach_memory_cat_goal';
      case 'schedule':
        return 'coach_memory_cat_schedule';
      case 'coaching_preference':
        return 'coach_memory_cat_coaching';
      case 'profile_preference':
        return 'coach_memory_cat_profile';
      default:
        return 'coach_memory_cat_profile';
    }
  }

  Future<void> _confirmClear(BuildContext context) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('coach_memory_clear_title'.tr()),
        content: Text('coach_memory_clear_body'.tr()),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text('common_cancel'.tr()),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text('coach_memory_clear_confirm'.tr()),
          ),
        ],
      ),
    );
    if (ok == true && context.mounted) {
      await context.read<CoachMemoryCubit>().clearAll();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'coach_memory_title'.tr(),
        actions: [
          BlocBuilder<CoachMemoryCubit, CoachMemoryState>(
            builder: (context, state) {
              final hasItems = state is CoachMemoryLoaded && state.items.isNotEmpty;
              if (!hasItems) return const SizedBox.shrink();
              return IconButton(
                tooltip: 'coach_memory_clear'.tr(),
                onPressed: () => _confirmClear(context),
                icon: const Icon(Icons.delete_outline),
              );
            },
          ),
        ],
      ),
      body: OctaPage(
        child: BlocBuilder<CoachMemoryCubit, CoachMemoryState>(
          builder: (context, state) {
            if (state is CoachMemoryLoading || state is CoachMemoryInitial) {
              return const Center(child: CircularProgressIndicator());
            }
            if (state is CoachMemoryError) {
              return Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(state.messageKey.tr()),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: () => context.read<CoachMemoryCubit>().load(),
                      child: Text('common_retry'.tr()),
                    ),
                  ],
                ),
              );
            }
            final loaded = state as CoachMemoryLoaded;
            return RefreshIndicator(
              onRefresh: () => context.read<CoachMemoryCubit>().load(),
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
                children: [
                  OctaSurface(
                    elevated: true,
                    child: Text(
                      'coach_memory_privacy_body'.tr(),
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: AppColors.textSecondary,
                            height: 1.4,
                          ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.section),
                  if (loaded.errorKey != null) ...[
                    Text(
                      loaded.errorKey!.tr(),
                      style: TextStyle(color: Theme.of(context).colorScheme.error),
                    ),
                    const SizedBox(height: AppSpacing.md),
                  ],
                  if (loaded.items.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 40),
                      child: Center(
                        child: Text(
                          'coach_memory_empty'.tr(),
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                color: AppColors.textSecondary,
                              ),
                        ),
                      ),
                    )
                  else
                    ..._grouped(loaded.items).entries.map((entry) {
                      return Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            AppSectionHeader(
                              _categoryKey(entry.key),
                              accent: AppColors.ai,
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            ...entry.value.map(
                              (item) => Padding(
                                padding: const EdgeInsets.only(bottom: 8),
                                child: _MemoryTile(
                                  item: item,
                                  busy: loaded.busy,
                                  onRemove: () => context
                                      .read<CoachMemoryCubit>()
                                      .deactivate(item.id),
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  Map<String, List<CoachMemoryItem>> _grouped(List<CoachMemoryItem> items) {
    final map = <String, List<CoachMemoryItem>>{};
    for (final item in items) {
      map.putIfAbsent(item.category, () => []).add(item);
    }
    return map;
  }
}

class _MemoryTile extends StatelessWidget {
  const _MemoryTile({
    required this.item,
    required this.onRemove,
    required this.busy,
  });

  final CoachMemoryItem item;
  final VoidCallback onRemove;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return OctaSurface(
      elevated: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.key.replaceAll('_', ' '),
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  item.displayValue,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  item.explicit
                      ? 'coach_memory_source_explicit'.tr()
                      : 'coach_memory_source_inferred'.tr(),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: AppColors.textPrimary,
                      ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          IconButton(
            tooltip: 'coach_memory_remove'.tr(),
            onPressed: busy ? null : onRemove,
            icon: const Icon(Icons.close),
          ),
        ],
      ),
    );
  }
}
