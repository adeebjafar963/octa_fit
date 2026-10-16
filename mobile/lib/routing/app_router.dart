import 'dart:async';

import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/features/feature_flags.dart';
import 'package:octa_fit/presentation/admin/admin_community_screen.dart';
import 'package:octa_fit/presentation/admin/admin_dashboard_screen.dart';
import 'package:octa_fit/presentation/admin/admin_orders_screen.dart';
import 'package:octa_fit/presentation/admin/admin_products_screen.dart';
import 'package:octa_fit/presentation/admin/admin_settings_screen.dart';
import 'package:octa_fit/presentation/admin/admin_training_screen.dart';
import 'package:octa_fit/presentation/admin/admin_users_screen.dart';
import 'package:octa_fit/presentation/ai_hub/ai_hub_screen.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';
import 'package:octa_fit/presentation/auth/login_screen.dart';
import 'package:octa_fit/presentation/auth/register_screen.dart';
import 'package:octa_fit/presentation/coach/coach_chat_screen.dart';
import 'package:octa_fit/presentation/coach/coach_conversations_screen.dart';
import 'package:octa_fit/presentation/community/community_feed_screen.dart';
import 'package:octa_fit/presentation/community/create_post_screen.dart';
import 'package:octa_fit/presentation/community/create_story_screen.dart';
import 'package:octa_fit/presentation/community/friends_screens.dart';
import 'package:octa_fit/presentation/community/post_detail_screen.dart';
import 'package:octa_fit/presentation/home/home_screen.dart';
import 'package:octa_fit/presentation/hydration/hydration_screen.dart';
import 'package:octa_fit/presentation/physio/physio_assessment_detail_screen.dart';
import 'package:octa_fit/presentation/physio/physio_assessments_screen.dart';
import 'package:octa_fit/presentation/physio/physio_new_assessment_screen.dart';
import 'package:octa_fit/presentation/motion/motion_home_screen.dart';
import 'package:octa_fit/presentation/motion/motion_result_screen.dart';
import 'package:octa_fit/domain/entities/motion.dart';
import 'package:octa_fit/presentation/profile/change_password_screen.dart';
import 'package:octa_fit/presentation/profile/edit_profile_screen.dart';
import 'package:octa_fit/presentation/profile/onboarding/profile_onboarding_screen.dart';
import 'package:octa_fit/presentation/profile/profile_screen.dart';
import 'package:octa_fit/presentation/settings/coach_memory_screen.dart';
import 'package:octa_fit/presentation/settings/settings_screen.dart';
import 'package:octa_fit/presentation/shell/main_shell.dart';
import 'package:octa_fit/presentation/splash/splash_screen.dart';
import 'package:octa_fit/presentation/store/cart_screen.dart';
import 'package:octa_fit/presentation/store/checkout_screen.dart';
import 'package:octa_fit/presentation/store/orders_screen.dart';
import 'package:octa_fit/presentation/store/product_details_screen.dart';
import 'package:octa_fit/presentation/store/store_home_screen.dart';
import 'package:octa_fit/presentation/training/exercise_details_screen.dart';
import 'package:octa_fit/presentation/training/training_categories_screen.dart';
import 'package:octa_fit/presentation/training/training_home_screen.dart';
import 'package:octa_fit/presentation/training/training_levels_screen.dart';
import 'package:octa_fit/presentation/training/training_list_screen.dart';
import 'package:octa_fit/presentation/training/workout_history_screen.dart';
import 'package:octa_fit/presentation/training/workout_session_screen.dart';

class GoRouterRefreshStream extends ChangeNotifier {
  GoRouterRefreshStream(Stream<dynamic> stream) {
    _subscription = stream.asBroadcastStream().listen((_) => notifyListeners());
  }

  late final StreamSubscription<dynamic> _subscription;

  @override
  void dispose() {
    _subscription.cancel();
    super.dispose();
  }
}

bool _isAuthenticatedForRouting(AuthState authState) {
  if (authState is AuthAuthenticated) return true;
  if (authState is AuthError && authState.keepsSession) return true;
  return false;
}

AuthUserSession? _sessionFrom(AuthState authState) {
  if (authState is AuthAuthenticated) {
    return AuthUserSession(
      isAdmin: authState.user.isAdmin,
      profileComplete: authState.user.profileComplete,
    );
  }
  if (authState is AuthError && authState.sessionUser != null) {
    return AuthUserSession(
      isAdmin: authState.sessionUser!.isAdmin,
      profileComplete: authState.sessionUser!.profileComplete,
    );
  }
  return null;
}

class AuthUserSession {
  const AuthUserSession({
    required this.isAdmin,
    required this.profileComplete,
  });

  final bool isAdmin;
  final bool profileComplete;
}

/// Routes allowed while the authenticated user still needs to finish onboarding.
bool _isOnboardingAllowed(String loc) {
  return loc == '/profile/onboarding' ||
      loc == '/settings' ||
      loc == '/settings/coach-memory' ||
      loc == '/profile/password';
}

GoRouter createAppRouter(
  AuthCubit authCubit, {
  FeatureFlagsCubit? featureFlags,
}) {
  final refresh = featureFlags == null
      ? GoRouterRefreshStream(authCubit.stream)
      : _MultiRefresh([authCubit.stream, featureFlags.stream]);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refresh,
    errorBuilder: (context, state) => Scaffold(
      appBar: AppBar(title: Text('app_name'.tr())),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('error_not_found'.tr(), textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => context.go('/home'),
                child: Text('nav_home'.tr()),
              ),
            ],
          ),
        ),
      ),
    ),
    redirect: (context, state) {
      final authState = authCubit.state;
      final loc = state.matchedLocation;

      final isSplash = loc == '/splash';
      final isAuthRoute = loc == '/login' || loc == '/register';
      final isPublicRoute = isAuthRoute || loc == '/settings';

      if (authState is AuthInitial) {
        return isSplash ? null : '/splash';
      }

      if (authState is AuthLoading && isSplash) {
        return null;
      }

      if (_isAuthenticatedForRouting(authState)) {
        final session = _sessionFrom(authState)!;
        final homeOrOnboarding =
            session.profileComplete ? '/home' : '/profile/onboarding';

        if (isSplash || isAuthRoute) return homeOrOnboarding;

        if (!session.profileComplete) {
          if (_isOnboardingAllowed(loc)) return null;
          return '/profile/onboarding';
        }

        if (loc == '/profile/onboarding') return '/home';

        if (loc.startsWith('/admin') && !session.isAdmin) {
          return '/home';
        }

        final flags = featureFlags?.state ?? FeatureFlags.allEnabled;
        if (loc.startsWith('/coach') && !flags.coachEnabled) return '/home';
        if (loc.startsWith('/physio') && !flags.physioEnabled) return '/home';
        if (loc.startsWith('/motion') && !flags.motionEnabled) return '/home';
        if (loc == '/ai' &&
            !flags.coachEnabled &&
            !flags.physioEnabled &&
            !flags.motionEnabled) {
          return '/home';
        }

        return null;
      }

      // Login/register credential errors stay on public auth screens.
      if (authState is AuthUnauthenticated ||
          (authState is AuthError && !authState.keepsSession)) {
        if (isSplash) return '/login';
        if (!isPublicRoute) return '/login';
        return null;
      }

      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (context, state) => const SplashScreen()),
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(path: '/register', builder: (context, state) => const RegisterScreen()),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            MainShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(path: '/home', builder: (context, state) => const HomeScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/training',
                builder: (context, state) => const TrainingHomeScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/ai',
                builder: (context, state) => const AiHubScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/store',
                builder: (context, state) => const StoreHomeScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/community',
                builder: (context, state) => const CommunityFeedScreen(),
              ),
            ],
          ),
        ],
      ),
      GoRoute(path: '/profile', builder: (context, state) => const ProfileScreen()),
      GoRoute(
        path: '/profile/onboarding',
        builder: (context, state) => const ProfileOnboardingScreen(),
      ),
      GoRoute(path: '/profile/edit', builder: (context, state) => const EditProfileScreen()),
      GoRoute(
        path: '/profile/password',
        builder: (context, state) => const ChangePasswordScreen(),
      ),
      GoRoute(path: '/settings', builder: (context, state) => const SettingsScreen()),
      GoRoute(
        path: '/settings/coach-memory',
        builder: (context, state) => const CoachMemoryScreen(),
      ),
      GoRoute(
        path: '/training/history',
        builder: (context, state) => const WorkoutHistoryScreen(),
      ),
      GoRoute(
        path: '/training/:type/levels',
        builder: (context, state) {
          final type = state.pathParameters['type'] ?? 'bodybuilding';
          return TrainingLevelsScreen(type: type);
        },
      ),
      GoRoute(
        path: '/training/:type/levels/:level/categories',
        builder: (context, state) {
          final type = state.pathParameters['type'] ?? 'bodybuilding';
          final level = state.pathParameters['level'] ?? 'beginner';
          return TrainingCategoriesScreen(type: type, level: level);
        },
      ),
      GoRoute(
        path: '/training/:type/levels/:level/categories/:categoryId',
        builder: (context, state) {
          final type = state.pathParameters['type'] ?? 'bodybuilding';
          final level = state.pathParameters['level'];
          final categoryId = int.tryParse(state.pathParameters['categoryId'] ?? '');
          return TrainingListScreen(
            type: type,
            level: level,
            categoryId: categoryId,
          );
        },
      ),
      GoRoute(
        path: '/training/exercises/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return ExerciseDetailsScreen(exerciseId: id);
        },
      ),
      GoRoute(
        path: '/training/workout/start',
        builder: (context, state) {
          final programId = int.tryParse(state.uri.queryParameters['programId'] ?? '');
          final idsRaw = state.uri.queryParameters['exerciseIds'];
          final exerciseIds = idsRaw == null || idsRaw.isEmpty
              ? null
              : idsRaw
                  .split(',')
                  .map((e) => int.tryParse(e.trim()))
                  .whereType<int>()
                  .toList();
          return WorkoutSessionScreen(
            programId: programId,
            exerciseIds: exerciseIds,
          );
        },
      ),
      GoRoute(
        path: '/training/workout/:sessionId',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['sessionId'] ?? '') ?? 0;
          return WorkoutSessionScreen(sessionId: id);
        },
      ),
      GoRoute(path: '/store/cart', builder: (context, state) => const CartScreen()),
      GoRoute(path: '/store/checkout', builder: (context, state) => const CheckoutScreen()),
      GoRoute(path: '/store/orders', builder: (context, state) => const OrdersScreen()),
      GoRoute(
        path: '/store/product/:id',
        builder: (context, state) {
          final id = state.pathParameters['id'] ?? '';
          return ProductDetailsScreen(productId: id);
        },
      ),
      GoRoute(
        path: '/store/orders/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return OrderDetailsScreen(orderId: id);
        },
      ),
      GoRoute(path: '/community/create', builder: (context, state) => const CreatePostScreen()),
      GoRoute(
        path: '/community/story/create',
        builder: (context, state) => const CreateStoryScreen(),
      ),
      GoRoute(path: '/community/friends', builder: (context, state) => const FriendsScreen()),
      GoRoute(
        path: '/community/friends/find',
        builder: (context, state) => const FindFriendsScreen(),
      ),
      GoRoute(
        path: '/community/friends/requests',
        builder: (context, state) => const FriendRequestsScreen(),
      ),
      GoRoute(
        path: '/community/users/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return CommunityUserProfileScreen(userId: id);
        },
      ),
      GoRoute(
        path: '/community/post/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return PostDetailScreen(postId: id);
        },
      ),
      GoRoute(
        path: '/community/post/:id/edit',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return EditPostScreen(postId: id);
        },
      ),
      GoRoute(path: '/coach', builder: (context, state) => const CoachConversationsScreen()),
      GoRoute(
        path: '/coach/chat/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return CoachChatScreen(conversationId: id);
        },
      ),
      GoRoute(path: '/physio', builder: (context, state) => const PhysioAssessmentsScreen()),
      GoRoute(
        path: '/physio/new',
        builder: (context, state) => const PhysioNewAssessmentScreen(),
      ),
      GoRoute(
        path: '/physio/assessment/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return PhysioAssessmentDetailScreen(assessmentId: id);
        },
      ),
      GoRoute(path: '/motion', builder: (context, state) => const MotionHomeScreen()),
      GoRoute(
        path: '/hydration',
        builder: (context, state) => const HydrationScreen(),
      ),
      GoRoute(
        path: '/motion/result',
        builder: (context, state) {
          final extra = state.extra;
          if (extra is MotionAnalysisResult) {
            return MotionResultScreen(result: extra);
          }
          if (extra is Map) {
            final result = extra['result'];
            final videoPath = extra['videoPath'];
            if (result is MotionAnalysisResult) {
              return MotionResultScreen(
                result: result,
                videoPath: videoPath is String ? videoPath : null,
              );
            }
          }
          return const MotionHomeScreen();
        },
      ),
      GoRoute(path: '/admin', builder: (context, state) => const AdminDashboardScreen()),
      GoRoute(path: '/admin/users', builder: (context, state) => const AdminUsersScreen()),
      GoRoute(path: '/admin/training', builder: (context, state) => const AdminTrainingScreen()),
      GoRoute(path: '/admin/products', builder: (context, state) => const AdminProductsScreen()),
      GoRoute(path: '/admin/orders', builder: (context, state) => const AdminOrdersScreen()),
      GoRoute(
        path: '/admin/orders/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return AdminOrderDetailScreen(orderId: id);
        },
      ),
      GoRoute(path: '/admin/community', builder: (context, state) => const AdminCommunityScreen()),
      GoRoute(path: '/admin/settings', builder: (context, state) => const AdminSettingsScreen()),
    ],
  );
}

class _MultiRefresh extends ChangeNotifier {
  _MultiRefresh(List<Stream<dynamic>> streams) {
    _subs = streams
        .map((s) => s.asBroadcastStream().listen((_) => notifyListeners()))
        .toList();
  }

  late final List<StreamSubscription<dynamic>> _subs;

  @override
  void dispose() {
    for (final s in _subs) {
      s.cancel();
    }
    super.dispose();
  }
}
