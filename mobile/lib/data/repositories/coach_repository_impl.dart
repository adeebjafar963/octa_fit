import 'package:octa_fit/data/datasources/coach_remote_datasource.dart';
import 'package:octa_fit/domain/entities/coach.dart';
import 'package:octa_fit/domain/entities/coach_memory.dart';
import 'package:octa_fit/domain/repositories/coach_repository.dart';

class CoachRepositoryImpl implements CoachRepository {
  CoachRepositoryImpl(this._remote);

  final CoachRemoteDataSource _remote;

  @override
  Future<CoachConversationPage> getConversations({int page = 1}) =>
      _remote.fetchConversations(page: page);

  @override
  Future<CoachConversation> createConversation({String? title}) =>
      _remote.createConversation(title: title);

  @override
  Future<CoachConversation> getConversation(int id) => _remote.fetchConversation(id);

  @override
  Future<void> deleteConversation(int id) => _remote.deleteConversation(id);

  @override
  Future<CoachSendResult> sendMessage({
    required int conversationId,
    required String message,
    String? language,
  }) =>
      _remote.sendMessage(
        conversationId: conversationId,
        message: message,
        language: language,
      );

  @override
  Future<CoachMemoryPage> getMemories() => _remote.fetchMemories();

  @override
  Future<CoachMemoryItem> deactivateMemory(int id) => _remote.deactivateMemory(id);

  @override
  Future<void> deleteMemory(int id) => _remote.deleteMemory(id);

  @override
  Future<void> clearMemories() => _remote.clearMemories();
}
