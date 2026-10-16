import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/home/widgets/home_training_hero.dart';
import 'package:octa_fit/presentation/shared/widgets/octa_body_viewer.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    SharedPreferences.setMockInitialValues({});
    await EasyLocalization.ensureInitialized();
  });

  testWidgets('athlete silhouette stays visible from desktop to narrow mobile', (tester) async {
    Future<void> mount(double width) async {
      await tester.binding.setSurfaceSize(Size(width, 900));
      await tester.pumpWidget(
        EasyLocalization(
          supportedLocales: const [Locale('en'), Locale('ar')],
          path: 'assets/translations',
          fallbackLocale: const Locale('en'),
          startLocale: const Locale('en'),
          child: Builder(
            builder: (context) {
              return MediaQuery(
                data: MediaQueryData(size: Size(width, 900), disableAnimations: true),
                child: MaterialApp(
                  theme: AppTheme.dark(),
                  locale: context.locale,
                  supportedLocales: context.supportedLocales,
                  localizationsDelegates: context.localizationDelegates,
                  home: Scaffold(
                    body: Center(
                      child: SizedBox(
                        width: width,
                        child: HomeTrainingHero(onStartTraining: () {}),
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      );
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 80));
    }

    for (final width in [1920.0, 1366.0, 768.0, 480.0, 390.0, 360.0, 320.0]) {
      await mount(width);
      expect(find.byType(HomeTrainingHero), findsOneWidget, reason: 'hero @ $width');
      expect(
        find.byType(OctaBodyHeroSilhouette),
        findsOneWidget,
        reason: '3D figure must remain visible at width=$width',
      );
      expect(find.textContaining(AppBrand.name), findsWidgets);
      expect(find.text('Start Training'), findsOneWidget);
    }

    await tester.binding.setSurfaceSize(null);
  });
}
