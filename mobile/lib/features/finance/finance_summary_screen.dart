import 'financial_forms.dart';
import '../reports/report_screen.dart';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import 'student_fee_screen.dart';
import 'payment_screen.dart';

class FinanceSummaryScreen extends StatefulWidget {
  const FinanceSummaryScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<FinanceSummaryScreen> createState() => _FinanceSummaryScreenState();
}

class _FinanceSummaryScreenState extends State<FinanceSummaryScreen> {
  Map<String, dynamic>? data;
  Map<String, dynamic> filters = {};
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final query = Uri(
        queryParameters: filters.map((k, v) => MapEntry(k, '$v')),
      ).query;
      final result = await widget.auth.call(
        'finance/summary${query.isEmpty ? '' : '?$query'}',
      );
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

  Future<void> filter() async {
    try {
      final sessions = await widget.auth.call('catalog/sessions');
      final classes = await widget.auth.call('catalog/classes');
      if (!mounted) return;
      final result = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => DomainForm(
            title: 'Finance filters',
            fields: [
              for (final field in ['academic_session_id', 'class_id'])
                FormFieldSpec(
                  field,
                  field == 'class_id' ? 'class' : 'academicSession',
                  kind: FieldKind.choice,
                  required: false,
                  initial: filters[field],
                  options: {
                    for (final row
                        in (field == 'class_id' ? classes : sessions)['items']
                            as List)
                      row['id'] as String: row['name'] as String,
                  },
                ),
              FormFieldSpec(
                'period',
                'Previous dues before month (YYYY-MM)',
                required: false,
                initial: filters['period'],
              ),
              FormFieldSpec(
                'from',
                'Voucher issued from',
                kind: FieldKind.date,
                required: false,
                initial: filters['from'],
              ),
              FormFieldSpec(
                'to',
                'Voucher issued through',
                kind: FieldKind.date,
                required: false,
                initial: filters['to'],
              ),
            ],
            onSubmit: (v) async {
              filters = v;
            },
          ),
        ),
      );
      if (result == true) await load();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = data;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Finance overview'),
        actions: [
          IconButton(
            onPressed: filter,
            icon: const Icon(Icons.filter_alt_outlined),
          ),
          IconButton(
            onPressed: () {
              filters = {};
              load();
            },
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: d == null
          ? DataState(error: error, onRetry: load)
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                children: [
                  if (error != null)
                    StatusBanner(message: error!, kind: StatusKind.error),
                  const Text(
                    'Posted vouchers and allocated manual payments. Unbilled schedules are not counted as dues. Date filters apply to voucher issue dates; receipts are allocated to those vouchers.',
                  ),
                  SurfaceCard(
                    child: Column(
                      children: [
                        for (final row in [
                          ('Chargeable fees', 'total_minor'),
                          ('Net collected', 'paid_minor'),
                          ('Concessions', 'discount_minor'),
                          ('Outstanding', 'balance_minor'),
                          ('Previous-period dues', 'arrears_minor'),
                        ])
                          ValueRow(
                            label: row.$1,
                            value: Money.format(d[row.$2]),
                          ),
                        ValueRow(
                          label: 'Unpaid students',
                          value: '${d['students_unpaid']}',
                        ),
                        ValueRow(
                          label: 'Partially paid students',
                          value: '${d['students_partially_paid']}',
                        ),
                      ],
                    ),
                  ),
                  if (d['payroll'] != null) ...[
                    const SectionHeader(title: 'Approved payroll'),
                    for (final row in [
                      ('Net salaries', 'total_minor'),
                      ('Salary paid', 'paid_minor'),
                      ('Salary outstanding', 'balance_minor'),
                    ])
                      ValueRow(
                        label: row.$1,
                        value: Money.format(d['payroll'][row.$2]),
                      ),
                  ],
                  const SectionHeader(title: 'Missing monthly fees'),
                  ValueRow(
                    label: 'Students with unbilled months',
                    value: '${d['students_with_missing_months'] ?? 0}',
                  ),
                  ValueRow(
                    label: 'Unbilled configured tuition',
                    value: Money.format(d['unbilled_minor']),
                  ),
                  Text(
                    'Months needing fee setup: ${d['months_needing_configuration'] ?? 0}. Unbilled amounts are not included in posted outstanding.',
                  ),
                  if (widget.auth.can('finance.write'))
                    FilledButton(
                      onPressed: () async {
                        try {
                          await generateMonthlyFees(context, widget.auth);
                          await load();
                        } on ApiFailure catch (e) {
                          if (mounted) setState(() => error = e.code);
                        }
                      },
                      child: const Text('Preview / generate monthly fees'),
                    ),
                  if (widget.auth.can('reports.export'))
                    TextButton(
                      onPressed: () => openSchoolReport(
                        context,
                        widget.auth,
                        'unbilled_fees',
                        filters.map((k, v) => MapEntry(k, '$v')),
                      ),
                      child: const Text('Unbilled tuition report'),
                    ),
                  for (final student in d['unbilled_students'] as List? ?? [])
                    ListTile(
                      title: Text(
                        '${student['full_name']} ? ${student['registration_number']}',
                      ),
                      subtitle: Text(
                        '${student['missing_months']} missing months ? ${Money.format(student['unbilled_minor'])}',
                      ),
                      onTap: () => Navigator.push<void>(
                        context,
                        MaterialPageRoute(
                          builder: (_) => StudentFeeScreen(
                            auth: widget.auth,
                            studentId: student['student_id'] as String,
                          ),
                        ),
                      ).then((_) => load()),
                    ),
                  if (widget.auth.can('reports.export'))
                    TextButton(
                      onPressed: () => openSchoolReport(
                        context,
                        widget.auth,
                        'fee_balances',
                        filters.map((k, v) => MapEntry(k, '$v')),
                      ),
                      child: const Text('Student balances / arrears report'),
                    ),
                  const SectionHeader(title: 'Student balances'),
                  if ((d['student_balances'] as List).isEmpty)
                    const ListTile(
                      title: Text('No posted fees in this selection'),
                    ),
                  for (final student in d['student_balances'] as List)
                    ListTile(
                      title: Text(student['full_name'] as String),
                      subtitle: Text(student['registration_number'] as String),
                      trailing: Text(Money.format(student['balance_minor'])),
                      onTap: () => Navigator.push<void>(
                        context,
                        MaterialPageRoute(
                          builder: (_) => StudentFeeScreen(
                            auth: widget.auth,
                            studentId: student['student_id'] as String,
                          ),
                        ),
                      ).then((_) => load()),
                    ),
                  const SectionHeader(title: 'Recent payments'),
                  if ((d['recent_payments'] as List).isEmpty)
                    const ListTile(
                      title: Text('No payments recorded in this selection'),
                    ),
                  for (final payment in d['recent_payments'] as List)
                    ListTile(
                      title: Text(
                        '${payment['receipt_number'] ?? payment['status']} · ${Money.format(payment['amount_minor'])}',
                      ),
                      subtitle: Text(
                        '${payment['paid_on']} · ${payment['method']}',
                      ),
                      onTap: () => Navigator.push<void>(
                        context,
                        MaterialPageRoute(
                          builder: (_) => PaymentDetailScreen(
                            auth: widget.auth,
                            id: payment['id'] as String,
                          ),
                        ),
                      ).then((_) => load()),
                    ),
                ],
              ),
            ),
    );
  }
}
