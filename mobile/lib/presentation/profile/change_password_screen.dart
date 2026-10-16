import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false) || _submitting) return;
    setState(() => _submitting = true);
    final ok = await context.read<AuthCubit>().changePassword(
          currentPassword: _current.text,
          password: _password.text,
          passwordConfirmation: _confirm.text,
        );
    if (!mounted) return;
    setState(() => _submitting = false);
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('profile_password_changed'.tr())),
      );
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'profile_change_password'.tr()),
      body: BlocBuilder<AuthCubit, AuthState>(
        builder: (context, state) {
          final loading = _submitting;
          final error = state is AuthError && state.keepsSession ? state : null;

          return OctaPage(
            padding: EdgeInsets.zero,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    OctaSurface(
                      elevated: true,
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 40,
                                height: 40,
                                decoration: BoxDecoration(
                                  color: AppColors.primary.withValues(alpha: 0.14),
                                  borderRadius: BorderRadius.circular(AppRadii.sm),
                                ),
                                child: const Icon(
                                  Icons.lock_outline_rounded,
                                  color: AppColors.primaryHover,
                                  size: 20,
                                ),
                              ),
                              const SizedBox(width: AppSpacing.md),
                              Expanded(
                                child: Text(
                                  'profile_change_password'.tr(),
                                  style: Theme.of(context).textTheme.titleMedium,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: AppSpacing.lg),
                          TextFormField(
                            controller: _current,
                            obscureText: true,
                            enabled: !loading,
                            decoration: InputDecoration(
                              labelText: 'auth_current_password'.tr(),
                            ),
                            validator: (v) => (v == null || v.isEmpty)
                                ? 'auth_password_required'.tr()
                                : null,
                          ),
                          const SizedBox(height: AppSpacing.md),
                          TextFormField(
                            controller: _password,
                            obscureText: true,
                            enabled: !loading,
                            decoration: InputDecoration(
                              labelText: 'auth_new_password'.tr(),
                            ),
                            validator: (v) => (v == null || v.length < 8)
                                ? 'auth_password_min'.tr()
                                : null,
                          ),
                          const SizedBox(height: AppSpacing.md),
                          TextFormField(
                            controller: _confirm,
                            obscureText: true,
                            enabled: !loading,
                            decoration: InputDecoration(
                              labelText: 'auth_confirm_password'.tr(),
                            ),
                            validator: (v) =>
                                v != _password.text ? 'auth_password_mismatch'.tr() : null,
                          ),
                        ],
                      ),
                    ),
                    if (error != null) ...[
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        error.messageKey?.tr() ?? error.message,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: AppColors.danger,
                            ),
                      ),
                      if (error.fieldErrors != null)
                        ...error.fieldErrors!.entries.map(
                          (e) => Text(
                            e.value.join(' '),
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: AppColors.danger,
                                ),
                          ),
                        ),
                    ],
                    const SizedBox(height: AppSpacing.xl),
                    AppPrimaryButton(
                      labelKey: 'common_save',
                      icon: Icons.check_rounded,
                      isLoading: loading,
                      onPressed: loading ? null : _submit,
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
