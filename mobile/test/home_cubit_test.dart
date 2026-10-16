import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/data/models/health_response_dto.dart';
import 'package:octa_fit/domain/entities/health_status.dart';
import 'package:octa_fit/presentation/home/cubit/home_cubit.dart';
import 'package:octa_fit/domain/repositories/health_repository.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:mocktail/mocktail.dart';

class MockHealthRepository extends Mock implements HealthRepository {}

void main() {
  group('HealthResponseDto', () {
    test('parses API envelope', () {
      final dto = HealthResponseDto.fromJson({
        'success': true,
        'message': 'OCTA FIT API is running',
        'data': null,
      });
      expect(dto.success, isTrue);
      expect(dto.message, 'OCTA FIT API is running');
    });
  });

  group('HomeCubit', () {
    late MockHealthRepository repository;

    setUp(() {
      repository = MockHealthRepository();
    });

    blocTest<HomeCubit, HomeState>(
      'emits loading then loaded on success',
      build: () {
        when(() => repository.checkHealth()).thenAnswer(
          (_) async => const HealthStatus(
            success: true,
            message: 'OCTA FIT API is running',
          ),
        );
        return HomeCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const HomeLoading(),
        isA<HomeLoaded>(),
      ],
    );

    blocTest<HomeCubit, HomeState>(
      'emits loading then error on failure',
      build: () {
        when(() => repository.checkHealth()).thenThrow(Exception('down'));
        return HomeCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const HomeLoading(),
        const HomeError('common_error'),
      ],
    );
  });
}
