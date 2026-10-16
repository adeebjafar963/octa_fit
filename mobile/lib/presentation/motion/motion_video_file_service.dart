import 'dart:developer' as developer;
import 'dart:io';

import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

/// Validates and stages a user-selected motion video into an app-owned cache path.
///
/// Windows file pickers can return Unicode Desktop paths that some players handle
/// poorly; a stable ASCII cache copy keeps playback and analysis reliable.
class MotionVideoFileService {
  MotionVideoFileService();

  static const supportedExtensions = {
    '.mp4',
    '.mov',
    '.m4v',
    '.webm',
    '.mkv',
    '.avi',
  };

  Future<Directory> _cacheDir() async {
    final base = await getTemporaryDirectory();
    final dir = Directory(p.join(base.path, 'octa_fit_motion_videos'));
    if (!await dir.exists()) {
      await dir.create(recursive: true);
    }
    return dir;
  }

  /// Returns a readable local path, or throws [MotionVideoFileException].
  Future<String> prepareLocalVideo({
    required String sourcePath,
    String? displayName,
  }) async {
    final normalized = sourcePath.trim();
    if (normalized.isEmpty) {
      throw const MotionVideoFileException('empty_path');
    }

    final source = File(normalized);
    if (!await source.exists()) {
      developer.log(
        'motion video missing: $normalized',
        name: 'MotionVideoFileService',
      );
      throw const MotionVideoFileException('missing');
    }

    final length = await source.length();
    if (length <= 0) {
      throw const MotionVideoFileException('empty_file');
    }

    final ext = p.extension(normalized).toLowerCase();
    if (ext.isNotEmpty && !supportedExtensions.contains(ext)) {
      developer.log(
        'motion video unsupported extension: $ext',
        name: 'MotionVideoFileService',
      );
      throw const MotionVideoFileException('unsupported');
    }

    try {
      final raf = await source.open();
      await raf.close();
    } catch (e, st) {
      developer.log(
        'motion video unreadable: $e',
        name: 'MotionVideoFileService',
        error: e,
        stackTrace: st,
      );
      throw const MotionVideoFileException('unreadable');
    }

    final cache = await _cacheDir();
    final safeExt = supportedExtensions.contains(ext) ? ext : '.mp4';
    final stamp = DateTime.now().millisecondsSinceEpoch;
    final targetName = 'motion_$stamp$safeExt';
    final target = File(p.join(cache.path, targetName));

    try {
      await source.copy(target.path);
    } catch (e, st) {
      developer.log(
        'motion video copy failed: $e',
        name: 'MotionVideoFileService',
        error: e,
        stackTrace: st,
      );
      // Fall back to original path if copy fails but source is readable.
      return source.absolute.path;
    }

    if (!await target.exists() || await target.length() <= 0) {
      throw const MotionVideoFileException('copy_failed');
    }

    developer.log(
      'motion video staged: ${target.path} (${await target.length()} bytes)'
      ' from ${displayName ?? p.basename(normalized)}',
      name: 'MotionVideoFileService',
    );
    return target.absolute.path;
  }
}

class MotionVideoFileException implements Exception {
  const MotionVideoFileException(this.code);

  final String code;

  @override
  String toString() => 'MotionVideoFileException($code)';
}
