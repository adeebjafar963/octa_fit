import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/settings/cubit/locale_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  Future<void> _changeLocale(BuildContext context, String code) async {
    final locale = Locale(code);
    await context.read<LocaleCubit>().setLocale(locale);
    if (context.mounted) {
      await context.setLocale(locale);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'settings_title'.tr()),
      body: OctaPage(
        child: ListView(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
          children: [
            AppSectionHeader('settings_language', subtitleKey: 'settings_language_hint'),
            const SizedBox(height: AppSpacing.md),
            BlocBuilder<LocaleCubit, LocaleState>(
              builder: (context, state) {
                final code = state.locale.languageCode;
                return Row(
                  children: [
                    Expanded(
                      child: _LocaleCard(
                        labelKey: 'settings_language_en',
                        selected: code == 'en',
                        onTap: () => _changeLocale(context, 'en'),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: _LocaleCard(
                        labelKey: 'settings_language_ar',
                        selected: code == 'ar',
                        onTap: () => _changeLocale(context, 'ar'),
                      ),
                    ),
                  ],
                );
              },
            ),
            const SizedBox(height: AppSpacing.section),
            AppSectionHeader(
              'coach_memory_settings_entry',
              subtitleKey: 'coach_memory_settings_hint',
            ),
            const SizedBox(height: AppSpacing.md),
            OctaSurface(
              elevated: true,
              onTap: () => context.push('/settings/coach-memory'),
              child: Row(
                children: [
                  Icon(Icons.psychology_outlined, color: AppColors.aiHover),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: Text(
                      'coach_memory_settings_entry'.tr(),
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  Icon(
                    Icons.chevron_right,
                    color: AppColors.textSecondary,
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.section),
            AppSectionHeader('settings_about'),
            const SizedBox(height: AppSpacing.md),
            OctaSurface(
              elevated: true,
              child: Text(
                'settings_about_body'.tr(),
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: AppColors.textSecondary,
                    ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LocaleCard extends StatelessWidget {
  const _LocaleCard({
    required this.labelKey,
    required this.selected,
    required this.onTap,
  });

  final String labelKey;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return OctaSurface(
      onTap: onTap,
      elevated: selected,
      borderColor: selected ? AppColors.primary.withValues(alpha: 0.55) : null,
      child: Row(
        children: [
          Icon(
            selected ? Icons.check_circle_rounded : Icons.circle_outlined,
            color: selected ? AppColors.primaryHover : AppColors.textSecondary,
            size: 20,
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              labelKey.tr(),
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
        ],
      ),
    );
  }
}
