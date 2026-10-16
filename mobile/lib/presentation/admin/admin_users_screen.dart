import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';
import 'package:octa_fit/presentation/admin/cubit/admin_users_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class AdminUsersScreen extends StatelessWidget {
  const AdminUsersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => AdminUsersCubit(sl<AdminRepository>())..load(),
      child: const _AdminUsersView(),
    );
  }
}

class _AdminUsersView extends StatelessWidget {
  const _AdminUsersView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'admin_users'.tr()),
      body: BlocConsumer<AdminUsersCubit, AdminUsersState>(
        listener: (context, state) {
          if (state is AdminUsersLoaded && state.feedbackKey != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.feedbackKey!.tr())),
            );
          }
        },
        builder: (context, state) {
          return switch (state) {
            AdminUsersLoading() => const AppLoadingView(),
            AdminUsersError(:final messageKey) => AppEmptyState(
                messageKey: messageKey,
                onRetry: () => context.read<AdminUsersCubit>().load(),
              ),
            AdminUsersLoaded(:final users) => users.isEmpty
                ? AppEmptyState(messageKey: 'admin_no_data', onRetry: () => context.read<AdminUsersCubit>().load())
                : RefreshIndicator(
                    onRefresh: () => context.read<AdminUsersCubit>().load(),
                    child: ListView.separated(
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      itemCount: users.length,
                      separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
                      itemBuilder: (context, index) {
                        final user = users[index];
                        return Container(
                          padding: const EdgeInsets.all(AppSpacing.md),
                          decoration: BoxDecoration(
                            color: AppColors.surface,
                            borderRadius: BorderRadius.circular(AppRadii.md),
                            border: Border.all(color: AppColors.border),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(user.name, style: const TextStyle(fontWeight: FontWeight.w700)),
                              Text(user.email, style: const TextStyle(color: AppColors.textSecondary)),
                              const SizedBox(height: 4),
                              Text(
                                '${'admin_role'.tr()}: ${user.roleDisplayName ?? user.roleName ?? '-'} · '
                                '${user.isActive ? 'admin_active'.tr() : 'admin_inactive'.tr()}',
                              ),
                              const SizedBox(height: AppSpacing.sm),
                              Wrap(
                                spacing: 8,
                                children: [
                                  OutlinedButton(
                                    onPressed: () => context.read<AdminUsersCubit>().setRole(
                                          user.id,
                                          user.isAdmin ? 'user' : 'admin',
                                        ),
                                    child: Text(
                                      user.isAdmin
                                          ? 'admin_make_user'.tr()
                                          : 'admin_make_admin'.tr(),
                                    ),
                                  ),
                                  OutlinedButton(
                                    onPressed: () => context
                                        .read<AdminUsersCubit>()
                                        .setStatus(user.id, !user.isActive),
                                    child: Text(
                                      user.isActive
                                          ? 'admin_deactivate'.tr()
                                          : 'admin_activate'.tr(),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),
          };
        },
      ),
    );
  }
}
