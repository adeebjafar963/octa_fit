import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/domain/entities/motion.dart';

MotionAnalysisResult _squatProfile() {
  return MotionAnalysisResult.fromJson({
    'exercise': 'squat',
    'demo': true,
    'structured': true,
    'title': 'Squat',
    'analysis_label': 'Movement Analysis',
    'overall_status': 'Needs improvement',
    'overall_summary':
        'In this video a bodyweight squat shows clear forward trunk lean and limited depth.',
    'positive_points': [
      {
        'title': 'Foot stability',
        'explanation': 'Both feet stay planted and heels remain down.',
      },
    ],
    'movement_points': [
      {
        'title': 'Forward trunk lean',
        'status': 'attention',
        'status_label': 'Needs focus',
        'explanation': 'During the descent the trunk leans clearly forward.',
        'correction': 'Sit the hips slightly back while keeping the chest up.',
      },
    ],
    'coaching_cues': ['Drop the hips back and down instead of folding hard forward.'],
    'common_mistakes': [
      {
        'title': 'Excessive forward fold',
        'explanation': 'A heavy forward trunk lean reduces squat pattern quality.',
      },
    ],
    'improvement_drills': [
      {
        'name': 'Goblet Squat',
        'purpose': 'Encourage a taller trunk and reduce forward lean.',
        'sets': '2–3',
        'reps': '8–10',
        'rest': '60–90 seconds',
        'instructions': ['Hold a light load at the chest'],
      },
    ],
    'next_attempt': ['Sit the hips back and down instead of folding hard forward.'],
    'disclaimer':
        'This analysis is educational and is not a medical diagnosis. It does not replace assessment by a qualified coach or healthcare professional when needed.',
    'provider': 'motion_demo',
    'metrics': {'average_knee_angle': 91.2},
  });
}

MotionAnalysisResult _deadliftProfile() {
  return MotionAnalysisResult.fromJson({
    'exercise': 'deadlift',
    'demo': true,
    'structured': true,
    'title': 'Deadlift',
    'analysis_label': 'Movement Analysis',
    'overall_status': 'Needs improvement',
    'overall_summary':
        'In this video a hip-hinge deadlift shows back rounding at the bottom and occasional load drift.',
    'positive_points': [
      {'title': 'Hip-hinge pattern', 'explanation': 'The hips clearly push back.'},
    ],
    'movement_points': [
      {
        'title': 'Back rounding at the bottom',
        'status': 'attention',
        'status_label': 'Needs focus',
        'explanation': 'In the lower portion the back rounds clearly.',
        'correction': 'Shorten the lowering range where the back stays more stable.',
      },
    ],
    'coaching_cues': ['Keep the load close to the legs.'],
    'common_mistakes': [
      {
        'title': 'Rounding instead of a stable hinge',
        'explanation': 'Chasing extra depth at the cost of trunk stability reduces quality.',
      },
    ],
    'improvement_drills': [
      {
        'name': 'Hip Hinge Drill',
        'purpose': 'Reinforce the hinge pattern while keeping the trunk more stable.',
        'sets': '2–3',
        'reps': '8–10',
        'rest': '45–60 seconds',
        'instructions': ['Push the hips back with a soft knee bend'],
      },
    ],
    'next_attempt': ['Do not go deeper than the point where the back stays more stable.'],
    'disclaimer':
        'This analysis is educational and is not a medical diagnosis. It does not replace assessment by a qualified coach or healthcare professional when needed.',
    'provider': 'motion_demo',
  });
}

String _renderPremiumText(MotionAnalysisResult result) {
  return [
    result.title,
    result.analysisLabel,
    result.overallStatus,
    result.overallSummary,
    ...result.positivePoints.map((p) => '${p.title}\n${p.explanation}'),
    ...result.movementPoints.map(
      (p) => '${p.title}\n${p.statusLabel}\n${p.explanation}\n${p.correction}',
    ),
    ...result.coachingCues,
    ...result.commonMistakes.map((m) => '${m.title}\n${m.explanation}'),
    ...result.improvementDrills.map((d) => '${d.name}\n${d.purpose}'),
    ...result.nextAttempt,
    result.disclaimer,
  ].whereType<String>().join('\n');
}

void main() {
  test('catalog is a natural library and keeps squat/deadlift separated', () {
    final ids = MotionExercise.catalog.map((e) => e.id).toList();
    expect(ids, [
      'squat',
      'bench_press',
      'lunge',
      'front_pulldown',
      'shoulder_press',
      'deadlift',
      'push_up',
      'biceps_curl',
    ]);
    expect(ids.length, greaterThan(2));
    expect(MotionExercise.squatAndDeadliftAreAdjacent(), isFalse);
    expect(ids.indexOf('deadlift') - ids.indexOf('squat'), greaterThan(1));
  });

  test('catalog does not expose coming-soon or demo wording in ids/labels', () {
    final blob = MotionExercise.catalog.map((e) => e.id).join(' ').toLowerCase();
    expect(blob, isNot(contains('coming')));
    expect(blob, isNot(contains('demo')));
    expect(blob, isNot(contains('soon')));
  });

  test('supported analysis path remains squat and deadlift only', () {
    expect(
      MotionExercise.supported.map((e) => e.id).toList(),
      ['squat', 'deadlift'],
    );
  });

  test('squat video profile has squat-specific findings and drills', () {
    final squat = _squatProfile();
    expect(squat.hasPremiumStructure, isTrue);
    expect(squat.overallSummary, contains('forward trunk lean'));
    expect(squat.improvementDrills.map((d) => d.name), contains('Goblet Squat'));
    expect(squat.improvementDrills.map((d) => d.name), isNot(contains('Hip Hinge Drill')));
  });

  test('deadlift video profile has deadlift-specific findings and drills', () {
    final deadlift = _deadliftProfile();
    expect(deadlift.overallSummary, contains('back rounding'));
    expect(deadlift.improvementDrills.map((d) => d.name), contains('Hip Hinge Drill'));
    expect(deadlift.improvementDrills.map((d) => d.name), isNot(contains('Goblet Squat')));
  });

  test('squat and deadlift never mix content', () {
    final squat = _renderPremiumText(_squatProfile()).toLowerCase();
    final deadlift = _renderPremiumText(_deadliftProfile()).toLowerCase();
    expect(squat, contains('forward trunk lean'));
    expect(squat, isNot(contains('hip hinge drill')));
    expect(deadlift, contains('back rounding at the bottom'));
    expect(deadlift, isNot(contains('forward trunk lean')));
    expect(deadlift, isNot(contains('goblet squat')));
  });

  test('no internal ids or fake metrics leak into rendered premium text', () {
    for (final text in [
      _renderPremiumText(_squatProfile()),
      _renderPremiumText(_deadliftProfile()),
    ]) {
      expect(text.toLowerCase(), isNot(contains('motion_demo')));
      expect(text.toLowerCase(), isNot(contains('average_knee_angle')));
      expect(text, isNot(contains('91.2')));
      expect(text.toLowerCase(), isNot(contains('coming soon')));
      expect(text.toLowerCase(), isNot(contains('قريباً')));
    }
  });

  testWidgets('premium squat content renders and hides internals', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(child: Text(_renderPremiumText(_squatProfile()))),
        ),
      ),
    );
    expect(find.textContaining('forward trunk lean'), findsOneWidget);
    expect(find.textContaining('Goblet Squat'), findsOneWidget);
    expect(find.textContaining('Hip Hinge Drill'), findsNothing);
    expect(find.textContaining('motion_demo'), findsNothing);
  });

  testWidgets('premium deadlift content never shows squat-specific content', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(child: Text(_renderPremiumText(_deadliftProfile()))),
        ),
      ),
    );
    expect(find.textContaining('hip-hinge deadlift'), findsOneWidget);
    expect(find.textContaining('Hip Hinge Drill'), findsOneWidget);
    expect(find.textContaining('Goblet Squat'), findsNothing);
  });
}
