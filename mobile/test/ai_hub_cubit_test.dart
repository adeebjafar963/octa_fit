import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/domain/entities/ai_hub.dart';
import 'package:octa_fit/domain/entities/coach.dart';
import 'package:octa_fit/domain/repositories/ai_hub_repository.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';
import 'package:octa_fit/presentation/ai_hub/cubit/ai_hub_cubit.dart';

class MockAiHubRepository extends Mock implements AiHubRepository {}

class MockCoachRepository extends Mock implements CoachRepository {}

void main() {
  late MockAiHubRepository aiHub;
  late MockCoachRepository coach;

  final snapshot = AiHubSnapshot(
    capabilities: const AiHubCapabilities(coach: true, physio: true, motion: true),
    greeting: const AiHubGreeting(name: 'Adib', trainingGoal: 'muscle_gain'),
  );

  setUp(() {
    aiHub = MockAiHubRepository();
    coach = MockCoachRepository();
  });

  test('load emits hub snapshot', () async {
    when(() => aiHub.fetchHub()).thenAnswer((_) async => snapshot);
    final cubit = AiHubCubit(aiHub, coach);
    await cubit.load();
    final s = cubit.state;
    expect(s, isA<AiHubLoaded>());
    expect((s as AiHubLoaded).snapshot.greeting.name, 'Adib');
    await cubit.close();
  });

  test('ask coach intent creates conversation and navigates to chat', () async {
    when(() => aiHub.fetchHub()).thenAnswer((_) async => snapshot);
    when(() => aiHub.routeMessage(message: any(named: 'message'), language: any(named: 'language')))
        .thenAnswer(
      (_) async => const AiRouteDecision(
        intent: 'coach',
        capability: 'coach',
        action: 'open_coach_chat',
        priority: 'training',
        requiresFeature: 'coach',
        message: 'Create today workout',
        navigationRoute: '/coach/chat',
        createsConversation: true,
      ),
    );
    when(() => coach.createConversation(title: any(named: 'title'))).thenAnswer(
      (_) async => const CoachConversation(id: 42, title: 'Create today workout'),
    );
    when(() => coach.sendMessage(
          conversationId: any(named: 'conversationId'),
          message: any(named: 'message'),
          language: any(named: 'language'),
        )).thenAnswer(
      (_) async => const CoachSendResult(
        userMessage: CoachMessage(id: 1, conversationId: 42, role: 'user', content: 'x'),
        assistantMessage: CoachMessage(id: 2, conversationId: 42, role: 'assistant', content: 'y'),
        conversation: CoachConversation(id: 42),
      ),
    );

    final cubit = AiHubCubit(aiHub, coach);
    await cubit.load();
    final nav = await cubit.ask('Create today workout', language: 'en');
    expect(nav?.path, '/coach/chat/42');
    await cubit.close();
  });

  test('ask physio intent opens physio new without conversation', () async {
    when(() => aiHub.fetchHub()).thenAnswer((_) async => snapshot);
    when(() => aiHub.routeMessage(message: any(named: 'message'), language: any(named: 'language')))
        .thenAnswer(
      (_) async => const AiRouteDecision(
        intent: 'physio',
        capability: 'physio',
        action: 'open_physio_new',
        priority: 'medical',
        requiresFeature: 'physio',
        message: 'knee hurts',
        navigationRoute: '/physio/new',
        createsConversation: false,
      ),
    );

    final cubit = AiHubCubit(aiHub, coach);
    await cubit.load();
    final nav = await cubit.ask('knee hurts', language: 'en');
    expect(nav?.path, '/physio/new');
    verifyNever(() => coach.createConversation(title: any(named: 'title')));
    await cubit.close();
  });
}
