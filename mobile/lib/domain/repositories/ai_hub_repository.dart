import 'package:octa_fit/domain/entities/ai_hub.dart';

abstract class AiHubRepository {
  Future<AiHubSnapshot> fetchHub();
  Future<AiRouteDecision> routeMessage({
    required String message,
    String? language,
  });
}
