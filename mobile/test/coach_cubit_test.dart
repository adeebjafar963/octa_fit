import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/coach.dart';
import 'package:octa_fit/domain/entities/coach_structured.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';
import 'package:octa_fit/presentation/coach/cubit/coach_chat_cubit.dart';
import 'package:octa_fit/presentation/coach/cubit/coach_conversations_cubit.dart';
import 'package:octa_fit/presentation/coach/widgets/coach_evidence_section.dart';
import 'package:octa_fit/presentation/coach/widgets/coach_food_widgets.dart';
import 'package:octa_fit/presentation/coach/widgets/coach_nutrition_plan_card.dart';
import 'package:octa_fit/presentation/coach/widgets/coach_training_plan_card.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:bloc_test/bloc_test.dart';

class MockCoachRepository extends Mock implements CoachRepository {}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late MockCoachRepository repository;

  final conversation = CoachConversation(
    id: 1,
    title: 'Muscle Gain',
    updatedAt: '2026-09-20T18:00:00Z',
    messages: const [],
  );

  final userMessage = CoachMessage(
    id: 10,
    conversationId: 1,
    role: 'user',
    content: 'How can I build muscle?',
  );

  final assistantMessage = CoachMessage(
    id: 11,
    conversationId: 1,
    role: 'assistant',
    content: 'Progressive overload and protein.',
  );

  setUpAll(() async {
    SharedPreferences.setMockInitialValues({});
    await EasyLocalization.ensureInitialized();
  });

  setUp(() {
    repository = MockCoachRepository();
  });

  group('mapping', () {
    test('CoachConversation.fromJson', () {
      final parsed = CoachConversation.fromJson({
        'id': 1,
        'title': 'Hello',
        'messages': [
          {
            'id': 2,
            'conversation_id': 1,
            'role': 'user',
            'content': 'Hi',
          }
        ],
      });
      expect(parsed.title, 'Hello');
      expect(parsed.messages.first.isUser, isTrue);
    });

    test('CoachSendResult parses structured Phase 11 payloads', () {
      final result = CoachSendResult.fromJson({
        'user_message': {
          'id': 1,
          'conversation_id': 1,
          'role': 'user',
          'content': 'plan',
        },
        'assistant_message': {
          'id': 2,
          'conversation_id': 1,
          'role': 'assistant',
          'content': 'ok',
        },
        'conversation': {'id': 1, 'title': 't'},
        'grounded_exercises': [
          {'exercise_id': 5, 'name': 'Bench Press', 'grounded': true},
        ],
        'training_plan': {'schema_version': '11c.1', 'goal': 'hypertrophy'},
        'nutrition': {'ok': true, 'totals': {'calories_kcal': 130}},
        'nutrition_plan': {'schema_version': '11e.1', 'calorie_target': 2500},
        'knowledge_context': {
          'schema_version': '11f.1',
          'items': [
            {'source': 'ISSN', 'url': null, 'evidence_level': 'position_stand'},
          ],
        },
      });
      expect(result.groundedExercises.first.exerciseId, 5);
      expect(result.groundedExercises.first.detailRoute, '/training/exercises/5');
      expect(result.trainingPlan?['goal'], 'hypertrophy');
      expect(result.nutritionPlan?['calorie_target'], 2500);
      expect(result.knowledgeContext?['items'], isA<List>());
      final extras = CoachMessageExtras.fromSendResult(result);
      expect(extras.hasStructured, isTrue);
      expect(extras.hasKnowledge, isTrue);
    });

    test('missing exercise id is not navigable', () {
      final ex = CoachGroundedExercise.fromJson({'name': 'X', 'exercise_id': 0});
      expect(ex.exerciseId, 0);
    });

    test('CoachPlanParsing never invents numbers from null', () {
      expect(CoachPlanParsing.asDouble(null), isNull);
      expect(CoachPlanParsing.formatNumber(null), '—');
    });

    test('CoachSendResult accepts Map-shaped structured payloads (11I)', () {
      final result = CoachSendResult.fromJson({
        'user_message': {
          'id': 1,
          'conversation_id': 1,
          'role': 'user',
          'content': 'plan',
        },
        'assistant_message': {
          'id': 2,
          'conversation_id': 1,
          'role': 'assistant',
          'content': 'ok',
        },
        'conversation': {'id': 1, 'title': 't'},
        'training_plan': Map<dynamic, dynamic>.from({
          'goal': 'hypertrophy',
          'sessions': <dynamic>[],
        }),
        'nutrition_plan': Map<dynamic, dynamic>.from({'calorie_target': 2500}),
        'knowledge_context': Map<dynamic, dynamic>.from({
          'items': [
            Map<dynamic, dynamic>.from({'source': 'ISSN', 'url': null}),
          ],
        }),
      });
      expect(result.trainingPlan?['goal'], 'hypertrophy');
      expect(result.nutritionPlan?['calorie_target'], 2500);
      expect(result.knowledgeContext?['items'], isA<List>());
    });

    test('malformed structured payloads do not crash parsing', () {
      final result = CoachSendResult.fromJson({
        'user_message': {
          'id': 1,
          'conversation_id': 1,
          'role': 'user',
          'content': 'hi',
        },
        'assistant_message': {
          'id': 2,
          'conversation_id': 1,
          'role': 'assistant',
          'content': 'hello',
        },
        'conversation': {'id': 1, 'title': 't'},
        'training_plan': 'not-a-map',
        'nutrition_plan': 42,
        'knowledge_context': null,
        'grounded_exercises': ['bad', null, 3],
      });
      expect(result.trainingPlan, isNull);
      expect(result.nutritionPlan, isNull);
      expect(result.knowledgeContext, isNull);
      expect(result.groundedExercises, isEmpty);
    });
  });

  group('CoachConversationsCubit', () {
    blocTest<CoachConversationsCubit, CoachConversationsState>(
      'loads conversations',
      build: () {
        when(() => repository.getConversations(page: any(named: 'page'))).thenAnswer(
          (_) async => CoachConversationPage(
            items: [conversation],
            currentPage: 1,
            lastPage: 1,
            perPage: 20,
            total: 1,
          ),
        );
        return CoachConversationsCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const CoachConversationsLoading(),
        CoachConversationsLoaded([conversation]),
      ],
    );

    blocTest<CoachConversationsCubit, CoachConversationsState>(
      'emits error',
      build: () {
        when(() => repository.getConversations(page: any(named: 'page')))
            .thenThrow(ApiException('down', messageKey: 'error_network'));
        return CoachConversationsCubit(repository);
      },
      act: (cubit) => cubit.load(),
      expect: () => [
        const CoachConversationsLoading(),
        const CoachConversationsError('error_network'),
      ],
    );
  });

  group('CoachChatCubit', () {
    blocTest<CoachChatCubit, CoachChatState>(
      'loads empty conversation',
      build: () {
        when(() => repository.getConversation(1)).thenAnswer((_) async => conversation);
        return CoachChatCubit(repository);
      },
      act: (cubit) => cubit.load(1),
      expect: () => [
        const CoachChatLoading(),
        CoachChatLoaded(conversation: conversation, messages: const []),
      ],
    );

    blocTest<CoachChatCubit, CoachChatState>(
      'sends message and stores structured extras',
      build: () {
        when(() => repository.getConversation(1)).thenAnswer((_) async => conversation);
        when(
          () => repository.sendMessage(
            conversationId: 1,
            message: any(named: 'message'),
            language: any(named: 'language'),
          ),
        ).thenAnswer(
          (_) async => CoachSendResult(
            userMessage: userMessage,
            assistantMessage: assistantMessage,
            conversation: conversation,
            trainingPlan: const {
              'goal': 'hypertrophy',
              'days_per_week': 4,
              'sessions': [],
            },
            groundedExercises: const [
              CoachGroundedExercise(exerciseId: 9, name: 'Squat'),
            ],
          ),
        );
        return CoachChatCubit(repository);
      },
      act: (cubit) async {
        await cubit.load(1);
        await cubit.send('Give me a 4-day bulking plan.');
      },
      expect: () => [
        const CoachChatLoading(),
        CoachChatLoaded(conversation: conversation, messages: const []),
        isA<CoachChatLoaded>().having((s) => s.sending, 'sending', true),
        isA<CoachChatLoaded>()
            .having((s) => s.messages.length, 'messages', 2)
            .having((s) => s.extrasByMessageId.containsKey(11), 'extras', true)
            .having(
              (s) => s.extrasByMessageId[11]!.hasTrainingPlan,
              'training',
              true,
            ),
      ],
    );

    blocTest<CoachChatCubit, CoachChatState>(
      'handles send error with retry payload',
      build: () {
        when(() => repository.getConversation(1)).thenAnswer((_) async => conversation);
        when(
          () => repository.sendMessage(
            conversationId: 1,
            message: any(named: 'message'),
            language: any(named: 'language'),
          ),
        ).thenThrow(ApiException('down', messageKey: 'coach_unavailable'));
        return CoachChatCubit(repository);
      },
      act: (cubit) async {
        await cubit.load(1);
        await cubit.send('Hello');
      },
      expect: () => [
        const CoachChatLoading(),
        CoachChatLoaded(conversation: conversation, messages: const []),
        isA<CoachChatLoaded>().having((s) => s.sending, 'sending', true),
        isA<CoachChatLoaded>()
            .having((s) => s.sending, 'sending', false)
            .having((s) => s.errorKey, 'error', 'coach_unavailable')
            .having((s) => s.lastFailedText, 'failed', 'Hello'),
      ],
    );
  });

  group('Coach UI widgets', () {
    testWidgets('structured coach cards render safely', (tester) async {
      Future<void> mount(Widget child, Finder waitFor) async {
        await tester.binding.setSurfaceSize(const Size(900, 1400));
        // Clear previous EasyLocalization tree before remounting.
        await tester.pumpWidget(const SizedBox.shrink());
        await tester.pump();

        await tester.pumpWidget(
          EasyLocalization(
            key: UniqueKey(),
            supportedLocales: const [Locale('en'), Locale('ar')],
            path: 'assets/translations',
            fallbackLocale: const Locale('en'),
            startLocale: const Locale('en'),
            useOnlyLangCode: true,
            saveLocale: false,
            child: Builder(
              builder: (context) {
                return MaterialApp(
                  theme: ThemeData(useMaterial3: true),
                  localizationsDelegates: context.localizationDelegates,
                  supportedLocales: context.supportedLocales,
                  locale: context.locale,
                  home: Scaffold(body: SingleChildScrollView(child: child)),
                );
              },
            ),
          ),
        );

        for (var i = 0; i < 50; i++) {
          await tester.pump(const Duration(milliseconds: 50));
          if (waitFor.evaluate().isNotEmpty) return;
        }
        fail('Coach widget did not appear: $waitFor');
      }

      await mount(
        CoachTrainingPlanCard(
          plan: {
            'title': 'Plan',
            'goal': 'hypertrophy',
            'days_per_week': 4,
            'sessions': [
              {
                'day': 1,
                'title': 'Upper',
                'exercises': [
                  {
                    'exercise_id': 12,
                    'name': 'Bench',
                    'sets': 3,
                    'reps': '8-12',
                    'rest_seconds': 90,
                    'flutter_route': '/training/exercises/12',
                  },
                  {
                    'exercise_id': 0,
                    'name': 'Broken',
                  },
                ],
              },
            ],
          },
        ),
        find.text('Bench'),
      );
      expect(find.byKey(const ValueKey('open_exercise_12')), findsOneWidget);
      expect(find.byKey(const ValueKey('exercise_unavailable')), findsOneWidget);

      await mount(
        CoachNutritionPlanCard(
          plan: {
            'goal': 'fat_loss',
            'calorie_target': 2200,
            'meals_per_day': 3,
            'duration_days': 3,
            'macro_targets': {
              'protein_g': 150,
              'carbohydrates_g': 200,
              'fat_g': 60,
            },
            'days': [
              {
                'day_number': 1,
                'meals': [
                  {
                    'meal_number': 1,
                    'name': 'Breakfast',
                    'meal_totals': {
                      'calories_kcal': 500,
                      'protein_g': 30,
                      'carbohydrates_g': 50,
                      'fat_g': 15,
                    },
                    'foods': [
                      {
                        'name': 'Oats',
                        'normalized_grams': 200,
                        'unit': 'g',
                        'calories_kcal': 142,
                        'protein_g': 5,
                        'carbohydrates_g': 24,
                        'fat_g': 3,
                      },
                    ],
                  },
                ],
                'daily_totals': {
                  'calories_kcal': 500,
                  'protein_g': 30,
                  'carbohydrates_g': 50,
                  'fat_g': 15,
                },
              },
            ],
          },
        ),
        find.text('Breakfast'),
      );
      expect(find.text('Oats'), findsOneWidget);

      await mount(
        const CoachNutritionLookupCard(
          nutrition: {'ok': false, 'foods': [], 'totals': null},
        ),
        find.text(
          'Could not resolve this food lookup from the food database.',
        ),
      );

      await mount(
        CoachEvidenceSection(
          knowledgeContext: {
            'items': [
              {
                'source': 'WHO Healthy Diet',
                'organization': 'WHO',
                'publication_year': 2020,
                'evidence_level': 'official_guidance',
                'url': null,
              },
            ],
          },
        ),
        find.byType(ListTile),
      );
      await tester.tap(find.byType(ListTile));
      await tester.pump();
      expect(find.text('WHO Healthy Diet'), findsOneWidget);
      expect(find.byIcon(Icons.link), findsNothing);

      await mount(
        const CoachFoodRow(name: 'Banana', quantityLabel: '118 g'),
        find.text('Banana'),
      );
      expect(find.textContaining('kcal'), findsNothing);

      await tester.binding.setSurfaceSize(const Size(360, 800));
      await mount(
        const CoachMacroMetrics(
          calories: 2500,
          protein: 160,
          carbs: 280,
          fat: 70,
        ),
        find.byType(CoachMacroMetrics),
      );
      expect(find.textContaining('kcal'), findsWidgets);
      await tester.binding.setSurfaceSize(const Size(1100, 800));
      await tester.pump();
      expect(find.byType(CoachMacroMetrics), findsOneWidget);
      await tester.binding.setSurfaceSize(null);

      // Phase 11I — malformed structured maps must not throw.
      await mount(
        CoachTrainingPlanCard(
          plan: {
            'title': 'Broken',
            'sessions': null,
            'days_per_week': 'x',
          },
        ),
        find.text('Broken'),
      );
      expect(tester.takeException(), isNull);

      await mount(
        CoachNutritionPlanCard(
          plan: {
            'goal': 'fat_loss',
            'days': 'not-a-list',
            'macro_targets': null,
          },
        ),
        find.textContaining('Nutrition'),
      );
      expect(tester.takeException(), isNull);
    });
  });
}
