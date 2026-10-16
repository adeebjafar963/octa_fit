import 'package:octa_fit/domain/entities/coach.dart';
import 'package:octa_fit/domain/entities/coach_memory.dart';

abstract class CoachRepository {
  Future<CoachConversationPage> getConversations({int page = 1});

  Future<CoachConversation> createConversation({String? title});

  Future<CoachConversation> getConversation(int id);

  Future<void> deleteConversation(int id);

  Future<CoachSendResult> sendMessage({
    required int conversationId,
    required String message,
    String? language,
  });

  Future<CoachMemoryPage> getMemories();

  Future<CoachMemoryItem> deactivateMemory(int id);

  Future<void> deleteMemory(int id);

  Future<void> clearMemories();
}
