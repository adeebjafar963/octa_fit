import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/training.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';
import 'package:octa_fit/presentation/training/cubit/exercise_detail_cubit.dart';
import 'package:octa_fit/presentation/training/cubit/training_list_cubit.dart';

class MockTrainingRepository extends Mock implements TrainingRepository {}

void main() {
  late MockTrainingRepository repository;

  final squat = Exercise(
    id: 1,
    trainingCategoryId: 1,
    nameEn: 'Squat',
    nameAr: 'القرفصاء',
    difficulty: 'intermediate',
    descriptionEn: 'Lower body',
    descriptionAr: 'الجزء السفلي',
  );

  final page = ExercisePage(
    items: [squat],
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 1,
  );

  setUp(() {
    repository = MockTrainingRepository();
  });

  group('localized content', () {
    test('returns Arabic or English by locale', () {
      expect(squat.localizedName('ar'), 'القرفصاء');
      expect(squat.localizedName('en'), 'Squat');
      expect(squat.localizedDescription('ar'), 'الجزء السفلي');
    });
  });

  group('TrainingListCubit', () {
    blocTest<TrainingListCubit, TrainingListState>(
      'loads bodybuilding successfully',
      build: () {
        when(
          () => repository.getExercises(
            type: any(named: 'type'),
            level: any(named: 'level'),
            search: any(named: 'search'),
            difficulty: any(named: 'difficulty'),
            equipment: any(named: 'equipment'),
            categoryId: any(named: 'categoryId'),
            page: any(named: 'page'),
            perPage: any(named: 'perPage'),
          ),
        ).thenAnswer((_) async => page);
        return TrainingListCubit(repository, type: 'bodybuilding');
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const TrainingListLoading(),
        TrainingListLoaded(page),
      ],
    );

    blocTest<TrainingListCubit, TrainingListState>(
      'emits error on failure',
      build: () {
        when(
          () => repository.getExercises(
            type: any(named: 'type'),
            level: any(named: 'level'),
            search: any(named: 'search'),
            difficulty: any(named: 'difficulty'),
            equipment: any(named: 'equipment'),
            categoryId: any(named: 'categoryId'),
            page: any(named: 'page'),
            perPage: any(named: 'perPage'),
          ),
        ).thenThrow(ApiException('down', messageKey: 'error_network'));
        return TrainingListCubit(repository, type: 'mma');
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const TrainingListLoading(),
        const TrainingListError('error_network'),
      ],
    );
  });

  group('ExerciseDetailCubit', () {
    blocTest<ExerciseDetailCubit, ExerciseDetailState>(
      'loads exercise details',
      build: () {
        when(() => repository.getExercise(1)).thenAnswer((_) async => squat);
        return ExerciseDetailCubit(repository);
      },
      act: (cubit) => cubit.load(1),
      expect: () => [
        const ExerciseDetailLoading(),
        ExerciseDetailLoaded(squat),
      ],
    );
  });

  group('TrainingRepository wiring', () {
    test('repository mock returns page', () async {
      when(() => repository.getBodybuilding()).thenAnswer((_) async => page);
      final result = await repository.getBodybuilding();
      expect(result.total, 1);
      expect(result.items.first.nameEn, 'Squat');
    });
  });
}
