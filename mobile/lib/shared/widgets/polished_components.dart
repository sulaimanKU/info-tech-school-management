import 'package:flutter/material.dart';

import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';
import 'surface_card.dart';

/// A finite entrance: no repeating controller, no delay before interaction.
class Reveal extends StatelessWidget {
  const Reveal({super.key, required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context) => TweenAnimationBuilder<double>(
    tween: Tween(
      begin: MediaQuery.disableAnimationsOf(context) ? 1 : 0,
      end: 1,
    ),
    duration: AppMotion.duration(context),
    curve: Curves.easeOutCubic,
    child: child,
    builder: (context, value, child) => Opacity(
      opacity: value,
      child: Transform.translate(
        offset: Offset(0, 10 * (1 - value)),
        child: child,
      ),
    ),
  );
}

class PageBody extends StatelessWidget {
  const PageBody({super.key, required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context) => SafeArea(
    top: false,
    child: Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: AppSpace.contentWidth),
        child: child,
      ),
    ),
  );
}

class SectionHeader extends StatelessWidget {
  const SectionHeader({
    super.key,
    required this.title,
    this.description,
    this.icon,
  });
  final String title;
  final String? description;
  final IconData? icon;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: AppSpace.lg),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            if (icon != null) ...[
              Icon(icon, color: AppPalette.of(context).primary, size: 22),
              const SizedBox(width: AppSpace.sm),
            ],
            Expanded(
              child: Text(title, style: Theme.of(context).textTheme.titleLarge),
            ),
          ],
        ),
        if (description != null) ...[
          const SizedBox(height: AppSpace.sm),
          Text(
            description!,
            style: Theme.of(context).textTheme.bodyMedium
                ?.copyWith(color: AppPalette.of(context).supporting),
          ),
        ],
      ],
    ),
  );
}

class ProfileAvatar extends StatelessWidget {
  const ProfileAvatar({super.key, required this.name, this.size = 48});
  final String name;
  final double size;
  @override
  Widget build(BuildContext context) => ExcludeSemantics(
    child: Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.white.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(AppRadius.input),
        border: Border.all(color: AppColors.white.withValues(alpha: .3)),
      ),
      child: Text(
        name.trim().isEmpty ? '?' : name.trim().characters.first.toUpperCase(),
        textScaler: TextScaler.noScaling,
        style: Theme.of(context).textTheme.titleLarge
            ?.copyWith(color: AppColors.white),
      ),
    ),
  );
}

class ActionTile extends StatelessWidget {
  const ActionTile({
    super.key,
    required this.title,
    required this.icon,
    required this.onTap,
    this.description,
  });
  final String title;
  final IconData icon;
  final VoidCallback onTap;
  final String? description;
  @override
  Widget build(BuildContext context) => SurfaceCard(
    padding: EdgeInsets.zero,
    child: ListTile(
      leading: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: AppPalette.of(context).selected,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, size: 24),
      ),
      title: Text(title),
      subtitle: description == null ? null : Text(description!),
      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
      onTap: onTap,
    ),
  );
}

/// Monetary text is always LTR, uses tabular figures, and reflows at large text.
class ValueRow extends StatelessWidget {
  const ValueRow({
    super.key,
    required this.label,
    required this.value,
    this.emphasis = false,
  });
  final String label, value;
  final bool emphasis;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: AppSpace.md),
    child: LayoutBuilder(
      builder: (context, c) {
        final amount = Text(
          value,
          textDirection: TextDirection.ltr,
          style:
              (emphasis
                      ? Theme.of(context).textTheme.titleLarge
                      : Theme.of(context).textTheme.titleMedium)
                  ?.copyWith(
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
        );
        final title = Text(
          label,
          style: Theme.of(context).textTheme.bodyMedium
              ?.copyWith(color: AppPalette.of(context).supporting),
        );
        if (c.maxWidth < 340 ||
            MediaQuery.textScalerOf(context).scale(1) > 1.3) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              title,
              const SizedBox(height: AppSpace.xs),
              amount,
            ],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: title),
            const SizedBox(width: AppSpace.lg),
            Flexible(child: amount),
          ],
        );
      },
    ),
  );
}

Future<bool> confirmAction(
  BuildContext context, {
  required String title,
  required String message,
  required String action,
}) async {
  final s = Strings.of(context);
  return await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          icon: const Icon(Icons.fact_check_outlined),
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: Text(s.text('cancel')),
            ),
            FilledButton(
              key: const Key('confirm-action'),
              onPressed: () => Navigator.pop(c, true),
              child: Text(action),
            ),
          ],
        ),
      ) ??
      false;
}
