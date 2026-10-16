import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:media_kit/media_kit.dart';
import 'package:octa_fit/core/di/injection.dart';
import 'package:octa_fit/core/features/feature_flags.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/settings/cubit/locale_cubit.dart';
import 'package:octa_fit/routing/app_router.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  MediaKit.ensureInitialized();
  await EasyLocalization.ensureInitialized();
  await configureDependencies();

  final localeCubit = sl<LocaleCubit>();
  await localeCubit.load();

  final authCubit = sl<AuthCubit>();
  final featureFlags = sl<FeatureFlagsCubit>();

  runApp(
    EasyLocalization(
      supportedLocales: const [Locale('en'), Locale('ar')],
      path: 'assets/translations',
      fallbackLocale: const Locale('en'),
      startLocale: localeCubit.state.locale,
      child: OctaFitApp(
        localeCubit: localeCubit,
        authCubit: authCubit,
        featureFlags: featureFlags,
      ),
    ),
  );
}

class OctaFitApp extends StatelessWidget {
  const OctaFitApp({
    required this.localeCubit,
    required this.authCubit,
    required this.featureFlags,
    super.key,
  });

  final LocaleCubit localeCubit;
  final AuthCubit authCubit;
  final FeatureFlagsCubit featureFlags;

  @override
  Widget build(BuildContext context) {
    final router = createAppRouter(authCubit, featureFlags: featureFlags);

    return MultiBlocProvider(
      providers: [
        BlocProvider.value(value: localeCubit),
        BlocProvider.value(value: authCubit),
        BlocProvider.value(value: featureFlags),
      ],
      child: BlocListener<LocaleCubit, LocaleState>(
        listener: (context, state) {
          context.setLocale(state.locale);
        },
        child: BlocListener<AuthCubit, AuthState>(
          listener: (context, state) {
            if (state is AuthAuthenticated) {
              context.read<FeatureFlagsCubit>().load();
            }
          },
          child: MaterialApp.router(
            title: AppBrand.name,
            debugShowCheckedModeBanner: false,
            theme: AppTheme.dark(),
            darkTheme: AppTheme.dark(),
            themeMode: ThemeMode.dark,
            localizationsDelegates: context.localizationDelegates,
            supportedLocales: context.supportedLocales,
            locale: context.locale,
            routerConfig: router,
          ),
        ),
      ),
    );
  }
}
