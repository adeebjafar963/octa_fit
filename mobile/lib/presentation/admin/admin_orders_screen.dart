import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class AdminOrdersScreen extends StatefulWidget {
  const AdminOrdersScreen({super.key});

  @override
  State<AdminOrdersScreen> createState() => _AdminOrdersScreenState();
}

class _AdminOrdersScreenState extends State<AdminOrdersScreen> {
  final _repo = sl<AdminRepository>();
  bool _loading = true;
  String? _errorKey;
  List<Map<String, dynamic>> _orders = [];

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
      final page = await _repo.getOrders();
      setState(() {
        _orders = page.items;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _loading = false;
        _errorKey = e.messageKey ?? 'common_error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'admin_orders'.tr()),
      body: _loading
          ? const AppLoadingView()
          : _errorKey != null
              ? AppEmptyState(messageKey: _errorKey!, onRetry: _load)
              : _orders.isEmpty
                  ? AppEmptyState(messageKey: 'admin_no_data', onRetry: _load)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(AppSpacing.lg),
                        itemCount: _orders.length,
                        separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, index) {
                          final o = _orders[index];
                          final customer = o['customer'] as Map<String, dynamic>?;
                          return ListTile(
                            title: Text('${o['order_number']}'),
                            subtitle: Text(
                              '${customer?['name'] ?? '-'} · ${o['status']} · ${o['total']}',
                            ),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () => context.push('/admin/orders/${o['id']}'),
                          );
                        },
                      ),
                    ),
    );
  }
}

class AdminOrderDetailScreen extends StatefulWidget {
  const AdminOrderDetailScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<AdminOrderDetailScreen> createState() => _AdminOrderDetailScreenState();
}

class _AdminOrderDetailScreenState extends State<AdminOrderDetailScreen> {
  final _repo = sl<AdminRepository>();
  Map<String, dynamic>? _order;
  String? _errorKey;
  bool _loading = true;

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
      final order = await _repo.getOrder(widget.orderId);
      setState(() {
        _order = order;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _loading = false;
        _errorKey = e.messageKey ?? 'common_error';
      });
    }
  }

  Future<void> _setStatus(String status) async {
    await _repo.updateOrderStatus(widget.orderId, status);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'admin_order_detail'.tr()),
      body: _loading
          ? const AppLoadingView()
          : _errorKey != null
              ? AppEmptyState(messageKey: _errorKey!, onRetry: _load)
              : ListView(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  children: [
                    Text('${_order?['order_number']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18)),
                    Text('${'admin_status'.tr()}: ${_order?['status']}'),
                    Text('${'admin_total'.tr()}: ${_order?['total']}'),
                    const SizedBox(height: AppSpacing.md),
                    Text('admin_items'.tr(), style: const TextStyle(fontWeight: FontWeight.w700)),
                    ...((_order?['items'] as List<dynamic>? ?? []).whereType<Map<String, dynamic>>().map(
                          (item) => ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text('${item['product_name_en']}'),
                            subtitle: Text('x${item['quantity']} · ${item['line_total']}'),
                          ),
                        )),
                    const SizedBox(height: AppSpacing.lg),
                    Text('admin_update_status'.tr(), style: const TextStyle(fontWeight: FontWeight.w700)),
                    Wrap(
                      spacing: 8,
                      children: ['pending', 'confirmed', 'processing', 'completed', 'cancelled']
                          .map(
                            (s) => OutlinedButton(
                              onPressed: () => _setStatus(s),
                              child: Text(s),
                            ),
                          )
                          .toList(),
                    ),
                  ],
                ),
    );
  }
}
