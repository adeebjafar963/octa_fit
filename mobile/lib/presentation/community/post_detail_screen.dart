import 'package:cached_network_image/cached_network_image.dart';
import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';
import 'package:octa_fit/presentation/community/cubit/post_detail_cubit.dart';
import 'package:octa_fit/presentation/community/widgets/community_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class PostDetailScreen extends StatelessWidget {
  const PostDetailScreen({required this.postId, super.key});

  final int postId;

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => PostDetailCubit(sl<CommunityRepository>())..load(postId),
      child: _PostDetailView(postId: postId),
    );
  }
}

class _PostDetailView extends StatelessWidget {
  const _PostDetailView({required this.postId});

  final int postId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(title: 'community_post_details'.tr(), forceBack: true),
      body: BlocConsumer<PostDetailCubit, PostDetailState>(
        listener: (context, state) {
          if (state is PostDetailDeleted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('community_post_deleted'.tr())),
            );
            context.pop(true);
          }
          if (state is PostDetailLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.feedbackKey!.tr())),
            );
          }
        },
        builder: (context, state) {
          return switch (state) {
            PostDetailLoading() || PostDetailInitial() || PostDetailDeleted() =>
              const AppLoadingView(),
            PostDetailError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                onRetry: () => context.read<PostDetailCubit>().load(postId),
              ),
            PostDetailLoaded(:final post) => Align(
                alignment: Alignment.topCenter,
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 640),
                  child: ListView(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    children: [
                      Row(
                        children: [
                          CommunityAuthorAvatar(
                            name: post.author?.name,
                            avatarUrl: post.author?.avatarUrl,
                            radius: 22,
                          ),
                          const SizedBox(width: AppSpacing.md),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  post.author?.name ??
                                      'community_unknown_author'.tr(),
                                  style: Theme.of(context).textTheme.titleMedium,
                                ),
                                Text(
                                  formatCommunityDate(
                                    post.createdAt,
                                    locale: context.locale.languageCode,
                                  ),
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ],
                            ),
                          ),
                          if (post.isOwner)
                            PopupMenuButton<String>(
                              onSelected: (value) async {
                                if (value == 'edit') {
                                  await context.push('/community/post/$postId/edit');
                                  if (!context.mounted) return;
                                  context.read<PostDetailCubit>().load(postId);
                                  return;
                                }
                                if (value == 'delete') {
                                  if (!context.mounted) return;
                                  final ok = await confirmCommunityDelete(
                                    context,
                                    titleKey: 'community_delete_post',
                                    messageKey: 'community_delete_post_confirm',
                                  );
                                  if (!context.mounted) return;
                                  if (ok) {
                                    context.read<PostDetailCubit>().deletePost();
                                  }
                                }
                              },
                              itemBuilder: (_) => [
                                PopupMenuItem(
                                  value: 'edit',
                                  child: Text('community_edit_post'.tr()),
                                ),
                                PopupMenuItem(
                                  value: 'delete',
                                  child: Text('community_delete_post'.tr()),
                                ),
                              ],
                            ),
                        ],
                      ),
                      if (post.content.trim().isNotEmpty &&
                          post.content.trim() != ' ') ...[
                        const SizedBox(height: AppSpacing.lg),
                        Text(
                          post.content.trim(),
                          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                height: 1.5,
                              ),
                        ),
                      ],
                      if (post.imageUrl != null && post.imageUrl!.isNotEmpty) ...[
                        const SizedBox(height: AppSpacing.lg),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(AppRadii.md),
                          child: CachedNetworkImage(
                            imageUrl: post.imageUrl!,
                            fit: BoxFit.cover,
                          ),
                        ),
                      ],
                      const SizedBox(height: AppSpacing.md),
                      CommunityLikeButton(
                        isLiked: post.isLiked,
                        likeCount: post.likeCount,
                        onTap: () => context.read<PostDetailCubit>().toggleLike(),
                      ),
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
