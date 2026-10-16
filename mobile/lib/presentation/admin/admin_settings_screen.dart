import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/admin.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class AdminSettingsScreen extends StatefulWidget {
  const AdminSettingsScreen({super.key});

  @override
  State<AdminSettingsScreen> createState() => _AdminSettingsScreenState();
}

class _AdminSettingsScreenState extends State<AdminSettingsScreen> {
  final _repo = sl<AdminRepository>();
  bool _loading = true;
  bool _saving = false;
  String? _errorKey;
  List<AdminSettingItem> _items = [];
  final _controllers = <String, TextEditingController>{};

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _errorKey = null;
    });
    try {
      final items = await _repo.getSettings();
      for (final c in _controllers.values) {
        c.dispose();
      }
      _controllers.clear();
      for (final item in items) {
        _controllers[item.key] = TextEditingController(text: item.value ?? '');
      }
      setState(() {
        _items = items;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _loading = false;
        _errorKey = e.messageKey ?? 'common_error';
      });
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final payload = <String, String?>{};
      for (final entry in _controllers.entries) {
        payload[entry.key] = entry.value.text.trim();
      }
      final items = await _repo.updateSettings(payload);
      setState(() {
        _items = items;
        _saving = false;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('admin_settings_saved'.tr())),
        );
      }
    } on ApiException catch (e) {
      setState(() => _saving = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((e.messageKey ?? 'common_error').tr())),
        );
      }
    }
  }

  String _labelFor(String key) {
    return switch (key) {
      'app_name' => 'admin_setting_app_name'.tr(),
      'default_locale' => 'admin_setting_locale'.tr(),
      'support_text' => 'admin_setting_support'.tr(),
      'feature_coach_enabled' => 'admin_feature_coach'.tr(),
      'feature_physio_enabled' => 'admin_feature_physio'.tr(),
      'feature_motion_enabled' => 'admin_feature_motion'.tr(),
      _ => key,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'admin_settings'.tr()),
      body: _loading
          ? const AppLoadingView()
          : _errorKey != null
              ? AppEmptyState(messageKey: _errorKey!, onRetry: _load)
              : ListView(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  children: [
                    Text(
                      'admin_ai_settings_note'.tr(),
                      style: const TextStyle(color: AppColors.textSecondary, height: 1.4),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    ..._items.map((item) {
                      final controller = _controllers[item.key]!;
                      final isFeature = item.key.startsWith('feature_');
                      if (isFeature) {
                        final enabled = controller.text == '1' || controller.text == 'true';
                        return SwitchListTile(
                          title: Text(_labelFor(item.key)),
                          value: enabled,
                          onChanged: (v) {
                            setState(() => controller.text = v ? '1' : '0');
                          },
                        );
                      }
                      return Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.md),
                        child: TextField(
                          controller: controller,
                          maxLines: item.key == 'support_text' ? 3 : 1,
                          decoration: InputDecoration(
                            labelText: _labelFor(item.key),
                            border: const OutlineInputBorder(),
                          ),
                        ),
                      );
                    }),
                    const SizedBox(height: AppSpacing.lg),
                    ElevatedButton(
                      onPressed: _saving ? null : _save,
                      child: Text(_saving ? 'admin_saving'.tr() : 'admin_save'.tr()),
                    ),
                  ],
                ),
    );
  }
}
