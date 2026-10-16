import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:octa_fit/core/brand/app_brand.dart';
import 'package:octa_fit/core/theme/app_theme.dart';

/// Adaptive shell: NavigationRail on desktop, custom bottom bar with center AI on compact.
class MainShell extends StatelessWidget {
  const MainShell({required this.navigationShell, super.key});

  final StatefulNavigationShell navigationShell;

  void _onTap(int index) {
    navigationShell.goBranch(
      index,
      initialLocation: index == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) {
    final useSidebar = AppBreakpoints.useSidebar(context);

    // Order must match StatefulShellBranch indices in app_router.dart
    // Home | Training | AI | Store | Community
    final destinations = [
      _Dest(Icons.home_outlined, Icons.home_rounded, 'nav_home', 0),
      _Dest(
        Icons.fitness_center_outlined,
        Icons.fitness_center,
        'nav_training',
        1,
      ),
      _Dest(
        Icons.auto_awesome_outlined,
        Icons.auto_awesome,
        'nav_ai',
        2,
        isAi: true,
      ),
      _Dest(Icons.storefront_outlined, Icons.storefront, 'nav_store', 3),
      _Dest(Icons.groups_outlined, Icons.groups_rounded, 'nav_community', 4),
    ];

    if (useSidebar) {
      return Scaffold(
        body: Row(
          children: [
            NavigationRail(
              selectedIndex: navigationShell.currentIndex,
              onDestinationSelected: _onTap,
              extended:
                  MediaQuery.sizeOf(context).width >= AppBreakpoints.expanded,
              labelType:
                  MediaQuery.sizeOf(context).width >= AppBreakpoints.expanded
                  ? NavigationRailLabelType.none
                  : NavigationRailLabelType.all,
              leading: Padding(
                padding: const EdgeInsets.only(top: 12, bottom: 24),
                child: Column(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(AppRadii.md),
                        color: AppColors.primaryTint,
                        border: Border.all(
                          color: AppColors.primary.withValues(alpha: 0.4),
                        ),
                      ),
                      child: const Icon(
                        Icons.hexagon_outlined,
                        color: AppColors.primaryHover,
                        size: 22,
                      ),
                    ),
                    if (MediaQuery.sizeOf(context).width >=
                        AppBreakpoints.expanded) ...[
                      const SizedBox(height: 10),
                      Text(
                        AppBrand.name,
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(
                              color: AppColors.primaryHover,
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                    ],
                  ],
                ),
              ),
              destinations: [
                for (final d in destinations)
                  NavigationRailDestination(
                    icon: Icon(d.icon),
                    selectedIcon: Icon(
                      d.selectedIcon,
                      color: d.isAi ? AppColors.ai : AppColors.primary,
                    ),
                    label: Text(
                      d.labelKey.tr(),
                      style: d.isAi &&
                              navigationShell.currentIndex == d.branchIndex
                          ? Theme.of(context).textTheme.labelMedium?.copyWith(
                              color: AppColors.textPrimary,
                              fontWeight: FontWeight.w700,
                            )
                          : null,
                    ),
                  ),
              ],
            ),
            Expanded(child: navigationShell),
          ],
        ),
      );
    }

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: _OctaBottomBar(
        currentIndex: navigationShell.currentIndex,
        destinations: destinations,
        onTap: _onTap,
      ),
    );
  }
}

class _OctaBottomBar extends StatelessWidget {
  const _OctaBottomBar({
    required this.currentIndex,
    required this.destinations,
    required this.onTap,
  });

  final int currentIndex;
  final List<_Dest> destinations;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.navigation,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 72,
          child: Row(
            children: [
              for (final d in destinations)
                Expanded(
                  child: _NavItem(
                    icon: currentIndex == d.branchIndex
                        ? d.selectedIcon
                        : d.icon,
                    label: d.labelKey.tr(),
                    selected: currentIndex == d.branchIndex,
                    selectedIconColor:
                        d.isAi ? AppColors.ai : AppColors.primary,
                    selectedLabelColor: d.isAi
                        ? AppColors.textPrimary
                        : AppColors.primary,
                    indicatorColor:
                        d.isAi ? AppColors.aiTint : AppColors.primaryTint,
                    onTap: () => onTap(d.branchIndex),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
    this.selectedIconColor,
    this.selectedLabelColor,
    this.indicatorColor,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final Color? selectedIconColor;
  final Color? selectedLabelColor;
  final Color? indicatorColor;

  @override
  Widget build(BuildContext context) {
    final iconColor = selected
        ? (selectedIconColor ?? AppColors.primary)
        : AppColors.navInactive;
    final labelColor = selected
        ? (selectedLabelColor ?? selectedIconColor ?? AppColors.primary)
        : AppColors.navInactive;
    return Semantics(
      button: true,
      selected: selected,
      label: label,
      child: InkWell(
        onTap: onTap,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            AnimatedContainer(
              duration: AppMotion.fast,
              curve: AppMotion.ease,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              decoration: BoxDecoration(
                color: selected
                    ? (indicatorColor ?? AppColors.primaryTint)
                    : Colors.transparent,
                borderRadius: BorderRadius.circular(AppRadii.full),
              ),
              child: Icon(icon, color: iconColor, size: 24),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: labelColor,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Dest {
  const _Dest(
    this.icon,
    this.selectedIcon,
    this.labelKey,
    this.branchIndex, {
    this.isAi = false,
  });
  final IconData icon;
  final IconData selectedIcon;
  final String labelKey;
  final int branchIndex;
  final bool isAi;
}
