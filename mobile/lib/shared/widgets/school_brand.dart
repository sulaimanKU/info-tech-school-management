import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../l10n/strings.dart';

abstract final class BrandAssets {
  static const logo = 'assets/1.jpeg';
  static const campus = 'assets/2.jpeg';
}

class SchoolLogo extends StatelessWidget {
  const SchoolLogo({super.key, this.size = 64});
  final double size;
  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    padding: EdgeInsets.all(size >= 80 ? AppSpace.sm : AppSpace.xs),
    decoration: BoxDecoration(
      color: AppColors.white,
      borderRadius: BorderRadius.circular(AppRadius.input),
      border: Border.all(
        color: Theme.of(context).colorScheme.outlineVariant
            .withValues(alpha: .45),
      ),
    ),
    child: Image.asset(
      BrandAssets.logo,
      fit: BoxFit.contain,
      cacheWidth: (size * MediaQuery.devicePixelRatioOf(context)).ceil(),
      semanticLabel: Strings.of(context).text('officialLogo'),
    ),
  );
}

class SchoolBrand extends StatelessWidget {
  const SchoolBrand({super.key});
  @override
  Widget build(BuildContext context) => Row(
    children: [
      const SchoolLogo(size: 44),
      const SizedBox(width: AppSpace.md),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'INFO-TECH',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleMedium
                  ?.copyWith(letterSpacing: 1.1),
            ),
            Text(
              Strings.of(context).text('schoolManagement'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

class CampusHero extends StatelessWidget {
  const CampusHero({super.key, this.expanded = false});
  final bool expanded;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final text = Theme.of(context).textTheme;
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.card),
      child: Stack(
        children: [
          Positioned.fill(
            child: Image.asset(
              BrandAssets.campus,
              cacheWidth: expanded ? 1400 : 900,
              fit: BoxFit.cover,
              alignment: Alignment.center,
              excludeFromSemantics: true,
            ),
          ),
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(gradient: AppColors.heroGradient),
            ),
          ),
          Padding(
            padding: EdgeInsets.all(expanded ? AppSpace.xxl : AppSpace.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    SchoolLogo(size: expanded ? 80 : 64),
                    const SizedBox(width: AppSpace.lg),
                    Expanded(
                      child: Text(
                        s.text('secureCampusAccess'),
                        style: text.titleMedium?.copyWith(
                          color: AppColors.white,
                        ),
                      ),
                    ),
                  ],
                ),
                SizedBox(height: expanded ? 120 : AppSpace.lg),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'INFO-TECH',
                      style:
                          (expanded ? text.headlineLarge : text.headlineSmall)
                              ?.copyWith(
                                color: AppColors.white,
                                letterSpacing: 1,
                              ),
                    ),
                    const SizedBox(height: AppSpace.sm),
                    Text(
                      s.text('schoolName'),
                      style: text.titleMedium?.copyWith(color: AppColors.white),
                    ),
                    const SizedBox(height: AppSpace.lg),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(
                          Icons.location_on_outlined,
                          color: AppColors.mint,
                          size: 18,
                        ),
                        const SizedBox(width: AppSpace.xs),
                        Expanded(
                          child: Text(
                            s.text('campus'),
                            style: text.bodyMedium?.copyWith(
                              color: AppColors.white,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (expanded) ...[
                      const SizedBox(height: AppSpace.xl),
                      Text(
                        s.text('heroMessage'),
                        style: text.bodyLarge?.copyWith(color: AppColors.white),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
