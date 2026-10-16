import 'dart:math' as math;

import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';

/// Exercise visual (GIF or still). Loads a local asset or a remote API URL.
class ExerciseAnimation extends StatelessWidget {
  const ExerciseAnimation({required this.asset, super.key});

  /// Flutter asset path (`assets/...`) or absolute http(s) URL from the API.
  final String? asset;

  bool get _isNetwork {
    final value = asset;
    if (value == null) return false;
    return value.startsWith('http://') || value.startsWith('https://');
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final screenHeight = MediaQuery.sizeOf(context).height;
        final maxHeight = (screenHeight * 0.42).clamp(160.0, 420.0);
        final width = constraints.maxWidth.isFinite ? constraints.maxWidth : 480.0;
        final height = math.min(width * 340 / 480, maxHeight);
        return DecoratedBox(
          decoration: BoxDecoration(
            color: AppColors.background,
            borderRadius: BorderRadius.circular(AppRadii.xl),
            border: Border.all(color: AppColors.primary.withValues(alpha: 0.35)),
            boxShadow: AppShadows.soft,
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(AppRadii.xl),
            child: SizedBox(
              width: width,
              height: height,
              child: asset == null
                  ? const _AnimationMessage()
                  : (_isNetwork
                      ? Image.network(
                          asset!,
                          fit: BoxFit.contain,
                          gaplessPlayback: true,
                          filterQuality: FilterQuality.medium,
                          loadingBuilder: (context, child, progress) {
                            if (progress == null) return child;
                            return const Center(
                              child: CircularProgressIndicator(color: AppColors.primary),
                            );
                          },
                          errorBuilder: (_, _, _) => const _AnimationMessage(),
                        )
                      : Image.asset(
                          asset!,
                          fit: BoxFit.contain,
                          gaplessPlayback: true,
                          filterQuality: FilterQuality.medium,
                          frameBuilder: (context, child, frame, wasSynchronouslyLoaded) {
                            if (wasSynchronouslyLoaded || frame != null) return child;
                            return const Center(
                              child: CircularProgressIndicator(color: AppColors.primary),
                            );
                          },
                          errorBuilder: (_, _, _) => const _AnimationMessage(),
                        )),
            ),
          ),
        );
      },
    );
  }
}

class _AnimationMessage extends StatelessWidget {
  const _AnimationMessage();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Center(
        child: Text(
          'training_visual_unavailable'.tr(),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                color: AppColors.textSecondary,
              ),
        ),
      ),
    );
  }
}
