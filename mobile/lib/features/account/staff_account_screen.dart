import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../design/semantic_tokens.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/authorized_photo.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import '../files/private_file_screen.dart';
import '../home/widgets/account_sheet.dart';
import 'staff_profile_editor.dart';
import 'change_password_screen.dart';

class AccountFailure extends StatelessWidget {
  const AccountFailure({super.key, required this.code, this.retry});
  final String code;
  final VoidCallback? retry;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    final retryable = [
      'network',
      'server',
      'integrationUnavailable',
      'rateLimit',
    ].contains(code);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpace.xl),
        child: SurfaceCard(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                code == 'network' ? Icons.wifi_off : Icons.info_outline,
                color: AppPalette.of(context).error,
              ),
              const SizedBox(height: AppSpace.md),
              Text(
                s.text(
                  Strings.en.containsKey('accountError_$code')
                      ? 'accountError_$code'
                      : 'accountError_server',
                ),
                textAlign: TextAlign.center,
              ),
              if (retryable && retry != null)
                TextButton.icon(
                  onPressed: retry,
                  icon: const Icon(Icons.refresh),
                  label: Text(s.text('accountTryAgain')),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class AccountResource extends StatefulWidget {
  const AccountResource({
    super.key,
    required this.auth,
    required this.path,
    required this.builder,
  });
  final AuthController auth;
  final String path;
  final Widget Function(BuildContext, Map<String, dynamic>) builder;
  @override
  State<AccountResource> createState() => _AccountResourceState();
}

class _AccountResourceState extends State<AccountResource> {
  Map<String, dynamic>? data;
  String? error;
  int generation = 0;
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void didUpdateWidget(covariant AccountResource old) {
    super.didUpdateWidget(old);
    if (old.auth != widget.auth || old.path != widget.path) {
      data = null;
      error = null;
      load();
    }
  }

  Future<void> load() async {
    final version = ++generation;
    try {
      final v = await widget.auth.call(widget.path);
      if (mounted && version == generation) {
        setState(() {
          data = v;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted && version == generation) {
        setState(() {
          data = null;
          error = e.code;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => error != null
      ? AccountFailure(code: error!, retry: load)
      : data == null
      ? const DataState()
      : RefreshIndicator(
          onRefresh: load,
          child: ListView(
            padding: const EdgeInsets.all(AppSpace.lg),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [widget.builder(context, data!)],
          ),
        );
}

class StaffAccountScreen extends StatefulWidget {
  const StaffAccountScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<StaffAccountScreen> createState() => _StaffAccountScreenState();
}

class _StaffAccountScreenState extends State<StaffAccountScreen> {
  int revision = 0;
  @override
  void initState() {
    super.initState();
    widget.auth.addListener(sessionChanged);
  }

  void sessionChanged() {
    if (widget.auth.status == AuthStatus.signedOut) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
      });
    }
  }

  @override
  void dispose() {
    widget.auth.removeListener(sessionChanged);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('myStaffProfile'))),
      body: PageBody(
        child: AccountResource(
          key: ValueKey(revision),
          auth: widget.auth,
          path: 'account/profile',
          builder: (context, data) {
            final a = (data['account'] as Map).cast<String, dynamic>();
            final employment = data['profile'] as Map?;
            final roles = a['roles'] as List? ?? [];
            final p = AppPalette.of(context);
            Widget field(String key, dynamic value) => ValueRow(
              label: s.text(key),
              value: value?.toString().trim().isNotEmpty == true
                  ? value.toString()
                  : s.text('notRecorded'),
            );
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SurfaceCard(
                  accent: p.primary,
                  child: Column(
                    children: [
                      AuthorizedPhoto(
                        auth: widget.auth,
                        fileId: a['photo_file_id'] as String?,
                        name: a['name'] as String?,
                        size: 88,
                      ),
                      const SizedBox(height: AppSpace.md),
                      Text(
                        a['name'] as String,
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: AppSpace.sm),
                      Wrap(
                        alignment: WrapAlignment.center,
                        spacing: AppSpace.sm,
                        runSpacing: AppSpace.sm,
                        children: [
                          for (final r in roles)
                            AppBadge(
                              label: s.text(r['code'] as String),
                              icon: Icons.verified_user_outlined,
                            ),
                          AppBadge(
                            label: s.text(
                              a['is_active'] == true ? 'active' : 'inactive',
                            ),
                            status: a['is_active'] == true
                                ? 'active'
                                : 'inactive',
                          ),
                        ],
                      ),
                      if (employment != null) ...[
                        const SizedBox(height: AppSpace.md),
                        Text(
                          '${employment['employee_number']} · ${employment['designation']}',
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: AppSpace.xl),
                SectionHeader(
                  title: s.text('accountPersonal'),
                  description: s.text('accountEditableNote'),
                ),
                SurfaceCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      field('fullName', a['name']),
                      field('email', a['email']),
                      field('mobile', a['mobile']),
                      field('address', a['address']),
                      const SizedBox(height: AppSpace.md),
                      FilledButton.icon(
                        key: const Key('edit-own-profile'),
                        onPressed: () async {
                          final saved = await Navigator.push<bool>(
                            context,
                            MaterialPageRoute(
                              builder: (_) => StaffProfileEditor(
                                auth: widget.auth,
                                account: a,
                              ),
                            ),
                          );
                          if (saved == true && mounted) {
                            await widget.auth.refreshProfile();
                            if (mounted) {
                              setState(() => revision++);
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text(s.text('accountSaved')),
                                  ),
                                );
                              }
                            }
                          }
                        },
                        icon: const Icon(Icons.edit_outlined),
                        label: Text(s.text('accountEditProfile')),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpace.xl),
                SectionHeader(
                  title: s.text('accountEmployment'),
                  description: s.text('accountOfficialNote'),
                ),
                if (employment == null)
                  StatusBanner(message: s.text('staffNotLinked'))
                else
                  SurfaceCard(
                    child: Column(
                      children: [
                        field('employeeNumber', employment['employee_number']),
                        field('designation', employment['designation']),
                        field('joiningDate', employment['joined_on']),
                        field(
                          'status',
                          s.text(
                            employment['is_active'] == true
                                ? 'active'
                                : 'inactive',
                          ),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: AppSpace.xl),
                SectionHeader(title: s.text('accountSalary')),
                SurfaceCard(
                  padding: EdgeInsets.zero,
                  child: ListTile(
                    key: const Key('own-salary-slips'),
                    leading: Icon(
                      Icons.receipt_long_outlined,
                      color: p.success,
                    ),
                    title: Text(s.text('accountSalarySlips')),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => StaffSalaryScreen(auth: widget.auth),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: AppSpace.xl),
                SectionHeader(title: s.text('accountSecurity')),
                SurfaceCard(
                  child: Column(
                    children: [
                      ListTile(
                        key: const Key('change-own-password'),
                        leading: const Icon(Icons.lock_outline),
                        title: Text(s.text('accountChangePassword')),
                        subtitle: Text(s.text('accountPasswordPolicy')),
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute<void>(
                            builder: (_) =>
                                ChangePasswordScreen(auth: widget.auth),
                          ),
                        ),
                      ),
                      ListTile(
                        leading: const Icon(Icons.history),
                        title: Text(s.text('accountSessionNote')),
                      ),
                      OutlinedButton.icon(
                        onPressed: () => confirmLogout(context, widget.auth),
                        icon: const Icon(Icons.logout),
                        label: Text(s.text('logout')),
                      ),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class StaffSalaryScreen extends StatelessWidget {
  const StaffSalaryScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('accountSalarySlips'))),
      body: PageBody(
        child: AccountResource(
          auth: auth,
          path: 'my-staff-profile',
          builder: (context, data) {
            final items = data['items'] as List? ?? [];
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                StatusBanner(message: s.text('accountSalaryPrivate')),
                const SizedBox(height: AppSpace.lg),
                if (data['staff_linked'] == false)
                  StatusBanner(message: s.text('staffNotLinked'))
                else if (items.isEmpty)
                  SurfaceCard(
                    child: Column(
                      children: [
                        const Icon(Icons.receipt_long_outlined, size: 48),
                        const SizedBox(height: AppSpace.md),
                        Text(s.text('accountNoSalary')),
                        Text(
                          s.text('accountNoSalaryNote'),
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  ),
                for (final row in items)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpace.md),
                    child: SurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            row['period'].toString(),
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                          AppBadge(
                            label: s.text(
                              (row['payment_status'] ?? row['status'])
                                  as String,
                            ),
                            status: row['status'] as String,
                          ),
                          ValueRow(
                            label: s.text('paidOn'),
                            value: row['paid_on']?.toString() ?? '?',
                          ),
                          ValueRow(
                            label: s.text('accountBaseSalary'),
                            value: Money.format(row['base_salary_minor']),
                          ),
                          ValueRow(
                            label: s.text('accountAdjustment'),
                            value: Money.format(row['adjustment_minor']),
                          ),
                          ValueRow(
                            label: s.text('accountNetSalary'),
                            value: Money.format(row['net_salary_minor']),
                            emphasis: true,
                          ),
                          if (row['paid_minor'] != null)
                            ValueRow(
                              label: 'Amount paid',
                              value: Money.format(row['paid_minor']),
                            ),
                          if (row['balance_minor'] != null)
                            ValueRow(
                              label: 'Remaining salary',
                              value: Money.format(row['balance_minor']),
                            ),
                          for (final payment in row['payments'] as List? ?? [])
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(
                                '${payment['paid_on']} ? ${Money.format(payment['amount_minor'])}',
                              ),
                              subtitle: Text(
                                '${payment['method']} ? ${payment['reference'] ?? ''}\n${payment['notes'] ?? ''}',
                              ),
                            ),
                          if (row['paid_minor'] == null ||
                              (row['paid_minor'] as num) > 0)
                            TextButton.icon(
                              onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute<void>(
                                  builder: (_) => PrivateFileScreen(
                                    auth: auth,
                                    id: row['id'] as String,
                                    mime: 'application/pdf',
                                    path:
                                        'account/salary-slips/${row['id']}/document',
                                    errorBuilder: (context, code, retry) =>
                                        AccountFailure(
                                          code: code,
                                          retry: retry,
                                        ),
                                  ),
                                ),
                              ),
                              icon: const Icon(Icons.picture_as_pdf_outlined),
                              label: Text(s.text('accountSalaryPdf')),
                            ),
                        ],
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}
