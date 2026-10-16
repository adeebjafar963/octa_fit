import 'package:octa_fit/data/datasources/ai_hub_remote_datasource.dart';
import 'package:octa_fit/domain/entities/ai_hub.dart';
import 'package:octa_fit/domain/repositories/ai_hub_repository.dart';

class AiHubRepositoryImpl implements AiHubRepository {
  AiHubRepositoryImpl(this._remote);

  final AiHubRemoteDataSource _remote;

  @override
  Future<AiHubSnapshot> fetchHub() => _remote.fetchHub();

  @override
  Future<AiRouteDecision> routeMessage({
    required String message,
    String? language,
  }) =>
      _remote.routeMessage(message: message, language: language);
}
