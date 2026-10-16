import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/presentation/physio/physio_clinical_result_view.dart';

PhysioAssessment _assessment({
  required String caseId,
  required String bodyArea,
  required String severity,
  required String impression,
  List<Map<String, dynamic>>? exercises,
  String? medicalPriority,
}) {
  return PhysioAssessment(
    id: 99,
    bodyArea: bodyArea,
    symptoms: 'Body area: $bodyArea\nSymptoms: sample\nPain level: 4/10',
    riskLevel: severity.toLowerCase(),
    requiresUrgentCare: severity.toUpperCase() == 'URGENT',
    result: PhysioResult.fromJson({
      'demo': true,
      'structured': true,
      'case_id': caseId,
      'demo_case_id': caseId,
      'subtitle': 'Initial assessment based on reported symptoms',
      'clinical_impression': impression,
      'severity': severity,
      'confidence': 'Moderate',
      'key_findings': [
        {
          'title': 'Pattern',
          'detail': 'Symptoms relate to load and movement.',
          'icon': 'pattern',
        },
      ],
      'recommended_exercises': exercises ??
          [
            {
              'name': 'Sample Exercise',
              'purpose': 'Restore control',
              'difficulty': 'Beginner',
              'instructions': ['Step one', 'Step two'],
              'sets': '2',
              'reps': '10',
              'rest': '45s',
              'frequency': 'Daily',
              'safety_note': 'Stop if sharp pain',
            },
          ],
      'self_management': {
        'today': ['Stay gently active'],
        'next_days': ['Progress if stable'],
      },
      'what_to_avoid': ['Sudden heavy loading'],
      'red_flags': ['Rapid worsening'],
      'next_step': 'Continue conservative care and monitor.',
      'evidence_basis': ['APTA/JOSPT', 'WHO guidance'],
      'medical_priority_message': medicalPriority,
    }),
    messages: [
      PhysioMessage(
        id: 1,
        assessmentId: 99,
        role: 'user',
        content:
            'Body area: $bodyArea\nSymptoms: sample\nPain level: 4/10\nDuration: 2 weeks\nTrigger: training\nAdditional: worse with motion',
      ),
      PhysioMessage(
        id: 2,
        assessmentId: 99,
        role: 'assistant',
        content: 'Follow-up summary should never render in clinical cards.',
      ),
    ],
  );
}

Future<void> _pumpResult(WidgetTester tester, PhysioAssessment assessment) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: PhysioClinicalResultView(assessment: assessment),
        ),
      ),
    ),
  );
}

void assertNoInternalLeakage(WidgetTester tester) {
  expect(find.textContaining('PHYS-'), findsNothing);
  expect(find.textContaining('PHYS-13'), findsNothing);
  expect(find.textContaining('PHYS-14'), findsNothing);
  expect(find.textContaining('PHYS-46'), findsNothing);
  expect(find.textContaining('case_id'), findsNothing);
  expect(find.textContaining('caseId'), findsNothing);
  expect(find.textContaining('Evidence'), findsNothing);
  expect(find.textContaining('Evidence Basis'), findsNothing);
  expect(find.textContaining('الأساس'), findsNothing);
  expect(find.textContaining('Follow-up'), findsNothing);
  expect(find.textContaining('متابعة'), findsNothing);
  expect(find.textContaining('Body area:'), findsNothing);
  expect(find.textContaining('Symptoms:'), findsNothing);
  expect(find.textContaining('Pain level:'), findsNothing);
  expect(find.textContaining('Duration:'), findsNothing);
  expect(find.textContaining('Trigger:'), findsNothing);
  expect(find.textContaining('Additional:'), findsNothing);
  expect(find.textContaining('APTA/JOSPT'), findsNothing);
  expect(find.textContaining('WHO guidance'), findsNothing);
}

void main() {
  test('internal case/evidence data still parse but stay available in model', () {
    final result = PhysioResult.fromJson({
      'demo': true,
      'structured': true,
      'case_id': 'PHYS-13',
      'evidence_basis': ['APTA/JOSPT'],
      'clinical_impression': 'Compatible with anterior shoulder loading pattern.',
      'severity': 'LOW',
    });
    expect(result.caseId, 'PHYS-13');
    expect(result.evidenceBasis, contains('APTA/JOSPT'));
  });

  testWidgets('PHYS-13 style result hides evidence, follow-up, raw input, case id',
      (tester) async {
    final assessment = _assessment(
      caseId: 'PHYS-13',
      bodyArea: 'shoulder',
      severity: 'LOW',
      impression:
          'Symptoms appear most consistent with anterior shoulder loading discomfort.',
    );
    await _pumpResult(tester, assessment);

    expect(
      find.textContaining('anterior shoulder loading'),
      findsOneWidget,
    );
    expect(find.textContaining('Sample Exercise'), findsOneWidget);
    assertNoInternalLeakage(tester);
  });

  testWidgets('PHYS-14 style result hides internal metadata', (tester) async {
    final assessment = _assessment(
      caseId: 'PHYS-14',
      bodyArea: 'shoulder',
      severity: 'MODERATE',
      impression:
          'Presentation may be compatible with lateral shoulder pain and night discomfort.',
    );
    await _pumpResult(tester, assessment);

    expect(find.textContaining('lateral shoulder'), findsOneWidget);
    assertNoInternalLeakage(tester);
  });

  testWidgets('normal neck case renders clinical content without internals',
      (tester) async {
    final assessment = _assessment(
      caseId: 'PHYS-01',
      bodyArea: 'neck',
      severity: 'LOW',
      impression: 'Symptoms appear most consistent with mechanical neck pain.',
      exercises: [
        {
          'name': 'Chin Tucks',
          'purpose': 'Deep neck control',
          'difficulty': 'Beginner',
          'instructions': ['Sit tall', 'Chin back'],
          'sets': '2',
          'reps': '10',
          'rest': '45s',
          'frequency': 'Daily',
          'safety_note': 'Stop if sharp pain',
        },
      ],
    );
    await _pumpResult(tester, assessment);

    expect(find.textContaining('Chin Tucks'), findsOneWidget);
    expect(find.textContaining('mechanical neck pain'), findsOneWidget);
    assertNoInternalLeakage(tester);
  });

  testWidgets('red-flag case hides case id and evidence while showing priority',
      (tester) async {
    final assessment = _assessment(
      caseId: 'PHYS-46',
      bodyArea: 'lower_back',
      severity: 'URGENT',
      impression:
          'Preliminary assessment suggests a red-flag presentation requiring urgent medical evaluation.',
      exercises: const [],
      medicalPriority:
          'Urgent medical assessment is the priority. Do not use exercise as the primary response.',
    );
    await _pumpResult(tester, assessment);

    expect(find.textContaining('Urgent medical assessment'), findsWidgets);
    expect(find.textContaining('Chin Tucks'), findsNothing);
    assertNoInternalLeakage(tester);
  });
}
