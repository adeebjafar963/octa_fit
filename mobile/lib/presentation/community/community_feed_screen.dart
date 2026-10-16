import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';
import 'package:octa_fit/domain/repositories/friendship_repository.dart';
import 'package:octa_fit/presentation/community/cubit/community_feed_cubit.dart';
import 'package:octa_fit/presentation/community/widgets/community_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class CommunityFeedScreen extends StatelessWidget {
  const CommunityFeedScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => CommunityFeedCubit(
        sl<CommunityRepository>(),
        sl<FriendshipRepository>(),
      )..load(),
      child: const _CommunityFeedView(),
    );
  }
}

class _CommunityFeedView extends StatelessWidget {
  const _CommunityFeedView();

  Future<void> _openCreatePost(BuildContext context) async {
    final created = await context.push<bool>('/community/create');
    if (created == true && context.mounted) {
      context.read<CommunityFeedCubit>().refresh();
    }
  }

  Future<void> _openCreateStory(BuildContext context) async {
    final created = await context.push<bool>('/community/story/create');
    if (created == true && context.mounted) {
      context.read<CommunityFeedCubit>().refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    final isCompact = AppBreakpoints.isCompact(context);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(
        title: 'community_title'.tr(),
        accent: AppColors.primary,
        forceBack: true,
        actions: [
          IconButton(
            tooltip: 'friends_find'.tr(),
            onPressed: () async {
              await context.push('/community/friends/find');
              if (context.mounted) context.read<CommunityFeedCubit>().refresh();
            },
            icon: Icon(Icons.search_rounded, color: AppColors.primaryHover),
          ),
          BlocBuilder<CommunityFeedCubit, CommunityFeedState>(
            builder: (context, state) {
              final pending = state is CommunityFeedLoaded
                  ? state.pendingFriendRequests
                  : 0;
              return IconButton(
                tooltip: 'friends_title'.tr(),
                onPressed: () async {
                  await context.push('/community/friends');
                  if (context.mounted) {
                    context.read<CommunityFeedCubit>().refresh();
                  }
                },
                icon: Badge(
                  isLabelVisible: pending > 0,
                  label: Text('$pending'),
                  child: Icon(
                    Icons.group_outlined,
                    color: AppColors.primaryHover,
                  ),
                ),
              );
            },
          ),
          const SizedBox(width: AppSpacing.xs),
        ],
      ),
      floatingActionButton: SafeArea(
        child: FloatingActionButton.extended(
          onPressed: () => _openCreatePost(context),
          backgroundColor: AppColors.primary,
          foregroundColor: AppColors.onPrimary,
          elevation: 2,
          icon: const Icon(Icons.edit_outlined),
          label: Text('community_create_post'.tr()),
        ),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
      body: BlocConsumer<CommunityFeedCubit, CommunityFeedState>(
        listener: (context, state) {
          if (state is CommunityFeedLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(
              context,
            ).showSnackBar(SnackBar(content: Text(state.feedbackKey!.tr())));
          }
        },
        builder: (context, state) {
          return switch (state) {
            CommunityFeedLoading() || CommunityFeedInitial() => Align(
              alignment: Alignment.topCenter,
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxWidth: isCompact ? double.infinity : 640,
                ),
                child: const CommunityFeedSkeleton(),
              ),
            ),
            CommunityFeedError(:final messageKey) => Center(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.xl),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.wifi_off_rounded,
                      size: 40,
                      color: AppColors.textSecondary.withValues(alpha: 0.8),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      messageKey.tr(),
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    AppPrimaryButton(
                      labelKey: 'common_retry',
                      onPressed: () =>
                          context.read<CommunityFeedCubit>().load(),
                    ),
                  ],
                ),
              ),
            ),
            CommunityFeedLoaded(
              :final posts,
              :final stories,
              :final hasMore,
              :final loadingMore,
              :final friendCount,
            ) =>
              RefreshIndicator(
                color: AppColors.primary,
                onRefresh: () => context.read<CommunityFeedCubit>().refresh(),
                child: Align(
                  alignment: Alignment.topCenter,
                  child: ConstrainedBox(
                    constraints: BoxConstraints(
                      maxWidth: isCompact ? double.infinity : 640,
                    ),
                    child: CustomScrollView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      slivers: [
                        SliverPadding(
                          padding: EdgeInsets.fromLTRB(
                            isCompact ? AppSpacing.md : AppSpacing.lg,
                            AppSpacing.md,
                            isCompact ? AppSpacing.md : AppSpacing.lg,
                            0,
                          ),
                          sliver: SliverToBoxAdapter(
                            child: CommunityStoriesRow(
                              stories: stories,
                              onAddStory: () => _openCreateStory(context),
                              onOpenStory: (story) =>
                                  showStoryViewer(context, story),
                            ),
                          ),
                        ),
                        if (posts.isEmpty)
                          SliverFillRemaining(
                            hasScrollBody: false,
                            child: _CommunityEmptyState(
                              hasFriends: friendCount > 0,
                              onCreatePost: () => _openCreatePost(context),
                              onCreateStory: () => _openCreateStory(context),
                              onFindFriends: () =>
                                  context.push('/community/friends/find'),
                            ),
                          )
                        else ...[
                          SliverPadding(
                            padding: EdgeInsets.fromLTRB(
                              isCompact ? AppSpacing.md : AppSpacing.lg,
                              AppSpacing.lg,
                              isCompact ? AppSpacing.md : AppSpacing.lg,
                              0,
                            ),
                            sliver: SliverToBoxAdapter(
                              child: _CreatePostPrompt(
                                onTap: () => _openCreatePost(context),
                              ),
                            ),
                          ),
                          SliverPadding(
                            padding: EdgeInsets.fromLTRB(
                              isCompact ? AppSpacing.md : AppSpacing.lg,
                              AppSpacing.md,
                              isCompact ? AppSpacing.md : AppSpacing.lg,
                              110,
                            ),
                            sliver: SliverList.separated(
                              itemCount: posts.length + (hasMore ? 1 : 0),
                              separatorBuilder: (_, __) =>
                                  const SizedBox(height: AppSpacing.md),
                              itemBuilder: (context, index) {
                                if (index >= posts.length) {
                                  return Padding(
                                    padding: const EdgeInsets.symmetric(
                                      vertical: AppSpacing.md,
                                    ),
                                    child: Center(
                                      child: loadingMore
                                          ? const SizedBox(
                                              width: 24,
                                              height: 24,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2.2,
                                                color: AppColors.primary,
                                              ),
                                            )
                                          : TextButton(
                                              onPressed: () => context
                                                  .read<CommunityFeedCubit>()
                                                  .loadMore(),
                                              child: Text(
                                                'community_load_more'.tr(),
                                              ),
                                            ),
                                    ),
                                  );
                                }

                                final post = posts[index];
                                return CommunityPostCard(
                                  post: post,
                                  onLike: () => context
                                      .read<CommunityFeedCubit>()
                                      .toggleLike(post.id),
                                  onTap: () async {
                                    await context.push(
                                      '/community/post/${post.id}',
                                    );
                                    if (context.mounted) {
                                      context
                                          .read<CommunityFeedCubit>()
                                          .refresh();
                                    }
                                  },
                                  onEdit: () async {
                                    await context.push(
                                      '/community/post/${post.id}/edit',
                                    );
                                    if (context.mounted) {
                                      context
                                          .read<CommunityFeedCubit>()
                                          .refresh();
                                    }
                                  },
                                  onDelete: () async {
                                    final ok = await confirmCommunityDelete(
                                      context,
                                      titleKey: 'community_delete_post',
                                      messageKey:
                                          'community_delete_post_confirm',
                                    );
                                    if (ok && context.mounted) {
                                      context
                                          .read<CommunityFeedCubit>()
                                          .deletePost(post.id);
                                    }
                                  },
                                );
                              },
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
          };
        },
      ),
    );
  }
}

class _CreatePostPrompt extends StatelessWidget {
  const _CreatePostPrompt({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.md),
        child: Ink(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: AppSpacing.md,
          ),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.md),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Icon(
                Icons.add_circle_outline_rounded,
                color: AppColors.primaryHover,
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  'community_create_prompt'.tr(),
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CommunityEmptyState extends StatelessWidget {
  const _CommunityEmptyState({
    required this.hasFriends,
    required this.onCreatePost,
    required this.onCreateStory,
    required this.onFindFriends,
  });

  final bool hasFriends;
  final VoidCallback onCreatePost;
  final VoidCallback onCreateStory;
  final VoidCallback onFindFriends;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.xl),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.primary.withValues(alpha: 0.12),
              border: Border.all(
                color: AppColors.primary.withValues(alpha: 0.35),
              ),
            ),
            child: const Icon(
              Icons.groups_rounded,
              color: AppColors.primaryHover,
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(
            (hasFriends ? 'community_empty_title' : 'community_welcome_title')
                .tr(),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            (hasFriends
                    ? 'community_empty_subtitle'
                    : 'community_welcome_subtitle')
                .tr(),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: AppColors.textSecondary,
              height: 1.45,
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          if (!hasFriends) ...[
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: onFindFriends,
                icon: const Icon(Icons.search_rounded),
                label: Text('friends_find'.tr()),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
          ],
          AppPrimaryButton(
            labelKey: 'community_create_post',
            icon: Icons.add_rounded,
            onPressed: onCreatePost,
          ),
          const SizedBox(height: AppSpacing.md),
          TextButton(
            onPressed: onCreateStory,
            style: TextButton.styleFrom(
              foregroundColor: AppColors.textSecondary,
            ),
            child: Text('community_share_story'.tr()),
          ),
        ],
      ),
    );
  }
}
