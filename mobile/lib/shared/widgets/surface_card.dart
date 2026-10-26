import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';

class SurfaceCard extends StatelessWidget {
  const SurfaceCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpace.xl),
    this.accent,
  });
  final Color? accent;
  final Widget child;
  final EdgeInsetsGeometry padding;
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(AppRadius.card),
      boxShadow: [
        BoxShadow(
          color: AppColors.navy.withValues(
            alpha: Theme.of(context).brightness == Brightness.dark ? .03 : .04,
          ),
          blurRadius: 16,
          offset: const Offset(0, 4),
        ),
      ],
    ),
    child: Card(
      clipBehavior: Clip.antiAlias,
      color: accent == null
          ? null
          : Color.alphaBlend(
              accent!.withValues(
                alpha: Theme.of(context).brightness == Brightness.dark
                    ? .07
                    : .035,
              ),
              Theme.of(context).colorScheme.surface,
            ),
      shape: accent == null
          ? null
          : RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.card),
              side: BorderSide(color: accent!.withValues(alpha: .22)),
            ),
      child: Padding(padding: padding, child: child),
    ),
  );
}

class StatusBanner extends StatelessWidget {
  const StatusBanner({
    super.key,
    required this.message,
    this.kind = StatusKind.info,
  });
  final String message;
  final StatusKind kind;
  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final color = switch (kind) {
      StatusKind.error => scheme.error,
      StatusKind.success =>
        Theme.of(context).brightness == Brightness.dark
            ? AppColors.mint
            : AppColors.success,
      StatusKind.info => scheme.primary,
    };
    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.all(AppSpace.lg),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .08),
          borderRadius: BorderRadius.circular(AppRadius.input),
          border: Border.all(color: color.withValues(alpha: .2)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              switch (kind) {
                StatusKind.error => Icons.error_outline,
                StatusKind.success => Icons.check_circle_outline,
                StatusKind.info => Icons.info_outline,
              },
              color: color,
              size: 20,
            ),
            const SizedBox(width: AppSpace.md),
            Expanded(
              child: Text(
                message,
                style: Theme.of(context).textTheme.bodyMedium
                    ?.copyWith(color: color),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

enum StatusKind { info, success, error }

class AppBadge extends StatelessWidget {
  const AppBadge({
    super.key,
    required this.label,
    this.onDark = false,
    this.icon,
    this.status,
  });
  final String label;
  final bool onDark;
  final IconData? icon;
  final String? status;
  @override
  Widget build(BuildContext context) {
    final color = onDark
        ? AppColors.mint
        : status == null
        ? Theme.of(context).colorScheme.primary
        : AppPalette.of(context).tone(AppStatus.tone(status));
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpace.md,
        vertical: AppSpace.sm,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .1),
        borderRadius: BorderRadius.circular(AppRadius.badge),
        border: Border.all(color: color.withValues(alpha: .2)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null || status != null) ...[
            Icon(icon ?? AppStatus.icon(status), size: 16, color: color),
            const SizedBox(width: AppSpace.xs),
          ],
          Flexible(
            child: Text(
              label,
              style: Theme.of(context).textTheme.labelMedium
                  ?.copyWith(color: color, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
