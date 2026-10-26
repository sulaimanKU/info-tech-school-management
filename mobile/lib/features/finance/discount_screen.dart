import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import 'financial_forms.dart';

class DiscountScreen extends StatelessWidget {
  const DiscountScreen({super.key, required this.auth});
  final AuthController auth;
  Future<void> create(BuildContext context) async {
    final student = await chooseStudent(context, auth);
    if (student == null) return;
    final config = await auth.call('finance/configuration');
    final sessions = await auth.call('catalog/sessions');
    if (!context.mounted) return;
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'requestDiscount',
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
              'discount_policy_id',
              'discountPolicy',
              kind: FieldKind.choice,
              options: {
                for (final p in config['discount_policies'] as List)
                  (p['id'] as String):
                      '${p['name']} (${(p['maximum_basis_points'] as num) / 100}%)',
              },
            ),
            const FormFieldSpec(
              'basis_points',
              'discountPercent',
              kind: FieldKind.percent,
            ),
            const FormFieldSpec('starts_on', 'startDate', kind: FieldKind.date),
            const FormFieldSpec('ends_on', 'endDate', kind: FieldKind.date),
            const FormFieldSpec(
              'eligibility_notes',
              'eligibilityNotes',
              kind: FieldKind.multiline,
              maxLength: 2000,
            ),
          ],
          onSubmit: (v) async {
            await auth.call(
              'finance/discounts',
              body: {...v, 'student_id': student['id']},
            );
          },
        ),
      ),
    );
  }

  Future<void> details(BuildContext context, Map<String, dynamic> item) async {
    final s = Strings.of(context);
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (c) => SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpace.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              '${item['full_name']} · ${item['policy_name']}',
              style: Theme.of(c).textTheme.titleLarge,
            ),
            Text(
              '${(item['basis_points'] as num) / 100}% · ${item['starts_on']} — ${item['ends_on']}',
            ),
            Text(item['eligibility_notes'] as String),
            const SizedBox(height: AppSpace.lg),
            StatusBanner(message: s.text('discountRule')),
            if (auth.can('finance.approve')) ...[
              if (item['status'] == 'requested')
                FilledButton(
                  onPressed: () async {
                    Navigator.pop(c);
                    await Navigator.push<bool>(
                      context,
                      MaterialPageRoute(
                        builder: (_) => DomainForm(
                          title: 'approveDiscount',
                          fields: const [
                            FormFieldSpec(
                              'eligibility_verified',
                              'eligibilityVerified',
                              kind: FieldKind.boolean,
                              initial: false,
                            ),
                          ],
                          onSubmit: (v) async {
                            await auth.call(
                              'finance/discounts/${item['id']}/approve',
                              body: v,
                            );
                          },
                        ),
                      ),
                    );
                  },
                  child: Text(s.text('approveDiscount')),
                ),
              if (item['status'] == 'approved')
                OutlinedButton(
                  onPressed: () async {
                    try {
                      await auth.call(
                        'finance/discounts/${item['id']}/revoke',
                        body: {},
                      );
                      if (c.mounted) Navigator.pop(c);
                    } on ApiFailure catch (e) {
                      if (c.mounted) {
                        ScaffoldMessenger.of(
                          c,
                        ).showSnackBar(SnackBar(content: Text(s.text(e.code))));
                      }
                    }
                  },
                  child: Text(s.text('revokeDiscount')),
                ),
            ],
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'finance/discounts',
    title: 'discounts',
    recordTitle: (r) => '${r['full_name']} · ${r['policy_name']}',
    recordSubtitle: (r) =>
        '${(r['basis_points'] as num) / 100}% · ${r['starts_on']} — ${r['ends_on']}',
    onCreate: auth.can('finance.write') ? create : null,
    onOpen: details,
  );
}
