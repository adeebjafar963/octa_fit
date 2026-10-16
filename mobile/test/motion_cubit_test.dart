import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/motion.dart';
import 'package:octa_fit/domain/repositories/motion_repository.dart';
import 'package:octa_fit/presentation/motion/cubit/motion_analyzer_cubit.dart';

class MockMotionRepository extends Mock implements MotionRepository {}

void main() {
  late MockMotionRepository repository;

  final result = MotionAnalysisResult(
    exercise: 'squat',
    repetitions: 8,
    durationSeconds: 24.5,
    confidence: 0.8,
    movementQuality: 'good',
    observations: const ['Good squat depth detected.'],
    tips: const ['Keep your chest stable.'],
  );

  setUp(() {
    repository = MockMotionRepository();
  });

  group('mapping', () {
    test('MotionAnalysisResult.fromJson', () {
      final parsed = MotionAnalysisResult.fromJson({
        'exercise': 'deadlift',
        'repetitions': 10,
        'duration_seconds': 28,
        'confidence': 0.78,
        'movement_quality': 'good',
        'observations': ['ok'],
        'tips': ['tip'],
        'demo': true,
        'structured': true,
        'overall_summary': 'Deadlift summary',
        'movement_points': [
          {
            'title': 'Start',
            'status': 'good',
            'status_label': 'Good',
            'explanation': 'Stable',
          }
        ],
      });
      expect(parsed.exercise, 'deadlift');
      expect(parsed.repetitions, 10);
      expect(parsed.hasPremiumStructure, isTrue);
    });
  });

  group('MotionAnalyzerCubit', () {
    blocTest<MotionAnalyzerCubit, MotionAnalyzerState>(
      'starts in selecting state',
      build: () => MotionAnalyzerCubit(repository),
      expect: () => <MotionAnalyzerState>[],
      verify: (cubit) {
        expect(cubit.state, isA<MotionAnalyzerSelecting>());
      },
    );

    blocTest<MotionAnalyzerCubit, MotionAnalyzerState>(
      'selects exercise',
      build: () => MotionAnalyzerCubit(repository),
      act: (cubit) => cubit.selectExercise('squat'),
      expect: () => [
        const MotionAnalyzerSelecting(selectedExercise: 'squat'),
      ],
    );

    blocTest<MotionAnalyzerCubit, MotionAnalyzerState>(
      'selects any catalog exercise including non-prepared ones',
      build: () => MotionAnalyzerCubit(repository),
      act: (cubit) => cubit.selectExercise('bench_press'),
      expect: () => [
        const MotionAnalyzerSelecting(selectedExercise: 'bench_press'),
      ],
    );

    blocTest<MotionAnalyzerCubit, MotionAnalyzerState>(
      'non-prepared exercise analyze fails generically',
      build: () => MotionAnalyzerCubit(repository),
      seed: () => const MotionAnalyzerSelecting(
        selectedExercise: 'bench_press',
        videoPath: '/tmp/clip.mp4',
        videoName: 'clip.mp4',
        videoReady: true,
      ),
      act: (cubit) => cubit.analyze(),
      expect: () => [
        const MotionAnalyzerFailure(
          'motion_unable_analyze',
          selectedExercise: 'bench_press',
          videoPath: '/tmp/clip.mp4',
        ),
      ],
    );

    blocTest<MotionAnalyzerCubit, MotionAnalyzerState>(
      'analyzes successfully only when video is ready',
      build: () {
        when(
          () => repository.analyze(
            exercise: any(named: 'exercise'),
            videoPath: any(named: 'videoPath'),
            fileName: any(named: 'fileName'),
            language: any(named: 'language'),
          ),
        ).thenAnswer((_) async => result);
        return MotionAnalyzerCubit(repository);
      },
      seed: () => const MotionAnalyzerSelecting(
        selectedExercise: 'squat',
        videoPath: '/tmp/demo.mp4',
        videoName: 'demo.mp4',
        videoReady: true,
      ),
      act: (cubit) => cubit.analyze(),
      expect: () => [
        const MotionAnalyzerUploading('squat', videoPath: '/tmp/demo.mp4'),
        const MotionAnalyzerAnalyzing('squat', videoPath: '/tmp/demo.mp4'),
        MotionAnalyzerSuccess(result, videoPath: '/tmp/demo.mp4'),
      ],
    );

    blocTest<MotionAnalyzerCubit, MotionAnalyzerState>(
      'blocks analyze when video is not ready',
      build: () => MotionAnalyzerCubit(repository),
      seed: () => const MotionAnalyzerSelecting(
        selectedExercise: 'squat',
        videoPath: '/tmp/demo.mp4',
        videoReady: false,
      ),
      act: (cubit) => cubit.analyze(),
      expect: () => [
        const MotionAnalyzerFailure(
          'motion_video_not_ready',
          selectedExercise: 'squat',
          videoPath: '/tmp/demo.mp4',
        ),
      ],
    );

    blocTest<MotionAnalyzerCubit, MotionAnalyzerState>(
      'emits failure when video missing',
      build: () => MotionAnalyzerCubit(repository),
      seed: () => const MotionAnalyzerSelecting(selectedExercise: 'squat'),
      act: (cubit) => cubit.analyze(),
      expect: () => [
        const MotionAnalyzerFailure('motion_video_required', selectedExercise: 'squat'),
      ],
    );

    blocTest<MotionAnalyzerCubit, MotionAnalyzerState>(
      'emits error on repository failure',
      build: () {
        when(
          () => repository.analyze(
            exercise: any(named: 'exercise'),
            videoPath: any(named: 'videoPath'),
            fileName: any(named: 'fileName'),
            language: any(named: 'language'),
          ),
        ).thenThrow(ApiException('down', messageKey: 'motion_unavailable'));
        return MotionAnalyzerCubit(repository);
      },
      seed: () => const MotionAnalyzerSelecting(
        selectedExercise: 'squat',
        videoPath: '/tmp/demo.mp4',
        videoReady: true,
      ),
      act: (cubit) => cubit.analyze(),
      expect: () => [
        const MotionAnalyzerUploading('squat', videoPath: '/tmp/demo.mp4'),
        const MotionAnalyzerAnalyzing('squat', videoPath: '/tmp/demo.mp4'),
        const MotionAnalyzerFailure(
          'motion_unavailable',
          selectedExercise: 'squat',
          videoPath: '/tmp/demo.mp4',
        ),
      ],
    );
  });
}
