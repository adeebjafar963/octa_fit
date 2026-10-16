import 'dart:io';

import 'package:easy_localization/easy_localization.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';
import 'package:octa_fit/presentation/community/cubit/create_post_cubit.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class CreatePostScreen extends StatelessWidget {
  const CreatePostScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => CreatePostCubit(sl<CommunityRepository>()),
      child: const _CreatePostView(),
    );
  }
}

class _CreatePostView extends StatefulWidget {
  const _CreatePostView();

  @override
  State<_CreatePostView> createState() => _CreatePostViewState();
}

class _CreatePostViewState extends State<_CreatePostView> {
  final _controller = TextEditingController();
  String? _localPath;
  String? _fileName;
  String? _uploadedUrl;
  bool _uploading = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    final files = await FilePicker.pickFiles(type: FileType.image);
    if (files.isEmpty) return;
    final file = files.first;
    if (file.path == null) return;
    setState(() {
      _localPath = file.path;
      _fileName = file.name;
      _uploadedUrl = null;
      _uploading = true;
    });
    try {
      final url = await sl<CommunityRepository>().uploadMedia(
        filePath: file.path!,
        fileName: file.name,
      );
      if (!mounted) return;
      setState(() {
        _uploadedUrl = url;
        _uploading = false;
      });
    } on ApiException {
      if (!mounted) return;
      setState(() {
        _localPath = null;
        _fileName = null;
        _uploading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('common_error'.tr())),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _localPath = null;
        _fileName = null;
        _uploading = false;
      });
    }
  }

  bool get _canPublish {
    final hasText = _controller.text.trim().isNotEmpty;
    final hasImage = _uploadedUrl != null;
    return (hasText || hasImage) && !_uploading;
  }

  @override
  Widget build(BuildContext context) {
    final remaining = 1000 - _controller.text.length;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(title: 'community_create_post'.tr(), forceBack: true),
      body: BlocConsumer<CreatePostCubit, CreatePostState>(
        listener: (context, state) {
          if (state is CreatePostSuccess) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('community_post_created'.tr())),
            );
            context.pop(true);
          }
        },
        builder: (context, state) {
          final busy = state is CreatePostSubmitting || _uploading;
          final errorKey = state is CreatePostError ? state.messageKey : null;
          return OctaPage(
            child: ListView(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
              children: [
                TextField(
                  controller: _controller,
                  enabled: !busy,
                  maxLength: 1000,
                  maxLines: 8,
                  minLines: 5,
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        color: AppColors.textPrimary,
                        height: 1.5,
                      ),
                  decoration: InputDecoration(
                    hintText: 'community_write_post_hint'.tr(),
                    alignLabelWithHint: true,
                    counterText: '',
                    filled: true,
                    fillColor: AppColors.surface,
                  ),
                  onChanged: (_) => setState(() {}),
                ),
                const SizedBox(height: AppSpacing.sm),
                Align(
                  alignment: AlignmentDirectional.centerEnd,
                  child: Text(
                    '$remaining',
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: remaining < 80
                              ? AppColors.warning
                              : AppColors.textSecondary,
                        ),
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                OutlinedButton.icon(
                  onPressed: busy ? null : _pickImage,
                  icon: const Icon(Icons.image_outlined),
                  label: Text('community_add_image'.tr()),
                ),
                if (_localPath != null) ...[
                  const SizedBox(height: AppSpacing.md),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadii.md),
                    child: AspectRatio(
                      aspectRatio: 4 / 5,
                      child: Image.file(File(_localPath!), fit: BoxFit.cover),
                    ),
                  ),
                  if (_fileName != null)
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpacing.xs),
                      child: Text(_fileName!, style: Theme.of(context).textTheme.bodySmall),
                    ),
                ],
                if (errorKey != null) ...[
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    errorKey.tr(),
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: AppColors.danger,
                        ),
                  ),
                ],
                const SizedBox(height: AppSpacing.xl),
                AppPrimaryButton(
                  labelKey: 'community_post',
                  icon: Icons.send_rounded,
                  isLoading: busy,
                  onPressed: !_canPublish
                      ? null
                      : () => context.read<CreatePostCubit>().submit(
                            _controller.text,
                            imageUrl: _uploadedUrl,
                          ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class EditPostScreen extends StatelessWidget {
  const EditPostScreen({required this.postId, super.key});

  final int postId;

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => EditPostCubit(sl<CommunityRepository>())..load(postId),
      child: _EditPostView(postId: postId),
    );
  }
}

class _EditPostView extends StatefulWidget {
  const _EditPostView({required this.postId});

  final int postId;

  @override
  State<_EditPostView> createState() => _EditPostViewState();
}

class _EditPostViewState extends State<_EditPostView> {
  final _controller = TextEditingController();
  bool _seeded = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(title: 'community_edit_post'.tr(), forceBack: true),
      body: BlocConsumer<EditPostCubit, EditPostState>(
        listener: (context, state) {
          if (state is EditPostSuccess) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('community_post_updated'.tr())),
            );
            context.pop(true);
          }
          if (state is EditPostReady && !_seeded) {
            _controller.text = state.post.content.trim() == ' '
                ? ''
                : state.post.content;
            _seeded = true;
          }
        },
        builder: (context, state) {
          if (state is EditPostLoading || state is EditPostInitial) {
            return const AppLoadingView();
          }
          if (state is EditPostError) {
            return AppEmptyState(
              messageKey: state.messageKey,
              onRetry: () => context.read<EditPostCubit>().load(widget.postId),
            );
          }
          final ready = state is EditPostReady ? state : null;
          final busy = ready?.busy == true;
          return OctaPage(
            child: ListView(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
              children: [
                TextField(
                  controller: _controller,
                  enabled: !busy,
                  maxLength: 1000,
                  maxLines: 8,
                  minLines: 5,
                  decoration: InputDecoration(
                    hintText: 'community_write_post_hint'.tr(),
                    filled: true,
                    fillColor: AppColors.surface,
                  ),
                  onChanged: (_) => setState(() {}),
                ),
                if (ready?.errorKey != null) ...[
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    ready!.errorKey!.tr(),
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: AppColors.danger,
                        ),
                  ),
                ],
                const SizedBox(height: AppSpacing.xl),
                AppPrimaryButton(
                  labelKey: 'community_save_post',
                  isLoading: busy,
                  onPressed: _controller.text.trim().isEmpty
                      ? null
                      : () => context
                          .read<EditPostCubit>()
                          .submit(_controller.text),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
