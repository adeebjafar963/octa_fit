import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/presentation/training/widgets/exercise_animation.dart';
import 'package:video_player/video_player.dart';

class TrainingVideoPlayer extends StatefulWidget {
  const TrainingVideoPlayer({required this.video, super.key});

  final ExerciseVideo video;

  @override
  State<TrainingVideoPlayer> createState() => _TrainingVideoPlayerState();
}

class _TrainingVideoPlayerState extends State<TrainingVideoPlayer> {
  VideoPlayerController? _controller;
  String? _error;
  bool _initializing = false;

  bool get _isPlaceholder {
    final url = widget.video.videoUrl;
    return widget.video.isPlaceholder ||
        url.contains('demo.octafit.test/placeholder') ||
        url.startsWith('octa-fit://');
  }

  @override
  void initState() {
    super.initState();
    if (!_isGif && !_isPlaceholder) {
      _initPlayer();
    }
  }

  Future<void> _initPlayer() async {
    setState(() {
      _initializing = true;
      _error = null;
    });
    try {
      final controller = VideoPlayerController.networkUrl(Uri.parse(widget.video.videoUrl));
      await controller.initialize();
      if (!mounted) {
        await controller.dispose();
        return;
      }
      setState(() {
        _controller = controller;
        _initializing = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _initializing = false;
        _error = 'training_video_unavailable';
      });
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  bool get _isGif {
    final url = widget.video.videoUrl.toLowerCase();
    final type = widget.video.mediaType;
    return type == 'gif' ||
        type == 'image' ||
        type == 'png' ||
        type == 'jpg' ||
        type == 'jpeg' ||
        type == 'webp' ||
        widget.video.videoUrl.startsWith('assets/') ||
        url.endsWith('.gif') ||
        url.endsWith('.png') ||
        url.endsWith('.jpg') ||
        url.endsWith('.jpeg') ||
        url.endsWith('.webp');
  }

  @override
  Widget build(BuildContext context) {
    if (_isGif) {
      return ExerciseAnimation(asset: widget.video.videoUrl);
    }

    if (_isPlaceholder) {
      return _PlaceholderBox(messageKey: 'training_video_placeholder');
    }

    if (_initializing) {
      return const _VideoFrame(
        child: Center(child: CircularProgressIndicator(color: AppColors.primary)),
      );
    }

    if (_error != null || _controller == null || !_controller!.value.isInitialized) {
      return _PlaceholderBox(messageKey: _error ?? 'training_video_unavailable');
    }

    final controller = _controller!;
    return _VideoFrame(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          AspectRatio(
            aspectRatio: controller.value.aspectRatio == 0 ? 16 / 9 : controller.value.aspectRatio,
            child: VideoPlayer(controller),
          ),
          VideoProgressIndicator(controller, allowScrubbing: true),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(
                onPressed: () {
                  setState(() {
                    if (controller.value.isPlaying) {
                      controller.pause();
                    } else {
                      controller.play();
                    }
                  });
                },
                icon: Icon(
                  controller.value.isPlaying ? Icons.pause_circle_filled : Icons.play_circle_filled,
                  size: 42,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _VideoFrame extends StatelessWidget {
  const _VideoFrame({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: AppColors.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: child,
    );
  }
}

class _PlaceholderBox extends StatelessWidget {
  const _PlaceholderBox({required this.messageKey});

  final String messageKey;

  @override
  Widget build(BuildContext context) {
    return _VideoFrame(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          children: [
            const Icon(Icons.ondemand_video_outlined, size: 48, color: AppColors.textSecondary),
            const SizedBox(height: AppSpacing.sm),
            Text(
              messageKey.tr(),
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.textSecondary, height: 1.4),
            ),
          ],
        ),
      ),
    );
  }
}
