import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/presentation/physio/physio_clinical_result_view.dart';

void main() {
  test('PhysioResult parses premium structured demo payload', () {
    final result = PhysioResult.fromJson({
      'demo': true,
      'structured': true,
      'case_id': 'PHYS-01',
      'title': 'Mechanical neck pain',
      'subtitle': 'Initial assessment based on reported symptoms',
      'clinical_impression':
          'Symptoms appear most consistent with mechanical neck pain.',
      'initial_clinical_impression':
          'Symptoms appear most consistent with mechanical neck pain.',
      'severity': 'LOW',
      'escalation_level': 'LOW',
      'key_findings': [
        {
          'title': 'Duration',
          'detail': 'Short symptom duration fits a recent onset pattern.',
          'icon': 'duration',
        },
        {
          'title': 'Trigger',
          'detail': 'Prolonged computer use may contribute.',
          'icon': 'trigger',
        },
        {
          'title': 'Pattern',
          'detail': 'Symptoms link more to posture/load than rest.',
          'icon': 'pattern',
        },
      ],
      'recommended_exercises': [
        {
          'id': 'demo-ex-1-chin-tucks',
          'name': 'Chin Tucks',
          'purpose': 'Improve deep neck control',
          'difficulty': 'Beginner',
          'instructions': [
            'Sit tall',
            'Draw chin back gently',
            'Keep eyes level',
          ],
          'sets': '2',
          'reps': '10 repetitions',
          'rest': '45 seconds',
          'frequency': '2–3 times daily',
          'safety_note': 'Stop if arm symptoms appear',
        },
      ],
      'self_management': {
        'today': ['Take movement breaks'],
        'next_days': ['Progress carefully if stable'],
      },
      'what_to_avoid': ['Prolonged static posture'],
      'red_flags': ['New arm weakness'],
      'next_step': 'Continue conservative care and monitor.',
      'evidence_basis': ['APTA/JOSPT guidance', 'Evidence-based rehab'],
    });

    expect(result.hasStructuredClinicalOutput, isTrue);
    expect(result.demo, isTrue);
    expect(result.keyFindings, hasLength(3));
    expect(result.keyFindings.first.title, 'Duration');
    expect(result.recommendedExercises.first.instructions, hasLength(3));
    expect(result.selfManagement.today, contains('Take movement breaks'));
    expect(result.evidenceBasis, hasLength(2));
    expect(result.escalationLevel, 'LOW');
  });

  testWidgets('PhysioClinicalResultView renders premium sections', (tester) async {
    final assessment = PhysioAssessment(
      id: 1,
      bodyArea: 'neck',
      symptoms: 'Neck ache after computer use',
      riskLevel: 'low',
      result: PhysioResult.fromJson({
        'demo': true,
        'structured': true,
        'case_id': 'PHYS-01',
        'subtitle': 'Initial assessment based on reported symptoms',
        'clinical_impression':
            'Symptoms appear most consistent with mechanical neck pain.',
        'severity': 'LOW',
        'key_findings': [
          {
            'title': 'Duration',
            'detail': 'Recent onset pattern.',
            'icon': 'duration',
          },
        ],
        'recommended_exercises': [
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
        'self_management': {
          'today': ['Move comfortably'],
          'next_days': ['Progress gradually'],
        },
        'what_to_avoid': ['Static posture'],
        'red_flags': ['Arm weakness'],
        'next_step': 'Monitor and continue self-care.',
        'evidence_basis': ['APTA/JOSPT'],
      }),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: PhysioClinicalResultView(assessment: assessment),
          ),
        ),
      ),
    );

    expect(find.textContaining('Chin Tucks'), findsOneWidget);
    expect(find.textContaining('mechanical neck pain'), findsOneWidget);
    expect(find.textContaining('Duration'), findsOneWidget);
    expect(find.textContaining('APTA/JOSPT'), findsNothing);
    expect(find.textContaining('PHYS-01'), findsNothing);
    expect(find.textContaining('Evidence'), findsNothing);
  });

  test('urgent payload hides primary exercise list semantics', () {
    final result = PhysioResult.fromJson({
      'demo': true,
      'structured': true,
      'severity': 'URGENT',
      'clinical_impression': 'Requires urgent medical evaluation.',
      'recommended_exercises': <dynamic>[],
      'medical_priority_message':
          'Urgent medical assessment is the priority.',
      'key_findings': [
        {'title': 'Safety', 'detail': 'Red-flag features present', 'icon': 'safety'},
      ],
      'next_step': 'Seek urgent medical care.',
      'evidence_basis': ['Clinical safety screening'],
      'self_management': {
        'today': ['Seek urgent care'],
        'next_days': ['Follow medical advice'],
      },
    });

    expect(result.escalationLevel, 'URGENT');
    expect(result.recommendedExercises, isEmpty);
    expect(result.medicalPriorityMessage, contains('Urgent'));
  });
}
