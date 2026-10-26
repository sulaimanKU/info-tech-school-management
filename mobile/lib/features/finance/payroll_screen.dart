import '../reports/report_screen.dart';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import 'financial_forms.dart';

class PayrollScreen extends StatelessWidget {
  const PayrollScreen({super.key, required this.auth});
  final AuthController auth;
  Future<void> create(BuildContext context) async {
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'generatePayroll',
          fields: [
            FormFieldSpec(
              'period',
              'period',
              initial: DateTime.now()
                  .subtract(const Duration(days: 32))
                  .toIso8601String()
                  .substring(0, 7),
              maxLength: 7,
            ),
          ],
          onSubmit: (v) async {
            await auth.call('finance/payroll', body: v);
          },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'finance/payroll',
    title: 'payroll',
    recordTitle: (r) => r['period'] as String,
    recordSubtitle: (r) => Money.format(r['total_minor']),
    onCreate: auth.can('finance.write') ? create : null,
    onOpen: (c, r) async {
      await Navigator.push<void>(
        c,
        MaterialPageRoute(
          builder: (_) =>
              PayrollDetailScreen(auth: auth, id: r['id'] as String),
        ),
      );
    },
  );
}

class PayrollDetailScreen extends StatefulWidget {
  const PayrollDetailScreen({super.key, required this.auth, required this.id});
  final AuthController auth;
  final String id;
  @override
  State<PayrollDetailScreen> createState() => _PayrollDetailScreenState();
}

class _PayrollDetailScreenState extends State<PayrollDetailScreen> {
  Map<String, dynamic>? data;
  String? error;
  bool busy = false;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final result = await widget.auth.call('finance/payroll/${widget.id}');
      if (mounted) {
        setState(() {
          data = result;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> approve() async {
    setState(() => busy = true);
    try {
      await widget.auth.call('finance/payroll/${widget.id}/approve', body: {});
      await load();
    } on ApiFailure catch (e) {
      error = e.code;
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('payroll'))),
      body: data == null
          ? DataState(error: error, onRetry: load)
          : ListView(
              padding: const EdgeInsets.all(AppSpace.lg),
              children: [
                SurfaceCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        data!['period'] as String,
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      Text(
                        Money.format(data!['total_minor']),
                        style: Theme.of(context).textTheme.headlineMedium,
                      ),
                      AppBadge(
                        label: s.text(data!['status'] as String),
                        status: data!['status'] as String,
                      ),
                      if (data!['paid_on'] != null)
                        Text('${s.text('paymentDate')}: ${data!['paid_on']}'),
                    ],
                  ),
                ),
                if (error != null)
                  StatusBanner(message: s.text(error!), kind: StatusKind.error),
                for (final item in data!['items'] as List)
                  ListTile(
                    onTap: data!['status'] == 'paid'
                        ? () => openSchoolReport(
                            context,
                            widget.auth,
                            'salary_slip',
                            {'payroll_item_id': item['id'] as String},
                          )
                        : null,
                    title: Text(item['name'] as String),
                    subtitle: Text(
                      '${s.text('baseSalary')}: ${Money.format(item['base_salary_minor'])}\n${s.text('adjustments')}: ${Money.format(item['adjustment_minor'])}',
                    ),
                    trailing: Text(Money.format(item['net_salary_minor'])),
                  ),
                if (widget.auth.can('finance.approve') &&
                    data!['status'] == 'draft')
                  FilledButton(
                    onPressed: busy ? null : approve,
                    child: Text(s.text('approvePayroll')),
                  ),
                if (widget.auth.can('finance.write') &&
                    data!['status'] == 'approved')
                  FilledButton(
                    onPressed: busy
                        ? null
                        : () async {
                            await disbursementForm(
                              context,
                              widget.auth,
                              'finance/payroll/${widget.id}/pay',
                            );
                            load();
                          },
                    child: Text(s.text('disburse')),
                  ),
              ],
            ),
    );
  }
}

class MyStaffProfileScreen extends StatelessWidget {
  const MyStaffProfileScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'my-staff-profile',
    onOpen: (c, r) => openSchoolReport(c, auth, 'salary_slip', {
      'payroll_item_id': r['id'] as String,
    }),
    title: 'myStaffProfile',
    recordTitle: (r) => r['period'] as String,
    recordSubtitle: (r) =>
        '${r['paid_on']} · ${Money.format(r['net_salary_minor'])}',
    summary: (data) => Padding(
      padding: const EdgeInsets.only(bottom: AppSpace.xl),
      child: SurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              auth.user!['name'] as String,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            Text(
              '${data['profile']['employee_number']} · ${data['profile']['designation']}',
            ),
            Text(
              '${Strings.of(context).text('joiningDate')}: ${data['profile']['joined_on']}',
            ),
          ],
        ),
      ),
    ),
  );
}
