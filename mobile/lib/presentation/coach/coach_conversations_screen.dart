import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';
import 'package:octa_fit/presentation/coach/cubit/coach_conversations_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class CoachConversationsScreen extends StatelessWidget {
  const CoachConversationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => CoachConversationsCubit(sl<CoachRepository>())..load(),
      child: const _CoachConversationsView(),
    );
  }
}

class _CoachConversationsView extends StatelessWidget {
  const _CoachConversationsView();

  Future<void> _create(BuildContext context) async {
    try {
      final conversation = await context
          .read<CoachConversationsCubit>()
          .create();
      if (conversation != null && context.mounted) {
        await context.push('/coach/chat/${conversation.id}');
        if (context.mounted) {
          context.read<CoachConversationsCubit>().load();
        }
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((e.messageKey ?? 'common_error').tr())),
        );
      }
    }
  }

  String _formatDate(String? iso, BuildContext context) {
    if (iso == null || iso.isEmpty) return '';
    final date = DateTime.tryParse(iso)?.toLocal();
    if (date == null) return iso;
    return DateFormat.yMMMd(context.locale.languageCode).add_jm().format(date);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'coach_title'.tr(), accent: AppColors.ai),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _create(context),
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.onPrimary,
        child: const Icon(Icons.add_rounded),
      ),
      body: BlocConsumer<CoachConversationsCubit, CoachConversationsState>(
        listener: (context, state) {
          if (state is CoachConversationsLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(
              context,
            ).showSnackBar(SnackBar(content: Text(state.feedbackKey!.tr())));
          }
        },
        builder: (context, state) {
          return switch (state) {
            CoachConversationsLoading() ||
            CoachConversationsInitial() => const AppLoadingView(),
            CoachConversationsError(:final messageKey) => AppEmptyState(
              messageKey: messageKey,
              accent: AppColors.ai,
              icon: Icons.smart_toy_outlined,
              onRetry: () => context.read<CoachConversationsCubit>().load(),
            ),
            CoachConversationsLoaded(:final conversations) =>
              conversations.isEmpty
                  ? AppEmptyState(
                      messageKey: 'coach_empty',
                      accent: AppColors.ai,
                      icon: Icons.forum_outlined,
                      actionKey: 'common_retry',
                      onRetry: () => _create(context),
                    )
                  : RefreshIndicator(
                      color: AppColors.ai,
                      onRefresh: () =>
                          context.read<CoachConversationsCubit>().load(),
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
                            Container(
                              padding: const EdgeInsets.all(AppSpacing.md),
                              decoration: AppFeatureCardSurface.ai(
                                borderRadius: BorderRadius.circular(
                                  AppRadii.lg,
                                ),
                              ),
                              child: Row(
                                children: [
                                  const Icon(
                                    Icons.auto_awesome,
                                    color: AppColors.ai,
                                    size: 18,
                                  ),
                                  const SizedBox(width: AppSpacing.sm),
                                  Expanded(
                                    child: Text(
                                      'coach_welcome'.tr(),
                                      style: Theme.of(
                                        context,
                                      ).textTheme.bodySmall,
                                    ),
                                  ),
                                  OctaBadge(label: 'AI', color: AppColors.ai),
                                ],
                              ),
                            ),
                            const SizedBox(height: AppSpacing.lg),
                            ...conversations.map((item) {
                              return Padding(
                                padding: const EdgeInsets.only(
                                  bottom: AppSpacing.sm,
                                ),
                                child: OctaSurface(
                                  onTap: () async {
                                    await context.push(
                                      '/coach/chat/${item.id}',
                                    );
                                    if (context.mounted) {
                                      context
                                          .read<CoachConversationsCubit>()
                                          .load();
                                    }
                                  },
                                  elevated: true,
                                  borderColor: AppColors.ai.withValues(
                                    alpha: 0.18,
                                  ),
                                  padding: const EdgeInsets.all(AppSpacing.md),
                                  child: Row(
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
                                          Icons.chat_bubble_outline_rounded,
                                          color: AppColors.textPrimary,
                                          size: 20,
                                        ),
                                      ),
                                      const SizedBox(width: AppSpacing.md),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              item.displayTitle(
                                                'coach_untitled'.tr(),
                                              ),
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                              style: Theme.of(
                                                context,
                                              ).textTheme.titleMedium,
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              _formatDate(
                                                item.updatedAt ??
                                                    item.createdAt,
                                                context,
                                              ),
                                              style: Theme.of(
                                                context,
                                              ).textTheme.bodySmall,
                                            ),
                                          ],
                                        ),
                                      ),
                                      IconButton(
                                        icon: const Icon(
                                          Icons.delete_outline_rounded,
                                          color: AppColors.danger,
                                          size: 20,
                                        ),
                                        onPressed: () async {
                                          final ok = await showDialog<bool>(
                                            context: context,
                                            builder: (context) => AlertDialog(
                                              title: Text(
                                                'coach_delete_conversation'
                                                    .tr(),
                                              ),
                                              content: Text(
                                                'coach_delete_confirm'.tr(),
                                              ),
                                              actions: [
                                                TextButton(
                                                  onPressed: () =>
                                                      Navigator.pop(
                                                        context,
                                                        false,
                                                      ),
                                                  child: Text(
                                                    'common_cancel'.tr(),
                                                  ),
                                                ),
                                                TextButton(
                                                  onPressed: () =>
                                                      Navigator.pop(
                                                        context,
                                                        true,
                                                      ),
                                                  child: Text(
                                                    'common_confirm'.tr(),
                                                    style: const TextStyle(
                                                      color: AppColors.danger,
                                                    ),
                                                  ),
                                                ),
                                              ],
                                            ),
                                          );
                                          if (ok == true && context.mounted) {
                                            context
                                                .read<CoachConversationsCubit>()
                                                .delete(item.id);
                                          }
                                        },
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
