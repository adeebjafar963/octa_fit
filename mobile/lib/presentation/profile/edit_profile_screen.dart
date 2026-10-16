import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/profile/onboarding/widgets/onboarding_option_grid.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _age;
  late final TextEditingController _height;
  late final TextEditingController _weight;
  late final TextEditingController _goal;
  String? _gender;
  bool _ready = false;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthCubit>().currentUser;
    _name = TextEditingController(text: user?.name ?? '');
    _age = TextEditingController(text: user?.profile?.age?.toString() ?? '');
    final g = user?.profile?.gender?.trim().toLowerCase();
    _gender = (g == 'male' || g == 'female') ? g : null;
    _height = TextEditingController(text: user?.profile?.heightCm?.toString() ?? '');
    _weight = TextEditingController(text: user?.profile?.weightKg?.toString() ?? '');
    _goal = TextEditingController(text: user?.profile?.trainingGoal ?? '');
    _ready = true;
  }

  @override
  void dispose() {
    _name.dispose();
    _age.dispose();
    _height.dispose();
    _weight.dispose();
    _goal.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false) || _submitting) return;
    setState(() => _submitting = true);
    final ok = await context.read<AuthCubit>().updateProfile({
      'name': _name.text.trim(),
      'age': _age.text.trim().isEmpty ? null : int.tryParse(_age.text.trim()),
      'gender': _gender,
      'height_cm': _height.text.trim().isEmpty ? null : double.tryParse(_height.text.trim()),
      'weight_kg': _weight.text.trim().isEmpty ? null : double.tryParse(_weight.text.trim()),
      'training_goal': _goal.text.trim().isEmpty ? null : _goal.text.trim(),
      'locale': context.locale.languageCode,
    });
    if (!mounted) return;
    setState(() => _submitting = false);
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('profile_updated'.tr())),
      );
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_ready) return const Scaffold(body: AppLoadingView());

    return Scaffold(
      appBar: OctaAppBar(title: 'profile_edit'.tr()),
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
                          Text(
                            'auth_name'.tr(),
                            style: Theme.of(context).textTheme.labelMedium,
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          TextFormField(
                            controller: _name,
                            enabled: !loading,
                            decoration: InputDecoration(hintText: 'auth_name'.tr()),
                            validator: (v) => (v == null || v.trim().isEmpty)
                                ? 'auth_name_required'.tr()
                                : null,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    OctaSurface(
                      elevated: true,
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          AppSectionHeader('profile_fitness'),
                          const SizedBox(height: AppSpacing.md),
                          TextFormField(
                            controller: _age,
                            enabled: !loading,
                            keyboardType: TextInputType.number,
                            decoration: InputDecoration(labelText: 'profile_age'.tr()),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Text(
                            'profile_gender'.tr(),
                            style: Theme.of(context).textTheme.labelMedium,
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          GenderOptionRow(
                            selected: _gender,
                            enabled: !loading,
                            maleLabel: 'onboarding_gender_male'.tr(),
                            femaleLabel: 'onboarding_gender_female'.tr(),
                            onSelect: (v) => setState(() => _gender = v),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          TextFormField(
                            controller: _height,
                            enabled: !loading,
                            keyboardType:
                                const TextInputType.numberWithOptions(decimal: true),
                            decoration: InputDecoration(labelText: 'profile_height'.tr()),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          TextFormField(
                            controller: _weight,
                            enabled: !loading,
                            keyboardType:
                                const TextInputType.numberWithOptions(decimal: true),
                            decoration: InputDecoration(labelText: 'profile_weight'.tr()),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          TextFormField(
                            controller: _goal,
                            enabled: !loading,
                            decoration: InputDecoration(labelText: 'profile_goal'.tr()),
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
                    ],
                    const SizedBox(height: AppSpacing.xl),
                    OnboardingContinueButton(
                      label: 'common_save'.tr(),
                      icon: Icons.check_rounded,
                      isLoading: loading,
                      onPressed: loading ? null : _save,
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
