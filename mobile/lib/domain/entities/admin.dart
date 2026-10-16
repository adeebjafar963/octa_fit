import 'package:equatable/equatable.dart';

class AdminDashboardStats extends Equatable {
  const AdminDashboardStats({
    required this.totalUsers,
    required this.totalAdmins,
    required this.totalExercises,
    required this.totalProducts,
    required this.activeProducts,
    required this.totalOrders,
    required this.totalPosts,
    required this.totalCoachConversations,
    required this.totalPhysioAssessments,
    this.hydrationActiveUsers = 0,
    this.hydrationIntakes7d = 0,
  });

  factory AdminDashboardStats.fromJson(Map<String, dynamic> json) {
    return AdminDashboardStats(
      totalUsers: json['total_users'] as int? ?? 0,
      totalAdmins: json['total_admins'] as int? ?? 0,
      totalExercises: json['total_exercises'] as int? ?? 0,
      totalProducts: json['total_products'] as int? ?? 0,
      activeProducts: json['active_products'] as int? ?? 0,
      totalOrders: json['total_orders'] as int? ?? 0,
      totalPosts: json['total_posts'] as int? ?? 0,
      totalCoachConversations: json['total_coach_conversations'] as int? ?? 0,
      totalPhysioAssessments: json['total_physio_assessments'] as int? ?? 0,
      hydrationActiveUsers: json['hydration_active_users'] as int? ?? 0,
      hydrationIntakes7d: json['hydration_intakes_7d'] as int? ?? 0,
    );
  }

  final int totalUsers;
  final int totalAdmins;
  final int totalExercises;
  final int totalProducts;
  final int activeProducts;
  final int totalOrders;
  final int totalPosts;
  final int totalCoachConversations;
  final int totalPhysioAssessments;
  final int hydrationActiveUsers;
  final int hydrationIntakes7d;

  @override
  List<Object?> get props => [
        totalUsers,
        totalAdmins,
        totalExercises,
        totalProducts,
        activeProducts,
        totalOrders,
        totalPosts,
        totalCoachConversations,
        totalPhysioAssessments,
        hydrationActiveUsers,
        hydrationIntakes7d,
      ];
}

class AdminUserItem extends Equatable {
  const AdminUserItem({
    required this.id,
    required this.name,
    required this.email,
    required this.isActive,
    this.roleName,
    this.roleDisplayName,
  });

  factory AdminUserItem.fromJson(Map<String, dynamic> json) {
    final role = json['role'] as Map<String, dynamic>?;
    return AdminUserItem(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      isActive: json['is_active'] as bool? ?? true,
      roleName: role?['name'] as String?,
      roleDisplayName: role?['display_name'] as String?,
    );
  }

  final int id;
  final String name;
  final String email;
  final bool isActive;
  final String? roleName;
  final String? roleDisplayName;

  bool get isAdmin => roleName == 'admin';

  @override
  List<Object?> get props => [id, name, email, isActive, roleName];
}

class AdminSettingItem extends Equatable {
  const AdminSettingItem({
    required this.key,
    this.value,
    required this.group,
  });

  factory AdminSettingItem.fromJson(Map<String, dynamic> json) {
    return AdminSettingItem(
      key: json['key'] as String,
      value: json['value'] as String?,
      group: json['group'] as String? ?? 'general',
    );
  }

  final String key;
  final String? value;
  final String group;

  @override
  List<Object?> get props => [key, value, group];
}

class AdminPaged<T> extends Equatable {
  const AdminPaged({
    required this.items,
    required this.total,
    required this.currentPage,
    required this.lastPage,
  });

  final List<T> items;
  final int total;
  final int currentPage;
  final int lastPage;

  @override
  List<Object?> get props => [items, total, currentPage, lastPage];
}
