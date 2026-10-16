import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/presentation/motion/motion_video_file_service.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider_platform_interface/path_provider_platform_interface.dart';

class _FakePathProvider extends PathProviderPlatform {
  _FakePathProvider(this.tempPath);

  final String tempPath;

  @override
  Future<String?> getTemporaryPath() async => tempPath;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late Directory root;
  late MotionVideoFileService service;

  setUp(() async {
    root = await Directory.systemTemp.createTemp('motion_video_svc_');
    PathProviderPlatform.instance = _FakePathProvider(root.path);
    service = MotionVideoFileService();
  });

  tearDown(() async {
    if (await root.exists()) {
      await root.delete(recursive: true);
    }
  });

  test('accepts existing readable non-empty mp4 and stages a stable copy', () async {
    final source = File(p.join(root.path, 'source_squat.mp4'));
    await source.writeAsBytes(List<int>.filled(2048, 7));

    final staged = await service.prepareLocalVideo(
      sourcePath: source.path,
      displayName: 'تسجيل squat.mp4',
    );

    expect(File(staged).existsSync(), isTrue);
    expect(await File(staged).length(), greaterThan(0));
    expect(staged.toLowerCase(), contains('octa_fit_motion_videos'));
    expect(p.basename(staged), isNot(contains('تسجيل')));
  });

  test('rejects missing file', () async {
    expect(
      () => service.prepareLocalVideo(sourcePath: p.join(root.path, 'missing.mp4')),
      throwsA(isA<MotionVideoFileException>()),
    );
  });

  test('rejects zero-byte file', () async {
    final empty = File(p.join(root.path, 'empty.mp4'));
    await empty.create();
    expect(
      () => service.prepareLocalVideo(sourcePath: empty.path),
      throwsA(isA<MotionVideoFileException>()),
    );
  });

  test('rejects empty path', () async {
    expect(
      () => service.prepareLocalVideo(sourcePath: '   '),
      throwsA(isA<MotionVideoFileException>()),
    );
  });
}
