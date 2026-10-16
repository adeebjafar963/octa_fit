import 'dart:io';

import 'package:easy_localization/easy_localization.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';
import 'package:octa_fit/presentation/shared/widgets/app_widgets.dart';

class CreateStoryScreen extends StatefulWidget {
  const CreateStoryScreen({super.key});

  @override
  State<CreateStoryScreen> createState() => _CreateStoryScreenState();
}

class _CreateStoryScreenState extends State<CreateStoryScreen> {
  final _captionController = TextEditingController();
  String? _localPath;
  String? _fileName;
  String? _errorKey;
  bool _busy = false;

  @override
  void dispose() {
    _captionController.dispose();
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
      _errorKey = null;
    });
  }

  Future<void> _publish() async {
    if (_localPath == null) {
      setState(() => _errorKey = 'community_story_media_required');
      return;
    }
    setState(() {
      _busy = true;
      _errorKey = null;
    });
    try {
      final repo = sl<CommunityRepository>();
      final url = await repo.uploadMedia(filePath: _localPath!, fileName: _fileName);
      await repo.createStory(
        mediaUrl: url,
        caption: _captionController.text.trim().isEmpty
            ? null
            : _captionController.text.trim(),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('community_story_created'.tr())),
      );
      context.pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _errorKey = e.messageKey ?? 'common_error';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _errorKey = 'common_error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: OctaAppBar(title: 'community_create_story'.tr(), forceBack: true),
      body: OctaPage(
        child: ListView(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
          children: [
            Material(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(AppRadii.md),
              child: InkWell(
                onTap: _busy ? null : _pickImage,
                borderRadius: BorderRadius.circular(AppRadii.md),
                child: Ink(
                  height: 320,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(AppRadii.md),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: _localPath == null
                      ? Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.add_photo_alternate_outlined,
                              size: 40,
                              color: AppColors.primaryHover,
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            Text(
                              'community_story_pick_image'.tr(),
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ],
                        )
                      : ClipRRect(
                          borderRadius: BorderRadius.circular(AppRadii.md),
                          child: Image.file(
                            File(_localPath!),
                            fit: BoxFit.cover,
                            width: double.infinity,
                            height: 320,
                          ),
                        ),
                ),
              ),
            ),
            if (_fileName != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(_fileName!, style: Theme.of(context).textTheme.bodySmall),
            ],
            const SizedBox(height: AppSpacing.lg),
            TextField(
              controller: _captionController,
              enabled: !_busy,
              maxLength: 280,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: 'community_story_caption_hint'.tr(),
                filled: true,
                fillColor: AppColors.surface,
              ),
            ),
            if (_errorKey != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                _errorKey!.tr(),
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.danger,
                    ),
              ),
            ],
            const SizedBox(height: AppSpacing.xl),
            AppPrimaryButton(
              labelKey: 'community_publish_story',
              icon: Icons.send_rounded,
              isLoading: _busy,
              onPressed: _busy ? null : _publish,
            ),
          ],
        ),
      ),
    );
  }
}
