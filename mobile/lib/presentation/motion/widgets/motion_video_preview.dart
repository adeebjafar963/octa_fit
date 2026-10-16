import 'dart:developer' as developer;
import 'dart:io';

import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:media_kit/media_kit.dart';
import 'package:media_kit_video/media_kit_video.dart';
import 'package:octa_fit/core/theme/app_theme.dart';

/// Local motion video preview powered by media_kit (Windows-capable).
class MotionVideoPreview extends StatefulWidget {
  const MotionVideoPreview({
    super.key,
    required this.videoPath,
    this.compact = false,
    this.onReadyChanged,
    this.onRetry,
  });

  final String videoPath;
  final bool compact;
  final ValueChanged<bool>? onReadyChanged;
  final VoidCallback? onRetry;

  @override
  State<MotionVideoPreview> createState() => _MotionVideoPreviewState();
}

class _MotionVideoPreviewState extends State<MotionVideoPreview> {
  Player? _player;
  VideoController? _controller;
  bool _initializing = true;
  bool _failed = false;
  bool _ready = false;
  bool _muted = true;

  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;
  bool _playing = false;
  double _aspectRatio = 16 / 9;

  @override
  void initState() {
    super.initState();
    _open();
  }

  @override
  void didUpdateWidget(covariant MotionVideoPreview oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.videoPath != widget.videoPath) {
      _open();
    }
  }

  Future<void> _disposePlayer() async {
    final player = _player;
    _player = null;
    _controller = null;
    if (player != null) {
      await player.dispose();
    }
  }

  Future<void> _open() async {
    widget.onReadyChanged?.call(false);
    setState(() {
      _initializing = true;
      _failed = false;
      _ready = false;
      _playing = false;
      _position = Duration.zero;
      _duration = Duration.zero;
    });

    await _disposePlayer();

    try {
      final file = File(widget.videoPath);
      if (!await file.exists() || await file.length() <= 0) {
        throw StateError('missing_or_empty');
      }

      final player = Player();
      final controller = VideoController(player);

      player.stream.playing.listen((playing) {
        if (!mounted) return;
        setState(() => _playing = playing);
      });
      player.stream.position.listen((position) {
        if (!mounted) return;
        setState(() => _position = position);
      });
      player.stream.duration.listen((duration) {
        if (!mounted) return;
        setState(() => _duration = duration);
      });
      player.stream.width.listen((w) {
        final h = player.state.height;
        if (!mounted || w == null || h == null || h == 0) return;
        setState(() => _aspectRatio = w / h);
      });
      player.stream.height.listen((h) {
        final w = player.state.width;
        if (!mounted || w == null || h == null || h == 0) return;
        setState(() => _aspectRatio = w / h);
      });
      player.stream.error.listen((error) {
        if (error.trim().isEmpty) return;
        developer.log(
          'media_kit playback error: $error',
          name: 'MotionVideoPreview',
        );
      });

      // Do not autoplay; load paused.
      await player.open(
        Media(file.uri.toString()),
        play: false,
      );
      await player.setVolume(0);

      if (!mounted) {
        await player.dispose();
        return;
      }

      setState(() {
        _player = player;
        _controller = controller;
        _initializing = false;
        _ready = true;
        _muted = true;
        _duration = player.state.duration;
        final w = player.state.width;
        final h = player.state.height;
        if (w != null && h != null && h > 0) {
          _aspectRatio = w / h;
        }
      });
      widget.onReadyChanged?.call(true);
    } catch (e, st) {
      developer.log(
        'motion preview open failed: $e',
        name: 'MotionVideoPreview',
        error: e,
        stackTrace: st,
      );
      if (!mounted) return;
      setState(() {
        _initializing = false;
        _failed = true;
        _ready = false;
      });
      widget.onReadyChanged?.call(false);
    }
  }

  @override
  void dispose() {
    _disposePlayer();
    super.dispose();
  }

  String _format(Duration d) {
    final total = d.inSeconds.clamp(0, 24 * 3600);
    final m = (total ~/ 60).toString().padLeft(2, '0');
    final s = (total % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  Future<void> _togglePlay() async {
    final player = _player;
    if (player == null || !_ready) return;
    await player.playOrPause();
  }

  Future<void> _replay() async {
    final player = _player;
    if (player == null || !_ready) return;
    await player.seek(Duration.zero);
    await player.play();
  }

  Future<void> _toggleMute() async {
    final player = _player;
    if (player == null || !_ready) return;
    final next = !_muted;
    await player.setVolume(next ? 0 : 100);
    if (mounted) setState(() => _muted = next);
  }

  Future<void> _seekFraction(double value) async {
    final player = _player;
    if (player == null || !_ready || _duration.inMilliseconds <= 0) return;
    final ms = (_duration.inMilliseconds * value).round();
    await player.seek(Duration(milliseconds: ms));
  }

  @override
  Widget build(BuildContext context) {
    if (_initializing) {
      return _frame(
        child: SizedBox(
          height: 220,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const CircularProgressIndicator(color: AppColors.ai),
              const SizedBox(height: AppSpacing.md),
              Text(
                'motion_video_loading'.tr(),
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textSecondary,
                    ),
              ),
            ],
          ),
        ),
      );
    }

    if (_failed || _player == null || _controller == null || !_ready) {
      return _frame(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.videocam_off_outlined, size: 40, color: AppColors.warning),
              const SizedBox(height: AppSpacing.sm),
              Text(
                'motion_video_play_failed'.tr(),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const SizedBox(height: 6),
              Text(
                'motion_video_play_failed_hint'.tr(),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      height: 1.4,
                    ),
              ),
              if (widget.onRetry != null) ...[
                const SizedBox(height: AppSpacing.md),
                TextButton.icon(
                  onPressed: widget.onRetry,
                  icon: const Icon(Icons.video_library_outlined, size: 18),
                  label: Text('motion_choose_another_video'.tr()),
                ),
              ],
            ],
          ),
        ),
      );
    }

    final progress = _duration.inMilliseconds == 0
        ? 0.0
        : (_position.inMilliseconds / _duration.inMilliseconds).clamp(0.0, 1.0);

    return _frame(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          AspectRatio(
            aspectRatio: _aspectRatio <= 0 ? 16 / 9 : _aspectRatio,
            child: Stack(
              alignment: Alignment.center,
              children: [
                ColoredBox(
                  color: Colors.black,
                  child: Video(
                    controller: _controller!,
                    controls: NoVideoControls,
                    fill: Colors.black,
                  ),
                ),
                if (!_playing)
                  Material(
                    color: Colors.black45,
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: _togglePlay,
                      child: const Padding(
                        padding: EdgeInsets.all(14),
                        child: Icon(
                          Icons.play_arrow_rounded,
                          size: 42,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ),
                if (_playing)
                  Positioned.fill(
                    child: GestureDetector(
                      behavior: HitTestBehavior.opaque,
                      onTap: _togglePlay,
                    ),
                  ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(
              AppSpacing.md,
              widget.compact ? 8 : AppSpacing.sm,
              AppSpacing.md,
              AppSpacing.sm,
            ),
            child: Column(
              children: [
                SliderTheme(
                  data: SliderTheme.of(context).copyWith(
                    trackHeight: 3,
                    thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 7),
                    overlayShape: const RoundSliderOverlayShape(overlayRadius: 14),
                    activeTrackColor: AppColors.ai,
                    inactiveTrackColor: AppColors.border,
                    thumbColor: AppColors.ai,
                  ),
                  child: Slider(
                    value: progress,
                    onChanged: _seekFraction,
                  ),
                ),
                Row(
                  children: [
                    Text(
                      '${_format(_position)} / ${_format(_duration)}',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                            color: AppColors.textSecondary,
                          ),
                    ),
                    const Spacer(),
                    IconButton(
                      onPressed: _togglePlay,
                      icon: Icon(
                        _playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    IconButton(
                      onPressed: _replay,
                      icon: const Icon(Icons.replay_rounded, color: AppColors.textPrimary),
                    ),
                    IconButton(
                      onPressed: _toggleMute,
                      icon: Icon(
                        _muted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _frame({required Widget child}) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: AppColors.elevated,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        border: Border.all(color: AppColors.ai.withValues(alpha: 0.12)),
      ),
      clipBehavior: Clip.antiAlias,
      child: child,
    );
  }
}
