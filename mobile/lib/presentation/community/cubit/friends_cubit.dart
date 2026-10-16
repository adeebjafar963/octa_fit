import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:octa_fit/core/errors/api_exception.dart';
import 'package:octa_fit/domain/entities/friendship.dart';
import 'package:octa_fit/domain/repositories/friendship_repository.dart';

sealed class FriendsState extends Equatable {
  const FriendsState();

  @override
  List<Object?> get props => [];
}

class FriendsInitial extends FriendsState {
  const FriendsInitial();
}

class FriendsLoading extends FriendsState {
  const FriendsLoading();
}

class FriendsLoaded extends FriendsState {
  const FriendsLoaded({
    required this.friends,
    required this.pendingCount,
    this.feedbackKey,
  });

  final List<CommunityUser> friends;
  final int pendingCount;
  final String? feedbackKey;

  FriendsLoaded copyWith({
    List<CommunityUser>? friends,
    int? pendingCount,
    String? feedbackKey,
    bool clearFeedback = false,
  }) {
    return FriendsLoaded(
      friends: friends ?? this.friends,
      pendingCount: pendingCount ?? this.pendingCount,
      feedbackKey: clearFeedback ? null : (feedbackKey ?? this.feedbackKey),
    );
  }

  @override
  List<Object?> get props => [friends, pendingCount, feedbackKey];
}

class FriendsError extends FriendsState {
  const FriendsError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class FriendsCubit extends Cubit<FriendsState> {
  FriendsCubit(this._repository) : super(const FriendsInitial());

  final FriendshipRepository _repository;

  Future<void> load() async {
    emit(const FriendsLoading());
    try {
      final page = await _repository.getFriends();
      emit(FriendsLoaded(
        friends: page.items,
        pendingCount: page.pendingIncomingCount,
      ));
    } on ApiException catch (e) {
      emit(FriendsError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const FriendsError('common_error'));
    }
  }

  Future<void> removeFriend(int userId) async {
    final current = state;
    if (current is! FriendsLoaded) return;
    try {
      await _repository.removeFriend(userId);
      emit(current.copyWith(
        friends: current.friends.where((f) => f.id != userId).toList(),
        feedbackKey: 'friends_removed',
      ));
    } on ApiException catch (e) {
      emit(current.copyWith(feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(feedbackKey: 'common_error'));
    }
  }
}

sealed class FindFriendsState extends Equatable {
  const FindFriendsState();

  @override
  List<Object?> get props => [];
}

class FindFriendsInitial extends FindFriendsState {
  const FindFriendsInitial();
}

class FindFriendsLoading extends FindFriendsState {
  const FindFriendsLoading();
}

class FindFriendsLoaded extends FindFriendsState {
  const FindFriendsLoaded({
    required this.query,
    required this.results,
    this.busyId,
    this.feedbackKey,
  });

  final String query;
  final List<CommunityUser> results;
  final int? busyId;
  final String? feedbackKey;

  FindFriendsLoaded copyWith({
    List<CommunityUser>? results,
    int? busyId,
    String? feedbackKey,
    bool clearBusy = false,
    bool clearFeedback = false,
  }) {
    return FindFriendsLoaded(
      query: query,
      results: results ?? this.results,
      busyId: clearBusy ? null : (busyId ?? this.busyId),
      feedbackKey: clearFeedback ? null : (feedbackKey ?? this.feedbackKey),
    );
  }

  @override
  List<Object?> get props => [query, results, busyId, feedbackKey];
}

class FindFriendsError extends FindFriendsState {
  const FindFriendsError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class FindFriendsCubit extends Cubit<FindFriendsState> {
  FindFriendsCubit(this._repository) : super(const FindFriendsInitial());

  final FriendshipRepository _repository;

  Future<void> search(String query) async {
    final q = query.trim();
    if (q.isEmpty) {
      emit(const FindFriendsInitial());
      return;
    }
    emit(const FindFriendsLoading());
    try {
      final page = await _repository.searchUsers(query: q);
      emit(FindFriendsLoaded(query: q, results: page.items));
    } on ApiException catch (e) {
      emit(FindFriendsError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const FindFriendsError('common_error'));
    }
  }

  Future<void> sendRequest(int userId) async {
    final current = state;
    if (current is! FindFriendsLoaded) return;
    emit(current.copyWith(busyId: userId, clearFeedback: true));
    try {
      await _repository.sendRequest(userId);
      final updated = current.results
          .map((u) => u.id == userId
              ? u.copyWith(friendshipStatus: FriendshipStatus.pendingSent)
              : u)
          .toList();
      emit(current.copyWith(
        results: updated,
        clearBusy: true,
        feedbackKey: 'friends_request_sent',
      ));
    } on ApiException catch (e) {
      emit(current.copyWith(clearBusy: true, feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(clearBusy: true, feedbackKey: 'common_error'));
    }
  }

  Future<void> acceptFromSearch(int userId) async {
    final current = state;
    if (current is! FindFriendsLoaded) return;
    emit(current.copyWith(busyId: userId, clearFeedback: true));
    try {
      final requests = await _repository.getIncomingRequests();
      final match = requests.items.cast<FriendRequestItem?>().firstWhere(
            (r) => r?.sender?.id == userId,
            orElse: () => null,
          );
      if (match == null) {
        emit(current.copyWith(clearBusy: true, feedbackKey: 'common_error'));
        return;
      }
      await _repository.acceptRequest(match.id);
      final updated = current.results
          .map((u) => u.id == userId
              ? u.copyWith(friendshipStatus: FriendshipStatus.friends)
              : u)
          .toList();
      emit(current.copyWith(
        results: updated,
        clearBusy: true,
        feedbackKey: 'friends_accepted',
      ));
    } on ApiException catch (e) {
      emit(current.copyWith(clearBusy: true, feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(clearBusy: true, feedbackKey: 'common_error'));
    }
  }
}

sealed class FriendRequestsState extends Equatable {
  const FriendRequestsState();

  @override
  List<Object?> get props => [];
}

class FriendRequestsLoading extends FriendRequestsState {
  const FriendRequestsLoading();
}

class FriendRequestsLoaded extends FriendRequestsState {
  const FriendRequestsLoaded({required this.requests, this.feedbackKey});

  final List<FriendRequestItem> requests;
  final String? feedbackKey;

  FriendRequestsLoaded copyWith({
    List<FriendRequestItem>? requests,
    String? feedbackKey,
    bool clearFeedback = false,
  }) {
    return FriendRequestsLoaded(
      requests: requests ?? this.requests,
      feedbackKey: clearFeedback ? null : (feedbackKey ?? this.feedbackKey),
    );
  }

  @override
  List<Object?> get props => [requests, feedbackKey];
}

class FriendRequestsError extends FriendRequestsState {
  const FriendRequestsError(this.messageKey);

  final String messageKey;

  @override
  List<Object?> get props => [messageKey];
}

class FriendRequestsCubit extends Cubit<FriendRequestsState> {
  FriendRequestsCubit(this._repository) : super(const FriendRequestsLoading());

  final FriendshipRepository _repository;

  Future<void> load() async {
    emit(const FriendRequestsLoading());
    try {
      final page = await _repository.getIncomingRequests();
      emit(FriendRequestsLoaded(requests: page.items));
    } on ApiException catch (e) {
      emit(FriendRequestsError(e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(const FriendRequestsError('common_error'));
    }
  }

  Future<void> accept(int requestId) async {
    final current = state;
    if (current is! FriendRequestsLoaded) return;
    try {
      await _repository.acceptRequest(requestId);
      emit(current.copyWith(
        requests: current.requests.where((r) => r.id != requestId).toList(),
        feedbackKey: 'friends_accepted',
      ));
    } on ApiException catch (e) {
      emit(current.copyWith(feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(feedbackKey: 'common_error'));
    }
  }

  Future<void> reject(int requestId) async {
    final current = state;
    if (current is! FriendRequestsLoaded) return;
    try {
      await _repository.rejectRequest(requestId);
      emit(current.copyWith(
        requests: current.requests.where((r) => r.id != requestId).toList(),
        feedbackKey: 'friends_rejected',
      ));
    } on ApiException catch (e) {
      emit(current.copyWith(feedbackKey: e.messageKey ?? 'common_error'));
    } catch (_) {
      emit(current.copyWith(feedbackKey: 'common_error'));
    }
  }
}
