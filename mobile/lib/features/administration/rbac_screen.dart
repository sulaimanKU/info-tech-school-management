import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';

class RbacScreen extends StatefulWidget {
  const RbacScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<RbacScreen> createState() => _RbacScreenState();
}

class _RbacScreenState extends State<RbacScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    if (widget.auth.can('rbac.manage')) {
      load();
    } else {
      error = 'forbidden';
    }
  }

  Future<void> load() async {
    try {
      final d = await widget.auth.call('rbac');
      if (mounted) {
        setState(() {
          data = d;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> edit(Map<String, dynamic> role) async {
    final permissions = (role['permissions'] as List).cast<String>().toSet();
    final s = Strings.of(context);
    bool busy = false;
    String? localError;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheet) => StatefulBuilder(
        builder: (context, setSheet) => FractionallySizedBox(
          heightFactor: .9,
          child: ListView(
            padding: const EdgeInsets.all(AppSpace.xl),
            children: [
              Text(
                s.text(role['code'] as String),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: AppSpace.md),
              StatusBanner(message: s.text('roleChangeNotice')),
              for (final raw in data!['permissions'] as List)
                if (raw['code'] != 'rbac.manage')
                  CheckboxListTile(
                    title: Text(s.text('perm_${raw['code']}')),
                    subtitle: Text(
                      raw['code'] as String,
                      textDirection: TextDirection.ltr,
                    ),
                    value: permissions.contains(raw['code']),
                    onChanged: busy
                        ? null
                        : (v) => setSheet(() {
                            if (v == true) {
                              permissions.add(raw['code'] as String);
                            } else {
                              permissions.remove(raw['code']);
                            }
                          }),
                  ),
              if (localError != null)
                StatusBanner(
                  message: s.text(localError!),
                  kind: StatusKind.error,
                ),
              FilledButton(
                onPressed: busy
                    ? null
                    : () async {
                        if (!await confirmAction(
                          context,
                          title: s.text('rolePermissions'),
                          message: s.text('roleChangeNotice'),
                          action: s.text('confirm'),
                        )) {
                          return;
                        }
                        setSheet(() => busy = true);
                        try {
                          await widget.auth.call(
                            'rbac/${role['code']}',
                            method: 'PUT',
                            body: {'permissions': permissions.toList()},
                          );
                          if (sheet.mounted) Navigator.pop(sheet);
                        } on ApiFailure catch (e) {
                          if (sheet.mounted) {
                            setSheet(() => localError = e.code);
                          }
                        } finally {
                          if (sheet.mounted) setSheet(() => busy = false);
                        }
                      },
                child: Text(s.text('savePermissions')),
              ),
            ],
          ),
        ),
      ),
    );
    if (mounted) load();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('accessControl'))),
      body: data == null
          ? DataState(
              error: error,
              onRetry: widget.auth.can('rbac.manage') ? load : null,
            )
          : PageBody(
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                children: [
                  for (final raw in data!['roles'] as List)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpace.md),
                      child: SurfaceCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              s.text(raw['code'] as String),
                              style: Theme.of(context).textTheme.titleLarge,
                            ),
                            const SizedBox(height: AppSpace.md),
                            Wrap(
                              spacing: AppSpace.sm,
                              runSpacing: AppSpace.sm,
                              children: [
                                for (final p in raw['permissions'] as List)
                                  AppBadge(label: s.text('perm_$p')),
                              ],
                            ),
                            if (![
                              'super_admin',
                              'parent_guardian',
                            ].contains(raw['code']))
                              TextButton.icon(
                                onPressed: () =>
                                    edit((raw as Map).cast<String, dynamic>()),
                                icon: const Icon(Icons.security_outlined),
                                label: Text(s.text('rolePermissions')),
                              ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}
