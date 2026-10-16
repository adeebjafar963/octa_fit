import 'dart:math' as math;

import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/core/theme/app_theme.dart';
import 'package:octa_fit/presentation/auth/cubit/auth_cubit.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with TickerProviderStateMixin {
  late final AnimationController _enter = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1400),
  )..forward();

  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2400),
  )..repeat(reverse: true);

  late final AnimationController _orbit = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 9000),
  )..repeat();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AuthCubit>().bootstrap();
    });
  }

  @override
  void dispose() {
    _enter.dispose();
    _pulse.dispose();
    _orbit.dispose();
    super.dispose();
  }

  void _onAuth(BuildContext context, AuthState state) {
    if (state is AuthAuthenticated) {
      final dest =
          state.user.profileComplete ? '/home' : '/profile/onboarding';
      context.go(dest);
    } else if (state is AuthUnauthenticated) {
      context.go('/login');
    }
    // AuthError with session (profile failures) — do not navigate away / logout.
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<AuthCubit, AuthState>(
      listener: _onAuth,
      child: Scaffold(
        body: Stack(
          children: [
            const Positioned.fill(child: _SplashBackdrop()),
            AnimatedBuilder(
              animation: Listenable.merge([_orbit, _pulse]),
              builder: (context, _) {
                return Positioned.fill(
                  child: CustomPaint(
                    painter: _OrbitPainter(
                      progress: _orbit.value,
                      pulse: _pulse.value,
                    ),
                  ),
                );
              },
            ),
            Center(
              child: FadeTransition(
                opacity: CurvedAnimation(
                  parent: _enter,
                  curve: const Interval(0.0, 0.55, curve: Curves.easeOutCubic),
                ),
                child: ScaleTransition(
                  scale: Tween(begin: 0.88, end: 1.0).animate(
                    CurvedAnimation(
                      parent: _enter,
                      curve: const Interval(0.0, 0.7, curve: Curves.easeOutBack),
                    ),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      AnimatedBuilder(
                        animation: _pulse,
                        builder: (context, child) {
                          final glow = 0.18 + (_pulse.value * 0.14);
                          return Container(
                            width: 96,
                            height: 96,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(AppRadii.xl),
                              gradient: LinearGradient(
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                                colors: [
                                  AppColors.elevated,
                                  AppColors.primaryTint,
                                ],
                              ),
                              border: Border.all(
                                color: AppColors.primary.withValues(alpha: 0.55),
                                width: 1.4,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: AppColors.primary.withValues(alpha: glow),
                                  blurRadius: 28,
                                  spreadRadius: 2,
                                ),
                                ...AppShadows.lift,
                              ],
                            ),
                            child: child,
                          );
                        },
                        child: const Icon(
                          Icons.hexagon_outlined,
                          color: AppColors.primaryHover,
                          size: 44,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      Text(
                        AppBrand.name,
                        style: Theme.of(context).textTheme.displayMedium?.copyWith(
                              letterSpacing: 1.4,
                            ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      FadeTransition(
                        opacity: CurvedAnimation(
                          parent: _enter,
                          curve: const Interval(0.35, 0.9, curve: Curves.easeOut),
                        ),
                        child: Text(
                          'splash_tagline'.tr(),
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                color: AppColors.textSecondary,
                              ),
                        ),
                      ),
                      const SizedBox(height: AppSpacing.xl),
                      SizedBox(
                        width: 120,
                        child: AnimatedBuilder(
                          animation: _pulse,
                          builder: (context, _) {
                            return ClipRRect(
                              borderRadius: BorderRadius.circular(AppRadii.full),
                              child: LinearProgressIndicator(
                                value: 0.35 + (_pulse.value * 0.45),
                                minHeight: 3,
                                backgroundColor: AppColors.border,
                                color: AppColors.primaryHover,
                              ),
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SplashBackdrop extends StatelessWidget {
  const _SplashBackdrop();

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.background,
            AppColors.elevated,
            AppColors.primaryTint.withValues(alpha: 0.55),
            AppColors.background,
          ],
          stops: const [0.0, 0.35, 0.7, 1.0],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            top: -100,
            right: -60,
            child: Container(
              width: 280,
              height: 280,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    AppColors.primary.withValues(alpha: 0.08),
                    Colors.transparent,
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            bottom: -80,
            left: -50,
            child: Container(
              width: 240,
              height: 240,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    AppColors.primary.withValues(alpha: 0.05),
                    Colors.transparent,
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OrbitPainter extends CustomPainter {
  _OrbitPainter({required this.progress, required this.pulse});

  final double progress;
  final double pulse;

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2 - 24);
    final radius = math.min(size.width, size.height) * 0.28;

    final ring = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1
      ..color = AppColors.primary.withValues(alpha: 0.12 + pulse * 0.06);
    canvas.drawCircle(center, radius, ring);
    canvas.drawCircle(center, radius * 1.35, ring);

    final angle = progress * math.pi * 2;
    final dot = Offset(
      center.dx + math.cos(angle) * radius,
      center.dy + math.sin(angle) * radius,
    );
    canvas.drawCircle(
      dot,
      3.5,
      Paint()..color = AppColors.primaryHover.withValues(alpha: 0.85),
    );

    final angle2 = angle + math.pi * 0.7;
    final dot2 = Offset(
      center.dx + math.cos(angle2) * radius * 1.35,
      center.dy + math.sin(angle2) * radius * 1.35,
    );
    canvas.drawCircle(
      dot2,
      2.5,
      Paint()..color = AppColors.primary.withValues(alpha: 0.7),
    );
  }

  @override
  bool shouldRepaint(covariant _OrbitPainter oldDelegate) =>
      oldDelegate.progress != progress || oldDelegate.pulse != pulse;
}
