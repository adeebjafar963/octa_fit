import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/domain/entities/hydration.dart';
import 'package:octa_fit/domain/repositories/hydration_repository.dart';
import 'package:octa_fit/presentation/hydration/cubit/hydration_cubit.dart';

class MockHydrationRepository extends Mock implements HydrationRepository {}

void main() {
  test('AppBrand.name is always OCTA FIT', () {
    expect(AppBrand.name, 'OCTA FIT');
  });

  late MockHydrationRepository repo;

  final empty = HydrationSummary(
    date: '2026-09-21',
    goalMl: 2500,
    consumedMl: 0,
    remainingMl: 2500,
    progress: 0,
    intakeCount: 0,
    goalMet: false,
    recommendedGoalMl: 2500,
  );

  setUp(() {
    repo = MockHydrationRepository();
  });

  test('load emits summary', () async {
    when(() => repo.today(timezone: any(named: 'timezone')))
        .thenAnswer((_) async => empty);
    final cubit = HydrationCubit(repo);
    await cubit.load();
    expect(cubit.state, isA<HydrationLoaded>());
    expect((cubit.state as HydrationLoaded).summary.goalMl, 2500);
    await cubit.close();
  });

  test('addWater updates consumed after success', () async {
    when(() => repo.today(timezone: any(named: 'timezone')))
        .thenAnswer((_) async => empty);
    when(() => repo.addIntake(
          amountMl: any(named: 'amountMl'),
          timezone: any(named: 'timezone'),
        )).thenAnswer(
      (_) async => HydrationSummary(
        date: '2026-09-21',
        goalMl: 2500,
        consumedMl: 500,
        remainingMl: 2000,
        progress: 0.2,
        intakeCount: 1,
        goalMet: false,
      ),
    );

    final cubit = HydrationCubit(repo);
    await cubit.load();
    await cubit.addWater(500);
    final s = cubit.state as HydrationLoaded;
    expect(s.summary.consumedMl, 500);
    expect(s.lastAddedMl, 500);
    await cubit.close();
  });

  test('invalid amount does not call repository', () async {
    when(() => repo.today(timezone: any(named: 'timezone')))
        .thenAnswer((_) async => empty);
    final cubit = HydrationCubit(repo);
    await cubit.load();
    await cubit.addWater(0);
    verifyNever(() => repo.addIntake(
          amountMl: any(named: 'amountMl'),
          timezone: any(named: 'timezone'),
        ));
    await cubit.close();
  });

  test('addWater rolls back on failure', () async {
    when(() => repo.today(timezone: any(named: 'timezone')))
        .thenAnswer((_) async => empty);
    when(() => repo.addIntake(
          amountMl: any(named: 'amountMl'),
          timezone: any(named: 'timezone'),
        )).thenThrow(Exception('fail'));

    final cubit = HydrationCubit(repo);
    await cubit.load();
    await cubit.addWater(500);
    final s = cubit.state as HydrationLoaded;
    expect(s.summary.consumedMl, 0);
    expect(s.errorKey, 'common_error');
    await cubit.close();
  });
}
