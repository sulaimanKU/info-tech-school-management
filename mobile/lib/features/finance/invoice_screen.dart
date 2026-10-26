import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/money.dart';
import '../../core/operation_id.dart';
import '../../design/app_theme.dart';
import '../../l10n/strings.dart';
import '../../shared/forms/domain_form.dart';
import '../../shared/widgets/data_state.dart';
import '../../shared/widgets/records_screen.dart';
import '../../shared/widgets/surface_card.dart';
import '../../shared/widgets/polished_components.dart';
import '../auth/auth_controller.dart';
import 'financial_forms.dart';
import '../reports/report_screen.dart';

class InvoiceScreen extends StatelessWidget {
  const InvoiceScreen({super.key, required this.auth});
  final AuthController auth;
  @override
  Widget build(BuildContext context) => RecordsScreen(
    auth: auth,
    path: 'finance/invoices',
    title: 'feeVouchers',
    recordTitle: (i) => '${i['full_name']} · ${i['voucher_number']}',
    recordSubtitle: (i) =>
        '${i['period']} · ${Money.format(i['balance_minor'])}',
    onCreate: auth.can('finance.write')
        ? (c) async {
            await generateVoucher(c, auth);
          }
        : null,
    onOpen: (c, i) async {
      await Navigator.push<void>(
        c,
        MaterialPageRoute(
          builder: (_) =>
              InvoiceDetailScreen(auth: auth, id: i['id'] as String),
        ),
      );
    },
  );
}

class InvoiceDetailScreen extends StatefulWidget {
  const InvoiceDetailScreen({super.key, required this.auth, required this.id});
  final AuthController auth;
  final String id;
  @override
  State<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends State<InvoiceDetailScreen> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final value = await widget.auth.call('finance/invoices/${widget.id}');
      if (mounted) {
        setState(() {
          data = value;
          error = null;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted) setState(() => error = e.code);
    }
  }

  Future<void> adjust() async {
    final key = operationId();
    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DomainForm(
          title: 'adjustInvoice',
          fields: const [
            FormFieldSpec(
              'amount_minor',
              'adjustmentAmount',
              kind: FieldKind.money,
            ),
            FormFieldSpec(
              'reason',
              'reason',
              kind: FieldKind.multiline,
              maxLength: 500,
            ),
          ],
          onSubmit: (v) async {
            await widget.auth.call(
              'finance/invoices/${widget.id}/adjust',
              body: {...v, 'request_id': key},
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
      appBar: AppBar(
        title: Text(s.text('feeVoucher')),
        actions: [
          IconButton(
            tooltip: s.text('downloadPdf'),
            icon: const Icon(Icons.picture_as_pdf_outlined),
            onPressed: () => openSchoolReport(context, widget.auth, 'voucher', {
              'invoice_id': widget.id,
            }),
          ),
        ],
      ),
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
                        Text(
                          data!['voucher_number'] as String,
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        Text(
                          '${data!['period']} · ${s.text(data!['kind'] as String)}',
                        ),
                        const SizedBox(height: AppSpace.lg),
                        AppBadge(
                          label: s.text(data!['status'] as String),
                          status: data!['status'] as String,
                        ),
                        const SizedBox(height: AppSpace.lg),
                        Text('${s.text('dueDate')}: ${data!['due_on']}'),
                        for (final line in data!['lines'] as List)
                          ValueRow(
                            label: line['description'] as String,
                            value: Money.format(line['amount_minor']),
                          ),
                        const Divider(),
                        for (final field in {
                          'gross_minor': 'grossAmount',
                          'discount_minor': 'discount',
                          'paid_minor': 'paidAmount',
                          'balance_minor': 'balance',
                        }.entries)
                          ValueRow(
                            label: s.text(field.value),
                            value: Money.format(data![field.key]),
                            emphasis: field.key == 'balance_minor',
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpace.lg),
                  for (final a in data!['adjustments'] as List)
                    ListTile(
                      title: Text(a['reason'] as String),
                      trailing: Text(Money.format(a['amount_minor'])),
                    ),
                  if (widget.auth.can('finance.write') &&
                      (data!['balance_minor'] as num) > 0)
                    FilledButton.icon(
                      onPressed: () async {
                        try {
                          await recordPayment(context, widget.auth, [data!]);
                          load();
                        } on ApiFailure catch (e) {
                          if (mounted) setState(() => error = e.code);
                        }
                      },
                      icon: const Icon(Icons.payments_outlined),
                      label: Text(s.text('recordPayment')),
                    ),
                  if (widget.auth.can('finance.approve'))
                    const SizedBox(height: AppSpace.sm),
                  if (widget.auth.can('finance.approve'))
                    OutlinedButton(
                      onPressed: adjust,
                      child: Text(s.text('adjustInvoice')),
                    ),
                ],
              ),
            ),
    );
  }
}
