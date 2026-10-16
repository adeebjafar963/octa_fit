import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/friendship.dart';
import 'package:octa_fit/domain/repositories/friendship_repository.dart';
import 'package:octa_fit/presentation/community/cubit/friends_cubit.dart';
import 'package:octa_fit/presentation/community/widgets/community_widgets.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class FindFriendsScreen extends StatelessWidget {
  const FindFriendsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => FindFriendsCubit(sl<FriendshipRepository>()),
      child: const _FindFriendsView(),
    );
  }
}

class _FindFriendsView extends StatefulWidget {
  const _FindFriendsView();

  @override
  State<_FindFriendsView> createState() => _FindFriendsViewState();
}

class _FindFriendsViewState extends State<_FindFriendsView> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(title: 'friends_find'.tr(), forceBack: true),
      body: BlocConsumer<FindFriendsCubit, FindFriendsState>(
        listener: (context, state) {
          if (state is FindFriendsLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.feedbackKey!.tr())),
            );
          }
        },
        builder: (context, state) {
          return Align(
            alignment: Alignment.topCenter,
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 640),
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: TextField(
                      controller: _controller,
                      textInputAction: TextInputAction.search,
                      decoration: InputDecoration(
                        hintText: 'friends_search_hint'.tr(),
                        prefixIcon: const Icon(Icons.search_rounded),
                        filled: true,
                        fillColor: AppColors.surface,
                      ),
                      onSubmitted: (q) => context.read<FindFriendsCubit>().search(q),
                      onChanged: (q) {
                        if (q.trim().length >= 2) {
                          context.read<FindFriendsCubit>().search(q);
                        }
                      },
                    ),
                  ),
                  Expanded(child: _buildBody(context, state)),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildBody(BuildContext context, FindFriendsState state) {
    return switch (state) {
      FindFriendsInitial() => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.xl),
            child: Text(
              'friends_search_prompt'.tr(),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ),
        ),
      FindFriendsLoading() => const AppLoadingView(),
      FindFriendsError(:final messageKey) => AppEmptyState(
          messageKey: messageKey,
          onRetry: () => context.read<FindFriendsCubit>().search(_controller.text),
        ),
      FindFriendsLoaded(:final results, :final busyId) => results.isEmpty
          ? Center(
              child: Text(
                'friends_search_empty'.tr(),
                style: Theme.of(context).textTheme.bodyLarge,
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.md,
                0,
                AppSpacing.md,
                AppSpacing.xl,
              ),
              itemCount: results.length,
              separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.sm),
              itemBuilder: (context, index) {
                final user = results[index];
                return _UserResultTile(
                  user: user,
                  busy: busyId == user.id,
                  onAction: () => _onAction(context, user),
                  onTap: () => context.push('/community/users/${user.id}'),
                );
              },
            ),
    };
  }

  void _onAction(BuildContext context, CommunityUser user) {
    final cubit = context.read<FindFriendsCubit>();
    switch (user.friendshipStatus) {
      case FriendshipStatus.none:
        cubit.sendRequest(user.id);
      case FriendshipStatus.pendingReceived:
        cubit.acceptFromSearch(user.id);
      default:
        break;
    }
  }
}

class FriendsScreen extends StatelessWidget {
  const FriendsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => FriendsCubit(sl<FriendshipRepository>())..load(),
      child: const _FriendsView(),
    );
  }
}

class _FriendsView extends StatelessWidget {
  const _FriendsView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(
        title: 'friends_title'.tr(),
        forceBack: true,
        actions: [
          IconButton(
            tooltip: 'friends_requests'.tr(),
            onPressed: () async {
              await context.push('/community/friends/requests');
              if (context.mounted) context.read<FriendsCubit>().load();
            },
            icon: BlocBuilder<FriendsCubit, FriendsState>(
              builder: (context, state) {
                final count = state is FriendsLoaded ? state.pendingCount : 0;
                return Badge(
                  isLabelVisible: count > 0,
                  label: Text('$count'),
                  child: const Icon(Icons.person_add_alt_1_rounded),
                );
              },
            ),
          ),
          IconButton(
            tooltip: 'friends_find'.tr(),
            onPressed: () => context.push('/community/friends/find'),
            icon: const Icon(Icons.search_rounded),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: BlocConsumer<FriendsCubit, FriendsState>(
        listener: (context, state) {
          if (state is FriendsLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.feedbackKey!.tr())),
            );
          }
        },
        builder: (context, state) {
          return switch (state) {
            FriendsInitial() || FriendsLoading() => const AppLoadingView(),
            FriendsError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                onRetry: () => context.read<FriendsCubit>().load(),
              ),
            FriendsLoaded(:final friends) => friends.isEmpty
                ? _FriendsEmpty(
                    onFind: () => context.push('/community/friends/find'),
                  )
                : Align(
                    alignment: Alignment.topCenter,
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 640),
                      child: ListView.separated(
                        padding: const EdgeInsets.all(AppSpacing.md),
                        itemCount: friends.length,
                        separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, index) {
                          final user = friends[index];
                          return ListTile(
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(AppRadii.md),
                              side: BorderSide(color: AppColors.border),
                            ),
                            tileColor: AppColors.surface,
                            leading: CommunityAuthorAvatar(
                              name: user.name,
                              avatarUrl: user.avatarUrl,
                              radius: 22,
                            ),
                            title: Text(user.name),
                            subtitle: Text('friends_status_friends'.tr()),
                            onTap: () => context.push('/community/users/${user.id}'),
                            trailing: IconButton(
                              tooltip: 'friends_remove'.tr(),
                              onPressed: () async {
                                final ok = await confirmCommunityDelete(
                                  context,
                                  titleKey: 'friends_remove',
                                  messageKey: 'friends_remove_confirm',
                                );
                                if (ok && context.mounted) {
                                  context.read<FriendsCubit>().removeFriend(user.id);
                                }
                              },
                              icon: const Icon(Icons.person_remove_outlined),
                            ),
                          );
                        },
                      ),
                    ),
                  ),
          };
        },
      ),
    );
  }
}

class FriendRequestsScreen extends StatelessWidget {
  const FriendRequestsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => FriendRequestsCubit(sl<FriendshipRepository>())..load(),
      child: const _FriendRequestsView(),
    );
  }
}

class _FriendRequestsView extends StatelessWidget {
  const _FriendRequestsView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(title: 'friends_requests'.tr(), forceBack: true),
      body: BlocConsumer<FriendRequestsCubit, FriendRequestsState>(
        listener: (context, state) {
          if (state is FriendRequestsLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.feedbackKey!.tr())),
            );
          }
        },
        builder: (context, state) {
          return switch (state) {
            FriendRequestsLoading() => const AppLoadingView(),
            FriendRequestsError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                onRetry: () => context.read<FriendRequestsCubit>().load(),
              ),
            FriendRequestsLoaded(:final requests) => requests.isEmpty
                ? Center(child: Text('friends_requests_empty'.tr()))
                : Align(
                    alignment: Alignment.topCenter,
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 640),
                      child: ListView.separated(
                        padding: const EdgeInsets.all(AppSpacing.md),
                        itemCount: requests.length,
                        separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, index) {
                          final req = requests[index];
                          final name = req.sender?.name ?? 'community_unknown_author'.tr();
                          return Material(
                            color: AppColors.surface,
                            borderRadius: BorderRadius.circular(AppRadii.md),
                            child: Padding(
                              padding: const EdgeInsets.all(AppSpacing.md),
                              child: Row(
                                children: [
                                  CommunityAuthorAvatar(
                                    name: name,
                                    avatarUrl: req.sender?.avatarUrl,
                                    radius: 22,
                                  ),
                                  const SizedBox(width: AppSpacing.md),
                                  Expanded(
                                    child: Text(
                                      name,
                                      style: Theme.of(context).textTheme.titleSmall,
                                    ),
                                  ),
                                  TextButton(
                                    onPressed: () => context
                                        .read<FriendRequestsCubit>()
                                        .reject(req.id),
                                    child: Text('friends_reject'.tr()),
                                  ),
                                  FilledButton(
                                    onPressed: () => context
                                        .read<FriendRequestsCubit>()
                                        .accept(req.id),
                                    style: FilledButton.styleFrom(
                                      backgroundColor: AppColors.primary,
                                    ),
                                    child: Text('friends_accept'.tr()),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ),
          };
        },
      ),
    );
  }
}

class CommunityUserProfileScreen extends StatefulWidget {
  const CommunityUserProfileScreen({required this.userId, super.key});

  final int userId;

  @override
  State<CommunityUserProfileScreen> createState() => _CommunityUserProfileScreenState();
}

class _CommunityUserProfileScreenState extends State<CommunityUserProfileScreen> {
  CommunityUser? _user;
  String? _errorKey;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _busy = true;
      _errorKey = null;
    });
    try {
      final user = await sl<FriendshipRepository>().getPublicUser(widget.userId);
      if (!mounted) return;
      setState(() {
        _user = user;
        _busy = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _errorKey = 'common_error';
      });
    }
  }

  Future<void> _action() async {
    final user = _user;
    if (user == null) return;
    final repo = sl<FriendshipRepository>();
    setState(() => _busy = true);
    try {
      switch (user.friendshipStatus) {
        case FriendshipStatus.none:
          await repo.sendRequest(user.id);
        case FriendshipStatus.pendingReceived:
          final page = await repo.getIncomingRequests();
          FriendRequestItem? match;
          for (final r in page.items) {
            if (r.sender?.id == user.id) {
              match = r;
              break;
            }
          }
          if (match != null) await repo.acceptRequest(match.id);
        case FriendshipStatus.friends:
          final ok = await confirmCommunityDelete(
            context,
            titleKey: 'friends_remove',
            messageKey: 'friends_remove_confirm',
          );
          if (ok) await repo.removeFriend(user.id);
        case FriendshipStatus.pendingSent:
          break;
      }
      await _load();
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(title: 'friends_profile'.tr(), forceBack: true),
      body: _busy && _user == null
          ? const AppLoadingView()
          : _errorKey != null
              ? AppEmptyState(messageKey: _errorKey!, onRetry: _load)
              : _user == null
                  ? const SizedBox.shrink()
                  : Align(
                      alignment: Alignment.topCenter,
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 480),
                        child: Padding(
                          padding: const EdgeInsets.all(AppSpacing.xl),
                          child: Column(
                            children: [
                              CommunityAuthorAvatar(
                                name: _user!.name,
                                avatarUrl: _user!.avatarUrl,
                                radius: 40,
                              ),
                              const SizedBox(height: AppSpacing.lg),
                              Text(
                                _user!.name,
                                style: Theme.of(context).textTheme.headlineSmall,
                              ),
                              const SizedBox(height: AppSpacing.xs),
                              Text(
                                'friends_member_label'.tr(),
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              const SizedBox(height: AppSpacing.xl),
                              AppPrimaryButton(
                                labelKey: _actionKey(_user!.friendshipStatus),
                                isLoading: _busy,
                                onPressed: _user!.friendshipStatus ==
                                        FriendshipStatus.pendingSent
                                    ? null
                                    : _action,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
    );
  }

  String _actionKey(FriendshipStatus status) {
    return switch (status) {
      FriendshipStatus.none => 'friends_add',
      FriendshipStatus.pendingSent => 'friends_request_sent_label',
      FriendshipStatus.pendingReceived => 'friends_accept',
      FriendshipStatus.friends => 'friends_remove',
    };
  }
}

class _UserResultTile extends StatelessWidget {
  const _UserResultTile({
    required this.user,
    required this.onAction,
    required this.onTap,
    this.busy = false,
  });

  final CommunityUser user;
  final VoidCallback onAction;
  final VoidCallback onTap;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.md),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              CommunityAuthorAvatar(
                name: user.name,
                avatarUrl: user.avatarUrl,
                radius: 22,
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(user.name, style: Theme.of(context).textTheme.titleSmall),
                    Text(
                      'friends_member_label'.tr(),
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              if (busy)
                const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                )
              else
                _StatusChip(status: user.friendshipStatus, onPressed: onAction),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status, required this.onPressed});

  final FriendshipStatus status;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final (label, enabled) = switch (status) {
      FriendshipStatus.none => ('friends_add'.tr(), true),
      FriendshipStatus.pendingSent => ('friends_request_sent_label'.tr(), false),
      FriendshipStatus.pendingReceived => ('friends_accept'.tr(), true),
      FriendshipStatus.friends => ('friends_status_friends'.tr(), false),
    };

    if (!enabled) {
      return Text(
        label,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppColors.primaryHover,
              fontWeight: FontWeight.w600,
            ),
      );
    }

    return FilledButton(
      onPressed: onPressed,
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.primary,
        visualDensity: VisualDensity.compact,
      ),
      child: Text(label),
    );
  }
}

class _FriendsEmpty extends StatelessWidget {
  const _FriendsEmpty({required this.onFind});

  final VoidCallback onFind;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.xl),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            'friends_empty_title'.tr(),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'friends_empty_subtitle'.tr(),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                ),
          ),
          const SizedBox(height: AppSpacing.xl),
          AppPrimaryButton(
            labelKey: 'friends_find',
            icon: Icons.search_rounded,
            onPressed: onFind,
          ),
        ],
      ),
    );
  }
}
