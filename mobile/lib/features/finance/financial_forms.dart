import 'monthly_fee_preview.dart';

import 'package:flutter/material.dart';

import '../../core/money.dart';
import '../../core/api_client.dart';
import '../../core/operation_id.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../auth/auth_controller.dart';
import 'payment_screen.dart';
import '../students/student_list_screen.dart';

Future<Map<String, dynamic>?> chooseStudent(
  BuildContext context,
  AuthController auth,
) => Navigator.push<Map<String, dynamic>>(
  context,
  MaterialPageRoute(
    builder: (_) => StudentListScreen(auth: auth, selection: true),
  ),
);
Future<bool?> generateVoucher(
  BuildContext context,
  AuthController auth, {
  Map<String, dynamic>? selectedStudent,
  String? sessionId,
}) async {
  final student = selectedStudent ?? await chooseStudent(context, auth);
  if (student == null) return false;
  final sessions = await auth.call('catalog/sessions');
  if (!context.mounted) return false;
  final s = Strings.of(context);
  final requestId = operationId();
  return Navigator.push<bool>(
    context,
    MaterialPageRoute(
      builder: (_) => DomainForm(
        title: 'generateVoucher',
        fields: [
          FormFieldSpec(
            'academic_session_id',
            'academicSession',
            initial: sessionId,
            kind: FieldKind.choice,
            options: {
              for (final v in sessions['items'] as List)
                (v['id'] as String): v['name'] as String,
            },
          ),
          FormFieldSpec(
            'kind',
            'feeKind',
            kind: FieldKind.choice,
            initial: 'tuition',
            options: {
              for (final key in [
                'tuition',
                'admission',
                'promotion',
                'additional',
                'opening_balance',
              ])
                key: s.text(key),
            },
          ),
          FormFieldSpec(
            'period',
            'period',
            initial: DateTime.now().toIso8601String().substring(0, 7),
            maxLength: 7,
          ),
          FormFieldSpec(
            'issued_on',
            'issuedDate',
            kind: FieldKind.date,
            initial: DateTime.now().toIso8601String().substring(0, 10),
          ),
          const FormFieldSpec('due_on', 'dueDate', kind: FieldKind.date),
          const FormFieldSpec(
            'additional_amount',
            'additionalCharge',
            kind: FieldKind.money,
            required: false,
          ),
          const FormFieldSpec(
            'additional_description',
            'chargeDescription',
            required: false,
          ),
        ],
        onSubmit: (v) async {
          final amount = v.remove('additional_amount') as int?;
          final description = v.remove('additional_description') as String?;
          await auth.call(
            'finance/invoices',
            body: {
              ...v,
              'student_id': student['id'],
              'request_id': requestId,
              'charges': amount == null || amount == 0
                  ? []
                  : [
                      {
                        'description':
                            description ?? s.text('additionalCharge'),
                        'amount_minor': amount,
                      },
                    ],
            },
          );
        },
      ),
    ),
  );
}

String? defaultCashMethod(Map<String, dynamic> config) =>
    (config['payment_methods'] as List)
        .where((m) => m['code'] == 'cash')
        .map((m) => m['id'] as String)
        .firstOrNull;

bool nonCashMethod(Map<String, dynamic> config, Map<String, dynamic> values) =>
    (config['payment_methods'] as List).any(
      (m) => m['id'] == values['payment_method_id'] && m['code'] != 'cash',
    );

Future<bool?> recordPayment(
  BuildContext context,
  AuthController auth,
  List<Map<String, dynamic>> invoices, {
  String? studentLabel,
}) async {
  final due = invoices.where((i) => (i['balance_minor'] as num) > 0).toList()
    ..sort(
      (a, b) => (a['due_on']?.toString() ?? '').compareTo(
        b['due_on']?.toString() ?? '',
      ),
    );
  if (due.isEmpty) {
    await showDialog<void>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('No posted dues'),
        content: const Text(
          'Generate a fee voucher before recording a payment. Configured fee schedules are not posted charges.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c),
            child: const Text('OK'),
          ),
        ],
      ),
    );
    return false;
  }
  final config = await auth.call('finance/configuration');
  if (!context.mounted) return false;
  if ((config['payment_methods'] as List? ?? []).isEmpty) {
    await showDialog<void>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('No active payment methods'),
        content: const Text(
          'Ask the school administrator to configure manual payment methods before recording a payment.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c),
            child: const Text('OK'),
          ),
        ],
      ),
    );
    return false;
  }
  final requestId = operationId();
  final outstanding = due.fold<int>(
    0,
    (sum, i) => sum + (i['balance_minor'] as num).toInt(),
  );
  String? paymentId;
  final saved = await Navigator.push<bool>(
    context,
    MaterialPageRoute(
      builder: (_) => DomainForm(
        title: 'recordPayment',
        summary: Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: Text(
            '${studentLabel ?? ''}\nOutstanding before payment: ${Money.format(outstanding)}\nPayments are entered manually and allocated to the oldest due vouchers first.',
          ),
        ),
        fields: [
          FormFieldSpec(
            'payment_method_id',
            'paymentMethod',
            kind: FieldKind.choice,
            initial: defaultCashMethod(config),
            options: {
              for (final v in config['payment_methods'] as List)
                (v['id'] as String): v['name'] as String,
            },
          ),
          FormFieldSpec(
            'paid_on',
            'paymentDate',
            kind: FieldKind.date,
            initial: DateTime.now().toIso8601String().substring(0, 10),
          ),
          FormFieldSpec('reference', 'transactionReference', required: false),
          const FormFieldSpec(
            'notes',
            'Notes',
            required: false,
            kind: FieldKind.multiline,
            maxLength: 500,
          ),
          FormFieldSpec(
            'amount_received',
            'Amount received (PKR)',
            kind: FieldKind.money,
            initial: outstanding,
          ),
        ],
        onSubmit: (v) async {
          final amount = v.remove('amount_received') as int;
          if (amount <= 0 || amount > outstanding) {
            throw const ApiFailure(
              'validation',
              fields: {
                'amount_received': [
                  'Enter a positive amount no greater than outstanding dues.',
                ],
              },
            );
          }
          var remaining = amount;
          final allocations = <Map<String, dynamic>>[];
          for (final i in due) {
            final part = remaining < (i['balance_minor'] as num).toInt()
                ? remaining
                : (i['balance_minor'] as num).toInt();
            if (part > 0) {
              allocations.add({
                'fee_invoice_id': i['id'],
                'amount_minor': part,
              });
              remaining -= part;
            }
          }
          final result = await auth.call(
            'finance/payments',
            body: {
              ...v,
              'amount_minor': allocations.fold<int>(
                0,
                (sum, a) => sum + (a['amount_minor'] as int),
              ),
              'request_id': requestId,
              'allocations': allocations,
            },
          );
          paymentId = result['id'] as String;
        },
      ),
    ),
  );
  if (saved == true && context.mounted && paymentId != null) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Payment recorded successfully')),
    );
    await Navigator.push<void>(
      context,
      MaterialPageRoute(
        builder: (_) => PaymentDetailScreen(auth: auth, id: paymentId!),
      ),
    );
  }
  return saved;
}

Future<bool?> disbursementForm(
  BuildContext context,
  AuthController auth,
  String path, {
  int? outstanding,
  String? payrollItemId,
  String? staffName,
}) async {
  final methods = await auth.call('finance/payment-methods');
  final config = {'payment_methods': methods['items'] ?? []};
  if (!context.mounted) return false;
  if ((config['payment_methods'] as List? ?? []).isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'No active payment methods. Ask the administrator to configure them.',
        ),
      ),
    );
    return false;
  }
  final requestId = operationId();
  return Navigator.push<bool>(
    context,
    MaterialPageRoute(
      builder: (_) => DomainForm(
        title: 'disburse',
        summary: outstanding == null
            ? null
            : Text(
                '${staffName ?? 'Payroll'}\nOutstanding: ${Money.format(outstanding)}',
              ),
        fields: [
          if (outstanding != null)
            FormFieldSpec(
              'amount_minor',
              'Amount paid (PKR)',
              kind: FieldKind.money,
              initial: outstanding,
            ),
          FormFieldSpec(
            'payment_method_id',
            'paymentMethod',
            kind: FieldKind.choice,
            initial: defaultCashMethod(config),
            options: {
              for (final v in config['payment_methods'] as List)
                (v['id'] as String): v['name'] as String,
            },
          ),
          FormFieldSpec(
            'paid_on',
            'paymentDate',
            kind: FieldKind.date,
            initial: DateTime.now().toIso8601String().substring(0, 10),
          ),
          FormFieldSpec('reference', 'transactionReference', required: false),
          if (outstanding != null)
            const FormFieldSpec(
              'notes',
              'Notes',
              required: false,
              kind: FieldKind.multiline,
              maxLength: 500,
            ),
        ],
        onSubmit: (v) async {
          if (outstanding != null) {
            final amount = v['amount_minor'] as int;
            if (amount <= 0 || amount > outstanding) {
              throw const ApiFailure('validation');
            }
            v['request_id'] = requestId;
            if (payrollItemId != null) {
              v['allocations'] = [
                {'payroll_item_id': payrollItemId, 'amount_minor': amount},
              ];
            }
          }
          await auth.call(path, body: v);
        },
      ),
    ),
  );
}

Future<bool?> generateMonthlyFees(
  BuildContext context,
  AuthController auth, {
  String? studentId,
  String? studentLabel,
}) async {
  final sessions = await auth.call('catalog/sessions');
  final classes = studentId == null
      ? await auth.call('catalog/classes')
      : {'items': []};
  if (!context.mounted) return false;
  return Navigator.push<bool>(
    context,
    MaterialPageRoute(
      builder: (_) => DomainForm(
        title: 'Generate monthly fees',
        summary: Text(
          '${studentLabel ?? 'Select a class/session or all authorized students.'}\nPreview missing months before posting. Existing vouchers and payments will not be changed.',
        ),
        fields: [
          FormFieldSpec(
            'through',
            'Through month (YYYY-MM)',
            initial: DateTime.now().toIso8601String().substring(0, 7),
            maxLength: 7,
          ),
          const FormFieldSpec(
            'current_only',
            'Only the selected month',
            kind: FieldKind.boolean,
            initial: false,
          ),
          FormFieldSpec(
            'academic_session_id',
            'academicSession',
            kind: FieldKind.choice,
            required: false,
            options: {
              for (final row in sessions['items'] as List)
                row['id'] as String: row['name'] as String,
            },
          ),
          if (studentId == null)
            FormFieldSpec(
              'class_id',
              'class',
              kind: FieldKind.choice,
              required: false,
              options: {
                for (final row in classes['items'] as List)
                  row['id'] as String: row['name'] as String,
              },
            ),
        ],
        onSubmit: (v) async {
          final body = {...v};
          if (studentId != null) {
            body['student_id'] = studentId;
          }
          final preview = await auth.call(
            'finance/monthly-fees',
            body: {...body, 'preview': true},
          );
          if (!context.mounted) return;
          final confirmed = await showDialog<bool>(
            context: context,
            builder: (_) => MonthlyFeePreview(preview: preview),
          );
          if (confirmed != true && preview['missing_months'] == 0) return;
          if (confirmed != true) throw const ApiFailure('cancelled');
          await auth.call(
            'finance/monthly-fees',
            body: {...body, 'preview': false},
          );
        },
      ),
    ),
  );
}
