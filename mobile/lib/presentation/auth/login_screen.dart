import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    await context.read<AuthCubit>().login(
          email: _email.text.trim(),
          password: _password.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    final wide = MediaQuery.sizeOf(context).width >= 900;

    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    AppColors.background,
                    AppColors.primaryTint,
                    AppColors.background,
                  ],
                ),
              ),
            ),
          ),
          SafeArea(
            child: BlocConsumer<AuthCubit, AuthState>(
              listener: (context, state) {
                if (state is AuthAuthenticated) {
                  context.go('/home');
                }
              },
              builder: (context, state) {
                final loading = state is AuthLoading;
                final error = state is AuthError && !state.keepsSession ? state : null;

                return Center(
                  child: ConstrainedBox(
                    constraints: BoxConstraints(maxWidth: wide ? 920 : 480),
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      child: wide
                          ? Row(
                              crossAxisAlignment: CrossAxisAlignment.center,
                              children: [
                                Expanded(child: _BrandPanel()),
                                const SizedBox(width: AppSpacing.xl),
                                Expanded(
                                  child: _LoginForm(
                                    formKey: _formKey,
                                    email: _email,
                                    password: _password,
                                    obscure: _obscure,
                                    loading: loading,
                                    error: error,
                                    onToggleObscure: () => setState(() => _obscure = !_obscure),
                                    onSubmit: _submit,
                                  ),
                                ),
                              ],
                            )
                          : Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                _BrandPanel(compact: true),
                                const SizedBox(height: AppSpacing.xl),
                                _LoginForm(
                                  formKey: _formKey,
                                  email: _email,
                                  password: _password,
                                  obscure: _obscure,
                                  loading: loading,
                                  error: error,
                                  onToggleObscure: () => setState(() => _obscure = !_obscure),
                                  onSubmit: _submit,
                                ),
                              ],
                            ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _BrandPanel extends StatelessWidget {
  const _BrandPanel({this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: compact ? 56 : 72,
          height: compact ? 56 : 72,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.lg),
            color: AppColors.surface,
            border: Border.all(color: AppColors.primary.withValues(alpha: 0.4)),
          ),
          child: const Icon(Icons.hexagon_outlined, color: AppColors.primaryHover, size: 32),
        ),
        SizedBox(height: compact ? AppSpacing.md : AppSpacing.lg),
        Text(AppBrand.name, style: Theme.of(context).textTheme.displaySmall),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'splash_tagline'.tr(),
          style: Theme.of(context).textTheme.titleMedium?.copyWith(color: AppColors.textSecondary),
        ),
        if (!compact) ...[
          const SizedBox(height: AppSpacing.lg),
          Text(
            'auth_login_lead'.tr(),
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: AppColors.textSecondary),
          ),
        ],
      ],
    );
  }
}

class _LoginForm extends StatelessWidget {
  const _LoginForm({
    required this.formKey,
    required this.email,
    required this.password,
    required this.obscure,
    required this.loading,
    required this.error,
    required this.onToggleObscure,
    required this.onSubmit,
  });

  final GlobalKey<FormState> formKey;
  final TextEditingController email;
  final TextEditingController password;
  final bool obscure;
  final bool loading;
  final AuthError? error;
  final VoidCallback onToggleObscure;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: OctaSurface(
        elevated: true,
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('auth_login_title'.tr(), style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: AppSpacing.lg),
            TextFormField(
              controller: email,
              enabled: !loading,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              decoration: InputDecoration(
                labelText: 'auth_email'.tr(),
                prefixIcon: const Icon(Icons.mail_outline),
              ),
              validator: (v) {
                if (v == null || v.trim().isEmpty) return 'auth_email_required'.tr();
                if (!v.contains('@')) return 'auth_email_invalid'.tr();
                return null;
              },
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              controller: password,
              enabled: !loading,
              obscureText: obscure,
              decoration: InputDecoration(
                labelText: 'auth_password'.tr(),
                prefixIcon: const Icon(Icons.lock_outline),
                suffixIcon: IconButton(
                  onPressed: onToggleObscure,
                  icon: Icon(obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                ),
              ),
              validator: (v) => (v == null || v.isEmpty) ? 'auth_password_required'.tr() : null,
            ),
            if (error != null) ...[
              const SizedBox(height: AppSpacing.md),
              Text(
                error!.messageKey?.tr() ?? error!.message,
                style: const TextStyle(color: AppColors.danger),
              ),
              if (error!.fieldErrors != null)
                ...error!.fieldErrors!.entries.map(
                  (e) => Text(
                    e.value.join(' '),
                    style: const TextStyle(color: AppColors.danger, fontSize: 13),
                  ),
                ),
            ],
            const SizedBox(height: AppSpacing.lg),
            AppPrimaryButton(
              labelKey: 'auth_login',
              isLoading: loading,
              onPressed: loading ? null : onSubmit,
            ),
            const SizedBox(height: AppSpacing.md),
            TextButton(
              onPressed: loading ? null : () => context.push('/register'),
              child: Text('auth_no_account'.tr()),
            ),
          ],
        ),
      ),
    );
  }
}
