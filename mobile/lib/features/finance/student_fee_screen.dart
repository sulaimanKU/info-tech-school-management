import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import 'invoice_screen.dart';
import '../reports/report_screen.dart';
import 'financial_forms.dart';

class StudentFeeScreen extends StatefulWidget {
  const StudentFeeScreen({
    super.key,
    required this.auth,
    required this.studentId,
  });
  final AuthController auth;
  final String studentId;
  @override
  State<StudentFeeScreen> createState() => _StudentFeeScreenState();
}

class _StudentFeeScreenState extends State<StudentFeeScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final result = await widget.auth.call(
        'students/${widget.studentId}/finance',
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

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('finance'))),
      body: error != null
          ? DataState(error: error, onRetry: load)
          : data == null
          ? const DataState()
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpace.lg),
                children: [
                  SurfaceCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (data!['student'] != null) ...[
                          Text(data!['student']['full_name'] as String, style: Theme.of(context).textTheme.titleLarge),
                          Text('${data!['student']['registration_number']} ? ${data!['student']['class_name'] ?? ''}'),
                          Text('Total: ${Money.format(data!['total_minor'])} ? Paid: ${Money.format(data!['paid_minor'])}'),
                          const SizedBox(height: AppSpace.sm),
                        ],
                        Text(
                          s.text('currentBalance'),
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: AppSpace.sm),
                        Text(
                          Money.format(data!['balance_minor']),
                          style: Theme.of(context).textTheme.headlineMedium,
                        ),
                        Text(
                          '${s.text('arrears')}: ${Money.format(data!['arrears_minor'])}',
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpace.xl),
                  if ((data!['fee_schedule'] as List? ?? []).isNotEmpty) ...[
                    SectionHeader(title: s.text('feeSchedules')),
                    for (final row in data!['fee_schedule'] as List)
                      ValueRow(
                        label: s.text(row['kind'] as String),
                        value: Money.format(row['amount_minor']),
                      ),
                    const SizedBox(height: AppSpace.lg),
                  ],
                  Text(
                    s.text('feeVouchers'),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  for (final i in data!['items'] as List)
                    ListTile(
                      title: Text(i['voucher_number'] as String),
                      subtitle: Text(
                        '${i['period']} · ${s.text(i['status'] as String)}\n${s.text('currentBalance')}: ${Money.format(i['balance_minor'])} · ${s.text('dueDate')}: ${i['due_on']}',
                      ),
                      isThreeLine: true,
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (_) => InvoiceDetailScreen(
                            auth: widget.auth,
                            id: i['id'] as String,
                          ),
                        ),
                      ).then((_) => load()),
                    ),
                  if (widget.auth.can('finance.write'))
                    OutlinedButton(
                      onPressed: () async {
                        try {
                          await recordPayment(
                            context,
                            widget.auth,
                            (data!['items'] as List)
                                .map((v) => (v as Map).cast<String, dynamic>())
                                .toList(),
                          );
                          load();
                        } on ApiFailure catch (e) {
                          if (mounted) setState(() => error = e.code);
                        }
                      },
                      child: Text(s.text('payMultiple')),
                    ),
                  const SizedBox(height: AppSpace.xl),
                  Text(
                    s.text('paymentHistory'),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  for (final p in data!['payments'] as List)
                    ListTile(
                      leading: const Icon(Icons.receipt_long_outlined),
                      title: Text(
                        p['receipt_number']?.toString() ??
                            s.text('pendingVerification'),
                      ),
                      subtitle: Text(
                        '${p['paid_on']} · ${p['method']} · ${s.text(p['status'] as String)}\n${Money.format(p['amount_minor'])}',
                      ),
                      onTap: p['status'] == 'verified'
                          ? () => openSchoolReport(
                              context,
                              widget.auth,
                              'receipt',
                              {
                                'payment_id': p['id'] as String,
                                'student_id': widget.studentId,
                              },
                            )
                          : null,
                      isThreeLine: true,
                    ),
                  const SizedBox(height: AppSpace.xl),
                  Text(
                    s.text('approvedDiscounts'),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  for (final d in data!['discounts'] as List)
                    ListTile(
                      title: Text(d['name'] as String),
                      subtitle: Text('${d['starts_on']} — ${d['ends_on']}'),
                      trailing: Text('${(d['basis_points'] as num) / 100}%'),
                    ),
                  if ((data!['items'] as List).isEmpty)
                    const DataState(empty: true),
                ],
              ),
            ),
    );
  }
}
