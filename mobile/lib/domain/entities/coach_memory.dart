import 'package:equatable/equatable.dart';

/// Phase 11H — Coach long-term personalization memory (API list item).
class CoachMemoryItem extends Equatable {
  const CoachMemoryItem({
    required this.id,
    required this.category,
    required this.key,
    required this.value,
    required this.source,
    required this.explicit,
    required this.active,
    this.confidence,
    this.lastConfirmedAt,
    this.expiresAt,
  });

  factory CoachMemoryItem.fromJson(Map<String, dynamic> json) {
    return CoachMemoryItem(
      id: json['id'] as int? ?? 0,
      category: json['category'] as String? ?? '',
      key: json['key'] as String? ?? '',
      value: json['value'],
      source: json['source'] as String? ?? 'explicit',
      explicit: json['explicit'] as bool? ?? true,
      active: json['active'] as bool? ?? true,
      confidence: (json['confidence'] as num?)?.toDouble(),
      lastConfirmedAt: json['last_confirmed_at'] as String?,
      expiresAt: json['expires_at'] as String?,
    );
  }

  final int id;
  final String category;
  final String key;
  final dynamic value;
  final String source;
  final bool explicit;
  final bool active;
  final double? confidence;
  final String? lastConfirmedAt;
  final String? expiresAt;

  String get displayValue {
    if (value is Map) {
      final map = Map<String, dynamic>.from(value as Map);
      if (map['text'] != null) return '${map['text']}';
      if (map['style'] != null) return '${map['style']}';
      if (map['goal'] != null) return '${map['goal']}';
      if (map['mode'] != null) return '${map['mode']}';
      if (map['food'] != null) return '${map['food']}';
      if (map['days'] != null) return '${map['days']}';
      if (map['minutes'] != null) return '${map['minutes']}';
      if (map['slot'] != null) return '${map['slot']}';
      if (map['hour'] != null) {
        final h = map['hour'];
        final m = map['minute'] ?? 0;
        final ampm = map['ampm'];
        return ampm != null ? '$h:${m.toString().padLeft(2, '0')} $ampm' : '$h:${m.toString().padLeft(2, '0')}';
      }
      if (map['items'] is List) {
        return (map['items'] as List).map((e) => '$e').join(', ');
      }
      return map.entries.map((e) => '${e.key}: ${e.value}').join(', ');
    }
    if (value is List) {
      return (value as List).map((e) => '$e').join(', ');
    }
    return value?.toString() ?? '';
  }

  @override
  List<Object?> get props => [id, category, key, active];
}

class CoachMemoryPage extends Equatable {
  const CoachMemoryPage({
    required this.items,
    required this.count,
    this.schemaVersion = '11h.1',
  });

  factory CoachMemoryPage.fromJson(Map<String, dynamic> json) {
    final items = (json['items'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(CoachMemoryItem.fromJson)
        .toList();
    return CoachMemoryPage(
      items: items,
      count: json['count'] as int? ?? items.length,
      schemaVersion: json['schema_version'] as String? ?? '11h.1',
    );
  }

  final List<CoachMemoryItem> items;
  final int count;
  final String schemaVersion;

  @override
  List<Object?> get props => [items, count, schemaVersion];
}
