import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/profile/onboarding/widgets/onboarding_option_grid.dart';

void main() {
  testWidgets('OnboardingOptionGrid uses 3 columns at desktop width', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.dark(),
        home: Scaffold(
          body: Center(
            child: SizedBox(
              width: 900,
              child: OnboardingOptionGrid(
                selected: 'a',
                onSelect: (_) {},
                options: const [
                  OnboardingOption(value: 'a', label: 'One', icon: Icons.looks_one),
                  OnboardingOption(value: 'b', label: 'Two', icon: Icons.looks_two),
                  OnboardingOption(value: 'c', label: 'Three', icon: Icons.looks_3),
                  OnboardingOption(value: 'd', label: 'Four', icon: Icons.looks_4),
                  OnboardingOption(value: 'e', label: 'Five', icon: Icons.looks_5),
                  OnboardingOption(value: 'f', label: 'Six', icon: Icons.looks_6),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('One'), findsOneWidget);
    expect(find.text('Six'), findsOneWidget);
    // Two rows of IntrinsicHeight Row → 3+3
    expect(find.byType(Row), findsNWidgets(2));
  });

  testWidgets('GenderOptionRow exposes only male and female', (tester) async {
    String? selected;
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.dark(),
        home: Scaffold(
          body: GenderOptionRow(
            selected: selected,
            maleLabel: 'Male',
            femaleLabel: 'Female',
            onSelect: (v) => selected = v,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Male'), findsOneWidget);
    expect(find.text('Female'), findsOneWidget);
    expect(find.text('Other'), findsNothing);

    await tester.tap(find.text('Female'));
    await tester.pumpAndSettle();
    expect(selected, 'female');
  });

  testWidgets('OnboardingOptionGrid drops to 1 column when narrow', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.dark(),
        home: Scaffold(
          body: SizedBox(
            width: 300,
            child: OnboardingOptionGrid(
              onSelect: (_) {},
              options: const [
                OnboardingOption(value: 'a', label: 'One'),
                OnboardingOption(value: 'b', label: 'Two'),
                OnboardingOption(value: 'c', label: 'Three'),
              ],
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byType(Row), findsNWidgets(3));
  });
}
