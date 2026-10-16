import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class AdminProductsScreen extends StatefulWidget {
  const AdminProductsScreen({super.key});

  @override
  State<AdminProductsScreen> createState() => _AdminProductsScreenState();
}

class _AdminProductsScreenState extends State<AdminProductsScreen> {
  final _repo = sl<AdminRepository>();
  bool _loading = true;
  String? _errorKey;
  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _products = [];

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
      final cats = await _repo.getProductCategories();
      final products = await _repo.getProducts();
      setState(() {
        _categories = cats;
        _products = products.items;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _loading = false;
        _errorKey = e.messageKey ?? 'common_error';
      });
    }
  }

  Future<void> _createProduct() async {
    if (_categories.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('admin_no_categories'.tr())));
      return;
    }
    final nameEn = TextEditingController();
    final nameAr = TextEditingController();
    final price = TextEditingController(text: '10');
    final stock = TextEditingController(text: '5');
    var categoryId = _categories.first['id'] as int;

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: Text('admin_create_product'.tr()),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<int>(
                  initialValue: categoryId,
                  items: _categories
                      .map((c) => DropdownMenuItem(value: c['id'] as int, child: Text('${c['name_en']}')))
                      .toList(),
                  onChanged: (v) => setLocal(() => categoryId = v ?? categoryId),
                  decoration: InputDecoration(labelText: 'admin_category'.tr()),
                ),
                TextField(controller: nameEn, decoration: InputDecoration(labelText: 'admin_name_en'.tr())),
                TextField(controller: nameAr, decoration: InputDecoration(labelText: 'admin_name_ar'.tr())),
                TextField(controller: price, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'admin_price'.tr())),
                TextField(controller: stock, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'admin_stock'.tr())),
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
      await _repo.createProduct({
        'category_id': categoryId,
        'name_en': nameEn.text.trim(),
        'name_ar': nameAr.text.trim(),
        'price': double.tryParse(price.text) ?? 0,
        'stock_quantity': int.tryParse(stock.text) ?? 0,
      });
      await _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text((e.messageKey ?? 'common_error').tr())));
      }
    }
  }

  Future<void> _toggleActive(Map<String, dynamic> product) async {
    await _repo.updateProduct(product['id'] as int, {
      'is_active': !(product['is_active'] as bool? ?? true),
    });
    await _load();
  }

  Future<void> _delete(int id) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('admin_confirm_delete'.tr()),
        content: Text('admin_delete_product_confirm'.tr()),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text('common_cancel'.tr())),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text('common_confirm'.tr())),
        ],
      ),
    );
    if (ok != true) return;
    await _repo.deleteProduct(id);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'admin_products'.tr(),
        actions: [
          IconButton(onPressed: _createProduct, icon: const Icon(Icons.add)),
        ],
      ),
      body: _loading
          ? const AppLoadingView()
          : _errorKey != null
              ? AppEmptyState(messageKey: _errorKey!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.separated(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    itemCount: _products.length,
                    separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
                    itemBuilder: (context, index) {
                      final p = _products[index];
                      final active = p['is_active'] as bool? ?? true;
                      return ListTile(
                        title: Text('${p['name_en']}'),
                        subtitle: Text(
                          '${'admin_price'.tr()}: ${p['price']} · ${'admin_stock'.tr()}: ${p['stock_quantity']} · '
                          '${active ? 'admin_active'.tr() : 'admin_inactive'.tr()}',
                        ),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              icon: Icon(active ? Icons.visibility_off : Icons.visibility),
                              onPressed: () => _toggleActive(p),
                            ),
                            IconButton(
                              icon: const Icon(Icons.delete_outline),
                              onPressed: () => _delete(p['id'] as int),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
