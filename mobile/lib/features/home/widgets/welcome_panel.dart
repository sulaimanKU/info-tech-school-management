import 'package:flutter/material.dart';

import '../../../design/app_theme.dart';
import '../../../design/semantic_tokens.dart';
import '../../../l10n/strings.dart';
import '../../../shared/widgets/surface_card.dart';

class WelcomePanel extends StatelessWidget {
  const WelcomePanel({super.key, required this.user, this.onAccount});
  final Map<String, dynamic> user;
  final VoidCallback? onAccount;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final hour = DateTime.now().hour;
    return SurfaceCard(
      accent: AppPalette.of(context).primary,
      padding: const EdgeInsets.all(AppSpace.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      s.text(
                        hour < 12
                            ? 'goodMorning'
                            : hour < 17
                            ? 'goodAfternoon'
                            : 'goodEvening',
                      ),
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: AppSpace.xs),
                    Text(
                      user['name'] as String,
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                  ],
                ),
              ),
              IconButton(
                key: const Key('account-details'),
                tooltip: s.text('viewAccount'),
                onPressed: onAccount,
                icon: const Icon(Icons.account_circle_outlined),
                iconSize: 32,
              ),
            ],
          ),
          const SizedBox(height: AppSpace.sm),
          Text(
            'INFO-TECH · ${s.text('campus')}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: AppSpace.md),
          Wrap(
            spacing: AppSpace.sm,
            runSpacing: AppSpace.sm,
            children: [
              for (final raw in user['roles'] as List? ?? [])
                AppBadge(
                  label: s.text(raw['code'] as String),
                  icon: Icons.verified_user_outlined,
                ),
            ],
          ),
        ],
      ),
    );
  }
}
