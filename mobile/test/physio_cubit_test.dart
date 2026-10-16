import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/physio.dart';
import 'package:octa_fit/domain/repositories/physio_repository.dart';
import 'package:octa_fit/presentation/physio/cubit/physio_assessment_cubit.dart';
import 'package:octa_fit/presentation/physio/cubit/physio_assessments_cubit.dart';

class MockPhysioRepository extends Mock implements PhysioRepository {}

void main() {
  late MockPhysioRepository repository;

  final assessment = PhysioAssessment(
    id: 1,
    bodyArea: 'knee',
    symptoms: 'Mild pain after squats',
    painLevel: 3,
    riskLevel: 'moderate',
    requiresUrgentCare: false,
    result: const PhysioResult(
      summary: 'Moderate discomfort may relate to training.',
      selfCare: ['Reduce aggravating exercise'],
    ),
    updatedAt: '2026-09-20T18:00:00Z',
    messages: const [],
  );

  final urgentAssessment = PhysioAssessment(
    id: 2,
    bodyArea: 'knee',
    symptoms: 'Severe pain unable to bear weight',
    painLevel: 9,
    riskLevel: 'urgent',
    requiresUrgentCare: true,
    result: const PhysioResult(
      summary: 'Urgent medical evaluation may be needed.',
    ),
  );

  setUpAll(() {
    registerFallbackValue(
      const PhysioCreateInput(bodyArea: 'knee', symptoms: 'pain'),
    );
  });

  setUp(() {
    repository = MockPhysioRepository();
  });

  group('mapping', () {
    test('PhysioAssessment.fromJson', () {
      final parsed = PhysioAssessment.fromJson({
        'id': 1,
        'body_area': 'legs',
        'symptoms': 'sore',
        'risk_level': 'low',
        'requires_urgent_care': false,
        'result': {
          'summary': 'Likely soreness',
          'self_care': ['Rest lightly'],
        },
        'messages': [
          {
            'id': 2,
            'assessment_id': 1,
            'role': 'user',
            'content': 'sore legs',
          }
        ],
      });
      expect(parsed.bodyArea, 'legs');
      expect(parsed.result.summary, 'Likely soreness');
      expect(parsed.messages.first.isUser, isTrue);
    });
  });

  group('PhysioAssessmentsCubit', () {
    blocTest<PhysioAssessmentsCubit, PhysioAssessmentsState>(
      'loads assessments',
      build: () {
        when(() => repository.getAssessments(page: any(named: 'page'))).thenAnswer(
          (_) async => PhysioAssessmentPage(
            items: [assessment],
            currentPage: 1,
            lastPage: 1,
            perPage: 20,
            total: 1,
          ),
        );
        return PhysioAssessmentsCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const PhysioAssessmentsLoading(),
        PhysioAssessmentsLoaded([assessment]),
      ],
    );

    blocTest<PhysioAssessmentsCubit, PhysioAssessmentsState>(
      'deletes assessment',
      build: () {
        when(() => repository.deleteAssessment(1)).thenAnswer((_) async {});
        return PhysioAssessmentsCubit(repository);
      },
      seed: () => PhysioAssessmentsLoaded([assessment]),
      act: (cubit) => cubit.delete(1),
      expect: () => [
        const PhysioAssessmentsLoaded([], feedbackKey: 'physio_assessment_deleted'),
      ],
    );

    blocTest<PhysioAssessmentsCubit, PhysioAssessmentsState>(
      'emits error on load failure',
      build: () {
        when(() => repository.getAssessments(page: any(named: 'page'))).thenThrow(
          ApiException('fail', messageKey: 'common_error'),
        );
        return PhysioAssessmentsCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const PhysioAssessmentsLoading(),
        const PhysioAssessmentsError('common_error'),
      ],
    );
  });

  group('PhysioAssessmentCubit', () {
    blocTest<PhysioAssessmentCubit, PhysioAssessmentState>(
      'submits assessment successfully',
      build: () {
        when(() => repository.createAssessment(any())).thenAnswer((_) async => assessment);
        return PhysioAssessmentCubit(repository);
      },
      act: (cubit) => cubit.submit(
        const PhysioCreateInput(
          bodyArea: 'knee',
          symptoms: 'Mild pain after squats',
          painLevel: 3,
        ),
      ),
      expect: () => [
        const PhysioAssessmentFormState(submitting: true),
        PhysioAssessmentLoaded(assessment: assessment, messages: const []),
      ],
    );

    blocTest<PhysioAssessmentCubit, PhysioAssessmentState>(
      'loads red-flag assessment',
      build: () {
        when(() => repository.getAssessment(2)).thenAnswer((_) async => urgentAssessment);
        return PhysioAssessmentCubit(repository);
      },
      act: (cubit) => cubit.load(2),
      expect: () => [
        const PhysioAssessmentLoading(),
        PhysioAssessmentLoaded(assessment: urgentAssessment, messages: const []),
      ],
      verify: (cubit) {
        final state = cubit.state;
        expect(state, isA<PhysioAssessmentLoaded>());
        expect((state as PhysioAssessmentLoaded).assessment.requiresUrgentCare, isTrue);
      },
    );

    test('follow-up send works after load', () async {
      when(() => repository.getAssessment(1)).thenAnswer((_) async => assessment);
      when(
        () => repository.sendMessage(
          assessmentId: 1,
          message: 'Can I train tomorrow?',
          language: any(named: 'language'),
        ),
      ).thenAnswer(
        (_) async => PhysioSendResult(
          userMessage: const PhysioMessage(
            id: 10,
            assessmentId: 1,
            role: 'user',
            content: 'Can I train tomorrow?',
          ),
          assistantMessage: const PhysioMessage(
            id: 11,
            assessmentId: 1,
            role: 'assistant',
            content: 'Return gradually if mild.',
          ),
          assessment: assessment,
        ),
      );

      final cubit = PhysioAssessmentCubit(repository);
      await cubit.load(1);
      await cubit.send('Can I train tomorrow?', language: 'en');
      final state = cubit.state as PhysioAssessmentLoaded;
      expect(state.messages.length, 2);
      expect(state.sending, isFalse);
      await cubit.close();
    });

    blocTest<PhysioAssessmentCubit, PhysioAssessmentState>(
      'submit error state',
      build: () {
        when(() => repository.createAssessment(any())).thenThrow(
          ApiException('down', messageKey: 'physio_unavailable'),
        );
        return PhysioAssessmentCubit(repository);
      },
      act: (cubit) => cubit.submit(
        const PhysioCreateInput(bodyArea: 'knee', symptoms: 'pain'),
      ),
      expect: () => [
        const PhysioAssessmentFormState(submitting: true),
        const PhysioAssessmentFormState(errorKey: 'physio_unavailable'),
      ],
    );
  });
}
