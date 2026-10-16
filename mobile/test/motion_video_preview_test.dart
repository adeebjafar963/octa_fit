import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/presentation/motion/widgets/motion_video_preview.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('missing video path settles without crashing', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: MotionVideoPreview(videoPath: 'C:/does/not/exist/motion_clip.mp4'),
        ),
      ),
    );

    await tester.pump();
    await tester.pump(const Duration(seconds: 1));

    expect(tester.takeException(), isNull);
    expect(find.byType(MotionVideoPreview), findsOneWidget);
  });
}
