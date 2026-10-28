import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import 'financial_forms.dart';

class FinanceConfigurationScreen extends StatefulWidget {
  const FinanceConfigurationScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  State<FinanceConfigurationScreen> createState() =>
      _FinanceConfigurationScreenState();
}

class _FinanceConfigurationScreenState
    extends State<FinanceConfigurationScreen> {
  Map<String, dynamic>? config;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final data = await widget.auth.call('finance/configuration');
      if (mounted) {
        setState(() {
          config = data;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> form(String kind) async {
    final s = Strings.of(context);
    final fields = <FormFieldSpec>[];
    String path = 'finance/$kind';
    String? studentId;
    final sessions = await widget.auth.call('catalog/sessions');
    final classes = await widget.auth.call('catalog/classes');
    FormFieldSpec choice(String name, String label, List values, String key) =>
        FormFieldSpec(
          name,
          label,
          kind: FieldKind.choice,
          options: {
            for (final v in values) (v['id'] as String): v[key] as String,
          },
        );
    switch (kind) {
      case 'fee-schedules':
        fields.addAll([
          choice(
            'academic_session_id',
            'academicSession',
            sessions['items'] as List,
            'name',
          ),
          choice('class_id', 'class', classes['items'] as List, 'name'),
          FormFieldSpec(
            'kind',
            'feeKind',
            kind: FieldKind.choice,
            options: {
              'tuition': s.text('tuition'),
              'admission_promotion': s.text('admissionPromotion'),
            },
          ),
          const FormFieldSpec(
            'amount_minor',
            'amountPkr',
            kind: FieldKind.money,
          ),
          const FormFieldSpec(
            'effective_on',
            'effectiveDate',
            kind: FieldKind.date,
          ),
        ]);
      case 'fee-assignments':
        if (!mounted) return;
        final student = await chooseStudent(context, widget.auth);
        if (student == null) return;
        studentId = student['id'] as String;
        fields.addAll([
          choice(
            'academic_session_id',
            'academicSession',
            sessions['items'] as List,
            'name',
          ),
          const FormFieldSpec(
            'tuition_override_minor',
            'tuitionOverride',
            kind: FieldKind.money,
            required: false,
          ),
          const FormFieldSpec(
            'effective_on',
            'effectiveDate',
            kind: FieldKind.date,
          ),
        ]);
      case 'discount-policies':
        fields.addAll([
          const FormFieldSpec('code', 'policyCode'),
          const FormFieldSpec('name', 'name'),
          const FormFieldSpec(
            'maximum_basis_points',
            'maximumDiscount',
            kind: FieldKind.percent,
          ),
          FormFieldSpec(
            'applies_to',
            'appliesTo',
            kind: FieldKind.choice,
            options: {
              'tuition': s.text('tuition'),
              'all': s.text('allCharges'),
            },
          ),
        ]);
      case 'payment-methods':
        fields.addAll([
          const FormFieldSpec('code', 'methodCode'),
          const FormFieldSpec('name', 'name'),
          choice(
            'account_id',
            'assetAccount',
            (config!['accounts'] as List)
                .where((a) => a['type'] == 'asset')
                .toList(),
            'name',
          ),
          const FormFieldSpec(
            'requires_verification',
            'requiresVerification',
            kind: FieldKind.boolean,
            initial: true,
          ),
        ]);
      case 'compensation':
      case 'payroll-adjustments':
        final payroll = await widget.auth.call('finance/payroll');
        fields.add(
          FormFieldSpec(
            'staff_profile_id',
            'staffProfile',
            kind: FieldKind.choice,
            options: {
              for (final p in payroll['staff'] as List)
                (p['id'] as String): '${p['name']} · ${p['employee_number']}',
            },
          ),
        );
        if (kind == 'compensation') {
          fields.addAll([
            const FormFieldSpec(
              'base_salary_minor',
              'baseSalary',
              kind: FieldKind.money,
            ),
            const FormFieldSpec(
              'effective_on',
              'effectiveDate',
              kind: FieldKind.date,
            ),
          ]);
        } else {
          fields.addAll([
            const FormFieldSpec('period', 'period', maxLength: 7),
            const FormFieldSpec(
              'amount_minor',
              'adjustmentAmount',
              kind: FieldKind.money,
            ),
            const FormFieldSpec(
              'reason',
              'reason',
              kind: FieldKind.multiline,
              maxLength: 500,
            ),
          ]);
        }
    }
    if (!mounted) return;
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: kind,
          fields: fields,
          onSubmit: (v) async {
            await widget.auth.call(
              path,
              body: {...v, 'student_id': ?studentId},
            );
          },
        ),
      ),
    );
    load();
  }

  @override
  Widget build(BuildContext context) {
    final s = Strings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.text('financeConfiguration'))),
      body: error != null
          ? DataState(error: error, onRetry: load)
          : config == null
          ? const DataState()
          : ListView(
              padding: const EdgeInsets.all(AppSpace.lg),
              children: [
                StatusBanner(message: s.text('discountRule')),
                const SizedBox(height: AppSpace.lg),
                if (widget.auth.can('finance.configure') ||
                    widget.auth.can('finance.approve'))
                  Wrap(
                    spacing: AppSpace.sm,
                    runSpacing: AppSpace.sm,
                    children: [
                      for (final kind in [
                        'fee-schedules',
                        'fee-assignments',
                        'discount-policies',
                        'payment-methods',
                        if (widget.auth.can('finance.approve')) ...[
                          'compensation',
                          'payroll-adjustments',
                        ],
                      ])
                        OutlinedButton(
                          onPressed: () => form(kind),
                          child: Text(s.text(kind)),
                        ),
                    ],
                  ),
                const SizedBox(height: AppSpace.xl),
                for (final item in config!['schedules'] as List)
                  ListTile(
                    title: Text(
                      '${item['class_name']} · ${item['session_name']}',
                    ),
                    subtitle: Text(
                      '${s.text(item['kind'] == 'admission_promotion' ? 'admissionPromotion' : item['kind'] as String)} · ${item['effective_on']}',
                    ),
                    trailing: Text(Money.format(item['amount_minor'])),
                  ),
              ],
            ),
    );
  }
}
