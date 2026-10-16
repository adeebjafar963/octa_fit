import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class AdminCommunityScreen extends StatefulWidget {
  const AdminCommunityScreen({super.key});

  @override
  State<AdminCommunityScreen> createState() => _AdminCommunityScreenState();
}

class _AdminCommunityScreenState extends State<AdminCommunityScreen> {
  final _repo = sl<AdminRepository>();
  bool _loading = true;
  String? _errorKey;
  List<Map<String, dynamic>> _posts = [];

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
      final page = await _repo.getPosts();
      setState(() {
        _posts = page.items;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _loading = false;
        _errorKey = e.messageKey ?? 'common_error';
      });
    }
  }

  Future<void> _delete(int id) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('admin_confirm_delete'.tr()),
        content: Text('admin_delete_post_confirm'.tr()),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text('common_cancel'.tr())),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text('common_confirm'.tr())),
        ],
      ),
    );
    if (ok != true) return;
    await _repo.deletePost(id);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OctaAppBar(title: 'admin_community'.tr()),
      body: _loading
          ? const AppLoadingView()
          : _errorKey != null
              ? AppEmptyState(messageKey: _errorKey!, onRetry: _load)
              : _posts.isEmpty
                  ? AppEmptyState(messageKey: 'admin_no_data', onRetry: _load)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(AppSpacing.lg),
                        itemCount: _posts.length,
                        separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, index) {
                          final p = _posts[index];
                          final author = p['author'] as Map<String, dynamic>?;
                          return ListTile(
                            title: Text(
                              '${p['content']}',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            subtitle: Text('${author?['name'] ?? '-'} · ♥ ${p['like_count']} · 💬 ${p['comment_count']}'),
                            trailing: IconButton(
                              icon: const Icon(Icons.delete_outline),
                              onPressed: () => _delete(p['id'] as int),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
