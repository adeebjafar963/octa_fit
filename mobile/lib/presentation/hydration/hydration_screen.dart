import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/hydration.dart';
import 'package:octa_fit/domain/repositories/hydration_repository.dart';
import 'package:octa_fit/presentation/hydration/cubit/hydration_cubit.dart';
import 'package:octa_fit/presentation/hydration/widgets/home_hydration_card.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';

class HydrationScreen extends StatelessWidget {
  const HydrationScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) =>
          HydrationCubit(sl<HydrationRepository>())..load(withHistory: true),
      child: const _HydrationView(),
    );
  }
}

class _HydrationView extends StatelessWidget {
  const _HydrationView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(
        title: 'hydration_title'.tr(),
        subtitle: 'hydration_goal'.tr(),
        accent: AppColors.primary,
      ),
      body: BlocBuilder<HydrationCubit, HydrationState>(
        builder: (context, state) {
          return switch (state) {
            HydrationInitial() || HydrationLoading() => const AppLoadingView(),
            HydrationError(:final messageKey) => AppEmptyState(
              messageKey: messageKey ?? 'common_error',
              accent: AppColors.primary,
              onRetry: () =>
                  context.read<HydrationCubit>().load(withHistory: true),
            ),
            HydrationLoaded(:final summary, :final history, :final busy) =>
              RefreshIndicator(
                color: AppColors.primary,
                onRefresh: () =>
                    context.read<HydrationCubit>().load(withHistory: true),
                child: ListView(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  children: [
                    const HomeHydrationCard(wide: true),
                    const SizedBox(height: AppSpacing.lg),
                    _GoalEditor(summary: summary, busy: busy),
                    const SizedBox(height: AppSpacing.lg),
                    Text(
                      'hydration_timeline'.tr(),
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    if (summary.intakes.isEmpty)
                      Text(
                        'hydration_empty'.tr(),
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      )
                    else
                      ...summary.intakes.reversed.map(
                        (i) => _IntakeTile(intake: i, busy: busy),
                      ),
                    if (history != null && history.days.isNotEmpty) ...[
                      const SizedBox(height: AppSpacing.xl),
                      Text(
                        'hydration_history'.tr(),
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: AppSpacing.md),
                      ...history.days.map((d) => _DayBar(day: d)),
                    ],
                    const SizedBox(height: AppSpacing.lg),
                    Text(
                      'hydration_goal_disclaimer'.tr(),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
          };
        },
      ),
    );
  }
}

class _GoalEditor extends StatefulWidget {
  const _GoalEditor({required this.summary, required this.busy});
  final HydrationSummary summary;
  final bool busy;

  @override
  State<_GoalEditor> createState() => _GoalEditorState();
}

class _GoalEditorState extends State<_GoalEditor> {
  late final TextEditingController _c;

  @override
  void initState() {
    super.initState();
    _c = TextEditingController(text: '${widget.summary.goalMl}');
  }

  @override
  void didUpdateWidget(covariant _GoalEditor oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.summary.goalMl != widget.summary.goalMl) {
      _c.text = '${widget.summary.goalMl}';
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cubit = context.read<HydrationCubit>();
    return OctaPanel(
      elevated: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'hydration_edit_goal'.tr(),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: AppSpacing.sm),
          if (widget.summary.recommendedGoalMl != null)
            Text(
              'hydration_recommended'.tr(
                namedArgs: {'n': '${widget.summary.recommendedGoalMl}'},
              ),
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
            ),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _c,
                  enabled: !widget.busy,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: InputDecoration(
                    labelText: 'hydration_goal'.tr(),
                    suffixText: 'hydration_ml'.tr(),
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              FilledButton(
                onPressed: widget.busy
                    ? null
                    : () {
                        final n = int.tryParse(_c.text);
                        if (n != null) cubit.updateGoal(n);
                      },
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: AppColors.onPrimary,
                ),
                child: Text('hydration_save_goal'.tr()),
              ),
            ],
          ),
          if (widget.summary.goalIsCustom) ...[
            const SizedBox(height: AppSpacing.sm),
            TextButton(
              onPressed: widget.busy ? null : cubit.useRecommendedGoal,
              child: Text('hydration_use_recommended'.tr()),
            ),
          ],
        ],
      ),
    );
  }
}

class _IntakeTile extends StatelessWidget {
  const _IntakeTile({required this.intake, required this.busy});
  final WaterIntake intake;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final local = intake.consumedAtLocal ?? intake.consumedAt;
    String timeLabel = '—';
    if (local != null) {
      final dt = DateTime.tryParse(local)?.toLocal();
      if (dt != null) {
        timeLabel =
            '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
      }
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
          side: const BorderSide(color: AppColors.border),
        ),
        tileColor: AppColors.surface,
        leading: const Icon(
          Icons.water_drop_outlined,
          color: AppColors.primary,
        ),
        title: Text('${intake.amountMl} ${'hydration_ml'.tr()}'),
        subtitle: Text(timeLabel),
        trailing: IconButton(
          tooltip: 'hydration_delete'.tr(),
          onPressed: busy
              ? null
              : () => context.read<HydrationCubit>().deleteIntake(intake.id),
          icon: const Icon(
            Icons.delete_outline,
            color: AppColors.textSecondary,
          ),
        ),
      ),
    );
  }
}

class _DayBar extends StatelessWidget {
  const _DayBar({required this.day});
  final HydrationDay day;

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(day.date);
    final label = date == null
        ? day.date
        : DateFormat.MMMd(context.locale.toString()).format(date);
    final pct = day.progress.clamp(0.0, 1.0);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  style: Theme.of(context).textTheme.labelLarge,
                ),
              ),
              Text(
                '${(day.consumedMl / 1000).toStringAsFixed(1)} / ${(day.goalMl / 1000).toStringAsFixed(1)} ${'hydration_l'.tr()}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadii.full),
            child: LinearProgressIndicator(
              value: pct,
              minHeight: 8,
              backgroundColor: AppColors.border,
              color: day.goalMet ? AppColors.success : AppColors.primary,
            ),
          ),
        ],
      ),
    );
  }
}
