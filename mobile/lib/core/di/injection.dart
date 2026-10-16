import 'package:get_it/get_it.dart';
import 'package:octa_fit/core/network/dio_client.dart';
import 'package:octa_fit/core/storage/token_storage.dart';
import 'package:octa_fit/data/datasources/admin_remote_datasource.dart';
import 'package:octa_fit/data/datasources/ai_hub_remote_datasource.dart';
import 'package:octa_fit/data/datasources/auth_remote_datasource.dart';
import 'package:octa_fit/data/datasources/health_remote_datasource.dart';
import 'package:octa_fit/data/datasources/hydration_remote_datasource.dart';
import 'package:octa_fit/data/datasources/coach_remote_datasource.dart';
import 'package:octa_fit/data/datasources/community_remote_datasource.dart';
import 'package:octa_fit/data/datasources/friendship_remote_datasource.dart';
import 'package:octa_fit/data/datasources/features_remote_datasource.dart';
import 'package:octa_fit/data/datasources/motion_remote_datasource.dart';
import 'package:octa_fit/core/features/feature_flags.dart';
import 'package:octa_fit/data/datasources/physio_remote_datasource.dart';
import 'package:octa_fit/data/datasources/store_remote_datasource.dart';
import 'package:octa_fit/data/datasources/training_remote_datasource.dart';
import 'package:octa_fit/data/repositories/admin_repository_impl.dart';
import 'package:octa_fit/data/repositories/ai_hub_repository_impl.dart';
import 'package:octa_fit/data/repositories/auth_repository_impl.dart';
import 'package:octa_fit/data/repositories/coach_repository_impl.dart';
import 'package:octa_fit/data/repositories/community_repository_impl.dart';
import 'package:octa_fit/data/repositories/friendship_repository_impl.dart';
import 'package:octa_fit/data/repositories/health_repository_impl.dart';
import 'package:octa_fit/data/repositories/hydration_repository_impl.dart';
import 'package:octa_fit/data/repositories/motion_repository_impl.dart';
import 'package:octa_fit/data/repositories/physio_repository_impl.dart';
import 'package:octa_fit/data/repositories/store_repository_impl.dart';
import 'package:octa_fit/data/repositories/training_repository_impl.dart';
import 'package:octa_fit/domain/repositories/admin_repository.dart';
import 'package:octa_fit/domain/repositories/ai_hub_repository.dart';
import 'package:octa_fit/domain/repositories/auth_repository.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';
import 'package:octa_fit/domain/repositories/community_repository.dart';
import 'package:octa_fit/domain/repositories/friendship_repository.dart';
import 'package:octa_fit/domain/repositories/health_repository.dart';
import 'package:octa_fit/domain/repositories/hydration_repository.dart';
import 'package:octa_fit/domain/repositories/motion_repository.dart';
import 'package:octa_fit/domain/repositories/physio_repository.dart';
import 'package:octa_fit/domain/repositories/store_repository.dart';
import 'package:octa_fit/domain/repositories/training_repository.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/settings/cubit/locale_cubit.dart';

final GetIt sl = GetIt.instance;

Future<void> configureDependencies() async {
  sl.registerLazySingleton<TokenStorage>(TokenStorage.new);

  sl.registerLazySingleton<DioClient>(
    () => DioClient(
      tokenStorage: sl<TokenStorage>(),
      onUnauthorized: () {
        Future.microtask(() async {
          await sl<TokenStorage>().clearToken();
          if (sl.isRegistered<AuthCubit>()) {
            sl<AuthCubit>().forceUnauthenticated();
          }
        });
      },
    ),
  );

  sl
    ..registerLazySingleton<AuthRemoteDataSource>(
      () => AuthRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<AuthRepository>(
      () => AuthRepositoryImpl(sl<AuthRemoteDataSource>(), sl<TokenStorage>()),
    )
    ..registerLazySingleton<HealthRemoteDataSource>(
      () => HealthRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<HealthRepository>(
      () => HealthRepositoryImpl(sl<HealthRemoteDataSource>()),
    )
    ..registerLazySingleton<HydrationRemoteDataSource>(
      () => HydrationRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<HydrationRepository>(
      () => HydrationRepositoryImpl(sl<HydrationRemoteDataSource>()),
    )
    ..registerLazySingleton<TrainingRemoteDataSource>(
      () => TrainingRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<TrainingRepository>(
      () => TrainingRepositoryImpl(sl<TrainingRemoteDataSource>()),
    )
    ..registerLazySingleton<StoreRemoteDataSource>(
      () => StoreRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<StoreRepository>(
      () => StoreRepositoryImpl(sl<StoreRemoteDataSource>()),
    )
    ..registerLazySingleton<CommunityRemoteDataSource>(
      () => CommunityRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<CommunityRepository>(
      () => CommunityRepositoryImpl(sl<CommunityRemoteDataSource>()),
    )
    ..registerLazySingleton<FriendshipRemoteDataSource>(
      () => FriendshipRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<FriendshipRepository>(
      () => FriendshipRepositoryImpl(sl<FriendshipRemoteDataSource>()),
    )
    ..registerLazySingleton<CoachRemoteDataSource>(
      () => CoachRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<CoachRepository>(
      () => CoachRepositoryImpl(sl<CoachRemoteDataSource>()),
    )
    ..registerLazySingleton<AiHubRemoteDataSource>(
      () => AiHubRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<AiHubRepository>(
      () => AiHubRepositoryImpl(sl<AiHubRemoteDataSource>()),
    )
    ..registerLazySingleton<PhysioRemoteDataSource>(
      () => PhysioRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<PhysioRepository>(
      () => PhysioRepositoryImpl(sl<PhysioRemoteDataSource>()),
    )
    ..registerLazySingleton<MotionRemoteDataSource>(
      () => MotionRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<MotionRepository>(
      () => MotionRepositoryImpl(sl<MotionRemoteDataSource>()),
    )
    ..registerLazySingleton<AdminRemoteDataSource>(
      () => AdminRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<AdminRepository>(
      () => AdminRepositoryImpl(sl<AdminRemoteDataSource>()),
    )
    ..registerLazySingleton<FeaturesRemoteDataSource>(
      () => FeaturesRemoteDataSource(sl<DioClient>()),
    )
    ..registerLazySingleton<FeatureFlagsCubit>(
      () => FeatureFlagsCubit(sl<FeaturesRemoteDataSource>()),
    )
    ..registerLazySingleton<AuthCubit>(() => AuthCubit(sl<AuthRepository>()))
    ..registerLazySingleton<LocaleCubit>(LocaleCubit.new);
}
