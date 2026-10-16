import 'package:equatable/equatable.dart';

enum FriendshipStatus {
  none,
  pendingSent,
  pendingReceived,
  friends;

  static FriendshipStatus fromApi(String? value) {
    return switch (value) {
      'pending_sent' => FriendshipStatus.pendingSent,
      'pending_received' => FriendshipStatus.pendingReceived,
      'friends' => FriendshipStatus.friends,
      _ => FriendshipStatus.none,
    };
  }

  String get apiValue => switch (this) {
        FriendshipStatus.none => 'none',
        FriendshipStatus.pendingSent => 'pending_sent',
        FriendshipStatus.pendingReceived => 'pending_received',
        FriendshipStatus.friends => 'friends',
      };
}

class CommunityUser extends Equatable {
  const CommunityUser({
    required this.id,
    required this.name,
    required this.friendshipStatus,
    this.avatarUrl,
  });

  factory CommunityUser.fromJson(Map<String, dynamic> json) {
    return CommunityUser(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      avatarUrl: json['avatar_url'] as String?,
      friendshipStatus: FriendshipStatus.fromApi(json['friendship_status'] as String?),
    );
  }

  final int id;
  final String name;
  final String? avatarUrl;
  final FriendshipStatus friendshipStatus;

  CommunityUser copyWith({FriendshipStatus? friendshipStatus}) {
    return CommunityUser(
      id: id,
      name: name,
      avatarUrl: avatarUrl,
      friendshipStatus: friendshipStatus ?? this.friendshipStatus,
    );
  }

  @override
  List<Object?> get props => [id, name, avatarUrl, friendshipStatus];
}

class FriendRequestItem extends Equatable {
  const FriendRequestItem({
    required this.id,
    required this.status,
    this.sender,
    this.createdAt,
  });

  factory FriendRequestItem.fromJson(Map<String, dynamic> json) {
    return FriendRequestItem(
      id: json['id'] as int,
      status: json['status'] as String? ?? 'pending',
      sender: json['sender'] is Map<String, dynamic>
          ? CommunityUser.fromJson(json['sender'] as Map<String, dynamic>)
          : null,
      createdAt: json['created_at'] as String?,
    );
  }

  final int id;
  final String status;
  final CommunityUser? sender;
  final String? createdAt;

  @override
  List<Object?> get props => [id, status, sender];
}

class CommunityUserPage extends Equatable {
  const CommunityUserPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
    this.pendingIncomingCount = 0,
  });

  final List<CommunityUser> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;
  final int pendingIncomingCount;

  bool get hasMore => currentPage < lastPage;

  @override
  List<Object?> get props => [items, currentPage, total, pendingIncomingCount];
}

class FriendRequestPage extends Equatable {
  const FriendRequestPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
    this.pendingIncomingCount = 0,
  });

  final List<FriendRequestItem> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;
  final int pendingIncomingCount;

  @override
  List<Object?> get props => [items, currentPage, total, pendingIncomingCount];
}
