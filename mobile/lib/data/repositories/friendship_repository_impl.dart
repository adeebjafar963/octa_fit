import 'package:octa_fit/data/datasources/friendship_remote_datasource.dart';
import 'package:octa_fit/domain/entities/friendship.dart';
import 'package:octa_fit/domain/repositories/friendship_repository.dart';

class FriendshipRepositoryImpl implements FriendshipRepository {
  FriendshipRepositoryImpl(this._remote);

  final FriendshipRemoteDataSource _remote;

  @override
  Future<CommunityUserPage> searchUsers({required String query, int page = 1}) =>
      _remote.searchUsers(query: query, page: page);

  @override
  Future<CommunityUserPage> getFriends({int page = 1}) => _remote.fetchFriends(page: page);

  @override
  Future<FriendRequestPage> getIncomingRequests({int page = 1}) =>
      _remote.fetchIncoming(page: page);

  @override
  Future<CommunityUser> getPublicUser(int id) => _remote.fetchPublicUser(id);

  @override
  Future<void> sendRequest(int userId) => _remote.sendRequest(userId);

  @override
  Future<CommunityUser?> acceptRequest(int requestId) => _remote.acceptRequest(requestId);

  @override
  Future<void> rejectRequest(int requestId) => _remote.rejectRequest(requestId);

  @override
  Future<void> removeFriend(int userId) => _remote.removeFriend(userId);
}
