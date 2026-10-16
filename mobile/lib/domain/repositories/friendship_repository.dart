import 'package:octa_fit/domain/entities/friendship.dart';

abstract class FriendshipRepository {
  Future<CommunityUserPage> searchUsers({required String query, int page = 1});

  Future<CommunityUserPage> getFriends({int page = 1});

  Future<FriendRequestPage> getIncomingRequests({int page = 1});

  Future<CommunityUser> getPublicUser(int id);

  Future<void> sendRequest(int userId);

  Future<CommunityUser?> acceptRequest(int requestId);

  Future<void> rejectRequest(int requestId);

  Future<void> removeFriend(int userId);
}
