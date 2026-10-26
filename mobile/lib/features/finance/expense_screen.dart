import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../auth/auth_controller.dart';
import 'financial_forms.dart';
import '../files/private_file_service.dart';
import '../files/private_file_screen.dart';

class ExpenseScreen extends StatelessWidget {
  const ExpenseScreen({super.key, required this.auth});
  final AuthController auth;
  Future<void> create(BuildContext context) async {
    final config = await auth.call('finance/configuration');
    if (!context.mounted) return;
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'requestExpense',
          fields: [
            FormFieldSpec(
              'proof_file_id',
              'supportingDocument',
              kind: FieldKind.attachment,
              required: false,
              picker: () async {
                final files = await const PrivateFileService().pickAndUpload(
                  auth,
                  purpose: 'expense_proof',
                  multiple: false,
                );
                return files.firstOrNull?['id'] as String?;
              },
            ),
            FormFieldSpec(
              'expense_category_id',
              'category',
              kind: FieldKind.choice,
              options: {
                for (final c in config['expense_categories'] as List)
                  (c['id'] as String): c['name'] as String,
              },
            ),
            const FormFieldSpec(
              'description',
              'description',
              kind: FieldKind.multiline,
              maxLength: 500,
            ),
            const FormFieldSpec(
              'amount_minor',
              'amountPkr',
              kind: FieldKind.money,
            ),
            FormFieldSpec(
              'expense_on',
              'date',
              kind: FieldKind.date,
              initial: DateTime.now().toIso8601String().substring(0, 10),
            ),
          ],
          onSubmit: (v) async {
            await auth.call('finance/expenses', body: v);
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
              item['description'] as String,
              style: Theme.of(c).textTheme.titleLarge,
            ),
            Text(
              Money.format(item['amount_minor']),
              style: Theme.of(c).textTheme.headlineMedium,
            ),
            Text('${item['category_name']} · ${item['expense_on']}'),
            const SizedBox(height: AppSpace.lg),
            AppBadge(
              label: s.text(item['status'] as String),
              status: item['status'] as String,
            ),
            const SizedBox(height: AppSpace.lg),
            StatusBanner(message: s.text('expenseRule')),
            if (item['proof_file_id'] != null)
              TextButton.icon(
                onPressed: () => Navigator.push(
                  c,
                  MaterialPageRoute<void>(
                    builder: (_) => PrivateFileScreen(
                      auth: auth,
                      id: item['proof_file_id'] as String,
                      mime: item['proof_mime'] as String? ?? 'application/pdf',
                    ),
                  ),
                ),
                icon: const Icon(Icons.attach_file),
                label: Text(s.text('viewAttachment')),
              ),
            const SizedBox(height: AppSpace.lg),
            if (auth.can('finance.approve') && item['status'] == 'requested')
              Wrap(
                spacing: AppSpace.sm,
                children: [
                  for (final action in ['approve', 'deny'])
                    OutlinedButton(
                      onPressed: () async {
                        try {
                          await auth.call(
                            'finance/expenses/${item['id']}/$action',
                            body: {},
                          );
                          if (c.mounted) Navigator.pop(c);
                        } on ApiFailure catch (e) {
                          if (c.mounted) {
                            ScaffoldMessenger.of(c).showSnackBar(
                              SnackBar(content: Text(s.text(e.code))),
                            );
                          }
                        }
                      },
                      child: Text(s.text(action)),
                    ),
                ],
              ),
            if (auth.can('finance.write') &&
                ['requested', 'approved'].contains(item['status']))
              FilledButton(
                onPressed: () async {
                  Navigator.pop(c);
                  await disbursementForm(
                    context,
                    auth,
                    'finance/expenses/${item['id']}/pay',
                  );
                },
                child: Text(s.text('disburse')),
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'finance/expenses',
    title: 'expenses',
    recordTitle: (r) => r['description'] as String,
    recordSubtitle: (r) =>
        '${r['expense_on']} · ${Money.format(r['amount_minor'])}',
    onCreate: auth.can('finance.write') ? create : null,
    onOpen: details,
  );
}
