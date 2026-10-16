import 'package:cached_network_image/cached_network_image.dart';
import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/community.dart';

String formatCommunityDate(String? iso, {String locale = 'en'}) {
  if (iso == null || iso.isEmpty) return '';
  final date = DateTime.tryParse(iso)?.toLocal();
  if (date == null) return iso;

  final now = DateTime.now();
  final diff = now.difference(date);

  if (diff.inSeconds < 60) return 'community_just_now'.tr();
  if (diff.inMinutes < 60) {
    return 'community_minutes_ago'.tr(namedArgs: {'count': '${diff.inMinutes}'});
  }
  if (diff.inHours < 24) {
    return 'community_hours_ago'.tr(namedArgs: {'count': '${diff.inHours}'});
  }
  if (diff.inDays == 1) return 'community_yesterday'.tr();
  if (diff.inDays < 7) {
    return 'community_days_ago'.tr(namedArgs: {'count': '${diff.inDays}'});
  }
  return DateFormat.yMMMd(locale).format(date);
}

class CommunityAuthorAvatar extends StatelessWidget {
  const CommunityAuthorAvatar({
    required this.name,
    super.key,
    this.avatarUrl,
    this.radius = 20,
    this.ring = false,
  });

  final String? name;
  final String? avatarUrl;
  final double radius;
  final bool ring;

  @override
  Widget build(BuildContext context) {
    final initial = (name != null && name!.isNotEmpty)
        ? name!.characters.first.toUpperCase()
        : '?';

    final avatar = Container(
      width: radius * 2,
      height: radius * 2,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.elevated,
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.45)),
      ),
      clipBehavior: Clip.antiAlias,
      child: avatarUrl != null && avatarUrl!.isNotEmpty
          ? CachedNetworkImage(
              imageUrl: avatarUrl!,
              fit: BoxFit.cover,
              errorWidget: (_, __, ___) => _Initial(initial: initial, radius: radius),
            )
          : _Initial(initial: initial, radius: radius),
    );

    if (!ring) return avatar;

    return Container(
      padding: const EdgeInsets.all(2.5),
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: AppColors.primary, width: 2),
      ),
      child: avatar,
    );
  }
}

class _Initial extends StatelessWidget {
  const _Initial({required this.initial, required this.radius});

  final String initial;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.primary.withValues(alpha: 0.12),
      child: Center(
        child: Text(
          initial,
          maxLines: 1,
          style: TextStyle(
            color: AppColors.primaryHover,
            fontWeight: FontWeight.w700,
            fontSize: radius * 0.85,
          ),
        ),
      ),
    );
  }
}

class CommunityLikeButton extends StatefulWidget {
  const CommunityLikeButton({
    required this.isLiked,
    required this.likeCount,
    required this.onTap,
    super.key,
  });

  final bool isLiked;
  final int likeCount;
  final VoidCallback? onTap;

  @override
  State<CommunityLikeButton> createState() => _CommunityLikeButtonState();
}

class _CommunityLikeButtonState extends State<CommunityLikeButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 160),
    lowerBound: 0.85,
    upperBound: 1,
    value: 1,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _handleTap() async {
    await _controller.reverse();
    await _controller.forward();
    widget.onTap?.call();
  }

  @override
  Widget build(BuildContext context) {
    final color = widget.isLiked ? AppColors.primary : AppColors.textSecondary;
    final label = widget.likeCount > 0
        ? '${widget.likeCount}'
        : 'community_like'.tr();

    return ScaleTransition(
      scale: _controller,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: widget.onTap == null ? null : _handleTap,
          borderRadius: BorderRadius.circular(AppRadii.full),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  widget.isLiked ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                  size: 22,
                  color: color,
                ),
                const SizedBox(width: 8),
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: color,
                        fontWeight: FontWeight.w600,
                      ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class CommunityPostCard extends StatelessWidget {
  const CommunityPostCard({
    required this.post,
    required this.onLike,
    super.key,
    this.onTap,
    this.onEdit,
    this.onDelete,
  });

  final CommunityPost post;
  final VoidCallback onLike;
  final VoidCallback? onTap;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final content = post.content.trim();
    final hasImage = post.imageUrl != null && post.imageUrl!.isNotEmpty;

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.md),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.md),
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.md,
              AppSpacing.md,
              AppSpacing.sm,
              AppSpacing.sm,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    CommunityAuthorAvatar(
                      name: post.author?.name,
                      avatarUrl: post.author?.avatarUrl,
                      radius: 18,
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            post.author?.name ?? 'community_unknown_author'.tr(),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w700,
                                ),
                          ),
                          Text(
                            formatCommunityDate(
                              post.createdAt,
                              locale: context.locale.languageCode,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: AppColors.textSecondary,
                                ),
                          ),
                        ],
                      ),
                    ),
                    if (post.isOwner)
                      PopupMenuButton<String>(
                        icon: const Icon(
                          Icons.more_horiz_rounded,
                          color: AppColors.textSecondary,
                        ),
                        onSelected: (value) {
                          if (value == 'edit') onEdit?.call();
                          if (value == 'delete') onDelete?.call();
                        },
                        itemBuilder: (context) => [
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
                if (content.isNotEmpty && content != ' ') ...[
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    content,
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                          color: AppColors.textPrimary,
                          height: 1.45,
                        ),
                  ),
                ],
                if (hasImage) ...[
                  const SizedBox(height: AppSpacing.md),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadii.sm),
                    child: AspectRatio(
                      aspectRatio: 4 / 5,
                      child: CachedNetworkImage(
                        imageUrl: post.imageUrl!,
                        fit: BoxFit.cover,
                        placeholder: (_, __) => ColoredBox(
                          color: AppColors.elevated,
                          child: const Center(
                            child: SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: AppColors.primary,
                              ),
                            ),
                          ),
                        ),
                        errorWidget: (_, __, ___) => ColoredBox(
                          color: AppColors.elevated,
                          child: Icon(
                            Icons.broken_image_outlined,
                            color: AppColors.textSecondary.withValues(alpha: 0.7),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: AppSpacing.xs),
                CommunityLikeButton(
                  isLiked: post.isLiked,
                  likeCount: post.likeCount,
                  onTap: onLike,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class CommunityStoriesRow extends StatelessWidget {
  const CommunityStoriesRow({
    required this.stories,
    required this.onAddStory,
    required this.onOpenStory,
    super.key,
  });

  final List<CommunityStory> stories;
  final VoidCallback onAddStory;
  final ValueChanged<CommunityStory> onOpenStory;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsetsDirectional.only(start: 2, bottom: 10),
          child: Text(
            'community_stories'.tr(),
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.2,
                ),
          ),
        ),
        SizedBox(
          height: 96,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: stories.length + 1,
            separatorBuilder: (_, __) => const SizedBox(width: 14),
            itemBuilder: (context, index) {
              if (index == 0) {
                return _StoryAddChip(onTap: onAddStory);
              }
              final story = stories[index - 1];
              return _StoryChip(
                story: story,
                onTap: () => onOpenStory(story),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _StoryAddChip extends StatelessWidget {
  const _StoryAddChip({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: SizedBox(
        width: 72,
        child: Column(
          children: [
            Container(
              width: 58,
              height: 58,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.elevated,
                border: Border.all(color: AppColors.primary, width: 2),
              ),
              child: const Icon(Icons.add_rounded, color: AppColors.primaryHover),
            ),
            const SizedBox(height: 6),
            Text(
              'community_your_story'.tr(),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StoryChip extends StatelessWidget {
  const _StoryChip({required this.story, required this.onTap});

  final CommunityStory story;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final name = story.author?.name ?? 'community_unknown_author'.tr();
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: SizedBox(
        width: 72,
        child: Column(
          children: [
            CommunityAuthorAvatar(
              name: name,
              avatarUrl: story.author?.avatarUrl,
              radius: 27,
              ring: true,
            ),
            const SizedBox(height: 6),
            Text(
              name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class CommunityFeedSkeleton extends StatelessWidget {
  const CommunityFeedSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(AppSpacing.lg, AppSpacing.md, AppSpacing.lg, 100),
      itemCount: 3,
      separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.md),
      itemBuilder: (_, __) => Container(
        height: 140,
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadii.md),
          border: Border.all(color: AppColors.border.withValues(alpha: 0.7)),
        ),
      ),
    );
  }
}

Future<bool> confirmCommunityDelete(
  BuildContext context, {
  required String titleKey,
  required String messageKey,
}) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(titleKey.tr()),
      content: Text(messageKey.tr()),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: Text('common_cancel'.tr())),
        TextButton(
          onPressed: () => Navigator.pop(context, true),
          child: Text(
            'common_confirm'.tr(),
            style: const TextStyle(color: AppColors.danger),
          ),
        ),
      ],
    ),
  );
  return result ?? false;
}

Future<void> showStoryViewer(BuildContext context, CommunityStory story) {
  return showDialog<void>(
    context: context,
    barrierColor: AppColors.background.withValues(alpha: 0.92),
    builder: (context) {
      return Dialog.fullscreen(
        backgroundColor: AppColors.background,
        child: SafeArea(
          child: Stack(
            children: [
              Center(
                child: CachedNetworkImage(
                  imageUrl: story.mediaUrl,
                  fit: BoxFit.contain,
                ),
              ),
              Positioned(
                top: 8,
                left: 8,
                right: 8,
                child: Row(
                  children: [
                    CommunityAuthorAvatar(
                      name: story.author?.name,
                      avatarUrl: story.author?.avatarUrl,
                      radius: 16,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        story.author?.name ?? 'community_unknown_author'.tr(),
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
              ),
              if (story.caption != null && story.caption!.isNotEmpty)
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 24,
                  child: Text(
                    story.caption!,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                          color: AppColors.textPrimary,
                        ),
                  ),
                ),
            ],
          ),
        ),
      );
    },
  );
}
