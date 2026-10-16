import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_layout.dart';
import 'package:octa_fit/presentation/training/training_theme.dart';

class WorkoutHistoryScreen extends StatefulWidget {
  const WorkoutHistoryScreen({super.key});

  @override
  State<WorkoutHistoryScreen> createState() => _WorkoutHistoryScreenState();
}

class _WorkoutHistoryScreenState extends State<WorkoutHistoryScreen> {
  WorkoutSessionPage? _page;
  String? _errorKey;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _errorKey = null;
    });
    try {
      final page = await sl<TrainingRepository>().getSessions();
      if (!mounted) return;
      setState(() {
        _page = page;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorKey = 'common_error';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'training_history'.tr()),
      body: _loading
          ? const AppLoadingView()
          : _errorKey != null
              ? AppEmptyState(messageKey: _errorKey!, onRetry: _load)
              : (_page?.items.isEmpty ?? true)
                  ? AppEmptyState(messageKey: 'training_history_empty', onRetry: _load)
                  : OctaPage(
                      child: ListView.separated(
                        padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
                        itemCount: _page!.items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.md),
                        itemBuilder: (context, i) {
                          final s = _page!.items[i];
                          final accent = trainingAccentFor(s.type);
                          final locale = context.locale.languageCode;
                          return OctaPanel(
                            onTap: () => context.push('/training/workout/${s.id}'),
                            borderColor: accent.withValues(alpha: 0.3),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  s.localizedTitle(locale),
                                  style: Theme.of(context).textTheme.titleLarge,
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  [
                                    if (s.completedAt != null) s.completedAt!.split('T').first,
                                    if (s.durationSeconds != null)
                                      formatDuration(s.durationSeconds!),
                                    s.status,
                                  ].join(' · '),
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
