import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:octa_fit/domain/entities/coach_memory.dart';
import 'package:octa_fit/presentation/settings/cubit/coach_memory_cubit.dart';
import 'package:mocktail/mocktail.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';
import 'package:octa_fit/core/errors/api_exception.dart';

class MockCoachRepository extends Mock implements CoachRepository {}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('CoachMemoryItem parsing', () {
    test('parses schema 11h.1 page without inventing fields', () {
      final page = CoachMemoryPage.fromJson({
        'schema_version': '11h.1',
        'count': 1,
        'items': [
          {
            'id': 9,
            'category': 'equipment',
            'key': 'available_equipment',
            'value': {'items': ['dumbbells'], 'mode': 'dumbbell'},
            'source': 'explicit',
            'explicit': true,
            'confidence': 1.0,
            'active': true,
            'last_confirmed_at': '2026-09-29T12:00:00Z',
            'expires_at': null,
          },
        ],
      });
      expect(page.schemaVersion, '11h.1');
      expect(page.count, 1);
      expect(page.items.first.key, 'available_equipment');
      expect(page.items.first.displayValue, contains('dumbbell'));
    });

    test('malformed items do not crash', () {
      final page = CoachMemoryPage.fromJson({
        'schema_version': '11h.1',
        'items': [null, 'x', 3],
      });
      expect(page.items, isEmpty);
    });
  });

  group('CoachMemoryCubit', () {
    late MockCoachRepository repository;

    setUp(() {
      repository = MockCoachRepository();
    });

    blocTest<CoachMemoryCubit, CoachMemoryState>(
      'loads memories',
      build: () {
        when(() => repository.getMemories()).thenAnswer(
          (_) async => const CoachMemoryPage(
            items: [
              CoachMemoryItem(
                id: 1,
                category: 'goal',
                key: 'primary_goal',
                value: {'goal': 'hypertrophy'},
                source: 'explicit',
                explicit: true,
                active: true,
              ),
            ],
            count: 1,
          ),
        );
        return CoachMemoryCubit(repository);
      },
      act: (c) => c.load(),
      expect: () => [
        const CoachMemoryLoading(),
        isA<CoachMemoryLoaded>().having((s) => s.items.length, 'items', 1),
      ],
    );

    blocTest<CoachMemoryCubit, CoachMemoryState>(
      'deactivate refreshes list',
      build: () {
        when(() => repository.getMemories()).thenAnswer(
          (_) async => const CoachMemoryPage(items: [], count: 0),
        );
        when(() => repository.deactivateMemory(1)).thenAnswer(
          (_) async => const CoachMemoryItem(
            id: 1,
            category: 'goal',
            key: 'primary_goal',
            value: {'goal': 'hypertrophy'},
            source: 'explicit',
            explicit: true,
            active: false,
          ),
        );
        return CoachMemoryCubit(repository);
      },
      seed: () => const CoachMemoryLoaded(
        items: [
          CoachMemoryItem(
            id: 1,
            category: 'goal',
            key: 'primary_goal',
            value: {'goal': 'hypertrophy'},
            source: 'explicit',
            explicit: true,
            active: true,
          ),
        ],
      ),
      act: (c) => c.deactivate(1),
      expect: () => [
        isA<CoachMemoryLoaded>().having((s) => s.busy, 'busy', true),
        isA<CoachMemoryLoaded>().having((s) => s.items, 'items', isEmpty),
      ],
    );

    blocTest<CoachMemoryCubit, CoachMemoryState>(
      'clearAll empties list',
      build: () {
        when(() => repository.clearMemories()).thenAnswer((_) async {});
        return CoachMemoryCubit(repository);
      },
      seed: () => const CoachMemoryLoaded(
        items: [
          CoachMemoryItem(
            id: 2,
            category: 'schedule',
            key: 'preferred_training_time',
            value: {'hour': 7},
            source: 'explicit',
            explicit: true,
            active: true,
          ),
        ],
      ),
      act: (c) => c.clearAll(),
      expect: () => [
        isA<CoachMemoryLoaded>().having((s) => s.busy, 'busy', true),
        const CoachMemoryLoaded(items: []),
      ],
    );

    blocTest<CoachMemoryCubit, CoachMemoryState>(
      'load error surfaces message key',
      build: () {
        when(() => repository.getMemories()).thenThrow(
          ApiException('down', messageKey: 'coach_unavailable'),
        );
        return CoachMemoryCubit(repository);
      },
      act: (c) => c.load(),
      expect: () => [
        const CoachMemoryLoading(),
        const CoachMemoryError('coach_unavailable'),
      ],
    );
  });

  group('localization', () {
    Future<Map<String, dynamic>> loadLocale(String code) async {
      final raw = await rootBundle.loadString('assets/translations/$code.json');
      return jsonDecode(raw) as Map<String, dynamic>;
    }

    test('EN and AR coach_memory keys stay in parity', () async {
      final en = await loadLocale('en');
      final ar = await loadLocale('ar');
      final enKeys = en.keys.where((k) => k.startsWith('coach_memory_')).toSet();
      final arKeys = ar.keys.where((k) => k.startsWith('coach_memory_')).toSet();
      expect(enKeys, isNotEmpty);
      expect(arKeys, enKeys);
      expect(en['coach_memory_title'], isNot(contains('OCTA FIT Coach Memory')));
      // Brand must remain OCTA FIT in both locales where mentioned.
      expect(en['coach_memory_settings_hint'], contains('OCTA FIT'));
      expect(ar['coach_memory_settings_hint'], contains('OCTA FIT'));
    });
  });
}
