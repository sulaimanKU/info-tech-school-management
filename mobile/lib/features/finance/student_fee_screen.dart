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
import 'payment_screen.dart';
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
  bool busy = false;
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

  Future<void> action(Future<void> Function() run) async {
    setState(() => busy = true);
    try {
      await run();
      await load();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    if (data == null) {
      return Scaffold(
        appBar: AppBar(title: Text(s.text('finance'))),
        body: DataState(error: error, onRetry: load),
      );
    }
    final d = data!;
    final student = d['student'] as Map? ?? {};
    final items = d['items'] as List? ?? [];
    final payments = d['payments'] as List? ?? [];
    final writable = widget.auth.can('finance.write');
    Widget value(String title, String key) =>
        ValueRow(label: title, value: Money.format(d[key] ?? 0));
    return Scaffold(
      appBar: AppBar(title: Text(s.text('finance'))),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(AppSpace.lg),
          children: [
            SurfaceCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    student['full_name']?.toString() ?? '',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  Text(
                    '${student['registration_number'] ?? ''} ? ${student['class_name'] ?? ''} ${student['section_name'] ?? ''}',
                  ),
                  Text('Period: ${d['period'] ?? ''}'),
                  value('Configured monthly tuition', 'configured_fee_minor'),
                  value('Configured concession', 'configured_discount_minor'),
                  value('Posted fees (before concessions)', 'gross_minor'),
                  value('Posted concessions', 'discount_minor'),
                  value('Total chargeable vouchers', 'total_minor'),
                  value(
                    'Current period posted fees',
                    'current_period_fee_minor',
                  ),
                  value('Previous-period outstanding', 'arrears_minor'),
                  value('Net paid (after refunds)', 'paid_minor'),
                  value('Outstanding posted dues', 'balance_minor'),
                  if ((d['credit_minor'] as num? ?? 0) > 0)
                    value('Credit', 'credit_minor'),
                ],
              ),
            ),
            const SizedBox(height: AppSpace.lg),
            if (error != null)
              StatusBanner(message: s.text(error!), kind: StatusKind.error),
            if (d['voucher_required'] == true) ...[
              StatusBanner(
                message:
                    'Tuition of ${Money.format(d['unbilled_minor'])} is configured but not billed for this period. Generate a voucher to post the due; it is not included again in the balance above.',
              ),
            ] else if (items.isEmpty)
              const StatusBanner(
                message: 'No posted fee vouchers. Configure the class/session fees, then generate a voucher before recording payment.',
              ),
            if (writable)
              FilledButton.icon(
                onPressed: busy
                    ? null
                    : () => action(() async {
                        await generateVoucher(
                          context,
                          widget.auth,
                          selectedStudent: {
                            'id': widget.studentId,
                            ...student.cast<String, dynamic>(),
                          },
                          sessionId: d['academic_session_id'] as String?,
                        );
                      }),
                icon: const Icon(Icons.add),
                label: Text(s.text('generateVoucher')),
              ),
            if (writable)
              OutlinedButton.icon(
                onPressed:
                    busy || !(items.any((i) => (i['balance_minor'] as num) > 0))
                    ? null
                    : () => action(() async {
                        await recordPayment(
                          context,
                          widget.auth,
                          items
                              .map((v) => (v as Map).cast<String, dynamic>())
                              .toList(),
                          studentLabel:
                              '${student['full_name'] ?? ''} ? ${student['registration_number'] ?? ''}',
                        );
                      }),
                icon: const Icon(Icons.payments_outlined),
                label: Text(s.text('recordPayment')),
              ),
            const SizedBox(height: AppSpace.lg),
            SectionHeader(title: s.text('feeSchedules')),
            for (final f in d['fee_schedule'] as List? ?? [])
              ValueRow(
                label: '${s.text(f['kind'] as String)} ? ${f['effective_on']}',
                value: Money.format(f['amount_minor']),
              ),
            SectionHeader(title: s.text('feeVouchers')),
            if (items.isEmpty)
              const ListTile(title: Text('No vouchers generated yet')),
            for (final i in items)
              ListTile(
                title: Text(i['voucher_number'] as String),
                subtitle: Text(
                  '${i['period']} ? ${s.text(i['status'] as String)}\nDue: ${Money.format(i['balance_minor'])} ? ${i['due_on']}',
                ),
                isThreeLine: true,
                onTap: () => Navigator.push<void>(
                  context,
                  MaterialPageRoute(
                    builder: (_) => InvoiceDetailScreen(
                      auth: widget.auth,
                      id: i['id'] as String,
                    ),
                  ),
                ).then((_) => load()),
              ),
            SectionHeader(title: s.text('paymentHistory')),
            if (payments.isEmpty)
              const ListTile(
                title: Text('No payments recorded yet'),
                subtitle: Text(
                  'Saved manual payments and receipts will appear here.',
                ),
              ),
            for (final p in payments)
              ListTile(
                leading: const Icon(Icons.receipt_long_outlined),
                title: Text(
                  '${p['receipt_number'] ?? s.text('pendingVerification')} ? ${Money.format(p['amount_minor'])}',
                ),
                subtitle: Text(
                  '${p['paid_on']} ? ${p['method']} ? ${s.text(p['status'] as String)}${p['reference'] == null ? '' : '\nReference: ${p['reference']}'}${p['notes'] == null ? '' : '\n${p['notes']}'}${p['recorded_by_name'] == null ? '' : '\nRecorded by: ${p['recorded_by_name']}'}',
                ),
                onTap: widget.auth.can('finance.read')
                    ? () => Navigator.push<void>(
                        context,
                        MaterialPageRoute(
                          builder: (_) => PaymentDetailScreen(
                            auth: widget.auth,
                            id: p['id'] as String,
                          ),
                        ),
                      ).then((_) => load())
                    : p['status'] == 'verified'
                    ? () => openSchoolReport(context, widget.auth, 'receipt', {
                        'payment_id': p['id'] as String,
                        'student_id': widget.studentId,
                      })
                    : null,
              ),
            SectionHeader(title: s.text('approvedDiscounts')),
            for (final discount in d['discounts'] as List? ?? [])
              ListTile(
                title: Text(discount['name'] as String),
                subtitle: Text(
                  '${discount['starts_on']} ? ${discount['ends_on']}',
                ),
                trailing: Text('${(discount['basis_points'] as num) / 100}%'),
              ),
          ],
        ),
      ),
    );
  }
}
