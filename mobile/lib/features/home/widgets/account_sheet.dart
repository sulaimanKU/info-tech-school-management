import 'package:flutter/material.dart';

import '../../../design/app_theme.dart';
import '../../../shared/widgets/records_screen.dart';
import '../../../l10n/strings.dart';
import '../../../shared/widgets/school_brand.dart';
import '../../../shared/widgets/surface_card.dart';
import '../../auth/auth_controller.dart';
import '../../support/support_screen.dart';
import '../../finance/payroll_screen.dart';
import '../../communication/push_service.dart';

Future<void> confirmLogout(BuildContext context, AuthController auth) async {
  if (auth.busy) return;
  final s = Strings.of(context);
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      icon: const Icon(Icons.logout_rounded),
      title: Text(s.text('logoutTitle')),
      content: Text(s.text('logoutMessage')),
      actions: [
        TextButton(
          key: const Key('cancel-logout'),
          onPressed: () => Navigator.pop(context, false),
          child: Text(s.text('cancel')),
        ),
        FilledButton(
          key: const Key('confirm-logout'),
          onPressed: () => Navigator.pop(context, true),
          child: Text(s.text('logout')),
        ),
      ],
    ),
  );
  if (confirmed == true && context.mounted) await auth.logout();
}

void showAccountSheet(
  BuildContext context,
  AuthController auth, {
  SchoolPushService? push,
}) {
  final user = auth.user!;
  showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (sheetContext) {
      final s = Strings.of(sheetContext);
      final theme = Theme.of(sheetContext);
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(
          AppSpace.xl,
          0,
          AppSpace.xl,
          AppSpace.xxl,
        ),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    const SchoolLogo(size: 56),
                    const SizedBox(width: AppSpace.lg),
                    Expanded(
                      child: Text(
                        s.text('account'),
                        style: theme.textTheme.titleLarge,
                      ),
                    ),
                    IconButton(
                      tooltip: s.text('close'),
                      onPressed: () => Navigator.pop(sheetContext),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpace.xl),
                Text(
                  user['name'] as String,
                  style: theme.textTheme.headlineSmall,
                ),
                if (user['email'] != null) ...[
                  const SizedBox(height: AppSpace.sm),
                  SelectableText(
                    user['email'] as String,
                    textDirection: TextDirection.ltr,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
                const SizedBox(height: AppSpace.lg),
                Wrap(
                  spacing: AppSpace.sm,
                  runSpacing: AppSpace.sm,
                  children: (user['roles'] as List)
                      .map(
                        (r) => AppBadge(
                          label: s.text(r['code'] as String),
                          icon: Icons.verified_user_outlined,
                        ),
                      )
                      .toList(),
                ),
                const SizedBox(height: AppSpace.xl),
                Text(s.text('campus'), style: theme.textTheme.bodyMedium),
                const SizedBox(height: AppSpace.xl),
                StatusBanner(message: s.text('accountHelp')),
                if (!auth.isParent)
                  ListTile(
                    leading: const Icon(Icons.badge_outlined),
                    title: Text(s.text('myStaffProfile')),
                    onTap: () {
                      Navigator.pop(sheetContext);
                      Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (_) => MyStaffProfileScreen(auth: auth),
                        ),
                      );
                    },
                  ),
                ListTile(
                  leading: const Icon(Icons.history),
                  title: Text(s.text('loginHistory')),
                  onTap: () {
                    Navigator.pop(sheetContext);
                    Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => RecordsScreen(
                          auth: auth,
                          path: 'auth/history',
                          title: 'loginHistory',
                          recordTitle: (v) => s.text(v['method'] as String),
                          recordSubtitle: (v) =>
                              '${v['created_at']} · ${v['revoked_at'] != null ? s.text('revoked') : v['expires_at']}',
                        ),
                      ),
                    );
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.help_outline),
                  title: Text(s.text('aboutSupport')),
                  onTap: () {
                    Navigator.pop(sheetContext);
                    Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => SupportScreen(auth: auth, push: push),
                      ),
                    );
                  },
                ),
                const SizedBox(height: AppSpace.xl),
                OutlinedButton.icon(
                  onPressed: auth.busy
                      ? null
                      : () {
                          Navigator.pop(sheetContext);
                          confirmLogout(context, auth);
                        },
                  icon: const Icon(Icons.logout_rounded),
                  label: Text(s.text('logout')),
                ),
              ],
            ),
          ),
        ),
      );
    },
  );
}
