import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/training/exercise_media_resolver.dart';
import 'package:octa_fit/presentation/training/widgets/exercise_animation.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    SharedPreferences.setMockInitialValues({});
    await EasyLocalization.ensureInitialized();
  });

  test('bodybuilding slugs resolve to local gif assets', () {
    expect(
      ExerciseMediaResolver.assetForSlug('bb-chest-barbell-bench-press'),
      'assets/exercises/bodybuilding/chest/bb_chest_barbell_bench_press.gif',
    );
    expect(
      ExerciseMediaResolver.assetForSlug('bb-full-body-burpee'),
      'assets/exercises/bodybuilding/full_body/bb_full_body_burpee.gif',
    );
    expect(ExerciseMediaResolver.assetForSlug('mma-jab'), isNull);
  });

  testWidgets('remote http gif url renders through Image.network', (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 800));
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: ExerciseAnimation(
            asset:
                'http://127.0.0.1:8000/exercises/bodybuilding/chest/bb_chest_flat_dumbbell_press.gif',
          ),
        ),
      ),
    );
    await tester.pump();
    expect(find.byType(Image), findsOneWidget);
  });

  testWidgets('exercise details animation loads the bench press gif', (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 800));
    await tester.pumpWidget(
      EasyLocalization(
        supportedLocales: const [Locale('en'), Locale('ar')],
        path: 'assets/translations',
        fallbackLocale: const Locale('en'),
        startLocale: const Locale('en'),
        saveLocale: false,
        child: Builder(
          builder: (context) {
            return MaterialApp(
              theme: AppTheme.dark(),
              locale: context.locale,
              supportedLocales: context.supportedLocales,
              localizationsDelegates: context.localizationDelegates,
              home: const Scaffold(
                body: ExerciseAnimation(
                  asset: 'assets/exercises/bodybuilding/chest/bb_chest_barbell_bench_press.gif',
                ),
              ),
            );
          },
        ),
      ),
    );
    await tester.pump();
    expect(find.byType(Image), findsOneWidget);
    expect(find.text('Visual demonstration is currently unavailable.'), findsNothing);
    expect(tester.takeException(), isNull);
    final box = tester.getSize(find.byType(ExerciseAnimation));
    expect(box.height, lessThanOrEqualTo(420));
    expect(box.width, lessThanOrEqualTo(390));
  });

  testWidgets('missing animation uses the arabic unavailable message', (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 800));
    await tester.pumpWidget(
      EasyLocalization(
        supportedLocales: const [Locale('en'), Locale('ar')],
        path: 'assets/translations',
        fallbackLocale: const Locale('ar'),
        startLocale: const Locale('ar'),
        saveLocale: false,
        child: Builder(
          builder: (context) {
            return MaterialApp(
              theme: AppTheme.dark(),
              locale: context.locale,
              supportedLocales: context.supportedLocales,
              localizationsDelegates: context.localizationDelegates,
              home: const Scaffold(body: ExerciseAnimation(asset: null)),
            );
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('الشرح المرئي غير متوفر حاليًا.'), findsOneWidget);
  });
}
