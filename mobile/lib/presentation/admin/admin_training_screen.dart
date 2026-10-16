import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class AdminTrainingScreen extends StatefulWidget {
  const AdminTrainingScreen({super.key});

  @override
  State<AdminTrainingScreen> createState() => _AdminTrainingScreenState();
}

class _AdminTrainingScreenState extends State<AdminTrainingScreen> {
  final _repo = sl<AdminRepository>();
  bool _loading = true;
  String? _errorKey;
  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _exercises = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _errorKey = null;
    });
    try {
      final cats = await _repo.getTrainingCategories();
      final exercises = await _repo.getExercises();
      setState(() {
        _categories = cats;
        _exercises = exercises.items;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _loading = false;
        _errorKey = e.messageKey ?? 'common_error';
      });
    } catch (_) {
      setState(() {
        _loading = false;
        _errorKey = 'common_error';
      });
    }
  }

  Future<void> _createCategory() async {
    final nameEn = TextEditingController();
    final nameAr = TextEditingController();
    var type = 'bodybuilding';
    var level = 'beginner';
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: Text('admin_create_category'.tr()),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(controller: nameEn, decoration: InputDecoration(labelText: 'admin_name_en'.tr())),
                TextField(controller: nameAr, decoration: InputDecoration(labelText: 'admin_name_ar'.tr())),
                DropdownButtonFormField<String>(
                  initialValue: type,
                  decoration: InputDecoration(labelText: 'admin_discipline'.tr()),
                  items: const [
                    DropdownMenuItem(value: 'bodybuilding', child: Text('Bodybuilding')),
                    DropdownMenuItem(value: 'mma', child: Text('MMA')),
                    DropdownMenuItem(value: 'home_calisthenics', child: Text('Home / Calisthenics')),
                  ],
                  onChanged: (v) => setLocal(() => type = v ?? type),
                ),
                DropdownButtonFormField<String>(
                  initialValue: level,
                  decoration: InputDecoration(labelText: 'admin_level'.tr()),
                  items: const [
                    DropdownMenuItem(value: 'beginner', child: Text('Beginner')),
                    DropdownMenuItem(value: 'intermediate', child: Text('Intermediate')),
                    DropdownMenuItem(value: 'advanced', child: Text('Advanced')),
                  ],
                  onChanged: (v) => setLocal(() => level = v ?? level),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text('common_cancel'.tr())),
            TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text('admin_save'.tr())),
          ],
        ),
      ),
    );
    if (ok != true) return;
    try {
      await _repo.createTrainingCategory({
        'name_en': nameEn.text.trim(),
        'name_ar': nameAr.text.trim(),
        'type': type,
        'level': level,
      });
      await _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text((e.messageKey ?? 'common_error').tr())));
      }
    }
  }

  Future<void> _deleteCategory(int id) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('admin_confirm_delete'.tr()),
        content: Text('admin_delete_category_confirm'.tr()),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text('common_cancel'.tr())),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text('common_confirm'.tr())),
        ],
      ),
    );
    if (ok != true) return;
    await _repo.deleteTrainingCategory(id);
    await _load();
  }

  Future<void> _deleteExercise(int id) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('admin_confirm_delete'.tr()),
        content: Text('admin_delete_exercise_confirm'.tr()),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text('common_cancel'.tr())),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text('common_confirm'.tr())),
        ],
      ),
    );
    if (ok != true) return;
    await _repo.deleteExercise(id);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'admin_training'.tr(),
        actions: [
          IconButton(onPressed: _createCategory, icon: const Icon(Icons.add), tooltip: 'admin_create_category'.tr()),
        ],
      ),
      body: _loading
          ? const AppLoadingView()
          : _errorKey != null
              ? AppEmptyState(messageKey: _errorKey!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    children: [
                      Text('admin_categories'.tr(), style: const TextStyle(fontWeight: FontWeight.w700)),
                      const SizedBox(height: AppSpacing.sm),
                      ..._categories.map((c) => ListTile(
                            title: Text('${c['name_en']} / ${c['name_ar']}'),
                            subtitle: Text('${c['type']} · ${c['level'] ?? 'beginner'}'),
                            trailing: IconButton(
                              icon: const Icon(Icons.delete_outline),
                              onPressed: () => _deleteCategory(c['id'] as int),
                            ),
                          )),
                      const SizedBox(height: AppSpacing.lg),
                      Text('admin_exercises'.tr(), style: const TextStyle(fontWeight: FontWeight.w700)),
                      const SizedBox(height: AppSpacing.sm),
                      ..._exercises.map((e) => ListTile(
                            title: Text('${e['name_en']}'),
                            subtitle: Text('${e['difficulty']}'),
                            trailing: IconButton(
                              icon: const Icon(Icons.delete_outline),
                              onPressed: () => _deleteExercise(e['id'] as int),
                            ),
                          )),
                    ],
                  ),
                ),
    );
  }
}
