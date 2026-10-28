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
Future<bool?> generateVoucher(BuildContext context, AuthController auth) async {
  final student = await chooseStudent(context, auth);
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
    (config['payment_methods'] as List).any((m) =>
        m['id'] == values['payment_method_id'] && m['code'] != 'cash');

Future<bool?> recordPayment(
  BuildContext context,
  AuthController auth,
  List<Map<String, dynamic>> invoices,
) async {
  final config = await auth.call('finance/configuration');
  if (!context.mounted) return false;
  final requestId = operationId();
  final due = invoices.where((i) => (i['balance_minor'] as num) > 0).toList();
  String? paymentId;
  final saved = await Navigator.push<bool>(
    context,
    MaterialPageRoute(
      builder: (_) => DomainForm(
        title: 'recordPayment',
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
          FormFieldSpec(
            'reference',
            'transactionReference',
            required: false,
            visibleWhen: (values) => nonCashMethod(config, values),
          ),
          const FormFieldSpec('notes', 'Notes', required: false, kind: FieldKind.multiline, maxLength: 500),
          for (final i in due)
            FormFieldSpec(
              'amount_${i['id']}',
              '${i['voucher_number']} · ${Money.format(i['balance_minor'])}',
              kind: FieldKind.money,
              required: false,
              initial: due.length == 1 ? i['balance_minor'] : null,
            ),
        ],
        onSubmit: (v) async {
          final allocations = <Map<String, dynamic>>[];
          for (final i in due) {
            final amount = v.remove('amount_${i['id']}') as int?;
            if (amount != null && amount > 0) {
              allocations.add({
                'fee_invoice_id': i['id'],
                'amount_minor': amount,
              });
            }
          }
          if (allocations.isEmpty) {
            throw const ApiFailure('validation');
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
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Payment recorded successfully')));
    await Navigator.push<void>(context, MaterialPageRoute(builder: (_) => PaymentDetailScreen(auth: auth, id: paymentId!)));
  }
  return saved;
}

Future<bool?> disbursementForm(
  BuildContext context,
  AuthController auth,
  String path,
) async {
  final config = await auth.call('finance/configuration');
  if (!context.mounted) return false;
  return Navigator.push<bool>(
    context,
    MaterialPageRoute(
      builder: (_) => DomainForm(
        title: 'disburse',
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
          FormFieldSpec(
            'reference',
            'transactionReference',
            required: false,
            visibleWhen: (values) => nonCashMethod(config, values),
          ),
        ],
        onSubmit: (v) async {
          await auth.call(path, body: v);
        },
      ),
    ),
  );
}
