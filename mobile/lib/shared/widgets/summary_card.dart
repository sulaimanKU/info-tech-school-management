import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import 'surface_card.dart';

class SummaryCard extends StatelessWidget {
  const SummaryCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.contextLabel,
    this.tone = StatusTone.info,
    this.category,
    this.onTap,
  });
  final String label, value;
  final String? contextLabel;
  final IconData icon;
  final StatusTone tone;
  final String? category;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final accent = category == null ? p.tone(tone) : p.module(category!);
    return Semantics(
      container: true,
      button: onTap != null,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.card),
        child: SurfaceCard(
          accent: accent,
          padding: const EdgeInsets.all(AppSpace.lg),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 122),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(AppSpace.sm),
                      decoration: BoxDecoration(
                        color: accent.withValues(alpha: .11),
                        borderRadius: BorderRadius.circular(AppRadius.badge),
                      ),
                      child: Icon(icon, color: accent, size: 21),
                    ),
                    const Spacer(),
                    if (onTap != null)
                      Icon(
                        Icons.arrow_outward_rounded,
                        size: 17,
                        color: p.supporting,
                      ),
                  ],
                ),
                const SizedBox(height: AppSpace.md),
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelLarge
                      ?.copyWith(color: p.supporting),
                ),
                const SizedBox(height: AppSpace.xs),
                Text(
                  value,
                  textDirection: TextDirection.ltr,
                  softWrap: true,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                if (contextLabel != null) ...[
                  const SizedBox(height: AppSpace.sm),
                  Text(
                    contextLabel!,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class SummaryGrid extends StatelessWidget {
  const SummaryGrid({super.key, required this.children});
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, c) {
      final w =
          c.maxWidth / MediaQuery.textScalerOf(context).scale(1).clamp(1, 2);
      final cols = w >= 1000
          ? 4
          : w >= 650
          ? 3
          : w >= 340
          ? 2
          : 1;
      return Wrap(
        spacing: AppSpace.md,
        runSpacing: AppSpace.md,
        children: [
          for (final child in children)
            SizedBox(
              width: (c.maxWidth - AppSpace.md * (cols - 1)) / cols,
              child: child,
            ),
        ],
      );
    },
  );
}
